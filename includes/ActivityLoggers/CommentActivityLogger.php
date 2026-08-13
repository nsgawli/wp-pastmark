<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace LogTrail\ActivityLoggers;

use LogTrail\Constants\Severity;
use LogTrail\Constants\Events;
use LogTrail\Constants\Actions;
use WP_Comment;

defined( 'ABSPATH' ) || exit;

/**
 * Comment activity logger.
 */
class CommentActivityLogger extends AbstractLogger {

	/**
	 * Comment fields captured before an edit, keyed by comment ID.
	 *
	 * `edit_comment` fires after the row is already saved, so the previous
	 * values have to be captured earlier via the `wp_update_comment_data`
	 * filter to allow a before/after diff.
	 *
	 * @var array<int, array>
	 */
	protected $pending_comment_values = array();

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

		add_action( 'comment_post', $this->guarded( array( $this, 'log_comment_posted' ) ), 10, 2 );

		add_filter( 'wp_update_comment_data', $this->guarded( array( $this, 'capture_comment_before_edit' ) ), 10, 2 );

		add_action( 'edit_comment', $this->guarded( array( $this, 'log_comment_edited' ) ) );

		add_action( 'transition_comment_status', $this->guarded( array( $this, 'log_comment_status_changed' ) ), 10, 3 );

