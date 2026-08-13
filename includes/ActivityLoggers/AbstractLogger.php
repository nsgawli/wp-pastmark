<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace LogTrail\ActivityLoggers;

use LogTrail\EventSettings\EventSettings;
use LogTrail\Utils\ExcludeHelper;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract logger base class.
 */
abstract class AbstractLogger {

	/**
	 * Logs model instance.
	 *
	 * @var \LogTrail\Models\LogTrail_Logs
	 */
	protected $logs_model;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logs_model = new \LogTrail\Models\LogTrail_Logs();
	}

	/**
	 * Wrap a hook callback so a bug in logger code (or in the data a hook
	 * hands us) can never break the WordPress action/filter chain it's
	 * attached to.
	 *
	 * PHP 7+ fatal errors (TypeError, Error, ArgumentCountError, ...)
	 * implement `\Throwable`, not `\Exception`, so this must catch
	 * `\Throwable` to actually stop them here instead of taking the site
	 * down. On failure, the first argument is returned unchanged so a
	 * guarded filter callback still no-ops safely.
	 *
	 * @param callable $callback Logger method to guard.
	 * @return callable
	 */
	protected function guarded( callable $callback ): callable {

		return function ( ...$args ) use ( $callback ) {

			try {
				return call_user_func_array( $callback, $args );
			} catch ( \Throwable $e ) {
				$this->handle_logger_exception( $e );
				return $args[0] ?? null;
			}
		};
	}

	/**
	 * Record a logger failure caught by `guarded()`.
	 *
	 * Only writes to the PHP error log (gated by WP_DEBUG_LOG, per WP
	 * plugin convention) so a broken logger stays silent-but-safe on
	 * production sites that don't have debug logging enabled, rather than
	 * risking a second failure by routing back through our own log storage.
	 *
	 * @param \Throwable $e Caught error/exception.
	 * @return void
	 */
	protected function handle_logger_exception( \Throwable $e ): void {

		if ( ! ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) {
			return;
		}

		$hook = current_filter();

		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			sprintf(
				'[LogTrail] %s caught in %s on hook "%s": %s in %s:%d',
				get_class( $e ),
				static::class,
				$hook ? $hook : 'unknown',
				$e->getMessage(),
				$e->getFile(),
				$e->getLine()
			)
		);
	}

	/**
	 * Insert activity log.
	 *
	 * @param array $data Log data.
	 * @return int|false
	 */
	protected function insert_log( array $data ) {

		$defaults = array(
			'timestamp'   => current_time( 'mysql', true ),
			'user_id'     => get_current_user_id(),
			'ip_address'  => $this->get_ip_address(),
			'event_type'  => '',
			'object_type' => '',
			'object_id'   => 0,
			'action'      => '',
			'message'     => '',
			'before_data' => '',
			'after_data'  => '',
			'context'     => array(),
			'severity'    => 'info',
			'site_id'     => get_current_blog_id(),
		);

		$data = wp_parse_args( $data, $defaults );

		if ( ExcludeHelper::should_exclude( $data ) ) {
			return false;
		}

		$data['context'] = wp_json_encode( $data['context'] );

		return $this->logs_model->insert( $data );
	}

	/**
	 * Insert event log.
	 *
	 * Checks whether the event/action is enabled before logging.
	 *
	 * @param string $event_type Event type.
	 * @param string $action Action.
	 * @param array  $data Log data.
	 *
	 * @return int|false
	 */
	protected function insert_event_log(
		string $event_type,
		string $action,
		array $data
	) {

		if ( ! EventSettings::is_enabled( $event_type, $action ) ) {
			return false;
		}

		$data['event_type'] = $event_type;
		$data['action']     = $action;

		return $this->insert_log( $data );
	}

	/**
	 * Get current user IP address.
	 *
	 * @return string
	 */
	protected function get_ip_address(): string {

		$ip_keys = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {

			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}

			$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

			$ip = explode( ',', $ip );
			$ip = trim( $ip[0] );

			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return '';
	}

	/**
	 * Get current user agent.
	 *
	 * @return string
	 */
	protected function get_user_agent(): string {

		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}

		return sanitize_text_field(
			wp_unslash( $_SERVER['HTTP_USER_AGENT'] )
		);
	}

	/**
	 * Get current request URL.
	 *
	 * @return string
	 */
	protected function get_request_url(): string {

		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		return esc_url_raw(
			wp_unslash( $_SERVER['REQUEST_URI'] )
		);
	}

	/**
	 * Get common context data.
	 *
	 * Every log entry automatically receives these values.
	 *
	 * `ip_address` is deliberately NOT included here even though every
	 * logger has it available: it's already stored on its own `ip_address`
	 * column (see `insert_log()`), and the admin UI reads it from there, so
	 * repeating it in `context` would just be noise in the details view.
	 *
	 * @param WP_User|null $actor The user who actually performed the action,
	 *                            when it isn't (yet) reflected by
	 *                            `wp_get_current_user()`. Some hooks
	 *                            (`wp_login`, `wp_logout`) fire before/after
	 *                            WordPress updates the "current user" global,
	 *                            so relying on it there records the wrong
	 *                            actor. Pass the `WP_User` the hook itself
	 *                            handed you in that case.
	 * @return array
	 */
	protected function get_common_context( ?WP_User $actor = null ): array {

		$current_user = $actor instanceof WP_User ? $actor : wp_get_current_user();

		return array(
			'current_user_id'    => $current_user->ID,
			'current_user_roles' => (array) $current_user->roles,

			'user_agent'         => $this->get_user_agent(),
			'request_url'        => $this->get_request_url(),
		);
	}
}
