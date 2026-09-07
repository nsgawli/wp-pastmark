<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Api;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Logs custom, externally-supplied events (from other plugins, themes, or
 * automation) through the same pipeline every native logger uses —
 * exclusion checks (`ExcludeHelper`), actor-type detection, and the unified
 * event schema — instead of a second, parallel storage mechanism.
 *
 * The documented public entry point is the `pastmark_log_event()` function
 * in `includes/pastmark-api.php`, not this class directly — its name and
 * namespace aren't part of the stable contract and may change.
 */
class CustomEventLogger extends AbstractLogger {

	/**
	 * Default integration/source tag for events logged through this API,
	 * distinguishing them from native `core`/`woocommerce` events in the
	 * log viewer. Callers can override via $args['integration'].
	 *
	 * @var string
	 */
	protected $integration = 'custom';

	/**
	 * Max length enforced on `event_type`/`action`/`object_type`, matching
	 * their `varchar(100)` columns (see
	 * `Installation\Autoloader::create_table()`). Native loggers never
	 * exceed this in practice, so they don't bother enforcing it; input
	 * here comes from outside this codebase, so it's capped defensively.
	 */
	private const MAX_KEY_LENGTH = 100;

	/**
	 * Max length enforced on `integration`, matching its `varchar(50)`
	 * column.
	 */
	private const MAX_INTEGRATION_LENGTH = 50;

	/**
	 * Log a custom event.
	 *
	 * @param string $event_type Event type/category, e.g. `my_plugin_sync`.
	 * @param string $action     Action within that event type, e.g. `completed`.
	 * @param array  $args {
	 *     Optional. Event data.
	 *
	 *     @type string $message     Human-readable summary shown in the log viewer.
	 *     @type string $object_type Type of the related object, if any.
	 *     @type int    $object_id   ID of the related object, if any.
	 *     @type string $severity    One of `Severity`'s constants. Defaults to `info`;
	 *                                an unrecognized value falls back to `info`.
	 *     @type array  $context     Arbitrary structured context, JSON-encoded on storage.
	 *     @type string $before_data Free-form "before" state.
	 *     @type string $after_data  Free-form "after" state.
	 *     @type string $integration Source tag shown in the log viewer. Defaults to `custom`.
	 *     @type int    $user_id     Attributed user ID. Defaults to the current user.
	 * }
	 * @return int|false Inserted log ID, or `false` if `$event_type`/`$action` were
	 *                    empty, the event is excluded by the site's exclusion
	 *                    settings, or the insert failed.
	 */
	public function log( string $event_type, string $action, array $args = array() ) {

		$event_type = $this->sanitize_key_field( $event_type, self::MAX_KEY_LENGTH );
		$action     = $this->sanitize_key_field( $action, self::MAX_KEY_LENGTH );

		if ( '' === $event_type || '' === $action ) {
			return false;
		}

		$severity = isset( $args['severity'] ) ? sanitize_text_field( (string) $args['severity'] ) : Severity::INFO;

		if ( ! in_array( $severity, self::valid_severities(), true ) ) {
			$severity = Severity::INFO;
		}

		$integration = isset( $args['integration'] )
			? $this->sanitize_key_field( (string) $args['integration'], self::MAX_INTEGRATION_LENGTH )
			: '';

		$data = array(
			'message'     => isset( $args['message'] ) ? sanitize_textarea_field( (string) $args['message'] ) : '',
			'object_type' => isset( $args['object_type'] ) ? $this->sanitize_key_field( (string) $args['object_type'], self::MAX_KEY_LENGTH ) : '',
			'object_id'   => isset( $args['object_id'] ) ? absint( $args['object_id'] ) : 0,
			'severity'    => $severity,
			'context'     => isset( $args['context'] ) && is_array( $args['context'] ) ? $args['context'] : array(),
			'before_data' => isset( $args['before_data'] ) ? sanitize_textarea_field( (string) $args['before_data'] ) : '',
			'after_data'  => isset( $args['after_data'] ) ? sanitize_textarea_field( (string) $args['after_data'] ) : '',
			'integration' => '' !== $integration ? $integration : $this->integration,
		);

		if ( isset( $args['user_id'] ) ) {
			$data['user_id'] = absint( $args['user_id'] );
		}

		return $this->insert_event_log( $event_type, $action, $data );
	}

	/**
	 * Sanitize and length-cap a schema key-style field (`event_type`,
	 * `action`, `object_type`, `integration`).
	 *
	 * @param string $value      Raw value.
	 * @param int    $max_length Maximum length to keep.
	 * @return string
	 */
	private function sanitize_key_field( string $value, int $max_length ): string {

		$value = sanitize_text_field( $value );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max_length );
		}

		return substr( $value, 0, $max_length );
	}

	/**
	 * Known severity values a custom event may use.
	 *
	 * @return string[]
	 */
	private static function valid_severities(): array {

		return array(
			Severity::INFO,
			Severity::WARNING,
			Severity::ERROR,
			Severity::CRITICAL,
			Severity::DEBUG,
		);
	}
}
