<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers;

use Pastmark\EventSettings\EventSettings;
use Pastmark\Utils\ExcludeHelper;
use Pastmark\Utils\IpAnonymizer;
use Pastmark\Utils\SensitiveDataMasker;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract logger base class.
 */
abstract class AbstractLogger {

	/**
	 * Logs model instance.
	 *
	 * @var \Pastmark\Models\Pastmark_Logs
	 */
	protected $logs_model;

	/**
	 * Which integration/source this logger's events belong to — `core` for
	 * WordPress-native activity, `woocommerce` for WooCommerce loggers.
	 * `Pastmark\ActivityLoggers\WooCommerce\*ActivityLogger` subclasses
	 * override this; core loggers can rely on the default below instead of
	 * repeating it.
	 *
	 * @var string
	 */
	protected $integration = 'core';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logs_model = new \Pastmark\Models\Pastmark_Logs();
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
	 * Delegates to the shared `ExceptionLogger::log()` helper (PM-149) -
	 * only writes to the PHP error log (gated by WP_DEBUG_LOG, per WP
	 * plugin convention) so a broken logger stays silent-but-safe on
	 * production sites that don't have debug logging enabled, rather than
	 * risking a second failure by routing back through our own log
	 * storage. `IntegrationRegistry::load_all()` calls the same helper
	 * for a failure caught during an integration's own bootstrap step,
	 * so this behavior is defined in exactly one place.
	 *
	 * @param \Throwable $e Caught error/exception.
	 * @return void
	 */
	protected function handle_logger_exception( \Throwable $e ): void {

		\Pastmark\Utils\ExceptionLogger::log( $e, static::class );
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
			'actor_type'  => $this->detect_actor_type(),
			'integration' => $this->integration,
			'site_id'     => get_current_blog_id(),
		);

		$data = wp_parse_args( $data, $defaults );

		if ( ExcludeHelper::should_exclude( $data ) ) {
			return false;
		}

		/*
		 * Mask sensitive values (passwords, tokens, secrets, API keys, ...)
		 * out of before/after/context data before it's ever written to
		 * storage. Runs here — the one choke point every native logger and
		 * the Public Logging API (`CustomEventLogger`) already funnel
		 * through — so both are protected by a single change. See
		 * `SensitiveDataMasker` and `docs/SECURITY-PRIVACY-BASELINE.md`.
		 */
		$data['before_data'] = SensitiveDataMasker::mask( $data['before_data'] );
		$data['after_data']  = SensitiveDataMasker::mask( $data['after_data'] );
		$data['context']     = SensitiveDataMasker::mask( $data['context'] );

		/*
		 * Anonymize the IP for storage only, after exclusion matching above
		 * has already run against the real address — `ExcludeHelper`'s
		 * `excludedIPs` rules must keep matching real IPs regardless of this
		 * setting, or turning it on would silently un-exclude anyone
		 * excluded by IP. See `IpAnonymizer`.
		 */
		$data['ip_address'] = IpAnonymizer::maybe_anonymize( $data['ip_address'] );

		$data['context'] = wp_json_encode( $data['context'] );

		$log_id = $this->logs_model->insert( $data );

		if ( $log_id ) {

			/**
			 * Fires immediately after a log row is successfully written.
			 *
			 * `$data` here is exactly what was just written to
			 * `wp_pastmark_logs` - already masked (`SensitiveDataMasker`)
			 * and IP-anonymized (`IpAnonymizer`), with `context` already
			 * JSON-encoded to a string. Does not fire for an event
			 * `ExcludeHelper::should_exclude()` filtered out above, since
			 * this point is only reached after that check already passed.
			 *
			 * The one real-time signal that a loggable event just
			 * happened - built for Pastmark Pro's alert-rule engine so it
			 * doesn't need to poll `wp_pastmark_logs` on a schedule, but
			 * available to any consumer that needs to react to a log
			 * write as it happens. See `docs/EXTENSION-POINTS.md`.
			 *
			 * @param int   $log_id The inserted row's ID.
			 * @param array $data   The full row data that was written.
			 */
			do_action( 'pastmark_log_inserted', $log_id, $data );
		}

