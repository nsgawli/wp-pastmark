<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\RankMath;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;
use Pastmark\Utils\ContentDiffer;

defined( 'ABSPATH' ) || exit;

/**
 * RankMath global-settings activity logger (PM-167).
 *
 * Confirmed against the real installed plugin's own settings registry
 * (`includes/class-settings.php`): RankMath stores its configuration across
 * four standalone options - `rank-math-options-general`,
 * `rank-math-options-titles`, `rank-math-options-sitemap`,
 * `rank-math-options-instant-indexing`. Hooks WordPress's own dynamic
 * `update_option_{$option}` action per option, same shape
 * `YoastSeo\SettingsActivityLogger` already uses.
 *
 * **Deliberately kept at one aggregated `ContentDiffer`-backed diff per
 * option, not expanded to per-setting granularity like Yoast's own settings
 * logger** - a disclosed scope decision, not an oversight. Unlike Yoast
 * (where WP Activity Log's real sensor diffs ~34 individual settings),
 * WP Activity Log's own RankMath sensor (`class-rank-math-sensor.php`,
 * read in full before this ticket was scoped) diffs **zero** RankMath
 * options at all - its only settings-adjacent alert is a native module-
 * enable/disable action, added below as its own dedicated event. Building
 * dozens of hand-invented per-setting actions with no real WP Activity Log
 * precedent and no other stated demand would be exactly the kind of
 * "building ahead of actual requirement" this project's own sprint doc
 * warns against (see `sprint-10.md`'s "Out of scope / deferred" section).
 * Revisit if real user demand for RankMath settings-level granularity
 * surfaces, the same way Yoast's own expansion did.
 *
 * **Also deliberately not built:** RankMath's 404-monitor and built-in
 * redirections *settings* (both live inside `rank-math-options-general`,
 * confirmed via `includes/modules/404-monitor/`, `includes/modules/
 * redirections/`) fall through to this logger's own generic fallback diff
 * like any other unmapped setting - they're not singled out with their own
 * action, since RankMath's actual redirect *rules* (the data a site owner
 * would care about auditing) live in a separate custom DB table
 * (`rank_math_redirections`), entirely outside `update_post_meta`/
 * `update_option`, and are out of scope for this ticket the same way Yoast
 * Premium's own redirect manager was out of scope for PM-166 - not to be
 * confused with PM-168's separate "Redirection" plugin integration.
 */
class SettingsActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'rank-math';

	/**
	 * RankMath's own global-settings options, mapped to a human-readable
	 * label matching the corresponding admin screen name.
	 *
	 * @var array<string, string>
	 */
	private const TRACKED_OPTIONS = array(
		'rank-math-options-general'          => 'General',
		'rank-math-options-titles'           => 'Titles & Meta',
		'rank-math-options-sitemap'          => 'Sitemap',
		'rank-math-options-instant-indexing' => 'Instant Indexing',
	);

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

		foreach ( array_keys( self::TRACKED_OPTIONS ) as $option ) {
			add_action( "update_option_{$option}", $this->guarded( array( $this, 'log_option_updated' ) ), 10, 3 );
		}

		// RankMath's own native module-toggle action - confirmed
		// (`includes/class-module-manager.php`, fires whenever a module
		// like Sitemap/Schema/Redirections/404 Monitor is switched on or
		// off from Settings -> Modules) hands over both the module key and
		// its new state directly, so no old/new bridging is needed here.
		add_action( 'rank_math/module_changed', $this->guarded( array( $this, 'log_module_changed' ) ), 10, 2 );
	}

	/**
	 * Declare the shared `rank-math` event group - both RankMath loggers'
	 * actions live under this one group, so only this logger (listed first
	 * in `RankMathIntegration::get_logger_classes()`) registers it.
	 *
	 * Uses the `pastmark_registered_events` filter (PM-151's settled
	 * convention, `source => 'rank-math'`) rather than editing
	 * `EventRegistry::get_events()` directly, per
	 * `docs/BUILDING-AN-INTEGRATION.md`.
	 *
	 * @return void
	 */
	protected function register_events(): void {

		add_filter( 'pastmark_registered_events', array( $this, 'add_event_group' ) );
	}

	/**
	 * `pastmark_registered_events` callback - adds RankMath's event group.
	 *
	 * Several actions here are the same `Actions` constants
	 * `YoastSeo\SettingsActivityLogger`/`PostMetaActivityLogger` already
	 * declare (e.g. `SEO_TITLE_CHANGE`) - deliberately reused, not
	 * duplicated, since `event_type`/`source` (`rank-math` vs `yoast-seo`)
	 * already distinguishes which plugin produced a given row; adding a
	 * second constant per plugin for an identical concept would just be
	 * redundant. Declaring the group here (again, from RankMath's own
	 * logger) is what gives these shared actions their own entry under
	 * RankMath's group in the Settings -> Events UI, same as Yoast's.
	 *
	 * @param array $events Registered events, keyed by event type.
	 * @return array
	 */
	public function add_event_group( array $events ): array {

		$events[ Events::RANK_MATH ] = array(
			'label'   => __( 'RankMath', 'pastmark' ),
			'source'  => 'rank-math',
			'actions' => array(
				array(
					'key'            => Actions::UPDATE,
					'label'          => Actions::resolve_label( Actions::UPDATE ),
					'description'    => __( 'A RankMath global setting changed that isn\'t covered by a more specific action below.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::SEO_MODULE_TOGGLE,
					'label'          => Actions::resolve_label( Actions::SEO_MODULE_TOGGLE ),
					'description'    => __( 'A RankMath feature module (Sitemap, Schema, Redirections, 404 Monitor, ...) enabled or disabled.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::SEO_META_UPDATE,
					'label'          => Actions::resolve_label( Actions::SEO_META_UPDATE ),
					'description'    => __( 'A post\'s RankMath SEO meta changed, not covered by a more specific action below.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::SEO_TITLE_CHANGE,
					'label'          => Actions::resolve_label( Actions::SEO_TITLE_CHANGE ),
					'description'    => __( 'A post\'s RankMath SEO title changed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::SEO_METADESC_CHANGE,
					'label'          => Actions::resolve_label( Actions::SEO_METADESC_CHANGE ),
					'description'    => __( 'A post\'s RankMath meta description changed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::SEO_FOCUS_KEYWORD_CHANGE,
					'label'          => Actions::resolve_label( Actions::SEO_FOCUS_KEYWORD_CHANGE ),
					'description'    => __( 'A post\'s RankMath focus keyword(s) changed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::SEO_CORNERSTONE_CHANGE,
					'label'          => Actions::resolve_label( Actions::SEO_CORNERSTONE_CHANGE ),
					'description'    => __( 'A post\'s RankMath "Pillar Content" flag toggled.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::SEO_NOINDEX_CHANGE,
					'label'          => Actions::resolve_label( Actions::SEO_NOINDEX_CHANGE ),
					'description'    => __( 'A post\'s "allow search engines to show it in results" setting changed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::SEO_NOFOLLOW_CHANGE,
					'label'          => Actions::resolve_label( Actions::SEO_NOFOLLOW_CHANGE ),
					'description'    => __( 'A post\'s "should search engines follow its links" setting changed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::SEO_ROBOTS_NOARCHIVE_CHANGE,
					'label'          => Actions::resolve_label( Actions::SEO_ROBOTS_NOARCHIVE_CHANGE ),
					'description'    => __( 'A post\'s "No Archive" robots-meta setting toggled.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::SEO_ROBOTS_NOIMAGEINDEX_CHANGE,
					'label'          => Actions::resolve_label( Actions::SEO_ROBOTS_NOIMAGEINDEX_CHANGE ),
					'description'    => __( 'A post\'s "No Image Index" robots-meta setting toggled.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::SEO_ROBOTS_NOSNIPPET_CHANGE,
					'label'          => Actions::resolve_label( Actions::SEO_ROBOTS_NOSNIPPET_CHANGE ),
					'description'    => __( 'A post\'s "No Snippet" robots-meta setting toggled.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::SEO_MAX_SNIPPET_LENGTH_CHANGE,
					'label'          => Actions::resolve_label( Actions::SEO_MAX_SNIPPET_LENGTH_CHANGE ),
					'description'    => __( 'A post\'s max-snippet-length advanced robots setting changed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::SEO_MAX_VIDEO_PREVIEW_CHANGE,
					'label'          => Actions::resolve_label( Actions::SEO_MAX_VIDEO_PREVIEW_CHANGE ),
					'description'    => __( 'A post\'s max-video-preview advanced robots setting changed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::SEO_MAX_IMAGE_PREVIEW_CHANGE,
					'label'          => Actions::resolve_label( Actions::SEO_MAX_IMAGE_PREVIEW_CHANGE ),
					'description'    => __( 'A post\'s max-image-preview advanced robots setting changed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::SEO_CANONICAL_URL_CHANGE,
					'label'          => Actions::resolve_label( Actions::SEO_CANONICAL_URL_CHANGE ),
					'description'    => __( 'A post\'s canonical URL changed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
			),
		);

		return $events;
	}

	/**
	 * Log a RankMath global-settings option update - one aggregated diff
	 * per option, per the class docblock's disclosed scope decision.
	 *
	 * @param mixed  $old_value Old option value.
	 * @param mixed  $value     New option value.
	 * @param string $option    Option name.
	 * @return void
	 */
	public function log_option_updated( $old_value, $value, string $option ): void {

		if ( ! array_key_exists( $option, self::TRACKED_OPTIONS ) ) {
			return;
		}

		$before = is_array( $old_value ) ? $old_value : array( 'value' => $old_value );
		$after  = is_array( $value ) ? $value : array( 'value' => $value );

		if ( $before === $after ) {
			return;
		}

		$label = self::TRACKED_OPTIONS[ $option ];

		$before_json = $this->encode_option_data( $before );
		$after_json  = $this->encode_option_data( $after );

		$diff = ContentDiffer::diff( $before_json, $after_json );

		$log_data = array(
			'object_type' => 'option',
			'object_id'   => 0,
			'user_id'     => get_current_user_id(),
			'severity'    => Severity::WARNING,
			'message'     => sprintf( 'RankMath "%s" settings updated.', $label ),
			'context'     => array_merge( $this->get_common_context(), array( 'option' => $option ) ),
		);

		if ( null !== $diff ) {
			$log_data['after_data'] = wp_json_encode( array( $label . ' diff' => $diff ) );
		} else {
			$log_data['before_data'] = $before_json;
			$log_data['after_data']  = $after_json;
		}

		$this->insert_event_log( Events::RANK_MATH, Actions::UPDATE, $log_data );
	}

	/**
	 * Log a RankMath module being enabled/disabled.
	 *
	 * @param string $module Module key (e.g. `'sitemap'`, `'404-monitor'`).
	 * @param bool   $state  New state - `true` when enabled.
	 * @return void
	 */
	public function log_module_changed( $module, $state ): void {

		$this->insert_event_log(
			Events::RANK_MATH,
			Actions::SEO_MODULE_TOGGLE,
			array(
				'object_type' => 'option',
				'object_id'   => 0,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'RankMath "%s" module %s.',
					$module,
					$state ? 'enabled' : 'disabled'
				),
				'before_data' => wp_json_encode( array( $module => ! $state ) ),
				'after_data'  => wp_json_encode( array( $module => (bool) $state ) ),
				'context'     => array_merge( $this->get_common_context(), array( 'module' => $module ) ),
			)
		);
	}

	/**
	 * Encode an option's array for diffing/storage.
	 *
	 * `ContentDiffer` diffs line-by-line; the default `wp_json_encode()`
	 * single-line output would make any change look like "the whole line
	 * changed" and defeat diffing entirely - the same fix PM-154 needed for
	 * ACF field-group data, applied here for the identical reason.
	 *
	 * @param array $data Option data.
	 * @return string
	 */
	protected function encode_option_data( array $data ): string {

		return (string) wp_json_encode( $data, JSON_PRETTY_PRINT );
	}
}
