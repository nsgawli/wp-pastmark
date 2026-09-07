<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Exclusion helper.
 */
class ExcludeHelper {

	/**
	 * Option name `get_settings()` reads and this class memoizes.
	 */
	const OPTION_NAME = 'pastmark_exclude_settings';

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	protected static ?array $settings = null;

	/**
	 * Register the hooks that keep `self::$settings` from going stale
	 * within a single PHP process.
	 *
	 * `get_settings()` memoizes the option for the process's entire
	 * lifetime with no invalidation - harmless on the overwhelmingly common
	 * path (a fresh PHP process per HTTP request, so a fresh empty cache
	 * every time), but wrong for the one request that both saves new
	 * exclude settings *and* triggers a logger call depending on the new
	 * value in that same request (e.g. `WPSettingsActivityLogger`'s own
	 * `updated_option`-triggered logging of the settings change itself).
	 * Found during Sprint 5's PM-140 regression pass.
	 *
	 * @return void
	 */
	public static function init(): void {

		add_action( 'update_option_' . self::OPTION_NAME, array( self::class, 'invalidate_cache' ) );
		add_action( 'add_option_' . self::OPTION_NAME, array( self::class, 'invalidate_cache' ) );
		add_action( 'delete_option_' . self::OPTION_NAME, array( self::class, 'invalidate_cache' ) );
	}

	/**
	 * Drop the memoized settings so the next `get_settings()` call in this
	 * process re-reads the option's real, current value.
	 *
	 * @return void
	 */
	public static function invalidate_cache(): void {

		self::$settings = null;
	}

