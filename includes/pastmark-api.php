<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
/**
 * Public functions other plugins/themes can call to interact with Pastmark,
 * without needing any of its internal namespaced classes.
 *
 * @package pastmark
 */

use Pastmark\Api\CustomEventLogger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pastmark_log_event' ) ) {

	/**
	 * Log a custom event into Pastmark's activity log.
	 *
	 * The public Logging API: lets other plugins/themes record their own
	 * events using Pastmark's schema, in one call, without touching
	 * Pastmark's internals. Goes through the same pipeline every native
	 * logger uses — exclusion rules (Settings -> Exclude) are respected,
	 * and `actor_type` is detected the same way (human/system/scheduled/
	 * ai_agent) — so a custom event behaves like a first-class citizen in
	 * the log viewer, not a bolted-on afterthought.
	 *
	 * A custom $event_type/$action pair that Pastmark's own Settings ->
	 * Events screen doesn't recognize still logs by default — Pastmark's
	 * event-enablement check fails open for unregistered pairs — but it
	 * won't have an on/off toggle or a severity label there. Pass
	 * `$args['severity']` explicitly if the default ('info') isn't right
	 * for the event.
	 *
	 * Call this after the `plugins_loaded` hook has finished firing (e.g.
	 * from `init` or later, or from the action/hook the event itself
	 * corresponds to) — Pastmark defines this function on its own
	 * `plugins_loaded` callback, so calling it any earlier will fail.
	 *
	 * Example:
	 *
	 *     pastmark_log_event( 'my_plugin_sync', 'completed', array(
	 *         'message'     => 'Synced 42 records from Acme CRM.',
	 *         'object_type' => 'sync_job',
	 *         'object_id'   => 42,
	 *         'severity'    => 'info',
	 *         'context'     => array( 'duration_ms' => 850 ),
	 *     ) );
	 *
	 * @param string $event_type Event type/category (e.g. `my_plugin_sync`). Required.
	 * @param string $action     Action within that event type (e.g. `completed`). Required.
	 * @param array  $args       {
	 *     Optional. See `Pastmark\Api\CustomEventLogger::log()` for the full field list.
	 *
	 *     @type string $message     Human-readable summary shown in the log viewer.
	 *     @type string $object_type Type of the related object, if any.
	 *     @type int    $object_id   ID of the related object, if any.
	 *     @type string $severity    One of `info`, `warning`, `error`, `critical`, `debug`.
	 *                                Defaults to `info`; an unrecognized value falls back to `info`.
	 *     @type array  $context     Arbitrary structured context, JSON-encoded on storage.
	 *     @type string $before_data Free-form "before" state.
	 *     @type string $after_data  Free-form "after" state.
	 *     @type string $integration Source tag shown in the log viewer. Defaults to `custom`.
	 *     @type int    $user_id     Attributed user ID. Defaults to the current user.
	 * }
	 *
	 * @return int|false Inserted log ID, or `false` if `$event_type`/`$action` were
	 *                    empty, the event is excluded by the site's exclusion settings,
	 *                    or the insert failed.
	 */
	function pastmark_log_event( string $event_type, string $action, array $args = array() ) {

		static $logger = null;

		if ( null === $logger ) {
			$logger = new CustomEventLogger();
		}

		return $logger->log( $event_type, $action, $args );
	}
}
