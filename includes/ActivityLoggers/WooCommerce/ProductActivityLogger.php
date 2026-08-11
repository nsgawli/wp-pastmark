<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace LogTrail\ActivityLoggers\WooCommerce;

use LogTrail\ActivityLoggers\AbstractLogger;
use LogTrail\Constants\Severity;
use LogTrail\Constants\Events;
use LogTrail\Constants\Actions;
use WC_Product;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce product activity logger.
 */
class ProductActivityLogger extends AbstractLogger {

	/**
	 * Product field snapshots captured before a save, keyed by product ID.
	 *
	 * There's no reliable "after" hook that also hands back the pre-save
	 * values, so the fields being diffed are read directly from postmeta/terms
	 * on `woocommerce_before_product_object_save` (before the new values are
	 * written) rather than through `wc_get_product()`, which may return the
	 * very same (already mutated) in-memory object.
	 *
	 * @var array<int, array>
	 */
	protected $pending_product_snapshots = array();

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

		add_action( 'transition_post_status', $this->guarded( array( $this, 'log_status_changed' ) ), 10, 3 );

		add_action( 'post_updated', $this->guarded( array( $this, 'log_title_changed' ) ), 10, 3 );

		add_action( 'untrashed_post', $this->guarded( array( $this, 'log_restored' ) ) );

		add_action( 'before_delete_post', $this->guarded( array( $this, 'log_deleted' ) ) );

		add_action( 'set_object_terms', $this->guarded( array( $this, 'log_category_changed' ) ), 10, 6 );

		add_action( 'woocommerce_before_product_object_save', $this->guarded( array( $this, 'capture_before_save' ) ) );

		add_action( 'woocommerce_update_product', $this->guarded( array( $this, 'log_product_updated' ) ), 10, 2 );

		add_action( 'woocommerce_product_before_set_stock', $this->guarded( array( $this, 'capture_stock_before_set' ) ) );

		add_action( 'woocommerce_variation_before_set_stock', $this->guarded( array( $this, 'capture_stock_before_set' ) ) );

		add_action( 'woocommerce_product_set_stock', $this->guarded( array( $this, 'log_stock_auto_changed' ) ) );

