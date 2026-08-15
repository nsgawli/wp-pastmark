<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\WooCommerce;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Severity;
use Pastmark\Constants\Events;
use Pastmark\Constants\Actions;
use WP_Comment;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce product review activity logger.
 */
class ReviewActivityLogger extends AbstractLogger {

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

		add_action( 'comment_post', $this->guarded( array( $this, 'log_review_posted' ) ), 10, 2 );

		add_action( 'transition_comment_status', $this->guarded( array( $this, 'log_status_changed' ) ), 10, 3 );

		add_action( 'delete_comment', $this->guarded( array( $this, 'log_deleted' ) ), 10, 2 );
	}

	/**
	 * Determine whether a comment is a product review.
	 *
	 * @param WP_Comment $comment Comment object.
	 * @return bool
	 */
	protected function is_review( WP_Comment $comment ): bool {

		return 'product' === get_post_type( (int) $comment->comment_post_ID );
	}

	/**
	 * Log a new product review.
	 *
	 * @param int        $comment_id Comment ID.
	 * @param int|string $comment_approved Approval status of the new comment.
	 * @return void
	 */
	public function log_review_posted( $comment_id, $comment_approved ): void {

		$comment = get_comment( $comment_id );

		if ( ! $comment || ! $this->is_review( $comment ) ) {
			return;
		}

		$product = get_post( $comment->comment_post_ID );

		$this->log_event(
			Actions::REVIEW_CREATE,
			$comment,
			sprintf(
				'New review posted for product "%s" by "%s".',
				$product ? $product->post_title : $comment->comment_post_ID,
				$comment->comment_author
			),
			Severity::INFO,
			array(),
			$this->prepare_review_data( $comment ),
			(int) $comment->user_id
		);
	}

	/**
	 * Log review status transitions: approve/unapprove, spam/not spam, trash.
	 *
	 * @param string     $new_status New status.
	 * @param string     $old_status Old status.
	 * @param WP_Comment $comment Comment object.
	 * @return void
	 */
	public function log_status_changed( string $new_status, string $old_status, WP_Comment $comment ): void {

		if ( $new_status === $old_status || ! $this->is_review( $comment ) ) {
			return;
		}

		$product      = get_post( $comment->comment_post_ID );
		$product_name = $product ? $product->post_title : $comment->comment_post_ID;

		if ( 'trash' === $new_status ) {
			$this->log_event(
				Actions::REVIEW_TRASH,
				$comment,
				sprintf( 'Review for product "%s" by "%s" moved to trash.', $product_name, $comment->comment_author ),
				Severity::WARNING,
				array( 'comment_approved' => $old_status ),
				array( 'comment_approved' => $new_status )
			);
			return;
		}

		if ( 'trash' === $old_status ) {
			return;
		}

		// Permanent deletion also fires this hook with a synthetic 'delete' status
		// (see `wp_delete_comment()`); that's already logged separately by `log_deleted()`.
		if ( 'delete' === $new_status ) {
			return;
		}

		if ( 'spam' === $new_status || 'spam' === $old_status ) {
			$message = ( 'spam' === $new_status )
				? sprintf( 'Review for product "%s" by "%s" marked as spam.', $product_name, $comment->comment_author )
				: sprintf( 'Review for product "%s" by "%s" marked as not spam.', $product_name, $comment->comment_author );

			$this->log_event(
				Actions::REVIEW_SPAM,
				$comment,
				$message,
				Severity::WARNING,
				array( 'comment_approved' => $old_status ),
				array( 'comment_approved' => $new_status )
			);
			return;
		}

		$message = ( 'approved' === $new_status )
			? sprintf( 'Review for product "%s" by "%s" approved.', $product_name, $comment->comment_author )
			: sprintf( 'Review for product "%s" by "%s" unapproved.', $product_name, $comment->comment_author );

		$this->log_event(
			'approved' === $new_status ? Actions::REVIEW_APPROVE : Actions::REVIEW_UNAPPROVE,
			$comment,
			$message,
			Severity::INFO,
			array( 'comment_approved' => $old_status ),
			array( 'comment_approved' => $new_status )
		);
	}

	/**
	 * Log permanent review deletion.
	 *
	 * @param int        $comment_id Comment ID.
	 * @param WP_Comment $comment Comment object.
	 * @return void
	 */
	public function log_deleted( int $comment_id, WP_Comment $comment ): void {

		if ( ! $this->is_review( $comment ) ) {
			return;
		}

		$product = get_post( $comment->comment_post_ID );

		$this->log_event(
			Actions::REVIEW_DELETE,
			$comment,
			sprintf(
				'Review for product "%s" by "%s" permanently deleted.',
				$product ? $product->post_title : $comment->comment_post_ID,
				$comment->comment_author
			),
			Severity::WARNING,
			$this->prepare_review_data( $comment )
		);
	}

	/**
	 * Prepare review data for storage.
	 *
	 * @param WP_Comment $comment Comment object.
	 * @return array
	 */
	protected function prepare_review_data( WP_Comment $comment ): array {

		return array(
			'comment_author'       => $comment->comment_author,
			'comment_author_email' => $comment->comment_author_email,
			'comment_content'      => $comment->comment_content,
			'comment_approved'     => $comment->comment_approved,
			'rating'               => get_comment_meta( $comment->comment_ID, 'rating', true ),
		);
	}

	/**
	 * Insert a review event log entry.
	 *
	 * Defaults to the current (moderating) user; `log_review_posted` overrides
	 * this with the reviewer's own user ID since that action is the reviewer's.
	 *
	 * @param string     $action Action key.
	 * @param WP_Comment $comment Comment object.
	 * @param string     $message Log message.
	 * @param string     $severity Severity.
	 * @param array      $before Before data.
	 * @param array      $after After data.
	 * @param int|null   $user_id Acting user ID override.
	 * @return void
	 */
	protected function log_event(
		string $action,
		WP_Comment $comment,
		string $message,
		string $severity = Severity::INFO,
		array $before = array(),
		array $after = array(),
		$user_id = null
	): void {

		$this->insert_event_log(
			Events::WOOCOMMERCE,
			$action,
			array(
				'object_type' => 'review',
				'object_id'   => (int) $comment->comment_ID,
				'user_id'     => null === $user_id ? get_current_user_id() : $user_id,
				'severity'    => $severity,
				'message'     => $message,
				'before_data' => $before ? wp_json_encode( $before ) : '',
				'after_data'  => $after ? wp_json_encode( $after ) : '',
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'comment_post_id' => (int) $comment->comment_post_ID,
					)
				),
			)
		);
	}
}
