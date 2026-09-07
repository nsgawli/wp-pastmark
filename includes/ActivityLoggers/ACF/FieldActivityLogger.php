<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\ACF;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * ACF individual-field activity logger (PM-154).
 *
 * ACF's field-level hooks are the most granular any of Sprint 8's three
 * target plugins offer - `acf/updated_field`/`acf/delete_field` fire once
 * per field, not once per whole field-group save. What they *don't* do is
 * only fire for fields that actually changed: saving a group in wp-admin
 * resubmits every field currently in the editor (`admin-field-group.php`'s
 * `save_post()` loop), so an untouched field round-trips through
 * `acf_update_field()` - and this logger's hooks - exactly like a genuinely
 * edited one. The same before/after bridge `FieldGroupActivityLogger` uses
 * (snapshot on the pre-save filter, diff on the post-save action) is reused
 * here specifically to filter that noise out: a field whose settings are
 * byte-identical before and after is skipped rather than logged as a no-op
 * "update."
 *
 * No `ContentDiffer` involved here, unlike the field-group logger - a single
 * field's settings are already small and structured (label/type/options/...),
 * so the ticket's own acceptance criteria call for plain before/after
 * storage, not diff-or-fallback.
 */
class FieldActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'acf';

	/**
	 * Fields snapshotted by `capture_field_before_update()`, keyed by the
	 * field's own stable `key` (not its numeric post ID - a brand-new field
	 * has no ID yet when the pre-save filter fires, but always already has
	 * its `key` assigned by the field-group editor). Consumed (and unset)
	 * the moment `log_field_updated()` runs for that same key.
	 *
	 * @var array<string, array|null>
	 */
	protected static $pending_fields = array();

	/**
	 * Constructor.
	 */
	public function __construct() {

		parent::__construct();

		$this->register_hooks();
	}

	/**
	 * Register hooks.
	 *
	 * Event-group registration (the `acf` group both ACF loggers' actions
	 * live under) is owned by `FieldGroupActivityLogger::register_events()`
	 * - see that method's docblock for why only one of the two registers it.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {

		add_filter( 'acf/update_field', $this->guarded( array( $this, 'capture_field_before_update' ) ), 10, 1 );

		add_action( 'acf/updated_field', $this->guarded( array( $this, 'log_field_updated' ) ), 10, 1 );

		add_action( 'acf/delete_field', $this->guarded( array( $this, 'log_field_deleted' ) ), 10, 1 );
	}

	/**
	 * Snapshot the stored field before `acf/update_field`'s write happens,
	 * keyed by its stable `key`.
	 *
	 * Must return `$field` unmodified - this is a filter ACF applies to the
	 * array it's about to save, not a value Pastmark should ever change.
	 *
	 * @param array $field Incoming field array, about to be saved.
	 * @return array
	 */
	public function capture_field_before_update( $field ) {

		if ( ! is_array( $field ) || empty( $field['key'] ) ) {
			return $field;
		}

		$id = isset( $field['ID'] ) ? (int) $field['ID'] : 0;

		$existing = ( $id > 0 && function_exists( 'acf_get_field' ) ) ? acf_get_field( $id ) : null;

		self::$pending_fields[ $field['key'] ] = is_array( $existing ) ? $existing : null;

		return $field;
	}

	/**
	 * Log a field create or update - skips fields the diff shows are
	 * actually unchanged (see class docblock).
	 *
	 * @param array $field Field array as saved.
	 * @return void
	 */
	public function log_field_updated( $field ): void {

		if ( ! is_array( $field ) || empty( $field['key'] ) || empty( $field['ID'] ) ) {
			return;
		}

		$key = $field['key'];

		$before = array_key_exists( $key, self::$pending_fields ) ? self::$pending_fields[ $key ] : null;

		unset( self::$pending_fields[ $key ] );

		$after_data = $this->prepare_field_data( $field );
		$label      = isset( $field['label'] ) ? (string) $field['label'] : '';
		$group_id   = isset( $field['parent'] ) ? (int) $field['parent'] : 0;

		if ( null === $before ) {

			$this->insert_event_log(
				Events::ACF,
				Actions::FIELD_CREATE,
				array(
					'object_type' => 'acf-field',
					'object_id'   => (int) $field['ID'],
					'message'     => sprintf( 'ACF field "%s" added.', $label ),
					'after_data'  => wp_json_encode( $after_data ),
					'context'     => array_merge(
						$this->get_common_context(),
						array( 'field_group_id' => $group_id )
					),
				)
			);

			return;
		}

		$before_data = $this->prepare_field_data( $before );

		if ( $before_data === $after_data ) {
			// Resubmitted unchanged as part of the same group save - not a
			// real edit, nothing worth a row for.
			return;
		}

		$this->insert_event_log(
			Events::ACF,
			Actions::FIELD_UPDATE,
			array(
				'object_type' => 'acf-field',
				'object_id'   => (int) $field['ID'],
				'message'     => sprintf( 'ACF field "%s" updated.', $label ),
				'before_data' => wp_json_encode( $before_data ),
				'after_data'  => wp_json_encode( $after_data ),
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'field_group_id' => $group_id )
				),
			)
		);
	}

	/**
	 * Log field removal.
	 *
	 * @param array $field Field array as it existed just before deletion.
	 * @return void
	 */
	public function log_field_deleted( $field ): void {

		if ( ! is_array( $field ) || empty( $field['ID'] ) ) {
			return;
		}

		$label    = isset( $field['label'] ) ? (string) $field['label'] : '';
		$group_id = isset( $field['parent'] ) ? (int) $field['parent'] : 0;

		$this->insert_event_log(
			Events::ACF,
			Actions::FIELD_DELETE,
			array(
				'object_type' => 'acf-field',
				'object_id'   => (int) $field['ID'],
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'ACF field "%s" removed.', $label ),
				'before_data' => wp_json_encode( $this->prepare_field_data( $field ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'field_group_id' => $group_id )
				),
			)
		);
	}

	/**
	 * Reduce a raw ACF field array to the settings worth comparing/storing -
	 * drops `ID` (already the row's own `object_id`), `parent` (already
	 * carried separately in `context['field_group_id']`), positional/
	 * internal noise (`menu_order`, `_valid`, `_name`, `_prepare`, `id`,
	 * `class`, `prefix`) that changes without representing a real settings
	 * edit, keeps everything else (label, name, type, instructions,
	 * required, and every type-specific setting).
	 *
	 * @param array $field Raw field array.
	 * @return array
	 */
	protected function prepare_field_data( array $field ): array {

		unset(
			$field['ID'],
			$field['parent'],
			$field['menu_order'],
			$field['_valid'],
			$field['_name'],
			$field['_prepare'],
			$field['id'],
			$field['class'],
			$field['prefix']
		);

		return $field;
	}
}
