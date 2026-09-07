<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Integrations;

use Pastmark\Utils\ExceptionLogger;

defined( 'ABSPATH' ) || exit;

/**
 * Registration/loading mechanism for third-party-plugin integrations
 * (PM-149).
 *
 * Replaces `ActivityLoggers\Autoloader::run()`'s old hardcoded
 * `if ( class_exists( 'WooCommerce' ) )` block with a filter/action-based
 * registration API any code - Pastmark's own bootstrap, Pastmark Pro, or
 * a genuine third party - can add to without editing a core file. See
 * `docs/INTEGRATION-FRAMEWORK-ARCHITECTURE.md` decision 1 for the full
 * design this implements.
 */
class IntegrationRegistry {

	/**
	 * Integration class names added via `register()`. Merged into the
	 * final list alongside the built-in list and anything a
	 * `pastmark_register_integrations` callback itself calls
	 * `register()` from.
	 *
	 * @var string[]
	 */
	private static array $registered = array();

	/**
	 * Pastmark's own built-in integration list.
	 *
	 * @return string[] Integration class names, filterable via
	 *                   `pastmark_core_integrations`.
	 */
	public static function get_integrations(): array {

		return apply_filters(
			'pastmark_core_integrations',
			array(
				WooCommerceIntegration::class,
				ACFIntegration::class,
				WPFormsIntegration::class,
				GravityFormsIntegration::class,
				YoastSeoIntegration::class,
				RankMathIntegration::class,
				RedirectionIntegration::class,
				TablePressIntegration::class,
				BbPressIntegration::class,
				WP2FAIntegration::class,
				UltimateMemberIntegration::class,
				WordfenceIntegration::class,
				TutorLmsIntegration::class,
			)
		);
	}

	/**
	 * Public API any code - Pro, or any other plugin - calls to add its
	 * own `IntegrationInterface`-implementing class name, without
	 * editing this file.
	 *
	 * @param string $integration_class Fully-qualified class name.
	 * @return void
	 */
	public static function register( string $integration_class ): void {

		self::$registered[] = $integration_class;
	}

	/**
	 * Merge the built-in list, whatever `pastmark_register_integrations`
	 * callbacks add via `register()`, and the final
	 * `pastmark_integrations_to_load` filter into one list.
	 *
	 * @return array Integration class names (not yet validated).
	 */
	private static function get_final_list(): array {

		$integrations = self::get_integrations();

		/**
		 * Fires before the integration list is finalized, giving
		 * external code a hook point to call `register()` from - fired
		 * from the same bootstrap timing `Autoloader::run()` already
		 * uses (`plugins_loaded`), since `load_all()` (which calls this
		 * method) is itself only ever called from there.
		 */
		do_action( 'pastmark_register_integrations' );

		$integrations = array_merge( $integrations, self::$registered );

		/**
		 * Final override point over the fully-merged (built-in +
		 * registered) list.
		 *
		 * @param array $integrations Integration class names.
		 */
		return apply_filters( 'pastmark_integrations_to_load', $integrations );
	}

	/**
	 * Load every valid, active integration's loggers.
	 *
	 * Fault-isolated at two levels, per
	 * `docs/INTEGRATION-FRAMEWORK-ARCHITECTURE.md` decision 3:
	 * - A class that isn't a real, conforming `IntegrationInterface`
	 *   implementation is silently skipped (logged, never thrown).
	 * - Each integration's own presence-check-and-instantiate step, and
	 *   each of its logger classes' construction, runs in its own
	 *   `try`/`catch (\Throwable $e)` - one integration (or one of its
	 *   logger classes) throwing can never prevent any other integration,
	 *   or that same integration's other logger classes, from loading.
	 *
	 * @return void
	 */
	public static function load_all(): void {

		foreach ( self::get_final_list() as $integration_class ) {

			try {

				if ( ! is_string( $integration_class )
					|| ! class_exists( $integration_class )
					|| ! is_subclass_of( $integration_class, IntegrationInterface::class )
				) {
					ExceptionLogger::log(
						new \RuntimeException( 'Registered integration does not implement IntegrationInterface.' ),
						is_string( $integration_class ) ? $integration_class : gettype( $integration_class ),
						''
					);
					continue;
				}

				$integration = new $integration_class();

				if ( ! $integration->is_active() ) {
					continue;
				}

				foreach ( $integration->get_logger_classes() as $logger_class ) {

					try {
						new $logger_class();
					} catch ( \Throwable $e ) {
						ExceptionLogger::log( $e, $integration_class );
					}
				}
			} catch ( \Throwable $e ) {
				ExceptionLogger::log(
					$e,
					is_string( $integration_class ) ? $integration_class : gettype( $integration_class )
				);
			}
		}
	}
}