	/**
	 * Should exclude log.
	 *
	 * @param array $data Log data.
	 *
	 * @return bool
	 */
	public static function should_exclude( array $data ): bool {

		$settings = self::get_settings();

		$context = $data['context'] ?? array();

		/*
		|--------------------------------------------------------------------------
		| User
		|--------------------------------------------------------------------------
		*/

		if (
			self::match_user(
				$data,
				$context,
				$settings
			)
		) {
			return true;
		}

		/*
		|--------------------------------------------------------------------------
		| Role
		|--------------------------------------------------------------------------
		*/

		if (
			self::match_roles(
				$context,
				$settings
			)
		) {
			return true;
		}

		/*
		|--------------------------------------------------------------------------
		| IP
		|--------------------------------------------------------------------------
		*/

		if (
			self::match_ip(
				$data,
				$context,
				$settings
			)
		) {
			return true;
		}

		/*
		|--------------------------------------------------------------------------
		| Post Type
		|--------------------------------------------------------------------------
		*/

		if (
			self::match_post_type(
				$data,
				$context,
				$settings
			)
		) {
			return true;
		}

		/*
		|--------------------------------------------------------------------------
		| Post Status
		|--------------------------------------------------------------------------
		*/

		if (
			self::match_post_status(
				$context,
				$settings
			)
		) {
			return true;
		}

		/*
		|--------------------------------------------------------------------------
		| Post Meta
		|--------------------------------------------------------------------------
		*/

		if (
			self::match_value(
				$context['meta_key'] ?? '',
				$settings['excludedPostMeta']
			)
		) {
			return true;
		}

		/*
		|--------------------------------------------------------------------------
		| User Meta
		|--------------------------------------------------------------------------
		*/

		if (
			self::match_value(
				$context['user_meta_key'] ?? '',
				$settings['excludedUserMeta']
			)
		) {
			return true;
		}

		/*
		|--------------------------------------------------------------------------
		| Plugin
		|--------------------------------------------------------------------------
		*/

		if (
		self::match_value(
			$context['plugin'] ?? '',
			self::extract_values(
				$settings['excludedPlugins'] ?? array()
			)
		)
		) {
			return true;
		}

		/*
		|--------------------------------------------------------------------------
		| Theme
		|--------------------------------------------------------------------------
		*/

		if (
		self::match_value(
			$context['theme'] ?? '',
			self::extract_values(
				$settings['excludedThemes'] ?? array()
			)
		)
		) {
			return true;
		}

		/*
		|--------------------------------------------------------------------------
		| Cron Requests
		|--------------------------------------------------------------------------
		*/

		if ( self::match_cron_request( $context, $settings ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get normalized settings.
	 *
	 * @return array
	 */
	protected static function get_settings(): array {

		if ( null !== self::$settings ) {
			return self::$settings;
		}

		$settings = wp_parse_args(
			get_option( self::OPTION_NAME, array() ),
			array(
				'excludedUsers'       => array(),
				'excludedRoles'       => array(),
				'excludedIPs'         => array(),
				'excludedPostTypes'   => array(),
				'excludedStatuses'    => array(),
				'excludedPostMeta'    => array(),
				'excludedUserMeta'    => array(),
				'excludeCronRequests' => false,
			)
		);

		$settings['excludedRoles']     = self::normalize_array( $settings['excludedRoles'] );
		$settings['excludedIPs']       = self::normalize_array( $settings['excludedIPs'] );
		$settings['excludedPostTypes'] = self::normalize_array( $settings['excludedPostTypes'] );
		$settings['excludedStatuses']  = self::normalize_array( $settings['excludedStatuses'] );
		$settings['excludedPostMeta']  = self::normalize_array( $settings['excludedPostMeta'] );
		$settings['excludedUserMeta']  = self::normalize_array( $settings['excludedUserMeta'] );
		$settings['excludeCronRequests'] = (bool) $settings['excludeCronRequests'];

		self::$settings = $settings;

		return self::$settings;
	}

	/**
	 * Normalize array values.
	 *
	 * @param array $values Values.
	 *
	 * @return array
	 */
	protected static function normalize_array( array $values ): array {

		$values = array_map( 'trim', $values );
		$values = array_map( 'strtolower', $values );

		return array_unique(
			array_filter( $values )
		);
	}

	/**
	 * Match user.
	 *
	 * @param array $data Data.
	 * @param array $context Context.
	 * @param array $settings Settings.
	 *
	 * @return bool
	 */
	protected static function match_user(
		array $data,
		array $context,
		array $settings
	): bool {

		$user_id = 0;

		if ( ! empty( $context['current_user_id'] ) ) {
			$user_id = (int) $context['current_user_id'];
		} elseif ( ! empty( $data['user_id'] ) ) {
			$user_id = (int) $data['user_id'];
		}

		foreach ( $settings['excludedUsers'] as $user ) {

			if (
				isset( $user['value'] ) &&
				(int) $user['value'] === $user_id
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Match cron request exclusion rule.
	 *
	 * @param array $context Context.
	 * @param array $settings Settings.
	 *
	 * @return bool
	 */
	protected static function match_cron_request(
		array $context,
		array $settings
	): bool {

		if ( empty( $settings['excludeCronRequests'] ) ) {
			return false;
		}

		if ( wp_doing_cron() ) {
			return true;
		}

		$request_url = (string) ( $context['request_url'] ?? '' );

		if ( '' === $request_url ) {
			return false;
		}

		return false !== strpos( $request_url, '/wp-cron.php' );
	}

	/**
	 * Match roles.
	 *
	 * @param array $context Context.
	 * @param array $settings Settings.
	 *
	 * @return bool
	 */
	protected static function match_roles(
		array $context,
		array $settings
	): bool {

		$roles = $context['current_user_roles']
			?? $context['roles']
			?? array();

		foreach ( (array) $roles as $role ) {

			if (
				self::match_value(
					(string) $role,
					$settings['excludedRoles']
				)
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Match IP.
	 *
	 * @param array $data Data.
	 * @param array $context Context.
	 * @param array $settings Settings.
	 *
	 * @return bool
	 */
	protected static function match_ip(
		array $data,
		array $context,
		array $settings
	): bool {

		$ip = $context['ip_address']
			?? $data['ip_address']
			?? '';

		return self::match_value(
			(string) $ip,
			$settings['excludedIPs']
		);
	}

	/**
	 * Match post type.
	 *
	 * @param array $data Data.
	 * @param array $context Context.
	 * @param array $settings Settings.
	 *
	 * @return bool
	 */
	protected static function match_post_type(
		array $data,
		array $context,
		array $settings
	): bool {

		$post_type = $context['post_type']
			?? $data['object_type']
			?? '';

		return self::match_value(
			(string) $post_type,
			$settings['excludedPostTypes']
		);
	}

	/**
	 * Match post status.
	 *
	 * @param array $context Context.
	 * @param array $settings Settings.
	 *
	 * @return bool
	 */
	protected static function match_post_status(
		array $context,
		array $settings
	): bool {

		$status = $context['post_status'] ?? '';

		return self::match_value(
			(string) $status,
			$settings['excludedStatuses']
		);
	}

	/**
	 * Match value against patterns.
	 *
	 * Supports:
	 *
	 * exact_value
	 * prefix*
	 * *suffix
	 * *contains*
	 *
	 * Case insensitive.
	 *
	 * @param string $value Value.
	 * @param array  $patterns Patterns.
	 *
	 * @return bool
	 */
	protected static function match_value(
		string $value,
		array $patterns
	): bool {

		if ( empty( $value ) ) {
			return false;
		}

		$value = strtolower( $value );

		foreach ( $patterns as $pattern ) {

			$pattern = strtolower(
				trim( (string) $pattern )
			);

			if ( empty( $pattern ) ) {
				continue;
			}

			if ( false === strpos( $pattern, '*' ) ) {

				if ( $pattern === $value ) {
					return true;
				}

				continue;
			}

			$regex = '/^' .
			str_replace(
				'\*',
				'.*',
				preg_quote( $pattern, '/' )
			) .
				'$/i';

			if ( preg_match( $regex, $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract select values.
	 *
	 * @param array $items Items.
	 *
	 * @return array
	 */
	protected static function extract_values( array $items ): array {

		$values = array();

		foreach ( $items as $item ) {

			if (
			is_array( $item ) &&
			isset( $item['value'] )
			) {
				$values[] = $item['value'];
			}
		}

		return $values;
	}
}
