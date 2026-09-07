<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers;

use Pastmark\Constants\Severity;
use Pastmark\Constants\Events;
use Pastmark\Constants\Actions;
use Pastmark\Utils\ContentDiffer;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Post activity logger.
 */
class PostActivityLogger extends AbstractLogger {

	/**
	 * Post types this logger shouldn't report on: WooCommerce types
	 * handled by their own dedicated loggers instead, plus WordPress-
	 * internal post types that aren't "content" a site owner edits -
	 * nav menu items (their own dedicated `MenuActivityLogger` already
	 * covers them) and the Customizer's draft/CSS storage types. Without
	 * this, every menu edit or Customizer save would also produce a
	 * confusingly-worded, duplicate generic "post updated" entry here.
	 *
	 * `shop_order_placehold` is HPOS's internal placeholder post type
	 * (`DataSynchronizer::PLACEHOLDER_ORDER_POST_TYPE`): every order or
	 * refund created on an HPOS-enabled store briefly creates one of
	 * these behind the scenes, which without this exclusion produced a
	 * blank, confusingly-worded "Shop_order_placehold "" created." entry
	 * here for every single order.
	 *
	 * `acf-field-group`/`acf-field` (added PM-154) are ACF's own custom
	 * post types - real `WP_Post`s under the hood, so without this
	 * exclusion every field-group/field save this sprint's new
	 * `FieldGroupActivityLogger`/`FieldActivityLogger` already log cleanly
	 * also produced a second, confusingly-capitalized generic entry here
	 * (e.g. "Acf-field-group "..." updated."), confirmed live during this
	 * ticket's own verification pass - same class of duplicate-logging
	 * bug PM-119 fixed for WooCommerce order creation in Sprint 2, just
	 * caught before shipping instead of after.
	 *
	 * `wpforms`/`wpforms-template` (added PM-155) are WPForms' own custom
	 * post types - same shape as the ACF pair above, added proactively
	 * this time since the ACF finding already showed exactly what to look
	 * for; still re-verified live rather than just assumed.
	 *
	 * `tablepress_table` (added PM-170, Sprint 11) is TablePress' own
	 * custom post type (`models/model-post.php` confirms a real
	 * `register_post_type()` call) - same shape as the ACF/WPForms pairs
	 * above, added proactively for the same reason: without it, every
	 * table create/edit this sprint's new `TableActivityLogger` already
	 * logs cleanly would also produce a second, confusingly-worded
	 * generic "Tablepress_table ... updated." entry here.
	 *
	 * `forum`/`topic`/`reply` (added PM-171, Sprint 11) are bbPress' own
	 * three custom post types - same shape again, confirmed against the
	 * real installed source (`includes/forums|topics|replies/functions.php`
	 * all call `wp_insert_post()`/`wp_update_post()` under the hood, and
	 * bbPress' own trash/untrash/delete lifecycle is wired through
	 * WordPress' native `wp_trash_post`/`untrash_post`/`before_delete_post`
	 * actions). Without this, every forum/topic/reply create/edit/trash/
	 * restore/delete this sprint's new `ForumActivityLogger`/
	 * `TopicActivityLogger`/`ReplyActivityLogger` already log cleanly
	 * would also produce a second, generic "Forum/Topic/Reply ... "
	 * entry here.
	 *
	 * `courses`/`lesson`/`topics`/`tutor_quiz` (added PM-176, Sprint 12)
	 * are Tutor LMS's own four custom post types - same shape again,
	 * confirmed against the real installed source (`classes/
	 * Post_types.php`'s `register_post_type()` calls; course/lesson/
	 * topic/quiz creation and editing all confirmed to route through
	 * `wp_insert_post()`/`wp_update_post()`, including topic creation
	 * via `Course::tutor_save_topic()` and quiz creation via
	 * `QuizBuilder`) - without this, every course/lesson/topic/quiz
	 * create/edit/trash/restore/delete this sprint's new
	 * `CourseContentActivityLogger` already logs cleanly would also
	 * produce a second, generic "Courses/Lesson/Topics/Tutor_quiz ..."
	 * entry here.
	 *
	 * @var string[]
	 */
	protected const EXCLUDED_POST_TYPES = array(
		'product',
		'product_variation',
		'shop_coupon',
		'shop_order',
		'shop_order_placehold',
		'nav_menu_item',
		'customize_changeset',
		'custom_css',
		'acf-field-group',
		'acf-field',
		'wpforms',
		'wpforms-template',
		'tablepress_table',
		'forum',
		'topic',
		'reply',
		'courses',
		'lesson',
		'topics',
		'tutor_quiz',
	);

