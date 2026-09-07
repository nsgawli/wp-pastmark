<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\YoastSeo;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;
use Pastmark\Utils\ContentDiffer;

defined( 'ABSPATH' ) || exit;

/**
 * Yoast SEO per-post meta activity logger (PM-166; expanded to per-field
 * granularity on real user request after comparing against WP Activity
 * Log's own Yoast alert set - see `sprint-10.md`'s PM-166 notes).
 *
 * Confirmed against the real installed plugin: Yoast stores per-post SEO
 * data as post meta, every key prefixed `_yoast_wpseo_`
 * (`WPSEO_Meta::$meta_prefix`, `inc/class-wpseo-meta.php`).
 *
 * WP Activity Log's own equivalent sensor reads these fields straight out
 * of `$_POST` on `admin_init`, exploiting the fact that hook fires before
 * WordPress's own classic post-editor save writes the new values - a
 * technique that does not cover Gutenberg's REST-based save path at all.
 * This logger instead reuses Pastmark's own, already-shipped, save-path-
 * agnostic mechanism: the `update_post_metadata` filter (fires
 * unconditionally at the top of WordPress's own `update_metadata()`,
 * before it decides insert vs. update) captures the pre-write value, and
 * `added_post_meta`/`updated_post_meta` supply the new one - works
 * identically whether the save came from the classic editor, the block
 * editor's REST autosave/publish, WP-CLI, or a direct API call.
 *
 * Saving the SEO meta box writes several `_yoast_wpseo_*` keys in one
 * request (one `updated_post_meta`/`added_post_meta` fire per key - no
 * single "the SEO meta box was saved" hook exists), so changes are
 * accumulated per post during the request and flushed on `shutdown` - the
 * same "capture now, emit at the end of the request" shape
 * `ThemeActivityLogger` already uses for its own file-edit logging.
 *
 * Unlike this ticket's original shape (one aggregated blob-diff row per
 * save, covering every changed key together), a key present in
 * `META_FIELD_MAP` now gets its **own** dedicated row with its own action -
 * matching WP Activity Log's own per-field alert granularity (e.g. a title
 * change and a focus-keyword change in the same save produce two distinct,
 * separately-labeled rows, not one). Any changed key *not* in the map still
 * falls back to one aggregated `SEO_META_UPDATE` row, exactly as before -
 * so a future Yoast release adding a new meta key still logs something
 * instead of being silently dropped.
 */
class PostMetaActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'yoast-seo';

	/**
	 * Prefix every Yoast SEO post-meta key uses.
	 */
	private const META_PREFIX = '_yoast_wpseo_';

	/**
	 * Yoast per-post meta keys (bare, prefix-stripped) with a dedicated
	 * `Actions` constant of their own - mirrors WP Activity Log's own
	 * per-field alert set (its `WSAL_ID` 8801-8808/8850-8852), confirmed
	 * against its real sensor source (`class-yoast-seo-sensor.php`).
	 *
	 * @var array<string, array{action: string, label: string}>
	 */
	private const META_FIELD_MAP = array(
		'title'                => array(
			'action' => Actions::SEO_TITLE_CHANGE,
			'label'  => 'SEO title',
		),
		'metadesc'             => array(
			'action' => Actions::SEO_METADESC_CHANGE,
			'label'  => 'meta description',
		),
		'focuskw'              => array(
			'action' => Actions::SEO_FOCUS_KEYWORD_CHANGE,
			'label'  => 'focus keyword',
		),
		'meta-robots-noindex'  => array(
			'action' => Actions::SEO_NOINDEX_CHANGE,
			'label'  => 'search-results visibility (noindex)',
		),
		'meta-robots-nofollow' => array(
			'action' => Actions::SEO_NOFOLLOW_CHANGE,
			'label'  => 'link-following setting (nofollow)',
		),
		'meta-robots-adv'      => array(
			'action' => Actions::SEO_ADVANCED_ROBOTS_CHANGE,
			'label'  => 'advanced meta robots setting',
		),
		'canonical'            => array(
			'action' => Actions::SEO_CANONICAL_URL_CHANGE,
			'label'  => 'canonical URL',
		),
		'is_cornerstone'       => array(
			'action' => Actions::SEO_CORNERSTONE_CHANGE,
			'label'  => 'cornerstone-content flag',
		),
		'bctitle'              => array(
			'action' => Actions::SEO_BREADCRUMB_TITLE_CHANGE,
			'label'  => 'breadcrumb title',
		),
		'schema_page_type'     => array(
			'action' => Actions::SEO_SCHEMA_PAGE_TYPE_CHANGE,
			'label'  => 'schema page type',
		),
		'schema_article_type'  => array(
			'action' => Actions::SEO_SCHEMA_ARTICLE_TYPE_CHANGE,
			'label'  => 'schema article type',
		),
	);

	/**
	 * Meta values captured before they're overwritten, keyed by
	 * "{post_id}:{meta_key}" - consumed (and unset) the moment the
	 * corresponding `added_post_meta`/`updated_post_meta` fires for that
	 * same key.
	 *
	 * @var array<string, mixed>
	 */
	protected $pending_before_values = array();

	/**
	 * Every Yoast meta key changed so far this request, keyed by post ID,
	 * each entry itself keyed by the bare (prefix-stripped) meta key name -
	 * flushed on `shutdown`: keys present in `META_FIELD_MAP` become their
	 * own dedicated row each, everything else is aggregated into one
	 * fallback `SEO_META_UPDATE` row for that post.
	 *
	 * @var array<int, array<string, array{before: mixed, after: mixed}>>
	 */
	protected $pending_changes = array();

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
	 * @return void
	 */
	protected function register_hooks(): void {

		add_filter( 'update_post_metadata', $this->guarded( array( $this, 'capture_meta_before_update' ) ), 10, 5 );

		add_action( 'added_post_meta', $this->guarded( array( $this, 'track_meta_change' ) ), 10, 4 );

		add_action( 'updated_post_meta', $this->guarded( array( $this, 'track_meta_change' ) ), 10, 4 );

		add_action( 'shutdown', $this->guarded( array( $this, 'flush_pending_changes' ) ) );
	}

	/**
	 * Snapshot a Yoast meta value just before it's overwritten.
	 *
	 * Must return `$check` unmodified - this is a short-circuit filter
	 * (`null` means "don't short-circuit, proceed with the real write"),
	 * not a value Pastmark should ever change.
	 *
	 * @param mixed  $check      Whether to short-circuit the meta write.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New meta value (unused - only the key matters here).
	 * @param mixed  $prev_value Value to match against for the update, if any (unused here).
	 * @return mixed
	 */
	public function capture_meta_before_update( $check, $object_id, $meta_key, $meta_value, $prev_value ) {

		if ( 0 !== strpos( (string) $meta_key, self::META_PREFIX ) ) {
			return $check;
		}

		$this->pending_before_values[ $object_id . ':' . $meta_key ] = get_post_meta( $object_id, $meta_key, true );

		return $check;
	}

	/**
	 * Accumulate a changed Yoast meta key for the current request - shared
	 * callback for both `added_post_meta` and `updated_post_meta`, since a
	 * brand-new key (no prior row for this post) fires the former, an
	 * existing key the latter, and this logger treats both identically.
	 *
	 * @param int    $meta_id    Meta ID (unused).
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New meta value.
	 * @return void
	 */
	public function track_meta_change( $meta_id, $object_id, $meta_key, $meta_value ): void {

		if ( 0 !== strpos( (string) $meta_key, self::META_PREFIX ) ) {
			return;
		}

		$pending_key = $object_id . ':' . $meta_key;

		$before = array_key_exists( $pending_key, $this->pending_before_values ) ? $this->pending_before_values[ $pending_key ] : '';

		unset( $this->pending_before_values[ $pending_key ] );

		if ( $before === $meta_value ) {
			// Every field in the SEO meta box round-trips through this hook
			// on every save whether or not it actually changed (same no-op
			// round-trip shape PM-154 found for ACF field groups) - skip
			// fields that didn't actually change rather than counting them
			// toward something changed for this post.
			return;
		}

		$short_key = substr( (string) $meta_key, strlen( self::META_PREFIX ) );

		$this->pending_changes[ (int) $object_id ][ $short_key ] = array(
			'before' => $before,
			'after'  => $meta_value,
		);
	}

	/**
	 * Flush every post's accumulated Yoast meta changes at the end of the
	 * request: one dedicated row per `META_FIELD_MAP` key that changed,
	 * plus (if any other, unmapped key also changed) one aggregated
	 * fallback row covering all of those together.
	 *
	 * @return void
	 */
	public function flush_pending_changes(): void {

		foreach ( $this->pending_changes as $post_id => $changes ) {

			$post = get_post( (int) $post_id );

			if ( ! $post ) {
				continue;
			}

			$unmapped = array();

			foreach ( $changes as $short_key => $change ) {

				if ( isset( self::META_FIELD_MAP[ $short_key ] ) ) {
					$this->log_mapped_field_changed( $post, self::META_FIELD_MAP[ $short_key ], $change );
					continue;
				}

				$unmapped[ $short_key ] = $change;
			}

			if ( ! empty( $unmapped ) ) {
				$this->log_unmapped_fields_changed( $post, $unmapped );
			}
		}

		$this->pending_changes = array();
	}

	/**
	 * Log a single dedicated row for one `META_FIELD_MAP` field.
	 *
	 * @param \WP_Post $post      The post the field belongs to.
	 * @param array    $field_map `META_FIELD_MAP` entry (`action`, `label`).
	 * @param array    $change    `array{before: mixed, after: mixed}`.
	 * @return void
	 */
	protected function log_mapped_field_changed( \WP_Post $post, array $field_map, array $change ): void {

		$before_str = (string) $change['before'];
		$after_str  = (string) $change['after'];

		$diff = ContentDiffer::diff( $before_str, $after_str );

		$log_data = array(
			// The post's own real post_type - unlike ACF's own
			// `acf-field-group`/`acf-field` post types, Yoast stores its
			// SEO data as meta *on the post already being edited*, so this
			// is a genuine WP_Post reference and resolves the log viewer's
			// "go to object" link the same way a native post-update event
			// already does.
			'object_type' => $post->post_type,
			'object_id'   => $post->ID,
			'severity'    => Severity::INFO,
			'message'     => sprintf( 'Yoast SEO %s changed for "%s".', $field_map['label'], get_the_title( $post ) ),
			'context'     => $this->get_common_context(),
		);

		if ( null !== $diff ) {
			$log_data['after_data'] = wp_json_encode( array( 'diff' => $diff ) );
		} else {
			$log_data['before_data'] = wp_json_encode( array( $field_map['label'] => $change['before'] ) );
			$log_data['after_data']  = wp_json_encode( array( $field_map['label'] => $change['after'] ) );
		}

		$this->insert_event_log( Events::YOAST_SEO, $field_map['action'], $log_data );
	}

	/**
	 * Log one aggregated fallback row covering every changed Yoast meta key
	 * that isn't in `META_FIELD_MAP` - the ticket's original "one blob diff
	 * per save" behavior, kept as a safety net for any key this logger
	 * doesn't yet know about by name.
	 *
	 * @param \WP_Post $post    The post the fields belong to.
	 * @param array    $changes Unmapped changes, keyed by bare meta key.
	 * @return void
	 */
	protected function log_unmapped_fields_changed( \WP_Post $post, array $changes ): void {

		$before = wp_list_pluck( $changes, 'before' );
		$after  = wp_list_pluck( $changes, 'after' );

		$before_json = (string) wp_json_encode( $before, JSON_PRETTY_PRINT );
		$after_json  = (string) wp_json_encode( $after, JSON_PRETTY_PRINT );

		$diff = ContentDiffer::diff( $before_json, $after_json );

		$log_data = array(
			'object_type' => $post->post_type,
			'object_id'   => $post->ID,
			'message'     => sprintf( 'Yoast SEO meta changed for "%s".', get_the_title( $post ) ),
			'context'     => $this->get_common_context(),
		);

		if ( null !== $diff ) {
			$log_data['after_data'] = wp_json_encode( array( 'seo_meta_diff' => $diff ) );
		} else {
			$log_data['before_data'] = $before_json;
			$log_data['after_data']  = $after_json;
		}

		$this->insert_event_log( Events::YOAST_SEO, Actions::SEO_META_UPDATE, $log_data );
	}
}
