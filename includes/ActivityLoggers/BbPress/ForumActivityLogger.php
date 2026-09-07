<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\BbPress;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;
use Pastmark\Utils\ContentDiffer;

defined( 'ABSPATH' ) || exit;

/**
 * The bbPress forum activity logger (PM-171, Sprint 11).
 *
 * A `forum` is a real `WP_Post` under the hood (`includes/forums/functions.php`
 * calls `wp_insert_post()`/`wp_update_post()` directly), confirmed against
 * the real installed source (bbPress 2.6.14, this dev environment) rather
 * than assumed from public documentation.
 *
 * **Create/update deliberately use WordPress' own `wp_insert_post`/
 * `post_updated` hooks, not bbPress' own `bbp_new_forum`/`bbp_edit_forum`
 * actions - found and corrected during this ticket's own kickoff research,
 * not assumed from the ticket's original hook-name hypothesis.** Live-
 * verified: creating a forum via a direct `wp_insert_post()` call - the
 * same path WordPress' own native post-editor screen for the `forum` post
 * type uses (bbPress registers no `save_post_forum` bridge to its own
 * `bbp_new_forum`/`bbp_edit_forum` actions - those only fire from
 * `bbp_new_forum_handler()`/`bbp_edit_forum_handler()`, bbPress' own
 * *front-end* submission-form handlers) - does **not** fire `bbp_new_forum`
 * at all. Since wp-admin's "Forums" post-list is genuinely just WordPress'
 * native post editor for this post type, relying on bbPress' own actions
 * alone would silently miss every admin-editor-created/edited forum -
 * exactly the kind of gap this project's standing practice requires
 * disclosing rather than shipping unnoticed. `wp_insert_post`/
 * `post_updated` fire for *both* paths (confirmed live: bbPress' front-end
 * handlers call `wp_insert_post()`/`wp_update_post()` internally too), so
 * hooking those directly covers both without needing to special-case
 * either. Trash/restore/delete keep using bbPress' own `bbp_trash_forum`/
 * `bbp_untrash_forum`/`bbp_delete_forum` - those three *are* hooked
 * unconditionally onto WordPress' own `wp_trash_post`/`untrash_post`/
 * `before_delete_post` actions (`includes/core/actions.php`), confirmed
 * live to fire for a directly-`wp_insert_post()`-created forum too, so no
 * equivalent gap exists there.
 *
 * `log_forum_updated()` only logs when `post_title`/`post_content`/
 * `post_parent`/`menu_order` actually changed - not a bare `post_status`
 * change - because bbPress' own forum-status toggle (`bbp_close_forum()`/
 * `bbp_open_forum()`) writes to the separate `_bbp_status` **post meta**
 * field, never touching `post_status`/calling `wp_update_post()` at all
 * (confirmed by reading `includes/forums/functions.php` directly), so this
 * gate is naturally never in tension with that feature - it exists mainly
 * to keep this logger quiet for any future WordPress-native-only status
 * transition that isn't itself a meaningful forum content edit.
 *
 * This logger's `add_event_group()` declares the *entire* `bbpress` event
 * group - covering `TopicActivityLogger`'s, `ReplyActivityLogger`'s, and
 * `SettingsActivityLogger`'s actions too - since `BbPressIntegration`
 * constructs this logger first and a `pastmark_registered_events` filter
 * callback for the same array key replaces rather than merges. Same
 * convention `FieldGroupActivityLogger` already established for ACF's two
 * loggers.
 */
class ForumActivityLogger extends AbstractLogger {

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
		$this->register_events();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {

		add_action( 'wp_insert_post', $this->guarded( array( $this, 'log_forum_created' ) ), 10, 3 );
		add_action( 'post_updated', $this->guarded( array( $this, 'log_forum_updated' ) ), 10, 3 );

		add_action( 'bbp_trash_forum', $this->guarded( array( $this, 'log_forum_trashed' ) ), 10, 1 );
		add_action( 'bbp_untrash_forum', $this->guarded( array( $this, 'log_forum_restored' ) ), 10, 1 );
		add_action( 'bbp_delete_forum', $this->guarded( array( $this, 'log_forum_deleted' ) ), 10, 1 );
	}

	/**
	 * Declare the shared `bbpress` event group - see this class' own
	 * docblock for why only this logger registers it.
	 *
	 * @return void
	 */
	protected function register_events(): void {

		add_filter( 'pastmark_registered_events', array( $this, 'add_event_group' ) );
	}

