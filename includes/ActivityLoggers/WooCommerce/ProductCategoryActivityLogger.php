<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\WooCommerce;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Severity;
use Pastmark\Constants\Events;
use Pastmark\Constants\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce product category activity logger.
 */
class ProductCategoryActivityLogger extends AbstractLogger {

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

		add_action( 'create_product_cat', $this->guarded( array( $this, 'log_category_created' ) ), 10, 2 );

		add_action( 'delete_product_cat', $this->guarded( array( $this, 'log_category_deleted' ) ), 10, 4 );
	}

	/**
	 * Log a new product category.
	 *
	 * @param int $term_id Term ID.
	 * @param int $tt_id Term taxonomy ID.
	 * @return void
	 */
	public function log_category_created( int $term_id, int $tt_id ): void {

		$term = get_term( $term_id, 'product_cat' );

		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		$this->insert_event_log(
			Events::WOOCOMMERCE,
			Actions::PRODUCT_CATEGORY_CREATE,
			array(
				'object_type' => 'product_cat',
				'object_id'   => $term_id,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf( 'New product category "%s" created.', $term->name ),
				'after_data'  => wp_json_encode(
					array(
						'name' => $term->name,
						'slug' => $term->slug,
					)
				),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log a deleted product category.
	 *
	 * @param int     $term Term ID.
	 * @param int     $tt_id Term taxonomy ID.
	 * @param WP_Term $deleted_term Deleted term object.
	 * @param array   $object_ids Object IDs that were associated with the term.
	 * @return void
	 */
	public function log_category_deleted( $term, $tt_id, $deleted_term, $object_ids ): void {

		if ( ! $deleted_term || is_wp_error( $deleted_term ) ) {
			return;
		}

		$this->insert_event_log(
			Events::WOOCOMMERCE,
			Actions::PRODUCT_CATEGORY_DELETE,
			array(
				'object_type' => 'product_cat',
				'object_id'   => $term,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'Product category "%s" deleted.', $deleted_term->name ),
				'before_data' => wp_json_encode(
					array(
						'name' => $deleted_term->name,
						'slug' => $deleted_term->slug,
					)
				),
				'context'     => $this->get_common_context(),
			)
		);
	}
}
