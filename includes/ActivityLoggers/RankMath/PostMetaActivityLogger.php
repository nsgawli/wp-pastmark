<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\RankMath;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Utils\ContentDiffer;

defined( 'ABSPATH' ) || exit;

/**
 * RankMath per-post meta activity logger (PM-167).
 *
 * Confirmed against the real installed plugin: RankMath stores per-post SEO
 * data as post meta, every key prefixed `rank_math_` (no leading
 * underscore, unlike Yoast's `_yoast_wpseo_` - confirmed via
 * `includes/class-metadata.php`'s `get_metadata()`/`update_metadata()`
 * helpers, which both literally concatenate `'rank_math_' . $key`).
 *
 * **A real, load-bearing question resolved empirically before writing this
 * class, not assumed:** RankMath's own metabox save handler
 * (`Metabox::save_meta()`, hooked to `save_post`) fires a `do_action()` with
 * no listener anywhere in the installed free-edition source - reading the
 * code alone left it genuinely unclear whether a real user-driven save
 * (via RankMath's React sidebar panel) ever calls plain
 * `update_post_meta()`/`add_post_meta()` at all, or bypasses them via some
 * other mechanism (its own REST payload injection, since `rank_math_*` keys
 * are confirmed absent from `core/editor`'s standard REST `meta` payload -
 * they're not `register_post_meta()`'d). Resolved by driving a real save
 * through the actual wp-admin editor with Playwright (auth-cookie-injected
 * session, no password touched) while a temporary diagnostic hook logged
 * every `added_post_meta`/`updated_post_meta` call: setting RankMath's
 * Focus Keyword field and saving produced real `add_post_meta()` calls for
 * `rank_math_focus_keyword` (and RankMath's own auto-computed
 * `rank_math_seo_score`) - confirming the same `update_post_metadata`
 * filter + `updated_post_meta`/`added_post_meta` action mechanism already
 * used for Yoast works identically here, whatever RankMath's own client-side
 * delivery mechanism turns out to be under the hood.
 *
 * Saving the SEO panel writes several `rank_math_*` keys in one request, so
 * changes are accumulated per post during the request and flushed on
 * `shutdown`, same shape as `YoastSeo\PostMetaActivityLogger`. A key present
 * in `META_FIELD_MAP` gets its own dedicated row; `rank_math_robots`/
 * `rank_math_advanced_robots` (each a single meta key whose *value* is an
 * array covering several independently-toggleable settings) get their own
 * per-term diffing so each toggled term produces its own row too - matching
 * WP Activity Log's own per-term alert granularity for the equivalent
 * fields (confirmed against its real sensor source,
 * `class-rank-math-sensor.php`), without copying two confirmed copy bugs in
 * its alert descriptions there (10708/10709's labels are swapped, and 10712
 * is mislabeled - none of that affects Pastmark, which doesn't reuse WP
 * Activity Log's alert text). Everything else falls back to one aggregated
 * `SEO_META_UPDATE` row, exactly like Yoast's logger.
 */
class PostMetaActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'rank-math';

	/**
	 * Prefix every RankMath post-meta key uses (no leading underscore,
	 * unlike Yoast's `_yoast_wpseo_`).
	 */
	private const META_PREFIX = 'rank_math_';

	/**
	 * RankMath per-post meta keys (bare, prefix-stripped) whose value is a
	 * plain scalar - each gets a dedicated `Actions` constant, reusing the
	 * same constants `YoastSeo\PostMetaActivityLogger` already declares for
	 * the conceptually identical Yoast field (event_type + integration
	 * already distinguish which plugin produced a row).
	 *
	 * @var array<string, array{action: string, label: string}>
	 */
	private const META_FIELD_MAP = array(
		'title'          => array(
			'action' => Actions::SEO_TITLE_CHANGE,
			'label'  => 'SEO title',
		),
		'description'    => array(
			'action' => Actions::SEO_METADESC_CHANGE,
			'label'  => 'meta description',
		),
		'focus_keyword'  => array(
			'action' => Actions::SEO_FOCUS_KEYWORD_CHANGE,
			'label'  => 'focus keyword',
		),
		'pillar_content' => array(
			'action' => Actions::SEO_CORNERSTONE_CHANGE,
			'label'  => 'Pillar Content flag',
		),
		'canonical_url'  => array(
			'action' => Actions::SEO_CANONICAL_URL_CHANGE,
			'label'  => 'canonical URL',
		),
	);

	/**
	 * Terms within the `rank_math_robots` meta value (a plain array of
	 * active term strings - confirmed via RankMath's own
	 * `Helper::get_robots_defaults()`, which explicitly checks
	 * `in_array( 'noindex', $robots, true )`) mapped to their own action.
	 * `'index'` is deliberately not listed - it's a companion "allowed"
	 * marker RankMath adds when `'noindex'` is absent, not an independently
	 * toggleable setting of its own; a change in `'noindex'`'s own presence
	 * already captures that same transition.
	 *
	 * @var array<string, string>
	 */
	private const ROBOTS_TERM_ACTIONS = array(
		'noindex'      => Actions::SEO_NOINDEX_CHANGE,
		'nofollow'     => Actions::SEO_NOFOLLOW_CHANGE,
		'noarchive'    => Actions::SEO_ROBOTS_NOARCHIVE_CHANGE,
		'noimageindex' => Actions::SEO_ROBOTS_NOIMAGEINDEX_CHANGE,
		'nosnippet'    => Actions::SEO_ROBOTS_NOSNIPPET_CHANGE,
	);

	/**
	 * Keys within the `rank_math_advanced_robots` meta value (an
	 * associative array, e.g. `'max-snippet' => '-1'` - confirmed via WP
	 * Activity Log's own real sensor, which reads this same shape) mapped
	 * to their own action.
	 *
	 * @var array<string, string>
	 */
	private const ADVANCED_ROBOTS_KEY_ACTIONS = array(
		'max-snippet'       => Actions::SEO_MAX_SNIPPET_LENGTH_CHANGE,
		'max-video-preview' => Actions::SEO_MAX_VIDEO_PREVIEW_CHANGE,
		'max-image-preview' => Actions::SEO_MAX_IMAGE_PREVIEW_CHANGE,
	);

	/**
	 * Meta values captured before they're overwritten, keyed by
	 * "{post_id}:{meta_key}".
	 *
	 * @var array<string, mixed>
	 */
	protected $pending_before_values = array();

	/**
	 * Every RankMath meta key changed so far this request, keyed by post ID,
	 * each entry itself keyed by the bare (prefix-stripped) meta key name.
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
	 * Snapshot a RankMath meta value just before it's overwritten.
	 *
	 * @param mixed  $check      Whether to short-circuit the meta write.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New meta value (unused).
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
	 * Accumulate a changed RankMath meta key for the current request.
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
			return;
		}

		$short_key = substr( (string) $meta_key, strlen( self::META_PREFIX ) );

		$this->pending_changes[ (int) $object_id ][ $short_key ] = array(
			'before' => $before,
			'after'  => $meta_value,
		);
	}

	/**
	 * Flush every post's accumulated RankMath meta changes at the end of
	 * the request.
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

				if ( 'robots' === $short_key ) {
					$this->log_robots_changed( $post, $change );
					continue;
				}

				if ( 'advanced_robots' === $short_key ) {
					$this->log_advanced_robots_changed( $post, $change );
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
			'object_type' => $post->post_type,
			'object_id'   => $post->ID,
			'message'     => sprintf( 'RankMath %s changed for "%s".', $field_map['label'], get_the_title( $post ) ),
			'context'     => $this->get_common_context(),
		);

		if ( null !== $diff ) {
			$log_data['after_data'] = wp_json_encode( array( 'diff' => $diff ) );
		} else {
			$log_data['before_data'] = wp_json_encode( array( $field_map['label'] => $change['before'] ) );
			$log_data['after_data']  = wp_json_encode( array( $field_map['label'] => $change['after'] ) );
		}

		$this->insert_event_log( Events::RANK_MATH, $field_map['action'], $log_data );
	}

	/**
	 * Log per-term changes within `rank_math_robots` - a plain array of
	 * active term strings (see `ROBOTS_TERM_ACTIONS`'s docblock). Each term
	 * whose presence differs between before/after produces its own row.
	 *
	 * @param \WP_Post $post   The post the field belongs to.
	 * @param array    $change `array{before: mixed, after: mixed}`.
	 * @return void
	 */
	protected function log_robots_changed( \WP_Post $post, array $change ): void {

		$before_terms = is_array( $change['before'] ) ? $change['before'] : array();
		$after_terms  = is_array( $change['after'] ) ? $change['after'] : array();

		$any_term_matched = false;

		foreach ( self::ROBOTS_TERM_ACTIONS as $term => $action ) {

			$was_present = in_array( $term, $before_terms, true );
			$is_present  = in_array( $term, $after_terms, true );

			if ( $was_present === $is_present ) {
				continue;
			}

			$any_term_matched = true;

			$this->insert_event_log(
				Events::RANK_MATH,
				$action,
				array(
					'object_type' => $post->post_type,
					'object_id'   => $post->ID,
					'message'     => sprintf( 'RankMath "%s" robots setting changed for "%s".', $term, get_the_title( $post ) ),
					'before_data' => wp_json_encode( array( $term => $was_present ) ),
					'after_data'  => wp_json_encode( array( $term => $is_present ) ),
					'context'     => $this->get_common_context(),
				)
			);
		}

		if ( $any_term_matched ) {
			return;
		}

		// The raw array differed (e.g. the internal `'index'` companion
		// marker's presence flipped, or a term this logger doesn't
		// recognize by name) but no known term's presence actually
		// changed - fall back rather than silently dropping a real change.
		$this->log_unmapped_fields_changed( $post, array( 'robots' => $change ) );
	}

	/**
	 * Log per-key changes within `rank_math_advanced_robots` - an
	 * associative array (see `ADVANCED_ROBOTS_KEY_ACTIONS`'s docblock).
	 *
	 * @param \WP_Post $post   The post the field belongs to.
	 * @param array    $change `array{before: mixed, after: mixed}`.
	 * @return void
	 */
	protected function log_advanced_robots_changed( \WP_Post $post, array $change ): void {

		$before = is_array( $change['before'] ) ? $change['before'] : array();
		$after  = is_array( $change['after'] ) ? $change['after'] : array();

		$any_key_matched = false;

		foreach ( self::ADVANCED_ROBOTS_KEY_ACTIONS as $key => $action ) {

			$old_val = $before[ $key ] ?? null;
			$new_val = $after[ $key ] ?? null;

			if ( $old_val === $new_val ) {
				continue;
			}

			$any_key_matched = true;

			$this->insert_event_log(
				Events::RANK_MATH,
				$action,
				array(
					'object_type' => $post->post_type,
					'object_id'   => $post->ID,
					'message'     => sprintf( 'RankMath "%s" advanced robots setting changed for "%s".', $key, get_the_title( $post ) ),
					'before_data' => wp_json_encode( array( $key => $old_val ) ),
					'after_data'  => wp_json_encode( array( $key => $new_val ) ),
					'context'     => $this->get_common_context(),
				)
			);
		}

		if ( $any_key_matched ) {
			return;
		}

		$this->log_unmapped_fields_changed( $post, array( 'advanced_robots' => $change ) );
	}

	/**
	 * Log one aggregated fallback row covering every changed RankMath meta
	 * key that isn't in `META_FIELD_MAP` and isn't `robots`/
	 * `advanced_robots` (or whose array value changed with no recognized
	 * term/key actually differing) - the same safety net
	 * `YoastSeo\PostMetaActivityLogger` uses.
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
			'message'     => sprintf( 'RankMath SEO meta changed for "%s".', get_the_title( $post ) ),
			'context'     => $this->get_common_context(),
		);

		if ( null !== $diff ) {
			$log_data['after_data'] = wp_json_encode( array( 'seo_meta_diff' => $diff ) );
		} else {
			$log_data['before_data'] = $before_json;
			$log_data['after_data']  = $after_json;
		}

		$this->insert_event_log( Events::RANK_MATH, Actions::SEO_META_UPDATE, $log_data );
	}
}