	/**
	 * Page template values captured before a postmeta update, keyed by post ID.
	 *
	 * Template is stored in postmeta (`_wp_page_template`), not on the
	 * `WP_Post` object, so it can't be diffed from `post_updated` like the
	 * other fields — the value has to be captured just before it's saved.
	 *
	 * @var array<int, string>
	 */
	protected $pending_template_values = array();

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

		add_action( 'wp_insert_post', $this->guarded( array( $this, 'log_post_created_directly' ) ), 10, 3 );

		add_action( 'post_updated', $this->guarded( array( $this, 'log_post_updated' ) ), 10, 3 );

		add_action( 'before_delete_post', $this->guarded( array( $this, 'log_post_deleted' ) ) );

		add_action( 'transition_post_status', $this->guarded( array( $this, 'log_post_status_changed' ) ), 10, 3 );

		add_action( 'untrashed_post', $this->guarded( array( $this, 'log_post_restored' ) ) );

		add_action( 'post_stuck', $this->guarded( array( $this, 'log_post_stuck' ) ) );

		add_action( 'post_unstuck', $this->guarded( array( $this, 'log_post_unstuck' ) ) );

		add_filter( 'update_post_metadata', $this->guarded( array( $this, 'capture_template_before_update' ) ), 10, 4 );

		add_action( 'updated_post_meta', $this->guarded( array( $this, 'log_template_changed' ) ), 10, 4 );

