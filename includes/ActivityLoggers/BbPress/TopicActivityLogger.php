<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\BbPress;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;
use Pastmark\Utils\ContentDiffer;

defined( 'ABSPATH' ) || exit;

/**
 * The bbPress topic activity logger (PM-171, Sprint 11).
 *
 * A `topic` is a real `WP_Post`, saved the same way a forum is
 * (`includes/topics/functions.php`). Create/update hook the same
 * WordPress-native `wp_insert_post`/`post_updated` actions
 * `ForumActivityLogger` does, for the identical reason documented in that
 * class' own docblock: bbPress' own `bbp_new_topic`/`bbp_edit_topic`
 * actions only fire from its *front-end* submission-form handlers, not
 * from WordPress' native post-editor screen (confirmed live during this
 * ticket's own kickoff research), so relying on them alone would miss
 * every admin-editor-created/edited topic.
 *
 * `log_topic_updated()`'s gate (title/content/forum-parent changed, not a
 * bare status change) matters more here than for forums: `bbp_close_topic()`/
 * `bbp_open_topic()`/`bbp_approve_topic()`/`bbp_unapprove_topic()` all call
 * `wp_update_post()` directly, changing **only** `post_status` (confirmed
 * by reading `includes/topics/functions.php`) - without this gate, every
 * close/open would *also* produce a spurious generic "topic updated" row
 * alongside its own dedicated `topic_close`/`topic_open` event.
 *
 * Close/open/stick/unstick keep using bbPress' own dedicated actions
 * (`bbp_closed_topic`/`bbp_opened_topic`/`bbp_stick_topic`+`bbp_stuck_
 * topic`/`bbp_unstuck_topic`) - unlike create/update, these have no
 * WordPress-native equivalent path to miss (there is no "close a topic"
 * concept in core WordPress), so no admin-editor-bypass gap exists for
 * them. Trash/restore/delete keep using `bbp_trash_topic`/`bbp_untrash_
 * topic`/`bbp_delete_topic`, confirmed (like forum's own trio) to fire
 * unconditionally on WordPress' own `wp_trash_post`/`untrash_post`/
 * `before_delete_post` regardless of which path triggered them.
 *
 * The `bbp_stick_topic()`-also-calls-`bbp_unstick_topic()`-internally
 * double-fire risk (a genuine "stick" action clearing any prior sticky
 * state first) and the disclosed, accepted trash/spam/delete/unapprove
 * side-effect unstick case are both explained in full where they're
 * actually handled, below.
 */
class TopicActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'bbpress';

	/**
	 * Whether a `bbp_stick_topic()` call is currently in progress - see
	 * `log_topic_unstuck()`'s own docblock.
	 *
	 * @var bool
	 */
	protected static $sticking = false;

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

		add_action( 'wp_insert_post', $this->guarded( array( $this, 'log_topic_created' ) ), 10, 3 );
		add_action( 'post_updated', $this->guarded( array( $this, 'log_topic_updated' ) ), 10, 3 );

		add_action( 'bbp_closed_topic', $this->guarded( array( $this, 'log_topic_closed' ) ), 10, 1 );
		add_action( 'bbp_opened_topic', $this->guarded( array( $this, 'log_topic_opened' ) ), 10, 1 );

		add_action( 'bbp_stick_topic', $this->guarded( array( $this, 'mark_sticking' ) ), 1, 2 );
		add_action( 'bbp_stuck_topic', $this->guarded( array( $this, 'log_topic_stuck' ) ), 10, 3 );
		add_action( 'bbp_unstuck_topic', $this->guarded( array( $this, 'log_topic_unstuck' ) ), 10, 2 );

		add_action( 'bbp_trash_topic', $this->guarded( array( $this, 'log_topic_trashed' ) ), 10, 1 );
		add_action( 'bbp_untrash_topic', $this->guarded( array( $this, 'log_topic_restored' ) ), 10, 1 );
		add_action( 'bbp_delete_topic', $this->guarded( array( $this, 'log_topic_deleted' ) ), 10, 1 );
	}

	/**
	 * Log a new topic's creation.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an existing post being updated (vs. a genuine new insert).
	 * @return void
	 */
	public function log_topic_created( $post_id, $post, $update ): void {

		if ( $update || ! $post instanceof \WP_Post || 'topic' !== $post->post_type ) {
			return;
		}

		$this->insert_event_log(
			Events::BBPRESS,
			Actions::TOPIC_CREATE,
			array(
				'object_type' => 'topic',
				'object_id'   => $post_id,
				'message'     => sprintf( 'Topic "%s" created in forum "%s".', $post->post_title, get_the_title( (int) $post->post_parent ) ),
				'after_data'  => $this->encode_topic_data( $this->prepare_topic_data( $post ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log a topic edit, diffed via `ContentDiffer` with the same fallback
	 * shape every prior integration's diff-capable logger uses. Only
	 * fires when title/content/forum actually changed - see this class'
	 * own docblock for why a bare status change is deliberately excluded.
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post_after  Post object after the update.
	 * @param \WP_Post $post_before Post object before the update.
	 * @return void
	 */
	public function log_topic_updated( $post_id, $post_after, $post_before ): void {

		if ( ! $post_after instanceof \WP_Post || 'topic' !== $post_after->post_type ) {
			return;
		}

		if ( $post_before->post_title === $post_after->post_title
			&& $post_before->post_content === $post_after->post_content
			&& $post_before->post_parent === $post_after->post_parent
		) {
			return;
		}

		$before = $this->prepare_topic_data( $post_before );
		$after  = $this->prepare_topic_data( $post_after );

		$before_json = $this->encode_topic_data( $before );
		$after_json  = $this->encode_topic_data( $after );

		$diff = ContentDiffer::diff( $before_json, $after_json );

		$log_data = array(
			'object_type' => 'topic',
			'object_id'   => $post_id,
			'message'     => sprintf( 'Topic "%s" updated.', $post_after->post_title ),
			'context'     => $this->get_common_context(),
		);

		if ( null !== $diff ) {
			$log_data['after_data'] = wp_json_encode( array( 'topic_diff' => $diff ) );
		} else {
			$log_data['before_data'] = $before_json;
			$log_data['after_data']  = $after_json;
		}

		$this->insert_event_log( Events::BBPRESS, Actions::TOPIC_UPDATE, $log_data );
	}

	/**
	 * Log a topic being closed.
	 *
	 * @param int $topic_id Topic ID.
	 * @return void
	 */
	public function log_topic_closed( $topic_id ): void {

		$topic_id = (int) $topic_id;
		$topic    = get_post( $topic_id );

		if ( ! $topic instanceof \WP_Post ) {
			return;
		}

		$this->insert_event_log(
			Events::BBPRESS,
			Actions::TOPIC_CLOSE,
			array(
				'object_type' => 'topic',
				'object_id'   => $topic_id,
				'message'     => sprintf( 'Topic "%s" closed.', $topic->post_title ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log a topic being reopened.
	 *
	 * @param int $topic_id Topic ID.
	 * @return void
	 */
	public function log_topic_opened( $topic_id ): void {

		$topic_id = (int) $topic_id;
		$topic    = get_post( $topic_id );

		if ( ! $topic instanceof \WP_Post ) {
			return;
		}

		$this->insert_event_log(
			Events::BBPRESS,
			Actions::TOPIC_OPEN,
			array(
				'object_type' => 'topic',
				'object_id'   => $topic_id,
				'message'     => sprintf( 'Topic "%s" reopened.', $topic->post_title ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Mark that a `bbp_stick_topic()` call is in progress.
	 *
	 * BbPress wires `add_action( 'bbp_stick_topic', 'bbp_unstick_topic' )`
	 * (clearing any prior sticky state before applying the new one) -
	 * confirmed by reading `bbp_stick_topic()`'s own body directly - which
	 * means every genuine "stick" action fires `bbp_unstick_topic`
	 * internally first. Without this guard, one admin "stick" click would
	 * log both an `unstick` and a `stick` row. This flag, set here (hooked
	 * on `bbp_stick_topic`, the *before* hook, at an early priority) and
	 * cleared by `log_topic_stuck()` (hooked on `bbp_stuck_topic`, the
	 * *after* hook, once the real write has happened), lets
	 * `log_topic_unstuck()` recognize and skip that internal pre-clear. A
	 * genuine standalone "unstick" (the admin UI's own "Unstick" link,
	 * which calls `bbp_unstick_topic()` directly, never through
	 * `bbp_stick_topic()`) leaves the flag unset and logs normally.
	 *
	 * **Known, disclosed limitation the guard above doesn't cover:**
	 * `bbp_unstick_topic` is *also* wired as a side effect of unapprove/
	 * spam/trash/delete (`add_action( 'bbp_trash_topic', 'bbp_unstick_
	 * topic' )` and three siblings, `includes/core/actions.php`) - a
	 * sticky topic being trashed genuinely does get unstuck as part of
	 * that, and this guard only covers the stick-specific double-fire. A
	 * trashed sticky topic therefore logs both its own `topic_trash` row
	 * and a `topic_unstick` row. Accepted as-is (both rows are
	 * individually accurate) rather than building further suppression,
	 * matching this codebase's existing "known, narrow limitation"
	 * precedent (PM-154's duplicate-detection edge case).
	 *
	 * @param int  $topic_id Topic ID (unused).
	 * @param bool $super    Whether this is a site-wide "super sticky" (unused).
	 * @return void
	 */
	public function mark_sticking( $topic_id, $super ): void {

		self::$sticking = true;
	}

	/**
	 * Log a topic being stuck (pinned).
	 *
	 * @param int  $topic_id Topic ID.
	 * @param bool $super    Whether this is a site-wide "super sticky".
	 * @param bool $success  Whether the stick actually succeeded.
	 * @return void
	 */
	public function log_topic_stuck( $topic_id, $super, $success ): void {

		self::$sticking = false;

		if ( ! $success ) {
			return;
		}

		$topic_id = (int) $topic_id;
		$topic    = get_post( $topic_id );

		if ( ! $topic instanceof \WP_Post ) {
			return;
		}

		$this->insert_event_log(
			Events::BBPRESS,
			Actions::TOPIC_STICK,
			array(
				'object_type' => 'topic',
				'object_id'   => $topic_id,
				'message'     => $super
					? sprintf( 'Topic "%s" stuck to the front (super sticky).', $topic->post_title )
					: sprintf( 'Topic "%s" stuck to its forum.', $topic->post_title ),
				'context'     => array_merge( $this->get_common_context(), array( 'super' => (bool) $super ) ),
			)
		);
	}

	/**
	 * Log a topic being unstuck (unpinned) - see `mark_sticking()`'s own
	 * docblock for the guard and its disclosed limitation.
	 *
	 * @param int  $topic_id Topic ID.
	 * @param bool $success  Whether the unstick actually succeeded.
	 * @return void
	 */
	public function log_topic_unstuck( $topic_id, $success ): void {

		if ( self::$sticking || ! $success ) {
			return;
		}

		$topic_id = (int) $topic_id;
		$topic    = get_post( $topic_id );

		if ( ! $topic instanceof \WP_Post ) {
			return;
		}

		$this->insert_event_log(
			Events::BBPRESS,
			Actions::TOPIC_UNSTICK,
			array(
				'object_type' => 'topic',
				'object_id'   => $topic_id,
				'message'     => sprintf( 'Topic "%s" unstuck.', $topic->post_title ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log a topic moved to trash.
	 *
	 * @param int $topic_id Topic ID.
	 * @return void
	 */
	public function log_topic_trashed( $topic_id ): void {

		$topic_id = (int) $topic_id;
		$topic    = get_post( $topic_id );

		if ( ! $topic instanceof \WP_Post ) {
			return;
		}

		$this->insert_event_log(
			Events::BBPRESS,
			Actions::TOPIC_TRASH,
			array(
				'object_type' => 'topic',
				'object_id'   => $topic_id,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'Topic "%s" moved to trash.', $topic->post_title ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log a topic restored from trash.
	 *
	 * @param int $topic_id Topic ID.
	 * @return void
	 */
	public function log_topic_restored( $topic_id ): void {

		$topic_id = (int) $topic_id;
		$topic    = get_post( $topic_id );

		if ( ! $topic instanceof \WP_Post ) {
			return;
		}

		$this->insert_event_log(
			Events::BBPRESS,
			Actions::TOPIC_RESTORE,
			array(
				'object_type' => 'topic',
				'object_id'   => $topic_id,
				'message'     => sprintf( 'Topic "%s" restored from trash.', $topic->post_title ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log a topic's permanent deletion.
	 *
	 * @param int $topic_id Topic ID.
	 * @return void
	 */
	public function log_topic_deleted( $topic_id ): void {

		$topic_id = (int) $topic_id;
		$topic    = get_post( $topic_id );

		if ( ! $topic instanceof \WP_Post ) {
			return;
		}

		$this->insert_event_log(
			Events::BBPRESS,
			Actions::TOPIC_DELETE,
			array(
				'object_type' => 'topic',
				'object_id'   => $topic_id,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'Topic "%s" permanently deleted.', $topic->post_title ),
				'before_data' => $this->encode_topic_data( $this->prepare_topic_data( $topic ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Reduce a topic `WP_Post` to the fields worth diffing/storing.
	 *
	 * @param \WP_Post $post Topic post.
	 * @return array
	 */
	protected function prepare_topic_data( \WP_Post $post ): array {

		return array(
			'title'   => $post->post_title,
			'content' => $post->post_content,
			'status'  => $post->post_status,
			'forum'   => $post->post_parent,
		);
	}

	/**
	 * Encode topic data for diffing/storage - see `ForumActivityLogger::
	 * encode_forum_data()`'s docblock for why pretty-printing matters here.
	 *
	 * @param array $data Topic data (already passed through `prepare_topic_data()`).
	 * @return string
	 */
	protected function encode_topic_data( array $data ): string {

		return (string) wp_json_encode( $data, JSON_PRETTY_PRINT );
	}
}
