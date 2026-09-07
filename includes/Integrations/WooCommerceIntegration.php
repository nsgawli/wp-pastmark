<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Integrations;

use Pastmark\ActivityLoggers\WooCommerce\CouponActivityLogger;
use Pastmark\ActivityLoggers\WooCommerce\OrderActivityLogger;
use Pastmark\ActivityLoggers\WooCommerce\ProductActivityLogger;
use Pastmark\ActivityLoggers\WooCommerce\ProductCategoryActivityLogger;
use Pastmark\ActivityLoggers\WooCommerce\ReviewActivityLogger;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce's integration manifest (PM-150).
 *
 * Replaces `ActivityLoggers\Autoloader.php`'s old hardcoded
 * `if ( class_exists( 'WooCommerce' ) )` block with the exact same
 * presence check and the exact same five logger classes, in the exact
 * same order - a zero-behavior-change refactor of logging that's been
 * live in production since Sprint 2 (2026-08-20), not new capability.
 */
class WooCommerceIntegration implements IntegrationInterface {

	/**
	 * {@inheritDoc}
	 */
	public function is_active(): bool {

		return class_exists( 'WooCommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_logger_classes(): array {

		return array(
			ProductActivityLogger::class,
			ProductCategoryActivityLogger::class,
			OrderActivityLogger::class,
			CouponActivityLogger::class,
			ReviewActivityLogger::class,
		);
	}
}
