<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\WooCommerce;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Severity;
use Pastmark\Constants\Events;
use Pastmark\Constants\Actions;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce order activity logger.
 */
class OrderActivityLogger extends AbstractLogger {

	/**
	 * Order billing/shipping/note snapshots captured before a save, keyed by order ID.
	 *
	 * @var array<int, array>
	 */
	protected $pending_order_snapshots = array();

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

		add_action( 'woocommerce_checkout_order_processed', $this->guarded( array( $this, 'log_order_placed' ) ) );

		add_action( 'woocommerce_order_status_changed', $this->guarded( array( $this, 'log_status_changed' ) ), 10, 4 );

		add_action( 'woocommerce_trash_order', $this->guarded( array( $this, 'log_trashed' ) ) );

		add_action( 'woocommerce_untrash_order', $this->guarded( array( $this, 'log_restored' ) ) );

		// CPT-storage fallback: the legacy data store doesn't fire `woocommerce_untrash_order`.
		add_action( 'untrashed_post', $this->guarded( array( $this, 'log_restored_legacy' ) ) );

		add_action( 'woocommerce_before_delete_order', $this->guarded( array( $this, 'log_deleted' ) ), 10, 2 );

		add_action( 'woocommerce_order_refunded', $this->guarded( array( $this, 'log_refunded' ) ), 10, 2 );

		add_action( 'woocommerce_order_note_added', $this->guarded( array( $this, 'log_note_added' ) ), 10, 2 );

		add_action( 'woocommerce_order_note_deleted', $this->guarded( array( $this, 'log_note_deleted' ) ), 10, 2 );

		add_action( 'woocommerce_before_order_object_save', $this->guarded( array( $this, 'capture_before_save' ) ) );