		add_action( 'added_post_meta', $this->guarded( array( $this, 'log_template_changed' ) ), 10, 4 );
	}

	/**
	 * Log post updates.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post_after Post object after update.
	 * @param WP_Post $post_before Post object before update.
	 * @return void
	 */
	public function log_post_updated(
		int $post_id,
		WP_Post $post_after,
		WP_Post $post_before
	): void {

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( in_array( $post_after->post_type, self::EXCLUDED_POST_TYPES, true ) ) {
			return;
		}

		if ( 'auto-draft' === $post_after->post_status ) {
			return;
		}

		$before_data = $this->prepare_post_data( $post_before );
		$after_data  = $this->prepare_post_data( $post_after );

		$action = Actions::UPDATE;

		if ( 'auto-draft' === $post_before->post_status ) {
			$action = Actions::CREATE;
		}

		$this->insert_event_log(
			Events::CONTENT,
			$action,
			array(
				'object_type' => $post_after->post_type,
				'object_id'   => $post_id,
				'user_id'     => get_current_user_id(),
				'action'      => $action,
				'message'     => sprintf(
					'%s "%s" updated.',
					ucfirst( $post_after->post_type ),
					$post_after->post_title
				),
				'before_data' => wp_json_encode( $before_data ),
				'after_data'  => wp_json_encode( $after_data ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'post_type'   => $post_after->post_type,
						'post_status' => $post_after->post_status,
					)
				),
			)
		);

		if ( Actions::CREATE === $action ) {
			return;
		}

		$this->log_post_author_changed( $post_before, $post_after );
		$this->log_post_slug_changed( $post_before, $post_after );
		$this->log_post_date_changed( $post_before, $post_after );
		$this->log_post_parent_changed( $post_before, $post_after );
		$this->log_post_content_changed( $post_before, $post_after );
		$this->log_post_password_changed( $post_before, $post_after );
	}

	/**
	 * Log a post created directly with a real status in a single step
	 * (e.g. the REST API or `wp_insert_post()` called with `post_status`
	 * already set), which never passes through the auto-draft-to-real-status
	 * transition that `log_post_updated()` relies on to detect creation.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @param bool    $update Whether this was an update to an existing post.
	 * @return void
	 */
	public function log_post_created_directly( int $post_id, WP_Post $post, bool $update ): void {

		if ( $update ) {
			return;
		}

		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( in_array( $post->post_type, self::EXCLUDED_POST_TYPES, true ) ) {
			return;
		}

		$this->insert_event_log(
			Events::CONTENT,
			Actions::CREATE,
			array(
				'object_type' => $post->post_type,
				'object_id'   => $post_id,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'%s "%s" created.',
					ucfirst( $post->post_type ),
					$post->post_title
				),
				'after_data'  => wp_json_encode( $this->prepare_post_data( $post ) ),
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

	/**
	 * Log post author/ownership change.
	 *
	 * @param WP_Post $post_before Post object before update.
	 * @param WP_Post $post_after Post object after update.
	 * @return void
	 */
	protected function log_post_author_changed( WP_Post $post_before, WP_Post $post_after ): void {

		if ( (int) $post_before->post_author === (int) $post_after->post_author ) {
			return;
		}

		$old_author = get_userdata( $post_before->post_author );
		$new_author = get_userdata( $post_after->post_author );

		$this->insert_event_log(
			Events::CONTENT,
			Actions::AUTHOR_CHANGE,
			array(
				'object_type' => $post_after->post_type,
				'object_id'   => $post_after->ID,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'%s "%s" author changed from "%s" to "%s".',
					ucfirst( $post_after->post_type ),
					$post_after->post_title,
					$old_author ? $old_author->user_login : $post_before->post_author,
					$new_author ? $new_author->user_login : $post_after->post_author
				),
				'before_data' => wp_json_encode(
					array( 'post_author' => $post_before->post_author )
				),
				'after_data'  => wp_json_encode(
					array( 'post_author' => $post_after->post_author )
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'post_type'   => $post_after->post_type,
						'post_status' => $post_after->post_status,
					)
				),
			)
		);
	}

	/**
	 * Log post URL slug change.
	 *
	 * @param WP_Post $post_before Post object before update.
	 * @param WP_Post $post_after Post object after update.
	 * @return void
	 */
	protected function log_post_slug_changed( WP_Post $post_before, WP_Post $post_after ): void {

		if ( $post_before->post_name === $post_after->post_name ) {
			return;
		}

		$this->insert_event_log(
			Events::CONTENT,
			Actions::SLUG_CHANGE,
			array(
				'object_type' => $post_after->post_type,
				'object_id'   => $post_after->ID,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'%s "%s" URL slug changed from "%s" to "%s".',
					ucfirst( $post_after->post_type ),
					$post_after->post_title,
					$post_before->post_name,
					$post_after->post_name
				),
				'before_data' => wp_json_encode(
					array( 'post_name' => $post_before->post_name )
				),
				'after_data'  => wp_json_encode(
					array( 'post_name' => $post_after->post_name )
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'post_type'   => $post_after->post_type,
						'post_status' => $post_after->post_status,
					)
				),
			)
		);
	}

	/**
	 * Log post date change.
	 *
	 * @param WP_Post $post_before Post object before update.
	 * @param WP_Post $post_after Post object after update.
	 * @return void
	 */
	protected function log_post_date_changed( WP_Post $post_before, WP_Post $post_after ): void {

		if ( $post_before->post_date === $post_after->post_date ) {
			return;
		}

		$this->insert_event_log(
			Events::CONTENT,
			Actions::DATE_CHANGE,
			array(
				'object_type' => $post_after->post_type,
				'object_id'   => $post_after->ID,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'%s "%s" date changed from "%s" to "%s".',
					ucfirst( $post_after->post_type ),
					$post_after->post_title,
					$post_before->post_date,
					$post_after->post_date
				),
				'before_data' => wp_json_encode(
					array( 'post_date' => $post_before->post_date )
				),
				'after_data'  => wp_json_encode(
					array( 'post_date' => $post_after->post_date )
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'post_type'   => $post_after->post_type,
						'post_status' => $post_after->post_status,
					)
				),
			)
		);
	}

	/**
	 * Log page parent change.
	 *
	 * @param WP_Post $post_before Post object before update.
	 * @param WP_Post $post_after Post object after update.
	 * @return void
	 */
	protected function log_post_parent_changed( WP_Post $post_before, WP_Post $post_after ): void {

		if ( (int) $post_before->post_parent === (int) $post_after->post_parent ) {
			return;
		}

		$old_parent = $post_before->post_parent ? get_post( $post_before->post_parent ) : null;
		$new_parent = $post_after->post_parent ? get_post( $post_after->post_parent ) : null;

		$this->insert_event_log(
			Events::CONTENT,
			Actions::PARENT_CHANGE,
			array(
				'object_type' => $post_after->post_type,
				'object_id'   => $post_after->ID,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'%s "%s" parent changed from "%s" to "%s".',
					ucfirst( $post_after->post_type ),
					$post_after->post_title,
					$old_parent ? $old_parent->post_title : __( 'None', 'pastmark' ),
					$new_parent ? $new_parent->post_title : __( 'None', 'pastmark' )
				),
				'before_data' => wp_json_encode(
					array( 'post_parent' => $post_before->post_parent )
				),
				'after_data'  => wp_json_encode(
					array( 'post_parent' => $post_after->post_parent )
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'post_type'   => $post_after->post_type,
						'post_status' => $post_after->post_status,
					)
				),
			)
		);
	}

	/**
	 * Log post content body change.
	 *
	 * Routes the before/after content through `ContentDiffer` first
	 * (PM-141): when a compact diff is worthwhile, the row stores just that
	 * diff (under `after_data['content_diff']`) instead of duplicating the
	 * full field into both `before_data` and `after_data`. When diffing
	 * isn't worthwhile (short content, or the differ decides against it),
	 * storage is byte-for-byte what this method did before PM-142.
	 *
	 * @param WP_Post $post_before Post object before update.
	 * @param WP_Post $post_after Post object after update.
	 * @return void
	 */
	protected function log_post_content_changed( WP_Post $post_before, WP_Post $post_after ): void {

		if ( $post_before->post_content === $post_after->post_content ) {
			return;
		}

		$diff = ContentDiffer::diff( $post_before->post_content, $post_after->post_content );

		$log_data = array(
			'object_type' => $post_after->post_type,
			'object_id'   => $post_after->ID,
			'user_id'     => get_current_user_id(),
			'message'     => sprintf(
				'%s "%s" content updated.',
				ucfirst( $post_after->post_type ),
				$post_after->post_title
			),
			'context'     => array_merge(
				$this->get_common_context(),
				array(
					'post_type'   => $post_after->post_type,
					'post_status' => $post_after->post_status,
				)
			),
		);

		if ( null !== $diff ) {
			$log_data['after_data'] = wp_json_encode( array( 'content_diff' => $diff ) );
		} else {
			$log_data['before_data'] = wp_json_encode(
				array( 'post_content' => $post_before->post_content )
			);
			$log_data['after_data']  = wp_json_encode(
				array( 'post_content' => $post_after->post_content )
			);
		}

		$this->insert_event_log( Events::CONTENT, Actions::CONTENT_CHANGE, $log_data );
	}

	/**
	 * Log post password-protection change (part of "visibility").
	 *
	 * @param WP_Post $post_before Post object before update.
	 * @param WP_Post $post_after Post object after update.
	 * @return void
	 */
	protected function log_post_password_changed( WP_Post $post_before, WP_Post $post_after ): void {

		if ( $post_before->post_password === $post_after->post_password ) {
			return;
		}

		$message = ( '' !== $post_after->post_password )
			? sprintf(
				'%s "%s" is now password protected.',
				ucfirst( $post_after->post_type ),
				$post_after->post_title
			)
			: sprintf(
				'%s "%s" password protection removed.',
				ucfirst( $post_after->post_type ),
				$post_after->post_title
			);

		$this->insert_event_log(
			Events::CONTENT,
			Actions::VISIBILITY_CHANGE,
			array(
				'object_type' => $post_after->post_type,
				'object_id'   => $post_after->ID,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => $message,
				'before_data' => wp_json_encode(
					array( 'has_password' => '' !== $post_before->post_password )
				),
				'after_data'  => wp_json_encode(
					array( 'has_password' => '' !== $post_after->post_password )
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'post_type'   => $post_after->post_type,
						'post_status' => $post_after->post_status,
					)
				),
			)
		);
	}

	/**
	 * Log post deletion.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function log_post_deleted( int $post_id ): void {

		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( in_array( $post->post_type, self::EXCLUDED_POST_TYPES, true ) ) {
			return;
		}

		$this->insert_event_log(
			Events::CONTENT,
			Actions::DELETE,
			array(
				'object_type' => $post->post_type,
				'object_id'   => $post_id,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'%s "%s" deleted.',
					ucfirst( $post->post_type ),
					$post->post_title
				),
				'before_data' => wp_json_encode(
					$this->prepare_post_data( $post )
				),
				'after_data'  => '',
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'post_type'   => $post->post_type,
						'post_status' => $post->post_status,
					)
				),
				'severity'    => Severity::WARNING,
			)
		);
	}

	/**
	 * Log post status changes.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function log_post_status_changed(
		string $new_status,
		string $old_status,
		WP_Post $post
	): void {

		if ( $new_status === $old_status ) {
			return;
		}

		if ( wp_is_post_revision( $post->ID ) ) {
			return;
		}

		if ( in_array( $post->post_type, self::EXCLUDED_POST_TYPES, true ) ) {
			return;
		}

		$message = sprintf(
			'%s "%s" status changed from "%s" to "%s".',
			ucfirst( $post->post_type ),
			$post->post_title,
			$old_status,
			$new_status
		);

		if ( 'pending' === $new_status ) {
			$message = sprintf(
				'%s "%s" submitted for review.',
				ucfirst( $post->post_type ),
				$post->post_title
			);
		} elseif ( 'future' === $new_status ) {
			$message = sprintf(
				'%s "%s" scheduled for %s.',
				ucfirst( $post->post_type ),
				$post->post_title,
				get_the_date( '', $post )
			);
		}

		$this->insert_event_log(
			Events::CONTENT,
			Actions::STATUS_CHANGE,
			array(
				'object_type' => $post->post_type,
				'object_id'   => $post->ID,
				'user_id'     => get_current_user_id(),
				'message'     => $message,
				'before_data' => wp_json_encode(
					array(
						'post_status' => $old_status,
					)
				),
				'after_data'  => wp_json_encode(
					array(
						'post_status' => $new_status,
					)
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'post_type'   => $post->post_type,
						'post_status' => $post->post_status,
					)
				),
			)
		);

		if ( 'private' === $new_status || 'private' === $old_status ) {
			$this->log_post_visibility_status_changed( $post, $old_status, $new_status );
		}
	}

	/**
	 * Log post visibility change driven by the "private" status (part of "visibility").
	 *
	 * @param WP_Post $post Post object.
	 * @param string  $old_status Old status.
	 * @param string  $new_status New status.
	 * @return void
	 */
	protected function log_post_visibility_status_changed(
		WP_Post $post,
		string $old_status,
		string $new_status
	): void {

		$message = ( 'private' === $new_status )
			? sprintf(
				'%s "%s" is now private.',
				ucfirst( $post->post_type ),
				$post->post_title
			)
			: sprintf(
				'%s "%s" is no longer private.',
				ucfirst( $post->post_type ),
				$post->post_title
			);

		$this->insert_event_log(
			Events::CONTENT,
			Actions::VISIBILITY_CHANGE,
			array(
				'object_type' => $post->post_type,
				'object_id'   => $post->ID,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => $message,
				'before_data' => wp_json_encode(
					array( 'post_status' => $old_status )
				),
				'after_data'  => wp_json_encode(
					array( 'post_status' => $new_status )
				),
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

	/**
	 * Log post restore.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function log_post_restored( int $post_id ): void {

		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		if ( in_array( $post->post_type, self::EXCLUDED_POST_TYPES, true ) ) {
			return;
		}

		$this->insert_event_log(
			Events::CONTENT,
			Actions::RESTORE,
			array(
				'object_type' => $post->post_type,
				'object_id'   => $post_id,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'%s "%s" restored from trash.',
					ucfirst( $post->post_type ),
					$post->post_title
				),
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

	/**
	 * Log post marked as sticky.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function log_post_stuck( int $post_id ): void {

		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		if ( in_array( $post->post_type, self::EXCLUDED_POST_TYPES, true ) ) {
			return;
		}

		$this->insert_event_log(
			Events::CONTENT,
			Actions::STICKY_CHANGE,
			array(
				'object_type' => $post->post_type,
				'object_id'   => $post_id,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'%s "%s" marked as sticky.',
					ucfirst( $post->post_type ),
					$post->post_title
				),
				'before_data' => wp_json_encode( array( 'sticky' => false ) ),
				'after_data'  => wp_json_encode( array( 'sticky' => true ) ),
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

	/**
	 * Log post removed from sticky.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function log_post_unstuck( int $post_id ): void {

		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		if ( in_array( $post->post_type, self::EXCLUDED_POST_TYPES, true ) ) {
			return;
		}

		$this->insert_event_log(
			Events::CONTENT,
			Actions::STICKY_CHANGE,
			array(
				'object_type' => $post->post_type,
				'object_id'   => $post_id,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'%s "%s" removed from sticky.',
					ucfirst( $post->post_type ),
					$post->post_title
				),
				'before_data' => wp_json_encode( array( 'sticky' => true ) ),
				'after_data'  => wp_json_encode( array( 'sticky' => false ) ),
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

	/**
	 * Capture the page template value before it's overwritten, for diffing.
	 *
	 * Must return `$check` unmodified so the actual meta save isn't
	 * short-circuited.
	 *
	 * @param mixed  $check Whether to short-circuit the meta update.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value New meta value.
	 * @return mixed
	 */
	public function capture_template_before_update( $check, $object_id, $meta_key, $meta_value ) {

		if ( '_wp_page_template' === $meta_key ) {
			$this->pending_template_values[ $object_id ] = get_post_meta( $object_id, $meta_key, true );
		}

		return $check;
	}

	/**
	 * Log page template change.
	 *
	 * @param int    $meta_id Meta ID.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value New meta value.
	 * @return void
	 */
	public function log_template_changed( $meta_id, $object_id, $meta_key, $meta_value ): void {

		if ( '_wp_page_template' !== $meta_key ) {
			return;
		}

		$old_template = isset( $this->pending_template_values[ $object_id ] )
			? $this->pending_template_values[ $object_id ]
			: '';

		unset( $this->pending_template_values[ $object_id ] );

		if ( $old_template === $meta_value ) {
			return;
		}

		$post = get_post( $object_id );

		if ( ! $post ) {
			return;
		}

		if ( in_array( $post->post_type, self::EXCLUDED_POST_TYPES, true ) ) {
			return;
		}

		$this->insert_event_log(
			Events::CONTENT,
			Actions::TEMPLATE_CHANGE,
			array(
				'object_type' => $post->post_type,
				'object_id'   => $object_id,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'%s "%s" template changed from "%s" to "%s".',
					ucfirst( $post->post_type ),
					$post->post_title,
					$old_template ? $old_template : __( 'Default', 'pastmark' ),
					$meta_value ? $meta_value : __( 'Default', 'pastmark' )
				),
				'before_data' => wp_json_encode( array( 'template' => $old_template ) ),
				'after_data'  => wp_json_encode( array( 'template' => $meta_value ) ),
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

	/**
	 * Prepare post data.
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	protected function prepare_post_data( WP_Post $post ): array {

		return array(
			'post_title'   => $post->post_title,
			'post_name'    => $post->post_name,
			'post_status'  => $post->post_status,
			'post_type'    => $post->post_type,
			'post_excerpt' => $post->post_excerpt,
			'post_author'  => $post->post_author,
		);
	}
}