		add_action( 'delete_comment', $this->guarded( array( $this, 'log_comment_deleted' ) ), 10, 2 );
	}

	/**
	 * Log a new comment or reply.
	 *
	 * @param int        $comment_id Comment ID.
	 * @param int|string $comment_approved Approval status of the new comment.
	 * @return void
	 */
	public function log_comment_posted( $comment_id, $comment_approved ): void {

		$comment = get_comment( $comment_id );

		if ( ! $comment || $this->is_handled_by_woocommerce_logger( $comment ) ) {
			return;
		}

		$is_reply = (int) $comment->comment_parent > 0;

		$post = get_post( $comment->comment_post_ID );

		$message = $is_reply
			? sprintf(
				'%s replied to a comment on "%s".',
				$comment->comment_author,
				$post ? $post->post_title : $comment->comment_post_ID
			)
			: sprintf(
				'%s posted a comment on "%s".',
				$comment->comment_author,
				$post ? $post->post_title : $comment->comment_post_ID
			);

		$this->insert_event_log(
			Events::COMMENT,
			$is_reply ? Actions::REPLY : Actions::CREATE,
			array(
				'object_type' => 'comment',
				'object_id'   => $comment_id,
				'user_id'     => (int) $comment->user_id,
				'message'     => $message,
				'after_data'  => wp_json_encode( $this->prepare_comment_data( $comment ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'comment_post_id' => (int) $comment->comment_post_ID,
						'comment_parent'  => (int) $comment->comment_parent,
					)
				),
			)
		);
	}

	/**
	 * Capture comment fields before they're overwritten, for diffing.
	 *
	 * Must return `$data` unmodified so the actual comment save isn't
	 * short-circuited.
	 *
	 * @param array $data Sanitized comment data about to be saved.
	 * @param array $comment Comment data prior to the update.
	 * @return array
	 */
	public function capture_comment_before_edit( $data, $comment ) {

		$comment_id = isset( $comment['comment_ID'] ) ? (int) $comment['comment_ID'] : 0;

		if ( $comment_id ) {
			$this->pending_comment_values[ $comment_id ] = array(
				'comment_content'      => $comment['comment_content'] ?? '',
				'comment_author'       => $comment['comment_author'] ?? '',
				'comment_author_email' => $comment['comment_author_email'] ?? '',
				'comment_author_url'   => $comment['comment_author_url'] ?? '',
			);
		}

		return $data;
	}

	/**
	 * Log comment edit.
	 *
	 * @param int $comment_id Comment ID.
	 * @return void
	 */
	public function log_comment_edited( int $comment_id ): void {

		$comment = get_comment( $comment_id );

		if ( ! $comment || $this->is_handled_by_woocommerce_logger( $comment ) ) {
			return;
		}

		$before = isset( $this->pending_comment_values[ $comment_id ] )
			? $this->pending_comment_values[ $comment_id ]
			: array();

		unset( $this->pending_comment_values[ $comment_id ] );

		$after = array(
			'comment_content'      => $comment->comment_content,
			'comment_author'       => $comment->comment_author,
			'comment_author_email' => $comment->comment_author_email,
			'comment_author_url'   => $comment->comment_author_url,
		);

		if ( $before === $after ) {
			return;
		}

		$post = get_post( $comment->comment_post_ID );

		$this->insert_event_log(
			Events::COMMENT,
			Actions::UPDATE,
			array(
				'object_type' => 'comment',
				'object_id'   => $comment_id,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'Comment by "%s" on "%s" edited.',
					$comment->comment_author,
					$post ? $post->post_title : $comment->comment_post_ID
				),
				'before_data' => wp_json_encode( $before ),
				'after_data'  => wp_json_encode( $after ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'comment_post_id' => (int) $comment->comment_post_ID,
					)
				),
			)
		);
	}

	/**
	 * Log comment status transitions: approve/unapprove, spam/not spam,
	 * moved to trash, restored from trash.
	 *
	 * @param string     $new_status New comment status.
	 * @param string     $old_status Old comment status.
	 * @param WP_Comment $comment Comment object.
	 * @return void
	 */
	public function log_comment_status_changed( string $new_status, string $old_status, WP_Comment $comment ): void {

		if ( $new_status === $old_status || $this->is_handled_by_woocommerce_logger( $comment ) ) {
			return;
		}

		// `wp_delete_comment()` also fires this transition with new_status
		// 'delete' so callers can react to the removal; permanent deletion
		// is already logged by `log_comment_deleted()` via `delete_comment`.
		if ( 'delete' === $new_status ) {
			return;
		}

		$post = get_post( $comment->comment_post_ID );

		$post_title = $post ? $post->post_title : $comment->comment_post_ID;

		if ( 'trash' === $new_status ) {
			$this->log_comment_transition(
				$comment,
				Actions::STATUS_CHANGE,
				sprintf( 'Comment by "%s" on "%s" moved to trash.', $comment->comment_author, $post_title ),
				$old_status,
				$new_status,
				Severity::WARNING
			);
			return;
		}

		if ( 'trash' === $old_status ) {
			$this->log_comment_transition(
				$comment,
				Actions::RESTORE,
				sprintf( 'Comment by "%s" on "%s" restored from trash.', $comment->comment_author, $post_title ),
				$old_status,
				$new_status
			);
			return;
		}

		if ( 'spam' === $new_status || 'spam' === $old_status ) {
			$message = ( 'spam' === $new_status )
				? sprintf( 'Comment by "%s" on "%s" marked as spam.', $comment->comment_author, $post_title )
				: sprintf( 'Comment by "%s" on "%s" marked as not spam.', $comment->comment_author, $post_title );

			$this->log_comment_transition(
				$comment,
				Actions::SPAM_CHANGE,
				$message,
				$old_status,
				$new_status,
				Severity::WARNING
			);
			return;
		}

		$message = ( 'approved' === $new_status )
			? sprintf( 'Comment by "%s" on "%s" approved.', $comment->comment_author, $post_title )
			: sprintf( 'Comment by "%s" on "%s" unapproved.', $comment->comment_author, $post_title );

		$this->log_comment_transition(
			$comment,
			Actions::STATUS_CHANGE,
			$message,
			$old_status,
			$new_status
		);
	}

	/**
	 * Insert a comment status transition log entry.
	 *
	 * @param WP_Comment $comment Comment object.
	 * @param string     $action Action key.
	 * @param string     $message Log message.
	 * @param string     $old_status Old status.
	 * @param string     $new_status New status.
	 * @param string     $severity Severity.
	 * @return void
	 */
	protected function log_comment_transition(
		WP_Comment $comment,
		string $action,
		string $message,
		string $old_status,
		string $new_status,
		string $severity = Severity::INFO
	): void {

		$this->insert_event_log(
			Events::COMMENT,
			$action,
			array(
				'object_type' => 'comment',
				'object_id'   => (int) $comment->comment_ID,
				'user_id'     => get_current_user_id(),
				'severity'    => $severity,
				'message'     => $message,
				'before_data' => wp_json_encode( array( 'comment_status' => $old_status ) ),
				'after_data'  => wp_json_encode( array( 'comment_status' => $new_status ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'comment_post_id' => (int) $comment->comment_post_ID,
					)
				),
			)
		);
	}

	/**
	 * Log permanent comment deletion.
	 *
	 * @param int        $comment_id Comment ID.
	 * @param WP_Comment $comment Comment object.
	 * @return void
	 */
	public function log_comment_deleted( int $comment_id, WP_Comment $comment ): void {

		if ( $this->is_handled_by_woocommerce_logger( $comment ) ) {
			return;
		}

		$post = get_post( $comment->comment_post_ID );

		$this->insert_event_log(
			Events::COMMENT,
			Actions::DELETE,
			array(
				'object_type' => 'comment',
				'object_id'   => $comment_id,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'Comment by "%s" on "%s" permanently deleted.',
					$comment->comment_author,
					$post ? $post->post_title : $comment->comment_post_ID
				),
				'before_data' => wp_json_encode( $this->prepare_comment_data( $comment ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'comment_post_id' => (int) $comment->comment_post_ID,
					)
				),
			)
		);
	}

	/**
	 * Determine whether a comment is one this logger should stay out of
	 * because a dedicated WooCommerce logger already handles it: a product
	 * review (`ReviewActivityLogger`) or an order note (`OrderActivityLogger`).
	 *
	 * Without the `order_note` check, deleting an order note (a comment
	 * under the hood, with `comment_type` `order_note`) also fired this
	 * logger's generic `delete_comment` hook, producing a duplicate,
	 * confusingly-worded "Comment ... permanently deleted" entry alongside
	 * the correct `order_note_delete` one - since HPOS orders aren't real
	 * posts, the post-title lookup for that entry also came up blank.
	 *
	 * @param WP_Comment $comment Comment object.
	 * @return bool
	 */
	protected function is_handled_by_woocommerce_logger( WP_Comment $comment ): bool {

		if ( 'order_note' === $comment->comment_type ) {
			return true;
		}

		return 'product' === get_post_type( (int) $comment->comment_post_ID );
	}

	/**
	 * Prepare comment data for storage.
	 *
	 * @param WP_Comment $comment Comment object.
	 * @return array
	 */
	protected function prepare_comment_data( WP_Comment $comment ): array {

		return array(
			'comment_author'       => $comment->comment_author,
			'comment_author_email' => $comment->comment_author_email,
			'comment_content'      => $comment->comment_content,
			'comment_approved'     => $comment->comment_approved,
			'comment_post_ID'      => $comment->comment_post_ID,
			'comment_parent'       => $comment->comment_parent,
		);
	}
}
