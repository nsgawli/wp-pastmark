<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\BbPress;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;
use Pastmark\Utils\ContentDiffer;

defined( 'ABSPATH' ) || exit;

/**
 * The bbPress reply activity logger (PM-171, Sprint 11).
 *
 * A `reply` is a real `WP_Post`, saved the same way a forum/topic is
 * (`includes/replies/functions.php`). Create/update hook the same
 * WordPress-native `wp_insert_post`/`post_updated` actions `Forum
 * ActivityLogger`/`TopicActivityLogger` do, for the identical reason
 * documented in `ForumActivityLogger`'s own docblock: bbPress' own
 * `bbp_new_reply`/`bbp_edit_reply` actions only fire from its front-end
 * submission-form handlers, not from WordPress' native post-editor
 * screen. Trash/restore/delete keep using `bbp_trash_reply`/`bbp_untrash_
 * reply`/`bbp_delete_reply`, confirmed (like forum's/topic's own trios)
 * to fire unconditionally on WordPress' own `wp_trash_post`/`untrash_
 * post`/`before_delete_post` regardless of which path triggered them.
 */
class ReplyActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'bbpress';

	/**
	 * Constructor.
	 */
	public function __construct() {

		parent::__construct();

		$this->register_hooks();
	}

	/**
	 * Register hooks. No `register_events()` here - `ForumActivityLogger`
	 * declares the shared `bbpress` event group (including this logger's
	 * own actions) since `BbPressIntegration` constructs it first.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {

		add_action( 'wp_insert_post', $this->guarded( array( $this, 'log_reply_created' ) ), 10, 3 );
		add_action( 'post_updated', $this->guarded( array( $this, 'log_reply_updated' ) ), 10, 3 );

		add_action( 'bbp_trash_reply', $this->guarded( array( $this, 'log_reply_trashed' ) ), 10, 1 );
		add_action( 'bbp_untrash_reply', $this->guarded( array( $this, 'log_reply_restored' ) ), 10, 1 );
		add_action( 'bbp_delete_reply', $this->guarded( array( $this, 'log_reply_deleted' ) ), 10, 1 );
	}

	/**
	 * Log a new reply's creation.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an existing post being updated (vs. a genuine new insert).
	 * @return void
	 */
	public function log_reply_created( $post_id, $post, $update ): void {

		if ( $update || ! $post instanceof \WP_Post || 'reply' !== $post->post_type ) {
			return;
		}

		$this->insert_event_log(
			Events::BBPRESS,
			Actions::REPLY_CREATE,
			array(
				'object_type' => 'reply',
				'object_id'   => $post_id,
				'message'     => sprintf( 'Reply posted in topic "%s".', get_the_title( (int) $post->post_parent ) ),
				'after_data'  => $this->encode_reply_data( $this->prepare_reply_data( $post ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log a reply edit, diffed via `ContentDiffer` with the same fallback
	 * shape every prior integration's diff-capable logger uses. Only
	 * fires when title/content/topic actually changed - a bare status
	 * change (e.g. spam/unspam, which bbPress applies via a direct
	 * `wp_update_post()` call touching only `post_status`) is deliberately
	 * excluded from that gate, the same reasoning `TopicActivityLogger::
	 * log_topic_updated()`'s own docblock explains for topics.
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post_after  Post object after the update.
	 * @param \WP_Post $post_before Post object before the update.
	 * @return void
	 */
	public function log_reply_updated( $post_id, $post_after, $post_before ): void {

		if ( ! $post_after instanceof \WP_Post || 'reply' !== $post_after->post_type ) {
			return;
		}

		if ( $post_before->post_title === $post_after->post_title
			&& $post_before->post_content === $post_after->post_content
			&& $post_before->post_parent === $post_after->post_parent
		) {
			return;
		}

		$before = $this->prepare_reply_data( $post_before );
		$after  = $this->prepare_reply_data( $post_after );

		$before_json = $this->encode_reply_data( $before );
		$after_json  = $this->encode_reply_data( $after );

		$diff = ContentDiffer::diff( $before_json, $after_json );

		$topic_title = get_the_title( (int) $post_after->post_parent );

		$log_data = array(
			'object_type' => 'reply',
			'object_id'   => $post_id,
			'message'     => sprintf( 'Reply in topic "%s" updated.', $topic_title ),
			'context'     => $this->get_common_context(),
		);

		if ( null !== $diff ) {
			$log_data['after_data'] = wp_json_encode( array( 'reply_diff' => $diff ) );
		} else {
			$log_data['before_data'] = $before_json;
			$log_data['after_data']  = $after_json;
		}

		$this->insert_event_log( Events::BBPRESS, Actions::REPLY_UPDATE, $log_data );
	}

	/**
	 * Log a reply moved to trash.
	 *
	 * @param int $reply_id Reply ID.
	 * @return void
	 */
	public function log_reply_trashed( $reply_id ): void {

		$reply_id = (int) $reply_id;
		$reply    = get_post( $reply_id );

		if ( ! $reply instanceof \WP_Post ) {
			return;
		}

		$this->insert_event_log(
			Events::BBPRESS,
			Actions::REPLY_TRASH,
			array(
				'object_type' => 'reply',
				'object_id'   => $reply_id,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'Reply in topic "%s" moved to trash.', get_the_title( (int) $reply->post_parent ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log a reply restored from trash.
	 *
	 * @param int $reply_id Reply ID.
	 * @return void
	 */
	public function log_reply_restored( $reply_id ): void {

		$reply_id = (int) $reply_id;
		$reply    = get_post( $reply_id );

		if ( ! $reply instanceof \WP_Post ) {
			return;
		}

		$this->insert_event_log(
			Events::BBPRESS,
			Actions::REPLY_RESTORE,
			array(
				'object_type' => 'reply',
				'object_id'   => $reply_id,
				'message'     => sprintf( 'Reply in topic "%s" restored from trash.', get_the_title( (int) $reply->post_parent ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log a reply's permanent deletion.
	 *
	 * @param int $reply_id Reply ID.
	 * @return void
	 */
	public function log_reply_deleted( $reply_id ): void {

		$reply_id = (int) $reply_id;
		$reply    = get_post( $reply_id );

		if ( ! $reply instanceof \WP_Post ) {
			return;
		}

		$this->insert_event_log(
			Events::BBPRESS,
			Actions::REPLY_DELETE,
			array(
				'object_type' => 'reply',
				'object_id'   => $reply_id,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'Reply in topic "%s" permanently deleted.', get_the_title( (int) $reply->post_parent ) ),
				'before_data' => $this->encode_reply_data( $this->prepare_reply_data( $reply ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Reduce a reply `WP_Post` to the fields worth diffing/storing.
	 *
	 * @param \WP_Post $post Reply post.
	 * @return array
	 */
	protected function prepare_reply_data( \WP_Post $post ): array {

		return array(
			'title'   => $post->post_title,
			'content' => $post->post_content,
			'status'  => $post->post_status,
			'topic'   => $post->post_parent,
		);
	}

	/**
	 * Encode reply data for diffing/storage - see `ForumActivityLogger::
	 * encode_forum_data()`'s docblock for why pretty-printing matters here.
	 *
	 * @param array $data Reply data (already passed through `prepare_reply_data()`).
	 * @return string
	 */
	protected function encode_reply_data( array $data ): string {

		return (string) wp_json_encode( $data, JSON_PRETTY_PRINT );
	}
}
