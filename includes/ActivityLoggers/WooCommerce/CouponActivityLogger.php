<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace LogTrail\ActivityLoggers\WooCommerce;

use LogTrail\ActivityLoggers\AbstractLogger;
use LogTrail\Constants\Severity;
use LogTrail\Constants\Events;
use LogTrail\Constants\Actions;
use WC_Coupon;
use WC_Data;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce coupon activity logger.
 */
class CouponActivityLogger extends AbstractLogger {

	/**
	 * Coupon amount snapshots captured before a save, keyed by coupon ID.
	 *
	 * @var array<int, string>
	 */
	protected $pending_coupon_snapshots = array();

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

		add_action( 'post_updated', $this->guarded( array( $this, 'log_code_changed' ) ), 10, 3 );

		add_action( 'untrashed_post', $this->guarded( array( $this, 'log_restored' ) ) );

		add_action( 'before_delete_post', $this->guarded( array( $this, 'log_deleted' ) ) );

		// WC_Coupon doesn't override `$object_type`, so its save() fires the generic `_data_` hooks.
		add_action( 'woocommerce_before_data_object_save', $this->guarded( array( $this, 'capture_before_save' ) ) );

		add_action( 'woocommerce_update_coupon', $this->guarded( array( $this, 'log_coupon_updated' ) ), 10, 2 );
	}

	/**
	 * Log coupon creation, trash and other status transitions.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function log_status_changed( string $new_status, string $old_status, WP_Post $post ): void {

		if ( 'shop_coupon' !== $post->post_type || $new_status === $old_status ) {
			return;
		}

		// `auto-draft` is the admin-UI creation flow (autosave, then a real status
		// on publish/save); `new` is a coupon inserted directly with its real
		// status already set (e.g. code, an importer, or the REST API) and never
		// passing through an auto-draft stage. Both mean "just created".
		if ( 'auto-draft' === $old_status || 'new' === $old_status ) {

			if ( 'auto-draft' === $new_status ) {
				// Still just the empty autosave staging row; nothing to report yet.
				return;
			}

			if ( 'trash' !== $new_status ) {
				$this->log_event(
					Actions::COUPON_CREATE,
					$post,
					sprintf( 'New coupon "%s" published.', $post->post_title )
				);
			}

			return;
		}

		if ( 'trash' === $new_status ) {
			$this->log_event(
				Actions::COUPON_TRASH,
				$post,
				sprintf( 'Coupon "%s" moved to trash.', $post->post_title ),
				Severity::WARNING
			);
			return;
		}

		if ( 'trash' === $old_status ) {
			// Restore is logged separately from `untrashed_post`, once the status has settled.
			return;
		}

		$this->log_event(
			Actions::COUPON_STATUS_CHANGE,
			$post,
			sprintf( 'Coupon "%s" status changed from "%s" to "%s".', $post->post_title, $old_status, $new_status ),
			Severity::INFO,
			array( 'coupon_status' => $old_status ),
			array( 'coupon_status' => $new_status )
		);
	}

	/**
	 * Log coupon code (title) change.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post_after Post object after update.
	 * @param WP_Post $post_before Post object before update.
	 * @return void
	 */
	public function log_code_changed( int $post_id, WP_Post $post_after, WP_Post $post_before ): void {

		if ( 'shop_coupon' !== $post_after->post_type ) {
			return;
		}

		if ( 'auto-draft' === $post_before->post_status ) {
			return;
		}

		if ( $post_before->post_title === $post_after->post_title ) {
			return;
		}

		$this->log_event(
			Actions::COUPON_RENAME,
			$post_after,
			sprintf( 'Coupon renamed from "%s" to "%s".', $post_before->post_title, $post_after->post_title ),
			Severity::INFO,
			array( 'post_title' => $post_before->post_title ),
			array( 'post_title' => $post_after->post_title )
		);
	}

	/**
	 * Log coupon restored from trash.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function log_restored( int $post_id ): void {

		$post = get_post( $post_id );

		if ( ! $post || 'shop_coupon' !== $post->post_type ) {
			return;
		}

		$this->log_event(
			Actions::COUPON_RESTORE,
			$post,
			sprintf( 'Coupon "%s" restored from trash.', $post->post_title )
		);
	}

	/**
	 * Log coupon permanently deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function log_deleted( int $post_id ): void {

		$post = get_post( $post_id );

		if ( ! $post || 'shop_coupon' !== $post->post_type ) {
			return;
		}

		// Fired on `before_delete_post`, so the coupon row/meta are still
		// intact - capture a snapshot now or a permanently-deleted coupon
		// leaves nothing behind to show in the log's detail view.
		$coupon = new WC_Coupon( $post_id );

		$this->log_event(
			Actions::COUPON_DELETE,
			$post,
			sprintf( 'Coupon "%s" permanently deleted.', $post->post_title ),
			Severity::WARNING,
			array(
				'post_title'    => $post->post_title,
				'post_status'   => $post->post_status,
				'coupon_amount' => $coupon->get_amount(),
				'discount_type' => $coupon->get_discount_type(),
			)
		);
	}

	/**
	 * Capture the coupon amount directly from the database before a save.
	 *
	 * @param WC_Data $wc_object Object being saved.
	 * @return void
	 */
	public function capture_before_save( WC_Data $wc_object ): void {

		if ( ! $wc_object instanceof WC_Coupon || ! $wc_object->get_id() ) {
			return;
		}

		$this->pending_coupon_snapshots[ $wc_object->get_id() ] = get_post_meta( $wc_object->get_id(), 'coupon_amount', true );
	}

	/**
	 * Diff and log the coupon amount change after a save.
	 *
	 * @param int       $coupon_id Coupon ID.
	 * @param WC_Coupon $coupon Coupon object.
	 * @return void
	 */
	public function log_coupon_updated( int $coupon_id, WC_Coupon $coupon ): void {

		if ( ! isset( $this->pending_coupon_snapshots[ $coupon_id ] ) ) {
			return;
		}

		$before_amount = $this->pending_coupon_snapshots[ $coupon_id ];

		unset( $this->pending_coupon_snapshots[ $coupon_id ] );

		$after_amount = $coupon->get_amount();

		if ( (string) $before_amount === (string) $after_amount ) {
			return;
		}

		$post = get_post( $coupon_id );

		if ( ! $post ) {
			return;
		}

		$this->log_event(
			Actions::COUPON_AMOUNT_CHANGE,
			$post,
			sprintf(
				'Discount amount of coupon "%s" changed from "%s" to "%s".',
				$post->post_title,
				$before_amount,
				$after_amount
			),
			Severity::WARNING,
			array( 'coupon_amount' => $before_amount ),
			array( 'coupon_amount' => $after_amount )
		);
	}

	/**
	 * Insert a coupon event log entry.
	 *
	 * @param string  $action Action key.
	 * @param WP_Post $post Coupon post object.
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
				'object_type' => 'shop_coupon',
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