		return $log_id;
	}

	/**
	 * Update an already-inserted row's message/context/severity.
	 *
	 * Used by throttling logic (see
	 * `UserActivityLogger::log_login_failed()` and `Utils\LoginThrottle`) to
	 * collapse a burst of repeated events into one row that keeps its count
	 * current, instead of inserting a new row per event once a threshold is
	 * crossed. `context` goes through the same masking pass as `insert_log()`
	 * so an updated row is never less protected than a freshly inserted one.
	 *
	 * @param int   $id     Row ID to update.
	 * @param array $fields Fields to update — see `Pastmark_Logs::update()`
	 *                      for which keys are recognized.
	 * @return bool
	 */
	protected function update_log( int $id, array $fields ): bool {

		if ( array_key_exists( 'context', $fields ) ) {
			$fields['context'] = wp_json_encode( SensitiveDataMasker::mask( $fields['context'] ) );
		}

		return $this->logs_model->update( $id, $fields );
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
	 * Determine which kind of actor performed the action being logged.
	 *
	 * Returns `scheduled` for WP-Cron-driven events, `system` for any other
	 * context with no logged-in user (e.g. `DOING_AUTOSAVE`, an
	 * unauthenticated REST callback, WP-CLI), `ai_agent` for a logged-in
	 * request whose user agent heuristically matches a known AI-agent/
	 * automation pattern, otherwise `human`.
	 *
	 * The `ai_agent` value is a heuristic, not a certainty — see
	 * `is_ai_agent_user_agent()` and `docs/AI-ACTOR-DETECTION.md`.
	 *
	 * @return string
	 */
	protected function detect_actor_type(): string {

		if ( wp_doing_cron() ) {
			return 'scheduled';
		}

		if ( ! get_current_user_id() ) {
			return 'system';
		}

		if ( $this->is_ai_agent_user_agent( $this->get_user_agent() ) ) {
			return 'ai_agent';
		}

		return 'human';
	}

	/**
	 * Heuristically match a user agent string against known AI-agent/
	 * automation UA substrings.
	 *
	 * This is a signal, not proof: a human can spoof a UA string to
	 * avoid/force this match, and a legitimate script can happen to share
	 * a substring with the pattern list. It exists to surface a likely
	 * AI-driven change for review, not to make an authentication claim —
	 * see `docs/AI-ACTOR-DETECTION.md` for the full caveat.
	 *
	 * The pattern list is intentionally small and extensible via the
	 * `pastmark_ai_agent_user_agent_patterns` filter rather than hardcoded,
	 * so a site/integrator can add its own agent's UA substring (or
	 * remove one producing false positives) without a core code change.
	 *
	 * @param string $user_agent User agent string to test (already the
	 *                           current request's, via `get_user_agent()`).
	 * @return bool
	 */
	protected function is_ai_agent_user_agent( string $user_agent ): bool {

		if ( '' === $user_agent ) {
			return false;
		}

		/**
		 * Filters the list of user-agent substrings treated as AI-agent/
		 * automation signals by `detect_actor_type()`.
		 *
		 * Matching is a case-insensitive substring search, so a pattern
		 * like `claude` matches `Claude-Code/1.0`, `claude-3-opus`, etc.
		 *
		 * @param string[] $patterns Default UA substrings (lowercase).
		 */
		$patterns = (array) apply_filters(
			'pastmark_ai_agent_user_agent_patterns',
			array(
				'claude',
				'anthropic',
				'gpt-',
				'chatgpt',
				'openai',
				'gemini',
				'copilot',
				'langchain',
				'autogpt',
				'mcp-client',
			)
		);

		$user_agent = strtolower( $user_agent );

		foreach ( $patterns as $pattern ) {

			$pattern = strtolower( trim( (string) $pattern ) );

			if ( '' === $pattern ) {
				continue;
			}

			if ( false !== strpos( $user_agent, $pattern ) ) {
				return true;
			}
		}

		return false;
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