		add_action( 'woocommerce_variation_set_stock', $this->guarded( array( $this, 'log_stock_auto_changed' ) ) );
	}

	/**
	 * Log product creation, publish, trash and other status transitions.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function log_status_changed( string $new_status, string $old_status, WP_Post $post ): void {

		if ( 'product' !== $post->post_type || $new_status === $old_status ) {
			return;
		}

		if ( 'auto-draft' === $old_status ) {

			if ( 'publish' === $new_status ) {
				$this->log_event(
					Actions::PRODUCT_PUBLISH,
					$post,
					sprintf( 'Product "%s" published.', $post->post_title )
				);
			} elseif ( 'trash' !== $new_status ) {
				$this->log_event(
					Actions::PRODUCT_CREATE,
					$post,
					sprintf( 'Product "%s" created.', $post->post_title )
				);
			}

			return;
		}

		if ( 'trash' === $new_status ) {
			$this->log_event(
				Actions::PRODUCT_TRASH,
				$post,
				sprintf( 'Product "%s" moved to trash.', $post->post_title ),
				Severity::WARNING
			);
			return;
		}

		if ( 'trash' === $old_status ) {
			// Restore is logged separately from `untrashed_post`, once the status has settled.
			return;
		}

		if ( 'publish' === $new_status ) {
			$this->log_event(
				Actions::PRODUCT_PUBLISH,
				$post,
				sprintf( 'Product "%s" published.', $post->post_title )
			);
			return;
		}

		$this->log_event(
			Actions::PRODUCT_STATUS_CHANGE,
			$post,
			sprintf( 'Product "%s" status changed from "%s" to "%s".', $post->post_title, $old_status, $new_status ),
			Severity::INFO,
			array( 'product_status' => $old_status ),
			array( 'product_status' => $new_status )
		);
	}

	/**
	 * Log product title change.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post_after Post object after update.
	 * @param WP_Post $post_before Post object before update.
	 * @return void
	 */
	public function log_title_changed( int $post_id, WP_Post $post_after, WP_Post $post_before ): void {

		if ( 'product' !== $post_after->post_type ) {
			return;
		}

		if ( 'auto-draft' === $post_before->post_status ) {
			return;
		}

		if ( $post_before->post_title === $post_after->post_title ) {
			return;
		}

		$this->log_event(
			Actions::PRODUCT_RENAME,
			$post_after,
			sprintf( 'Product renamed from "%s" to "%s".', $post_before->post_title, $post_after->post_title ),
			Severity::INFO,
			array( 'post_title' => $post_before->post_title ),
			array( 'post_title' => $post_after->post_title )
		);
	}

	/**
	 * Log product restored from trash.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function log_restored( int $post_id ): void {

		$post = get_post( $post_id );

		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}

		$this->log_event(
			Actions::PRODUCT_RESTORE,
			$post,
			sprintf( 'Product "%s" restored from trash.', $post->post_title )
		);
	}

	/**
	 * Log product permanently deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function log_deleted( int $post_id ): void {

		$post = get_post( $post_id );

		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}

		$this->log_event(
			Actions::PRODUCT_DELETE,
			$post,
			sprintf( 'Product "%s" permanently deleted.', $post->post_title ),
			Severity::WARNING
		);
	}

	/**
	 * Log a product's category assignment change.
	 *
	 * @param int    $object_id Post ID.
	 * @param array  $terms Term IDs or names.
	 * @param array  $tt_ids Term taxonomy IDs.
	 * @param string $taxonomy Taxonomy.
	 * @param bool   $append Whether terms were appended.
	 * @param array  $old_tt_ids Previous term taxonomy IDs.
	 * @return void
	 */
	public function log_category_changed( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ): void {

		if ( 'product_cat' !== $taxonomy ) {
			return;
		}

		$post = get_post( $object_id );

		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}

		sort( $tt_ids );
		sort( $old_tt_ids );

		if ( $tt_ids === $old_tt_ids ) {
			return;
		}

		$old_names = $this->get_term_names_from_tt_ids( $old_tt_ids );
		$new_names = $this->get_term_names_from_tt_ids( $tt_ids );

		$this->log_event(
			Actions::PRODUCT_CATEGORY_CHANGE,
			$post,
			sprintf(
				'Category of product "%s" changed from "%s" to "%s".',
				$post->post_title,
				$old_names ? implode( ', ', $old_names ) : __( 'None', 'logtrail' ),
				$new_names ? implode( ', ', $new_names ) : __( 'None', 'logtrail' )
			),
			Severity::INFO,
			array( 'product_cat' => $old_names ),
			array( 'product_cat' => $new_names )
		);
	}

	/**
	 * Resolve term names from term taxonomy IDs.
	 *
	 * @param int[] $tt_ids Term taxonomy IDs.
	 * @return string[]
	 */
	protected function get_term_names_from_tt_ids( array $tt_ids ): array {

		$names = array();

		foreach ( $tt_ids as $tt_id ) {

			$term = get_term_by( 'term_taxonomy_id', $tt_id, 'product_cat' );

			if ( $term ) {
				$names[] = $term->name;
			}
		}

		return $names;
	}

	/**
	 * Capture product field values directly from the database before a save.
	 *
	 * @param WC_Product $product Product being saved.
	 * @return void
	 */
	public function capture_before_save( WC_Product $product ): void {

		$product_id = $product->get_id();

		if ( ! $product_id ) {
			return;
		}

		$this->pending_product_snapshots[ $product_id ] = array(
			'sku'                => get_post_meta( $product_id, '_sku', true ),
			'regular_price'      => get_post_meta( $product_id, '_regular_price', true ),
			'sale_price'         => get_post_meta( $product_id, '_sale_price', true ),
			'stock_status'       => get_post_meta( $product_id, '_stock_status', true ),
			'stock_quantity'     => get_post_meta( $product_id, '_stock', true ),
			'catalog_visibility' => $this->get_catalog_visibility_from_terms( $product_id ),
		);
	}

	/**
	 * Diff and log product field changes after a save.
	 *
	 * @param int        $product_id Product ID.
	 * @param WC_Product $product Product object.
	 * @return void
	 */
	public function log_product_updated( int $product_id, WC_Product $product ): void {

		if ( ! isset( $this->pending_product_snapshots[ $product_id ] ) ) {
			return;
		}

		$before = $this->pending_product_snapshots[ $product_id ];

		unset( $this->pending_product_snapshots[ $product_id ] );

		$post = get_post( $product_id );

		if ( ! $post ) {
			return;
		}

		if ( $before['sku'] !== $product->get_sku() ) {
			$this->log_event(
				Actions::PRODUCT_SKU_CHANGE,
				$post,
				sprintf(
					'SKU of product "%s" changed from "%s" to "%s".',
					$post->post_title,
					$before['sku'] ? $before['sku'] : __( 'None', 'logtrail' ),
					$product->get_sku() ? $product->get_sku() : __( 'None', 'logtrail' )
				),
				Severity::INFO,
				array( 'sku' => $before['sku'] ),
				array( 'sku' => $product->get_sku() )
			);
		}

		if ( $before['regular_price'] !== $product->get_regular_price() || $before['sale_price'] !== $product->get_sale_price() ) {
			$this->log_event(
				Actions::PRODUCT_PRICE_CHANGE,
				$post,
				sprintf( 'Price of product "%s" changed.', $post->post_title ),
				Severity::WARNING,
				array(
					'regular_price' => $before['regular_price'],
					'sale_price'    => $before['sale_price'],
				),
				array(
					'regular_price' => $product->get_regular_price(),
					'sale_price'    => $product->get_sale_price(),
				)
			);
		}

		if ( $before['stock_status'] !== $product->get_stock_status() ) {
			$this->log_event(
				Actions::PRODUCT_STOCK_STATUS_CHANGE,
				$post,
				sprintf(
					'Stock status of product "%s" changed from "%s" to "%s".',
					$post->post_title,
					$before['stock_status'],
					$product->get_stock_status()
				),
				Severity::INFO,
				array( 'stock_status' => $before['stock_status'] ),
				array( 'stock_status' => $product->get_stock_status() )
			);
		}

		$after_stock_quantity = $product->get_stock_quantity();

		if ( null !== $after_stock_quantity && (string) $before['stock_quantity'] !== (string) $after_stock_quantity ) {
			$this->log_event(
				Actions::PRODUCT_STOCK_QTY_CHANGE,
				$post,
				sprintf(
					'Stock quantity of product "%s" changed from "%s" to "%s".',
					$post->post_title,
					'' !== $before['stock_quantity'] ? $before['stock_quantity'] : '0',
					$after_stock_quantity
				),
				Severity::INFO,
				array( 'stock_quantity' => $before['stock_quantity'] ),
				array( 'stock_quantity' => $after_stock_quantity )
			);
		}

		if ( $before['catalog_visibility'] !== $product->get_catalog_visibility() ) {
			$this->log_event(
				Actions::PRODUCT_VISIBILITY_CHANGE,
				$post,
				sprintf(
					'Catalog visibility of product "%s" changed from "%s" to "%s".',
					$post->post_title,
					$before['catalog_visibility'],
					$product->get_catalog_visibility()
				),
				Severity::WARNING,
				array( 'catalog_visibility' => $before['catalog_visibility'] ),
				array( 'catalog_visibility' => $product->get_catalog_visibility() )
			);
		}
	}

	/**
	 * Resolve the WooCommerce catalog visibility string directly from term relationships.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	protected function get_catalog_visibility_from_terms( int $product_id ): string {

		$terms = wp_get_object_terms( $product_id, 'product_visibility', array( 'fields' => 'slugs' ) );

		if ( ! is_array( $terms ) ) {
			return 'visible';
		}

		$hide_from_catalog = in_array( 'exclude-from-catalog', $terms, true );
		$hide_from_search  = in_array( 'exclude-from-search', $terms, true );

		if ( $hide_from_catalog && $hide_from_search ) {
			return 'hidden';
		}

		if ( $hide_from_search ) {
			return 'catalog';
		}

		if ( $hide_from_catalog ) {
			return 'search';
		}

		return 'visible';
	}

	/**
	 * Capture stock quantity directly before a system-driven stock update.
	 *
	 * @param WC_Product $product Product (or variation) being updated.
	 * @return void
	 */
	public function capture_stock_before_set( WC_Product $product ): void {

		$this->pending_product_snapshots[ 'stock_' . $product->get_id() ] = get_post_meta( $product->get_id(), '_stock', true );
	}

	/**
	 * Log an automated (order/plugin driven) stock quantity change.
	 *
	 * @param WC_Product $product Product (or variation) after the stock update.
	 * @return void
	 */
	public function log_stock_auto_changed( WC_Product $product ): void {

		$product_id = $product->get_id();
		$key        = 'stock_' . $product_id;

		$before_quantity = $this->pending_product_snapshots[ $key ] ?? '';

		unset( $this->pending_product_snapshots[ $key ] );

		// WooCommerce fires this same hook for a plain `$product->save()` (a manual
		// edit), not just for order/plugin-driven `wc_update_product_stock()` calls.
		// When a full object save is in progress, `log_product_updated()` already
		// reports the stock quantity diff as a manual change; skip here so the same
		// edit isn't also logged a second time, mislabeled as automated.
		if ( isset( $this->pending_product_snapshots[ $product_id ] ) ) {
			return;
		}

		$after_quantity = $product->get_stock_quantity();

		if ( null === $after_quantity || (string) $before_quantity === (string) $after_quantity ) {
			return;
		}

		$post = get_post( $product->get_id() );

		if ( ! $post ) {
			return;
		}

		$this->insert_event_log(
			Events::WOOCOMMERCE,
			Actions::PRODUCT_STOCK_AUTO_CHANGE,
			array(
				'object_type' => $product->is_type( 'variation' ) ? 'product_variation' : 'product',
				'object_id'   => $product->get_id(),
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'Stock quantity of product "%s" changed automatically from "%s" to "%s".',
					$post->post_title,
					'' !== $before_quantity ? $before_quantity : '0',
					$after_quantity
				),
				'before_data' => wp_json_encode( array( 'stock_quantity' => $before_quantity ) ),
				'after_data'  => wp_json_encode( array( 'stock_quantity' => $after_quantity ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Insert a product event log entry.
	 *
	 * @param string  $action Action key.
	 * @param WP_Post $post Product post object.
	 * @param string  $message Log message.
	 * @param string  $severity Severity.
	 * @param array   $before Before data.
	 * @param array   $after After data.
	 * @return void
	 */
	protected function log_event(
		string $action,
		WP_Post $post,
		string $message,
		string $severity = Severity::INFO,
		array $before = array(),
		array $after = array()
	): void {

		$this->insert_event_log(
			Events::WOOCOMMERCE,
			$action,
			array(
				'object_type' => 'product',
				'object_id'   => $post->ID,
				'user_id'     => get_current_user_id(),
				'severity'    => $severity,
				'message'     => $message,
				'before_data' => $before ? wp_json_encode( $before ) : '',
				'after_data'  => $after ? wp_json_encode( $after ) : '',
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'post_type'   => $post->post_type,
						'post_status' => $post->post_status,
					)
				),
			)
		);
	}
}
