<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Shared, WP_DEBUG_LOG-gated exception logging.
 *
 * Extracted (PM-149) from `AbstractLogger::handle_logger_exception()` so
 * `IntegrationRegistry::load_all()` (`includes/Integrations/IntegrationRegistry.php`)
 * can report a failure caught during an integration's own bootstrap step
 * the exact same way `AbstractLogger::guarded()` already reports one
 * caught from a hook callback, instead of duplicating this logic in two
 * places. See `docs/INTEGRATION-FRAMEWORK-ARCHITECTURE.md` decision 3.
 */
class ExceptionLogger {

	/**
	 * Write a caught `\Throwable` to the PHP error log, gated by
	 * `WP_DEBUG_LOG` per WP plugin convention, so a broken logger or
	 * integration stays silent-but-safe on a production site that
	 * doesn't have debug logging enabled, rather than risking a second
	 * failure by routing back through Pastmark's own log storage.
	 *
	 * Never throws, and never writes anywhere but the PHP error log -
	 * this is the last line of defense once something has already gone
	 * wrong.
	 *
	 * @param \Throwable  $e      Caught error/exception.
	 * @param string      $source Where the failure was caught - a class
	 *                            name (e.g. `static::class` from the
	 *                            logger that failed, or the integration
	 *                            class name that failed to load).
	 * @param string|null $hook   The current hook, if applicable.
	 *                            Defaults to `current_filter()`'s value;
	 *                            pass an explicit string (or `''`) when
	 *                            the caller already knows there's no
	 *                            meaningful hook for this failure.
	 * @return void
	 */
	public static function log( \Throwable $e, string $source, ?string $hook = null ): void {

		if ( ! ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) {
			return;
		}

		if ( null === $hook ) {
			$hook = (string) current_filter();
		}

		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			sprintf(
				'[Pastmark] %s caught in %s on hook "%s": %s in %s:%d',
				get_class( $e ),
				$source,
				$hook ? $hook : 'unknown',
				$e->getMessage(),
				$e->getFile(),
				$e->getLine()
			)
		);
	}
}
