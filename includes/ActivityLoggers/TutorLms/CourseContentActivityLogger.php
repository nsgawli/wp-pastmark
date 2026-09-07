<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\TutorLms;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;
use Pastmark\Utils\ContentDiffer;

defined( 'ABSPATH' ) || exit;

/**
 * Logs Tutor LMS course/lesson/topic/quiz content changes (PM-176,
 * Sprint 12).
 *
 * Courses/lessons/topics/quizzes are all real `WP_Post`s under the hood
 * (`classes/Post_types.php`'s own `register_post_type()` calls, four
 * separate post types: `courses`, `lesson`, `topics`, `tutor_quiz` -
 * confirmed against the real installed source, Tutor LMS 4.0.7, this dev
 * environment), so this logger uses WordPress' own `wp_insert_post`/
 * `post_updated`/`wp_trash_post`/`untrash_post`/`before_delete_post`
 * hooks - the same "generic WP post hooks, not the plugin's own"
 * approach `ForumActivityLogger` (PM-171) already settled on, confirmed
 * here the same way: course creation/editing (`classes/Course.php`),
 * lesson creation/editing (`classes/Lesson.php`), topic creation/editing
 * (`Course::tutor_save_topic()`), and quiz creation
 * (`classes/QuizBuilder.php`) all call `wp_insert_post()`/
 * `wp_update_post()` directly, and topic deletion
 * (`Course::tutor_delete_topic()`) calls `wp_delete_post()` directly - no
 * Tutor-specific save/delete bridge exists for any of the four, so
 * relying on Tutor's own (nonexistent) hooks here would silently miss
 * every one of these content changes.
 *
 * One class handles all four post types (matching this ticket's own
 * `CourseContentActivityLogger` naming) via a small per-post-type lookup
 * table (`self::CONTENT_TYPES`) rather than four near-identical classes -
 * the four post types share an identical create/update/trash/restore/
 * delete shape, just with their own action constants and object-type
 * label for the Events settings screen and "go to object" links.
 *
 * `log_content_updated()` diffs via `ContentDiffer` on title+content,
 * falling back to full before/after when the diff isn't worthwhile - the
 * same shape `TableActivityLogger`/`ForumActivityLogger` already use.
 * Corresponding CPTs are added to core `PostActivityLogger::
 * EXCLUDED_POST_TYPES` to avoid a duplicate generic "Courses/Lesson/
 * Topics/Tutor_quiz ..." entry for the same save - see that class' own
 * docblock.
 */
class CourseContentActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'tutor-lms';

	/**
	 * Per-post-type action/label lookup, confirmed against the real
	 * installed source's own post type slugs (`classes/Post_types.php`).
	 *
	 * @var array<string, array{object_type: string, label: string, create: string, update: string, trash: string, restore: string, delete: string}>
	 */
	private const CONTENT_TYPES = array(
		'courses'    => array(
			'object_type' => 'course',
			'label'       => 'Course',
			'create'      => Actions::COURSE_CREATE,
			'update'      => Actions::COURSE_UPDATE,
			'trash'       => Actions::COURSE_TRASH,
			'restore'     => Actions::COURSE_RESTORE,
			'delete'      => Actions::COURSE_DELETE,
		),
		'lesson'     => array(
			'object_type' => 'lesson',
			'label'       => 'Lesson',
			'create'      => Actions::LESSON_CREATE,
			'update'      => Actions::LESSON_UPDATE,
			'trash'       => Actions::LESSON_TRASH,
			'restore'     => Actions::LESSON_RESTORE,
			'delete'      => Actions::LESSON_DELETE,
		),
		'topics'     => array(
			'object_type' => 'course-topic',
			'label'       => 'Course Topic',
			'create'      => Actions::COURSE_TOPIC_CREATE,
			'update'      => Actions::COURSE_TOPIC_UPDATE,
			'trash'       => Actions::COURSE_TOPIC_TRASH,
			'restore'     => Actions::COURSE_TOPIC_RESTORE,
			'delete'      => Actions::COURSE_TOPIC_DELETE,
		),
		'tutor_quiz' => array(
			'object_type' => 'quiz',
			'label'       => 'Quiz',
			'create'      => Actions::QUIZ_CREATE,
			'update'      => Actions::QUIZ_UPDATE,
			'trash'       => Actions::QUIZ_TRASH,
			'restore'     => Actions::QUIZ_RESTORE,
			'delete'      => Actions::QUIZ_DELETE,
		),
	);

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

		add_action( 'wp_insert_post', $this->guarded( array( $this, 'log_content_created' ) ), 10, 3 );
		add_action( 'post_updated', $this->guarded( array( $this, 'log_content_updated' ) ), 10, 3 );
		add_action( 'wp_trash_post', $this->guarded( array( $this, 'log_content_trashed' ) ), 10, 1 );
		add_action( 'untrash_post', $this->guarded( array( $this, 'log_content_restored' ) ), 10, 1 );
		add_action( 'before_delete_post', $this->guarded( array( $this, 'log_content_deleted' ) ), 10, 1 );
	}

	/**
	 * Declare the shared `tutor-lms` event group - covers
	 * `ProgressActivityLogger`'s actions too; only this logger (listed
	 * first in `TutorLmsIntegration::get_logger_classes()`) registers it,
	 * matching `BbPressIntegration`'s existing convention.
	 *
	 * @return void
	 */
	protected function register_events(): void {

		add_filter( 'pastmark_registered_events', array( $this, 'add_event_group' ) );
	}

	/**
	 * `pastmark_registered_events` callback.
	 *
	 * @param array $events Registered events, keyed by event type.
	 * @return array
	 */
	public function add_event_group( array $events ): array {

		$actions = array(
			array(
				'key'            => Actions::COURSE_CREATE,
				'label'          => __( 'Course Create', 'pastmark' ),
				'description'    => __( 'A new Tutor LMS course was created.', 'pastmark' ),
				'severity'       => Severity::INFO,
				'severity_label' => Severity::resolve_label( Severity::INFO ),
			),
			array(
				'key'            => Actions::COURSE_UPDATE,
				'label'          => __( 'Course Update', 'pastmark' ),
				'description'    => __( "A Tutor LMS course's content changed.", 'pastmark' ),
				'severity'       => Severity::INFO,
				'severity_label' => Severity::resolve_label( Severity::INFO ),
			),
			array(
				'key'            => Actions::COURSE_TRASH,
				'label'          => __( 'Course Trash', 'pastmark' ),
				'description'    => __( 'A Tutor LMS course was moved to trash.', 'pastmark' ),
				'severity'       => Severity::WARNING,
				'severity_label' => Severity::resolve_label( Severity::WARNING ),
			),
			array(
				'key'            => Actions::COURSE_RESTORE,
				'label'          => __( 'Course Restore', 'pastmark' ),
				'description'    => __( 'A Tutor LMS course was restored from trash.', 'pastmark' ),
				'severity'       => Severity::INFO,
				'severity_label' => Severity::resolve_label( Severity::INFO ),
			),
			array(
				'key'            => Actions::COURSE_DELETE,
				'label'          => __( 'Course Delete', 'pastmark' ),
				'description'    => __( 'A Tutor LMS course was permanently deleted.', 'pastmark' ),
				'severity'       => Severity::WARNING,
				'severity_label' => Severity::resolve_label( Severity::WARNING ),
			),
			array(
				'key'            => Actions::LESSON_CREATE,
				'label'          => __( 'Lesson Create', 'pastmark' ),
				'description'    => __( 'A new Tutor LMS lesson was created.', 'pastmark' ),
				'severity'       => Severity::INFO,
				'severity_label' => Severity::resolve_label( Severity::INFO ),
			),
			array(
				'key'            => Actions::LESSON_UPDATE,
				'label'          => __( 'Lesson Update', 'pastmark' ),
				'description'    => __( "A Tutor LMS lesson's content changed.", 'pastmark' ),
				'severity'       => Severity::INFO,
				'severity_label' => Severity::resolve_label( Severity::INFO ),
			),
			array(
				'key'            => Actions::LESSON_TRASH,
				'label'          => __( 'Lesson Trash', 'pastmark' ),
				'description'    => __( 'A Tutor LMS lesson was moved to trash.', 'pastmark' ),
				'severity'       => Severity::WARNING,
				'severity_label' => Severity::resolve_label( Severity::WARNING ),
			),
			array(
				'key'            => Actions::LESSON_RESTORE,
				'label'          => __( 'Lesson Restore', 'pastmark' ),
				'description'    => __( 'A Tutor LMS lesson was restored from trash.', 'pastmark' ),
				'severity'       => Severity::INFO,
				'severity_label' => Severity::resolve_label( Severity::INFO ),
			),
			array(
				'key'            => Actions::LESSON_DELETE,
				'label'          => __( 'Lesson Delete', 'pastmark' ),
				'description'    => __( 'A Tutor LMS lesson was permanently deleted.', 'pastmark' ),
				'severity'       => Severity::WARNING,
				'severity_label' => Severity::resolve_label( Severity::WARNING ),
			),
			array(
				'key'            => Actions::COURSE_TOPIC_CREATE,
				'label'          => __( 'Course Topic Create', 'pastmark' ),
				'description'    => __( 'A new Tutor LMS course topic was created.', 'pastmark' ),
				'severity'       => Severity::INFO,
				'severity_label' => Severity::resolve_label( Severity::INFO ),
			),
			array(
				'key'            => Actions::COURSE_TOPIC_UPDATE,
				'label'          => __( 'Course Topic Update', 'pastmark' ),
				'description'    => __( "A Tutor LMS course topic's content changed.", 'pastmark' ),
				'severity'       => Severity::INFO,
				'severity_label' => Severity::resolve_label( Severity::INFO ),
			),
			array(
				'key'            => Actions::COURSE_TOPIC_TRASH,
				'label'          => __( 'Course Topic Trash', 'pastmark' ),
				'description'    => __( 'A Tutor LMS course topic was moved to trash.', 'pastmark' ),
				'severity'       => Severity::WARNING,
				'severity_label' => Severity::resolve_label( Severity::WARNING ),
			),
			array(
				'key'            => Actions::COURSE_TOPIC_RESTORE,
				'label'          => __( 'Course Topic Restore', 'pastmark' ),
				'description'    => __( 'A Tutor LMS course topic was restored from trash.', 'pastmark' ),
				'severity'       => Severity::INFO,
				'severity_label' => Severity::resolve_label( Severity::INFO ),
			),
			array(
				'key'            => Actions::COURSE_TOPIC_DELETE,
				'label'          => __( 'Course Topic Delete', 'pastmark' ),
				'description'    => __( 'A Tutor LMS course topic was permanently deleted.', 'pastmark' ),
				'severity'       => Severity::WARNING,
				'severity_label' => Severity::resolve_label( Severity::WARNING ),
			),
			array(
				'key'            => Actions::QUIZ_CREATE,
				'label'          => __( 'Quiz Create', 'pastmark' ),
				'description'    => __( 'A new Tutor LMS quiz was created.', 'pastmark' ),
				'severity'       => Severity::INFO,
				'severity_label' => Severity::resolve_label( Severity::INFO ),
			),
			array(
				'key'            => Actions::QUIZ_UPDATE,
				'label'          => __( 'Quiz Update', 'pastmark' ),
				'description'    => __( "A Tutor LMS quiz's content/questions/settings changed.", 'pastmark' ),
				'severity'       => Severity::INFO,
				'severity_label' => Severity::resolve_label( Severity::INFO ),
			),
			array(
				'key'            => Actions::QUIZ_TRASH,
				'label'          => __( 'Quiz Trash', 'pastmark' ),
				'description'    => __( 'A Tutor LMS quiz was moved to trash.', 'pastmark' ),
				'severity'       => Severity::WARNING,
				'severity_label' => Severity::resolve_label( Severity::WARNING ),
			),
			array(
				'key'            => Actions::QUIZ_RESTORE,
				'label'          => __( 'Quiz Restore', 'pastmark' ),
				'description'    => __( 'A Tutor LMS quiz was restored from trash.', 'pastmark' ),
				'severity'       => Severity::INFO,
				'severity_label' => Severity::resolve_label( Severity::INFO ),
			),
			array(
				'key'            => Actions::QUIZ_DELETE,
				'label'          => __( 'Quiz Delete', 'pastmark' ),
				'description'    => __( 'A Tutor LMS quiz was permanently deleted.', 'pastmark' ),
				'severity'       => Severity::WARNING,
				'severity_label' => Severity::resolve_label( Severity::WARNING ),
			),
		);

		// ProgressActivityLogger's actions - declared here since this
		// logger owns the whole `tutor-lms` group. See that class' own
		// docblock for the hooks behind each.
		$actions[] = array(
			'key'            => Actions::ENROLLMENT_GRANT,
			'label'          => __( 'Enrollment Grant', 'pastmark' ),
			'description'    => __( "A student's course enrollment was granted.", 'pastmark' ),
			'severity'       => Severity::INFO,
			'severity_label' => Severity::resolve_label( Severity::INFO ),
		);
		$actions[] = array(
			'key'            => Actions::ENROLLMENT_PENDING,
			'label'          => __( 'Enrollment Pending', 'pastmark' ),
			'description'    => __( "A student's course enrollment was created, pending payment.", 'pastmark' ),
			'severity'       => Severity::INFO,
			'severity_label' => Severity::resolve_label( Severity::INFO ),
		);
		$actions[] = array(
			'key'            => Actions::ENROLLMENT_REMOVE,
			'label'          => __( 'Enrollment Remove', 'pastmark' ),
			'description'    => __( "A student's course enrollment was removed.", 'pastmark' ),
			'severity'       => Severity::WARNING,
			'severity_label' => Severity::resolve_label( Severity::WARNING ),
		);
		$actions[] = array(
			'key'            => Actions::LESSON_COMPLETE,
			'label'          => __( 'Lesson Complete', 'pastmark' ),
			'description'    => __( 'A student completed a lesson.', 'pastmark' ),
			'severity'       => Severity::INFO,
			'severity_label' => Severity::resolve_label( Severity::INFO ),
		);
		$actions[] = array(
			'key'            => Actions::QUIZ_COMPLETE,
			'label'          => __( 'Quiz Complete', 'pastmark' ),
			'description'    => __( 'A student completed a quiz attempt.', 'pastmark' ),
			'severity'       => Severity::INFO,
			'severity_label' => Severity::resolve_label( Severity::INFO ),
		);
		$actions[] = array(
			'key'            => Actions::COURSE_COMPLETE,
			'label'          => __( 'Course Complete', 'pastmark' ),
			'description'    => __( 'A student completed an entire course.', 'pastmark' ),
			'severity'       => Severity::INFO,
			'severity_label' => Severity::resolve_label( Severity::INFO ),
		);

		$events[ Events::TUTOR_LMS ] = array(
			'label'   => __( 'Tutor LMS', 'pastmark' ),
			'source'  => 'tutor-lms',
			'actions' => $actions,
		);

		return $events;
	}

	/**
	 * Log a new course/lesson/topic/quiz's creation.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an existing post being updated (vs. a genuine new insert).
	 * @return void
	 */
	public function log_content_created( $post_id, $post, $update ): void {

		if ( $update || ! $post instanceof \WP_Post ) {
			return;
		}

		$type = self::CONTENT_TYPES[ $post->post_type ] ?? null;

		if ( null === $type ) {
			return;
		}

		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		$this->insert_event_log(
			Events::TUTOR_LMS,
			$type['create'],
			array(
				'object_type' => $type['object_type'],
				'object_id'   => $post_id,
				'message'     => sprintf( '%1$s "%2$s" created.', $type['label'], $post->post_title ),
				'after_data'  => $this->encode_content_data( $this->prepare_content_data( $post ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log a course/lesson/topic/quiz edit, diffed via `ContentDiffer`.
	 * Only fires when title/content/parent/menu_order actually changed.
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post_after  Post object after the update.
	 * @param \WP_Post $post_before Post object before the update.
	 * @return void
	 */
	public function log_content_updated( $post_id, $post_after, $post_before ): void {

		if ( ! $post_after instanceof \WP_Post ) {
			return;
		}

		$type = self::CONTENT_TYPES[ $post_after->post_type ] ?? null;

		if ( null === $type ) {
			return;
		}

		if ( 'auto-draft' === $post_after->post_status || 'trash' === $post_after->post_status ) {
			return;
		}

		if ( $post_before->post_title === $post_after->post_title
			&& $post_before->post_content === $post_after->post_content
			&& $post_before->post_parent === $post_after->post_parent
			&& $post_before->menu_order === $post_after->menu_order
		) {
			return;
		}

		$before = $this->prepare_content_data( $post_before );
		$after  = $this->prepare_content_data( $post_after );

		$before_json = $this->encode_content_data( $before );
		$after_json  = $this->encode_content_data( $after );

		$diff = ContentDiffer::diff( $before_json, $after_json );

		$log_data = array(
			'object_type' => $type['object_type'],
			'object_id'   => $post_id,
			'message'     => sprintf( '%1$s "%2$s" updated.', $type['label'], $post_after->post_title ),
			'context'     => $this->get_common_context(),
		);

		if ( null !== $diff ) {
			$log_data['after_data'] = wp_json_encode( array( 'diff' => $diff ) );
		} else {
			$log_data['before_data'] = $before_json;
			$log_data['after_data']  = $after_json;
		}

		$this->insert_event_log( Events::TUTOR_LMS, $type['update'], $log_data );
	}

	/**
	 * Log content moved to trash.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function log_content_trashed( $post_id ): void {

		$this->log_lifecycle_event( (int) $post_id, 'trash', Severity::WARNING, 'moved to trash' );
	}

	/**
	 * Log content restored from trash.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function log_content_restored( $post_id ): void {

		$this->log_lifecycle_event( (int) $post_id, 'restore', Severity::INFO, 'restored from trash' );
	}

	/**
	 * Log content permanently deleted.
	 *
	 * `before_delete_post` fires before the post row is actually removed -
	 * `get_post()` here still returns the content's data.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function log_content_deleted( $post_id ): void {

		$post_id = (int) $post_id;
		$post    = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$type = self::CONTENT_TYPES[ $post->post_type ] ?? null;

		if ( null === $type ) {
			return;
		}

		$this->insert_event_log(
			Events::TUTOR_LMS,
			$type['delete'],
			array(
				'object_type' => $type['object_type'],
				'object_id'   => $post_id,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( '%1$s "%2$s" permanently deleted.', $type['label'], $post->post_title ),
				'before_data' => $this->encode_content_data( $this->prepare_content_data( $post ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Shared trash/restore handler.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $event    'trash' or 'restore' - key into `self::CONTENT_TYPES[ $post_type ]`.
	 * @param string $severity Severity level.
	 * @param string $verb     Message verb ("moved to trash"/"restored from trash").
	 * @return void
	 */
	protected function log_lifecycle_event( int $post_id, string $event, string $severity, string $verb ): void {

		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$type = self::CONTENT_TYPES[ $post->post_type ] ?? null;

		if ( null === $type ) {
			return;
		}

		$this->insert_event_log(
			Events::TUTOR_LMS,
			$type[ $event ],
			array(
				'object_type' => $type['object_type'],
				'object_id'   => $post_id,
				'severity'    => $severity,
				'message'     => sprintf( '%1$s "%2$s" %3$s.', $type['label'], $post->post_title, $verb ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Reduce a course/lesson/topic/quiz `WP_Post` to the fields worth
	 * diffing/storing.
	 *
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	protected function prepare_content_data( \WP_Post $post ): array {

		return array(
			'title'      => $post->post_title,
			'content'    => $post->post_content,
			'status'     => $post->post_status,
			'parent'     => $post->post_parent,
			'menu_order' => $post->menu_order,
		);
	}

	/**
	 * JSON-encode prepared content data for `ContentDiffer`/storage.
	 *
	 * @param array $data Prepared data from `prepare_content_data()`.
	 * @return string
	 */
	protected function encode_content_data( array $data ): string {

		return (string) wp_json_encode( $data );
	}
}
