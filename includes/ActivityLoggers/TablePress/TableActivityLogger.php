<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\TablePress;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;
use Pastmark\Utils\ContentDiffer;

defined( 'ABSPATH' ) || exit;

/**
 * TablePress table activity logger (PM-170, Sprint 11).
 *
 * TablePress stores each table as a `tablepress_table` post (JSON-encoded
 * cell data in `post_content`, name/description in `post_title`/
 * `post_excerpt`) plus two post-meta fields (`_tablepress_table_options`,
 * `_tablepress_table_visibility`) - all reachable through
 * `TablePress::$model_table->load()`, confirmed against the real installed
 * source (`models/model-table.php`) rather than assumed from documentation.
 * That same file fires real `do_action()` calls around every lifecycle
 * method this logger cares about, so - unlike PM-168's Redirection groups -
 * no REST bridge is needed here:
 *
 * - `tablepress_event_added_table( $table_id )` - after `add()`, but only
 *   when called with `$copy_or_add === 'add'` (the default), so a copy's
 *   own `add()` call never double-fires this alongside
 *   `tablepress_event_copied_table` below. Also fires for a *new-table*
 *   import (`TablePress_Import::run()` calls `add()` when not replacing an
 *   existing table) - not distinguished from a manually-added table here,
 *   the same simplification this ticket's own scope keeps (import/export
 *   granularity is out of scope; only create/edit/duplicate/delete are).
 * - `tablepress_event_pre_save_table( $table_id )` / `tablepress_event_
 *   saved_table( $table_id )` - bracket every `save()` call, the path both
 *   a real table edit (`controller-admin_ajax.php`'s AJAX save handler)
 *   and an import-over-an-existing-table-ID both use. Mirrors the ACF
 *   `FieldGroupActivityLogger` pattern (PM-154): snapshot on the `pre_`
 *   hook, diff against the post-save state on the second.
 * - `tablepress_event_copied_table( $new_table_id, $table_id )` - after
 *   `copy()`, for both the single-row "Duplicate" action and the bulk
 *   "Copy" action (`controller-admin.php`, both call the same `copy()`).
 * - `tablepress_event_pre_delete_table( $table_id )` / `tablepress_event_
 *   deleted_table( $table_id )` - bracket `delete()`. The underlying post
 *   is gone by the time the second fires, so the table's name/data has to
 *   be captured on the first.
 *
 * `TablePress::$model_table->load()` reads through `get_post()`/
 * `get_post_meta()` (`models/model-post.php`), both backed by WordPress'
 * own object/meta cache - unlike PM-168's `Red_Group::get()` finding, there
 * is no separate un-invalidated cache here to work around: `save()`'s own
 * `wp_update_post()` call already runs `clean_post_cache()`, so a `load()`
 * after `tablepress_event_saved_table` fires sees the fresh data. Confirmed
 * live during this ticket's own verification, not assumed from the code
 * shape alone.
 */
class TableActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'tablepress';

	/**
	 * Table snapshots captured by `capture_before_save()`, keyed by table
	 * ID, consumed (and unset) the moment `log_table_saved()` runs for
	 * that same ID. `null` means the pre-save `load()` itself failed
	 * (`WP_Error`) - treated the same as "no snapshot" by
	 * `log_table_saved()`.
	 *
	 * @var array<string, array|null>
	 */
	protected static $pending_before = array();

	/**
	 * Table snapshots (plus the resolved WP post ID) captured by
	 * `capture_before_delete()`, keyed by table ID, consumed (and unset)
	 * the moment `log_table_deleted()` runs for that same ID - the only
	 * point at which the table's name/data can still be read, since the
	 * underlying post no longer exists once `tablepress_event_deleted_
	 * table` itself fires.
	 *
	 * @var array<string, array{table: array|null, post_id: int}>
	 */
	protected static $pending_deleted = array();

	/**
	 * Constructor.
	 */
	public function __construct() {

		parent::__construct();

		$this->register_hooks();
		$this->register_events();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {

		add_action( 'tablepress_event_added_table', $this->guarded( array( $this, 'log_table_added' ) ), 10, 1 );

		add_action( 'tablepress_event_pre_save_table', $this->guarded( array( $this, 'capture_before_save' ) ), 10, 1 );
		add_action( 'tablepress_event_saved_table', $this->guarded( array( $this, 'log_table_saved' ) ), 10, 1 );

		add_action( 'tablepress_event_copied_table', $this->guarded( array( $this, 'log_table_copied' ) ), 10, 2 );

		add_action( 'tablepress_event_pre_delete_table', $this->guarded( array( $this, 'capture_before_delete' ) ), 10, 1 );
		add_action( 'tablepress_event_deleted_table', $this->guarded( array( $this, 'log_table_deleted' ) ), 10, 1 );
	}

	/**
	 * Declare TablePress' event group via the `pastmark_registered_events`
	 * filter (PM-151's settled convention, `source => 'tablepress'`),
	 * rather than editing `EventRegistry::get_events()` directly - the
	 * pattern every integration since Sprint 8 has used.
	 *
	 * @return void
	 */
	protected function register_events(): void {

		add_filter( 'pastmark_registered_events', array( $this, 'add_event_group' ) );
	}

	/**
	 * `pastmark_registered_events` callback - adds TablePress' event group.
	 *
	 * @param array $events Registered events, keyed by event type.
	 * @return array
	 */
	public function add_event_group( array $events ): array {

		$events[ Events::TABLEPRESS ] = array(
			'label'   => __( 'TablePress', 'pastmark' ),
			'source'  => 'tablepress',
			'actions' => array(
				array(
					'key'            => Actions::TABLE_CREATE,
					'label'          => __( 'Table Create', 'pastmark' ),
					'description'    => __( 'New TablePress table created.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::TABLE_UPDATE,
					'label'          => __( 'Table Update', 'pastmark' ),
					'description'    => __( 'TablePress table content or settings changed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::TABLE_DELETE,
					'label'          => __( 'Table Delete', 'pastmark' ),
					'description'    => __( 'TablePress table permanently deleted.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::TABLE_DUPLICATE,
					'label'          => __( 'Table Duplicate', 'pastmark' ),
					'description'    => __( 'TablePress table duplicated.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
			),
		);

		return $events;
	}

	/**
	 * Log a new table's creation.
	 *
	 * @param string $table_id ID of the newly-added table.
	 * @return void
	 */
	public function log_table_added( $table_id ): void {

		$table_id = (string) $table_id;
		$table    = $this->load_table( $table_id );

		if ( null === $table ) {
			return;
		}

		$name = isset( $table['name'] ) ? (string) $table['name'] : '';

		$this->insert_event_log(
			Events::TABLEPRESS,
			Actions::TABLE_CREATE,
			array(
				'object_type' => 'tablepress_table',
				'object_id'   => $this->get_post_id_for_table( $table_id ),
				'message'     => sprintf( 'TablePress table "%s" created.', $name ),
				'after_data'  => $this->encode_table_data( $this->prepare_table_data( $table ) ),
				'context'     => array_merge( $this->get_common_context(), array( 'table_id' => $table_id ) ),
			)
		);
	}

	/**
	 * Snapshot the table's current state before `save()`'s write happens.
	 *
	 * @param string $table_id ID of the table about to be saved.
	 * @return void
	 */
	public function capture_before_save( $table_id ): void {

		$table_id = (string) $table_id;

		self::$pending_before[ $table_id ] = $this->load_table( $table_id );
	}

	/**
	 * Log a table edit, diffed via `ContentDiffer` with the same
	 * fallback shape every prior integration's diff-capable logger uses
	 * (PM-142, PM-154-156, PM-166-168): a non-null diff is stored alone,
	 * a `null` diff falls back to full before/after.
	 *
	 * @param string $table_id ID of the just-saved table.
	 * @return void
	 */
	public function log_table_saved( $table_id ): void {

		$table_id = (string) $table_id;

		$before = array_key_exists( $table_id, self::$pending_before ) ? self::$pending_before[ $table_id ] : null;

		unset( self::$pending_before[ $table_id ] );

		$after = $this->load_table( $table_id );

		if ( null === $after ) {
			return;
		}

		$name    = isset( $after['name'] ) ? (string) $after['name'] : '';
		$post_id = $this->get_post_id_for_table( $table_id );

		if ( null === $before ) {
			// No usable pre-save snapshot (e.g. the `pre_save` hook's own
			// load() failed) - log the update without a diff rather than
			// drop it silently.
			$this->insert_event_log(
				Events::TABLEPRESS,
				Actions::TABLE_UPDATE,
				array(
					'object_type' => 'tablepress_table',
					'object_id'   => $post_id,
					'message'     => sprintf( 'TablePress table "%s" updated.', $name ),
					'after_data'  => $this->encode_table_data( $this->prepare_table_data( $after ) ),
					'context'     => array_merge( $this->get_common_context(), array( 'table_id' => $table_id ) ),
				)
			);

			return;
		}

		$before_data = $this->prepare_table_data( $before );
		$after_data  = $this->prepare_table_data( $after );

		if ( $before_data === $after_data ) {
			// save() updates `last_modified`/`options.last_editor` on every
			// call, including a re-save with no other change (e.g. an
			// import that replaces a table with byte-identical content) -
			// prepare_table_data() strips both before comparing, so a
			// genuine no-op doesn't produce an empty-looking row.
			return;
		}

		$before_json = $this->encode_table_data( $before_data );
		$after_json  = $this->encode_table_data( $after_data );

		$diff = ContentDiffer::diff( $before_json, $after_json );

		$log_data = array(
			'object_type' => 'tablepress_table',
			'object_id'   => $post_id,
			'message'     => sprintf( 'TablePress table "%s" updated.', $name ),
			'context'     => array_merge( $this->get_common_context(), array( 'table_id' => $table_id ) ),
		);

		if ( null !== $diff ) {
			$log_data['after_data'] = wp_json_encode( array( 'table_diff' => $diff ) );
		} else {
			$log_data['before_data'] = $before_json;
			$log_data['after_data']  = $after_json;
		}

		$this->insert_event_log( Events::TABLEPRESS, Actions::TABLE_UPDATE, $log_data );
	}

	/**
	 * Log a table duplication.
	 *
	 * @param string $new_table_id ID of the new copy.
	 * @param string $table_id     ID of the table that was copied.
	 * @return void
	 */
	public function log_table_copied( $new_table_id, $table_id ): void {

		$new_table_id = (string) $new_table_id;
		$table_id     = (string) $table_id;

		$new_table = $this->load_table( $new_table_id );

		if ( null === $new_table ) {
			return;
		}

		$source_table = $this->load_table( $table_id, false, false );
		$source_name  = ( null !== $source_table && isset( $source_table['name'] ) ) ? (string) $source_table['name'] : '';
		$new_name     = isset( $new_table['name'] ) ? (string) $new_table['name'] : '';

		$this->insert_event_log(
			Events::TABLEPRESS,
			Actions::TABLE_DUPLICATE,
			array(
				'object_type' => 'tablepress_table',
				'object_id'   => $this->get_post_id_for_table( $new_table_id ),
				'message'     => sprintf( 'TablePress table "%s" duplicated from "%s".', $new_name, $source_name ),
				'after_data'  => $this->encode_table_data( $this->prepare_table_data( $new_table ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'table_id'        => $new_table_id,
						'source_table_id' => $table_id,
					)
				),
			)
		);
	}

	/**
	 * Snapshot the table's name/data (and resolved post ID) before
	 * `delete()`'s write happens - the last point either is still
	 * readable.
	 *
	 * @param string $table_id ID of the table about to be deleted.
	 * @return void
	 */
	public function capture_before_delete( $table_id ): void {

		$table_id = (string) $table_id;

		self::$pending_deleted[ $table_id ] = array(
			'table'   => $this->load_table( $table_id ),
			'post_id' => $this->get_post_id_for_table( $table_id ),
		);
	}

	/**
	 * Log a table deletion, using the snapshot `capture_before_delete()`
	 * took - the underlying post no longer exists by the time this fires.
	 *
	 * @param string $table_id ID of the just-deleted table.
	 * @return void
	 */
	public function log_table_deleted( $table_id ): void {

		$table_id = (string) $table_id;

		$snapshot = self::$pending_deleted[ $table_id ] ?? null;

		unset( self::$pending_deleted[ $table_id ] );

		$table   = $snapshot['table'] ?? null;
		$post_id = $snapshot['post_id'] ?? 0;
		$name    = ( null !== $table && isset( $table['name'] ) ) ? (string) $table['name'] : '';

		$log_data = array(
			'object_type' => 'tablepress_table',
			'object_id'   => $post_id,
			'severity'    => Severity::WARNING,
			'message'     => sprintf( 'TablePress table "%s" deleted.', $name ),
			'context'     => array_merge( $this->get_common_context(), array( 'table_id' => $table_id ) ),
		);

		if ( null !== $table ) {
			$log_data['before_data'] = $this->encode_table_data( $this->prepare_table_data( $table ) );
		}

		$this->insert_event_log( Events::TABLEPRESS, Actions::TABLE_DELETE, $log_data );
	}

	/**
	 * Load a table via TablePress' own model, normalizing its `WP_Error`
	 * failure case to `null`.
	 *
	 * @param string $table_id                Table ID.
	 * @param bool   $load_data               Whether to load the table's cell data.
	 * @param bool   $load_options_visibility Whether to load table options/visibility.
	 * @return array|null
	 */
	protected function load_table( string $table_id, bool $load_data = true, bool $load_options_visibility = true ): ?array {

		if ( ! class_exists( 'TablePress' ) || empty( \TablePress::$model_table ) ) {
			return null;
		}

		$table = \TablePress::$model_table->load( $table_id, $load_data, $load_options_visibility );

		return is_wp_error( $table ) ? null : $table;
	}

	/**
	 * Resolve a TablePress table ID to its underlying `WP_Post` ID via
	 * the `tablepress_tables` option's `table_post` map - the same data
	 * TablePress' own model reads, without calling any of its `protected`
	 * methods. Lets `object_type`/`object_id` resolve to a real "go to
	 * object" link the same way every other post-type-backed integration's
	 * events already do.
	 *
	 * TablePress' own `TablePress_WP_Option` wrapper (`classes/class-
	 * wp_option.php`) stores this option as a `wp_json_encode()`'d string,
	 * not a plain PHP array like a normal `update_option()` call - found
	 * live during this ticket's own verification pass (object IDs were
	 * coming back `0` for every event until this was traced here), not
	 * assumed. `get_option()` therefore hands back a raw JSON string here,
	 * which has to be decoded before the `table_post` map is readable.
	 *
	 * @param string $table_id Table ID.
	 * @return int Post ID, or `0` if it couldn't be resolved.
	 */
	protected function get_post_id_for_table( string $table_id ): int {

		$raw = get_option( 'tablepress_tables' );

		if ( ! is_string( $raw ) || '' === $raw ) {
			return 0;
		}

		$tables = json_decode( $raw, true );

		if ( ! is_array( $tables ) || empty( $tables['table_post'][ $table_id ] ) ) {
			return 0;
		}

		return (int) $tables['table_post'][ $table_id ];
	}

	/**
	 * Reduce a raw table array to the fields worth diffing/storing, and
	 * strip the two fields `save()` unconditionally rewrites on every call
	 * regardless of whether anything else changed (`last_modified`,
	 * `options.last_editor`) - without this, a genuine no-op re-save (or
	 * an import replacing a table with byte-identical content) would
	 * still look like a change.
	 *
	 * @param array $table Raw table array, as returned by `load_table()`.
	 * @return array
	 */
	protected function prepare_table_data( array $table ): array {

		unset( $table['last_modified'] );

		if ( isset( $table['options'] ) && is_array( $table['options'] ) ) {
			unset( $table['options']['last_editor'] );
		}

		return $table;
	}

	/**
	 * Encode table data for diffing/storage.
	 *
	 * `ContentDiffer` diffs line-by-line - the default `wp_json_encode()`
	 * produces a single-line string, which would make every table edit
	 * look like "the whole line changed" and defeat diffing entirely.
	 * Pretty-printing gives each field its own line, the same fix PM-154's
	 * `FieldGroupActivityLogger::encode_field_group_data()` already
	 * established for this exact class of problem.
	 *
	 * @param array $data Table data (already passed through `prepare_table_data()`).
	 * @return string
	 */
	protected function encode_table_data( array $data ): string {

		return (string) wp_json_encode( $data, JSON_PRETTY_PRINT );
	}
}