		add_action( 'woocommerce_update_order', $this->guarded( array( $this, 'log_order_edited' ) ), 10, 2 );
	}

	/**
	 * Log a new order placed at checkout.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function log_order_placed( int $order_id ): void {

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$this->log_event(
			Actions::ORDER_PLACED,
			$order,
			sprintf( 'Order #%d placed (%s).', $order->get_order_number(), $this->format_price( $order->get_total() ) )
		);
	}

	/**
	 * Log order status changes.
	 *
	 * @param int      $order_id Order ID.
	 * @param string   $old_status Old status.
	 * @param string   $new_status New status.
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function log_status_changed( int $order_id, string $old_status, string $new_status, WC_Order $order ): void {

		// A restore settles back to the order's pre-trash status via this same
		// hook; that's already logged by `log_restored()`/`log_restored_legacy()`,
		// so logging it again here would report the same restore twice.
		if ( 'trash' === $old_status ) {
			return;
		}

		$this->log_event(
			Actions::ORDER_STATUS_CHANGE,
			$order,
			sprintf( 'Order #%d status changed from "%s" to "%s".', $order->get_order_number(), $old_status, $new_status ),
			Severity::INFO,
			array( 'status' => $old_status ),
			array( 'status' => $new_status )
		);
	}

	/**
	 * Log order moved to trash.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function log_trashed( int $order_id ): void {

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$this->log_event(
			Actions::ORDER_TRASH,
			$order,
			sprintf( 'Order #%d moved to trash.', $order->get_order_number() ),
			Severity::WARNING
		);
	}

	/**
	 * Log order restored from trash (HPOS storage).
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function log_restored( int $order_id ): void {

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$this->log_event(
			Actions::ORDER_RESTORE,
			$order,
			sprintf( 'Order #%d restored from trash.', $order->get_order_number() )
		);
	}

	/**
	 * Log order restored from trash (legacy CPT storage).
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function log_restored_legacy( int $post_id ): void {

		if ( 'shop_order' !== get_post_type( $post_id ) ) {
			return;
		}

		$this->log_restored( $post_id );
	}

	/**
	 * Log order permanently deleted.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function log_deleted( int $order_id, WC_Order $order ): void {

		$this->log_event(
			Actions::ORDER_DELETE,
			$order,
			sprintf( 'Order #%d permanently deleted.', $order->get_order_number() ),
			Severity::WARNING,
			array(
				'status' => $order->get_status(),
				'total'  => $order->get_total(),
			)
		);
	}

	/**
	 * Log order refund.
	 *
	 * @param int $order_id Order ID.
	 * @param int $refund_id Refund ID.
	 * @return void
	 */
	public function log_refunded( int $order_id, int $refund_id ): void {

		$order  = wc_get_order( $order_id );
		$refund = wc_get_order( $refund_id );

		if ( ! $order || ! $refund ) {
			return;
		}

		$this->log_event(
			Actions::ORDER_REFUND,
			$order,
			sprintf(
				'Order #%d refunded (%s).',
				$order->get_order_number(),
				$this->format_price( abs( (float) $refund->get_total() ) )
			),
			Severity::WARNING,
			array(),
			array( 'refund_total' => abs( (float) $refund->get_total() ) )
		);
	}

	/**
	 * Log a manually-added order note (skips WooCommerce's own automated notes).
	 *
	 * @param int      $comment_id Note comment ID.
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function log_note_added( int $comment_id, WC_Order $order ): void {

		$note = wc_get_order_note( $comment_id );

		if ( ! $note || 'system' === $note->added_by ) {
			return;
		}

		$this->log_event(
			Actions::ORDER_NOTE_ADD,
			$order,
			sprintf( 'Note added to order #%d.', $order->get_order_number() ),
			Severity::INFO,
			array(),
			array( 'note' => $note->content )
		);
	}

	/**
	 * Log an order note deletion.
	 *
	 * @param int      $note_id Note comment ID.
	 * @param stdClass $note Deleted note details.
	 * @return void
	 */
	public function log_note_deleted( int $note_id, $note ): void {

		$order = wc_get_order( $note->order_id );

		if ( ! $order ) {
			return;
		}

		$this->log_event(
			Actions::ORDER_NOTE_DELETE,
			$order,
			sprintf( 'Note deleted from order #%d.', $order->get_order_number() ),
			Severity::INFO,
			array( 'note' => $note->content )
		);
	}

	/**
	 * Capture order billing, shipping and customer note before a save.
	 *
	 * @param WC_Order $order Order being saved.
	 * @return void
	 */
	public function capture_before_save( WC_Order $order ): void {

		$order_id = $order->get_id();

		if ( ! $order_id ) {
			return;
		}

		$data = $order->get_data();

		$this->pending_order_snapshots[ $order_id ] = array(
			'billing'       => $data['billing'],
			'shipping'      => $data['shipping'],
			'customer_note' => $data['customer_note'],
		);
	}

	/**
	 * Diff and log order detail edits after a save.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function log_order_edited( int $order_id, WC_Order $order ): void {

		if ( ! isset( $this->pending_order_snapshots[ $order_id ] ) ) {
			return;
		}

		$before_snapshot = $this->pending_order_snapshots[ $order_id ];

		unset( $this->pending_order_snapshots[ $order_id ] );

		$data = $order->get_data();

		$after_snapshot = array(
			'billing'       => $data['billing'],
			'shipping'      => $data['shipping'],
			'customer_note' => $data['customer_note'],
		);

		list( $before, $after ) = $this->diff_order_fields( $before_snapshot, $after_snapshot );

		if ( empty( $before ) && empty( $after ) ) {
			return;
		}

		$this->log_event(
			Actions::ORDER_EDIT,
			$order,
			sprintf( 'Order #%d edited.', $order->get_order_number() ),
			Severity::WARNING,
			$before,
			$after
		);
	}

	/**
	 * Diff two order detail snapshots down to just the individual
	 * billing/shipping address fields (and the customer note) that
	 * actually changed.
	 *
	 * Flattens each address into `billing_<field>`/`shipping_<field>`
	 * keys rather than diffing the two address arrays wholesale, so
	 * e.g. a single changed phone number shows up as one row instead of
	 * dumping the entire address twice for a one-field edit.
	 *
	 * @param array $before Snapshot captured before the save.
	 * @param array $after Snapshot captured after the save.
	 * @return array{0: array<string, mixed>, 1: array<string, mixed>} [ $before_out, $after_out ]
	 */
	protected function diff_order_fields( array $before, array $after ): array {

		$before_out = array();
		$after_out  = array();

		foreach ( array( 'billing', 'shipping' ) as $group ) {

			$before_group = (array) ( $before[ $group ] ?? array() );
			$after_group  = (array) ( $after[ $group ] ?? array() );

			$fields = array_unique( array_merge( array_keys( $before_group ), array_keys( $after_group ) ) );

			foreach ( $fields as $field ) {

				$old_value = $before_group[ $field ] ?? '';
				$new_value = $after_group[ $field ] ?? '';

				if ( $old_value === $new_value ) {
					continue;
				}

				$key                = $group . '_' . $field;
				$before_out[ $key ] = $old_value;
				$after_out[ $key ]  = $new_value;
			}
		}

		$old_note = $before['customer_note'] ?? '';
		$new_note = $after['customer_note'] ?? '';

		if ( $old_note !== $new_note ) {
			$before_out['customer_note'] = $old_note;
			$after_out['customer_note']  = $new_note;
		}

		return array( $before_out, $after_out );
	}

	/**
	 * Format an amount as plain-text currency for use in log messages.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	protected function format_price( float $amount ): string {

		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Insert an order event log entry.
	 *
	 * @param string   $action Action key.
	 * @param WC_Order $order Order object.
	 * @param string   $message Log message.
	 * @param string   $severity Severity.
	 * @param array    $before Before data.
	 * @param array    $after After data.
	 * @return void
	 */
	protected function log_event(
		string $action,
		WC_Order $order,
		string $message,
		string $severity = Severity::INFO,
		array $before = array(),
		array $after = array()
	): void {

		$this->insert_event_log(
			Events::WOOCOMMERCE,
			$action,
			array(
				'object_type' => 'shop_order',
				'object_id'   => $order->get_id(),
				'user_id'     => get_current_user_id(),
				'severity'    => $severity,
				'message'     => $message,
				'before_data' => $before ? wp_json_encode( $before ) : '',
				'after_data'  => $after ? wp_json_encode( $after ) : '',
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'order_status' => $order->get_status(),
					)
				),
			)
		);
	}
}