	/**
	 * `pastmark_registered_events` callback - adds bbPress' full event
	 * group (forums, topics, replies, settings).
	 *
	 * @param array $events Registered events, keyed by event type.
	 * @return array
	 */
	public function add_event_group( array $events ): array {

		$events[ Events::BBPRESS ] = array(
			'label'   => __( 'bbPress', 'pastmark' ),
			'source'  => 'bbpress',
			'actions' => array(
				array(
					'key'            => Actions::FORUM_CREATE,
					'label'          => __( 'Forum Create', 'pastmark' ),
					'description'    => __( 'New bbPress forum created.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FORUM_UPDATE,
					'label'          => __( 'Forum Update', 'pastmark' ),
					'description'    => __( 'bbPress forum content or settings changed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FORUM_TRASH,
					'label'          => __( 'Forum Trash', 'pastmark' ),
					'description'    => __( 'bbPress forum moved to trash.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::FORUM_RESTORE,
					'label'          => __( 'Forum Restore', 'pastmark' ),
					'description'    => __( 'bbPress forum restored from trash.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FORUM_DELETE,
					'label'          => __( 'Forum Delete', 'pastmark' ),
					'description'    => __( 'bbPress forum permanently deleted.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::TOPIC_CREATE,
					'label'          => __( 'Topic Create', 'pastmark' ),
					'description'    => __( 'New bbPress topic created.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::TOPIC_UPDATE,
					'label'          => __( 'Topic Update', 'pastmark' ),
					'description'    => __( 'bbPress topic content changed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::TOPIC_CLOSE,
					'label'          => __( 'Topic Close', 'pastmark' ),
					'description'    => __( 'bbPress topic closed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::TOPIC_OPEN,
					'label'          => __( 'Topic Open', 'pastmark' ),
					'description'    => __( 'bbPress topic reopened.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::TOPIC_STICK,
					'label'          => __( 'Topic Stick', 'pastmark' ),
					'description'    => __( 'bbPress topic pinned (stuck).', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::TOPIC_UNSTICK,
					'label'          => __( 'Topic Unstick', 'pastmark' ),
					'description'    => __( 'bbPress topic unpinned (unstuck).', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::TOPIC_TRASH,
					'label'          => __( 'Topic Trash', 'pastmark' ),
					'description'    => __( 'bbPress topic moved to trash.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::TOPIC_RESTORE,
					'label'          => __( 'Topic Restore', 'pastmark' ),
					'description'    => __( 'bbPress topic restored from trash.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::TOPIC_DELETE,
					'label'          => __( 'Topic Delete', 'pastmark' ),
					'description'    => __( 'bbPress topic permanently deleted.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::REPLY_CREATE,
					'label'          => __( 'Reply Create', 'pastmark' ),
					'description'    => __( 'New bbPress reply posted.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::REPLY_UPDATE,
					'label'          => __( 'Reply Update', 'pastmark' ),
					'description'    => __( 'bbPress reply content changed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::REPLY_TRASH,
					'label'          => __( 'Reply Trash', 'pastmark' ),
					'description'    => __( 'bbPress reply moved to trash.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::REPLY_RESTORE,
					'label'          => __( 'Reply Restore', 'pastmark' ),
					'description'    => __( 'bbPress reply restored from trash.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::REPLY_DELETE,
					'label'          => __( 'Reply Delete', 'pastmark' ),
					'description'    => __( 'bbPress reply permanently deleted.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::UPDATE,
					'label'          => __( 'Settings Update', 'pastmark' ),
					'description'    => __( 'bbPress setting changed.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
			),
		);

		return $events;
	}

	/**
	 * Log a new forum's creation.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an existing post being updated (vs. a genuine new insert).
	 * @return void
	 */
	public function log_forum_created( $post_id, $post, $update ): void {

		if ( $update || ! $post instanceof \WP_Post || 'forum' !== $post->post_type ) {
			return;
		}

		$this->insert_event_log(
			Events::BBPRESS,
			Actions::FORUM_CREATE,
			array(
				'object_type' => 'forum',
				'object_id'   => $post_id,
				'message'     => sprintf( 'Forum "%s" created.', $post->post_title ),
				'after_data'  => $this->encode_forum_data( $this->prepare_forum_data( $post ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log a forum edit, diffed via `ContentDiffer` with the same fallback
	 * shape every prior integration's diff-capable logger uses. Only
	 * fires when title/content/parent/order actually changed - see this
	 * class' own docblock for why a bare status change is deliberately
	 * excluded from that gate.
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post_after  Post object after the update.
	 * @param \WP_Post $post_before Post object before the update.
	 * @return void
	 */
	public function log_forum_updated( $post_id, $post_after, $post_before ): void {

		if ( ! $post_after instanceof \WP_Post || 'forum' !== $post_after->post_type ) {
			return;
		}

		if ( $post_before->post_title === $post_after->post_title
			&& $post_before->post_content === $post_after->post_content
			&& $post_before->post_parent === $post_after->post_parent
			&& $post_before->menu_order === $post_after->menu_order
		) {
			return;
		}

		$before = $this->prepare_forum_data( $post_before );
		$after  = $this->prepare_forum_data( $post_after );

		$before_json = $this->encode_forum_data( $before );
		$after_json  = $this->encode_forum_data( $after );

		$diff = ContentDiffer::diff( $before_json, $after_json );

		$log_data = array(
			'object_type' => 'forum',
			'object_id'   => $post_id,
			'message'     => sprintf( 'Forum "%s" updated.', $post_after->post_title ),
			'context'     => $this->get_common_context(),
		);

		if ( null !== $diff ) {
			$log_data['after_data'] = wp_json_encode( array( 'forum_diff' => $diff ) );
		} else {
			$log_data['before_data'] = $before_json;
			$log_data['after_data']  = $after_json;
		}

		$this->insert_event_log( Events::BBPRESS, Actions::FORUM_UPDATE, $log_data );
	}

	/**
	 * Log a forum moved to trash.
	 *
	 * `bbp_trash_forum` fires from WordPress' own `wp_trash_post`, before
	 * the post's status is actually changed - `get_post()` here still
	 * returns the forum's pre-trash data.
	 *
	 * @param int $forum_id ID of the forum being trashed.
	 * @return void
	 */
	public function log_forum_trashed( $forum_id ): void {

		$forum_id = (int) $forum_id;
		$forum    = get_post( $forum_id );

		if ( ! $forum instanceof \WP_Post ) {
			return;
		}

		$this->insert_event_log(
			Events::BBPRESS,
			Actions::FORUM_TRASH,
			array(
				'object_type' => 'forum',
				'object_id'   => $forum_id,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'Forum "%s" moved to trash.', $forum->post_title ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log a forum restored from trash.
	 *
	 * @param int $forum_id ID of the forum being restored.
	 * @return void
	 */
	public function log_forum_restored( $forum_id ): void {

		$forum_id = (int) $forum_id;
		$forum    = get_post( $forum_id );

		if ( ! $forum instanceof \WP_Post ) {
			return;
		}

		$this->insert_event_log(
			Events::BBPRESS,
			Actions::FORUM_RESTORE,
			array(
				'object_type' => 'forum',
				'object_id'   => $forum_id,
				'message'     => sprintf( 'Forum "%s" restored from trash.', $forum->post_title ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log a forum's permanent deletion.
	 *
	 * `bbp_delete_forum` fires from WordPress' own `before_delete_post`,
	 * before the post row is actually removed - `get_post()` here still
	 * returns the forum's data.
	 *
	 * @param int $forum_id ID of the forum being deleted.
	 * @return void
	 */
	public function log_forum_deleted( $forum_id ): void {

		$forum_id = (int) $forum_id;
		$forum    = get_post( $forum_id );

		if ( ! $forum instanceof \WP_Post ) {
			return;
		}

		$this->insert_event_log(
			Events::BBPRESS,
			Actions::FORUM_DELETE,
			array(
				'object_type' => 'forum',
				'object_id'   => $forum_id,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'Forum "%s" permanently deleted.', $forum->post_title ),
				'before_data' => $this->encode_forum_data( $this->prepare_forum_data( $forum ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Reduce a forum `WP_Post` to the fields worth diffing/storing.
	 *
	 * @param \WP_Post $post Forum post.
	 * @return array
	 */
	protected function prepare_forum_data( \WP_Post $post ): array {

		return array(
			'title'      => $post->post_title,
			'content'    => $post->post_content,
			'status'     => $post->post_status,
			'parent'     => $post->post_parent,
			'menu_order' => $post->menu_order,
			'type'       => get_post_meta( $post->ID, '_bbp_forum_type', true ),
			'open_state' => get_post_meta( $post->ID, '_bbp_status', true ),
		);
	}

	/**
	 * Encode forum data for diffing/storage.
	 *
	 * `ContentDiffer` diffs line-by-line - the default `wp_json_encode()`
	 * produces a single-line string, which would defeat diffing entirely.
	 * Pretty-printing gives each field its own line, the same fix PM-154's
	 * `FieldGroupActivityLogger::encode_field_group_data()` established.
	 *
	 * @param array $data Forum data (already passed through `prepare_forum_data()`).
	 * @return string
	 */
	protected function encode_forum_data( array $data ): string {

		return (string) wp_json_encode( $data, JSON_PRETTY_PRINT );
	}
}
