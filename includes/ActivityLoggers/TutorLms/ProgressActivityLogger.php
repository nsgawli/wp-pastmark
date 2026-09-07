<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\TutorLms;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Logs Tutor LMS student progress - enrollment (granted/pending/removed)
 * and completion (lesson/quiz/course) (PM-176, Sprint 12).
 *
 * No `register_events()` here - `CourseContentActivityLogger` (constructed
 * first) already declares the shared `tutor-lms` event group this
 * logger's actions belong to.
 *
 * **Enrollment is genuinely two hooks with an overlap, worked out by
 * reading `EnrollmentModel::do_enroll()` end to end (Tutor LMS 4.0.7,
 * this dev environment), not assumed**:
 * - `tutor_after_enroll( $course_id, $enrolled_id )` fires unconditionally
 *   right after the `tutor_enrolled` post is inserted, whether its status
 *   ends up `pending` (course requires purchase, not yet paid) or
 *   `completed` (free course, or already-paid).
 * - `tutor_after_enrolled( $course_id, $user_id, $enrolled_id )` fires
 *   immediately after that, in the *same* call, **only** when the status
 *   is `completed` - and separately, later, when a pending enrollment's
 *   order is confirmed (`classes/WooCommerce.php`/`ecommerce/
 *   HooksHandler.php` both call it directly on order completion, bypassing
 *   `do_enroll()`/`tutor_after_enroll` entirely since the enrollment
 *   record already exists).
 * So for the common "free course, immediate access" case, **both** fire
 * in the same request - logging both as "enrolled" would double-log every
 * such enrollment. Fixed by picking one meaning per hook instead: this
 * logger treats `tutor_after_enrolled` as the single source of truth for
 * "the student now has access" (`ENROLLMENT_GRANT`) - correct for both
 * the immediate and the confirmed-later case - and `tutor_after_enroll`
 * only logs when the enrollment it just created is genuinely `pending`
 * (checked via `get_post_status( $enrolled_id )` at hook time, which
 * already reflects the just-completed insert), producing the distinct,
 * lower-severity `ENROLLMENT_PENDING` instead.
 *
 * `tutor_after_enrollment_deleted`/`tutor_after_enrollment_cancelled`
 * (`Utils::cancel_course_enrol()`) both map to `ENROLLMENT_REMOVE` -
 * distinguished only by whether the enrollment record was fully deleted
 * or left in a `cancelled` status, noted in the message/context rather
 * than as separate actions (the AC only asks for one "removed" event).
 *
 * **Completion hooks, each confirmed to have exactly one real call site
 * (no duplicate-firing risk)**:
 * - `tutor_mark_lesson_complete_after( $post_id, $user_id )`
 *   (`models/LessonModel.php`) - used instead of the sibling
 *   `tutor_lesson_completed_after` (`classes/Lesson.php`), which is fired
 *   *from* the same AJAX handler that calls `mark_lesson_complete()` -
 *   i.e. both fire for the standard "mark complete" flow. The model-level
 *   hook is used since it fires for *any* caller of
 *   `LessonModel::mark_lesson_complete()`, not only the one AJAX handler
 *   that also happens to fire the other.
 * - `tutor_quiz_finished( $attempt_id, $quiz_id, $user_id )`
 *   (`classes/Quiz.php::finishing_quiz_attempt()`).
 * - `tutor_course_complete_after( $course_id, $user_id )`
 *   (`models/CourseModel.php::mark_course_as_completed()`).
 *
 * **No topic-completion event exists, and none is implemented here -
 * confirmed by reading the real source, not silently dropped**: Tutor LMS
 * has no independent "topic marked complete" state at all. A topic's
 * completion percentage (`Utils::count_completed_contents_by_topic()`) is
 * purely a computed rollup of its lessons/quizzes' own completion - there
 * is no `do_action()` anywhere in the plugin for a topic itself becoming
 * "complete", so there's no real event this logger could hook for it.
 */
class ProgressActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'tutor-lms';

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

		add_action( 'tutor_after_enroll', $this->guarded( array( $this, 'log_enrollment_created' ) ), 10, 2 );
		add_action( 'tutor_after_enrolled', $this->guarded( array( $this, 'log_enrollment_granted' ) ), 10, 3 );
		add_action( 'tutor_after_enrollment_deleted', $this->guarded( array( $this, 'log_enrollment_deleted' ) ), 10, 2 );
		add_action( 'tutor_after_enrollment_cancelled', $this->guarded( array( $this, 'log_enrollment_cancelled' ) ), 10, 2 );
		add_action( 'tutor_mark_lesson_complete_after', $this->guarded( array( $this, 'log_lesson_completed' ) ), 10, 2 );
		add_action( 'tutor_quiz_finished', $this->guarded( array( $this, 'log_quiz_completed' ) ), 10, 3 );
		add_action( 'tutor_course_complete_after', $this->guarded( array( $this, 'log_course_completed' ) ), 10, 2 );
	}

	/**
	 * Log a pending (awaiting payment) enrollment - skips if the
	 * enrollment `tutor_after_enrolled` already covered (same request) is
	 * actually `completed`. See class docblock.
	 *
	 * @param int $course_id  Course ID.
	 * @param int $enrolled_id Enrollment post ID.
	 * @return void
	 */
	public function log_enrollment_created( $course_id, $enrolled_id ): void {

		$enrolled_id = (int) $enrolled_id;

		if ( 'pending' !== get_post_status( $enrolled_id ) ) {
			// Completed - already covered by log_enrollment_granted(),
			// fired from the same do_enroll() call.
			return;
		}

		$user_id = (int) get_post_field( 'post_author', $enrolled_id );
		$course  = get_post( (int) $course_id );
		$student = $user_id ? get_userdata( $user_id ) : false;

		$this->insert_event_log(
			Events::TUTOR_LMS,
			Actions::ENROLLMENT_PENDING,
			array(
				'object_type' => 'course',
				'object_id'   => (int) $course_id,
				'user_id'     => $user_id,
				'message'     => sprintf(
					'Enrollment created for "%1$s" in course "%2$s" - pending payment.',
					$student ? $student->user_login : sprintf( 'user #%d', $user_id ),
					$course instanceof \WP_Post ? $course->post_title : sprintf( 'course #%d', (int) $course_id )
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'student_id'    => $user_id,
						'student_login' => $student ? $student->user_login : '',
						'enrollment_id' => $enrolled_id,
					)
				),
			)
		);
	}

	/**
	 * Log a student's enrollment being granted (active access).
	 *
	 * @param int $course_id  Course ID.
	 * @param int $user_id    Student user ID.
	 * @param int $enrolled_id Enrollment post ID.
	 * @return void
	 */
	public function log_enrollment_granted( $course_id, $user_id, $enrolled_id ): void {

		$user_id = (int) $user_id;
		$course  = get_post( (int) $course_id );
		$student = get_userdata( $user_id );

		$this->insert_event_log(
			Events::TUTOR_LMS,
			Actions::ENROLLMENT_GRANT,
			array(
				'object_type' => 'course',
				'object_id'   => (int) $course_id,
				'user_id'     => $user_id,
				'message'     => sprintf(
					'Student "%1$s" enrolled in course "%2$s".',
					$student ? $student->user_login : sprintf( 'user #%d', $user_id ),
					$course instanceof \WP_Post ? $course->post_title : sprintf( 'course #%d', (int) $course_id )
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'student_id'    => $user_id,
						'student_login' => $student ? $student->user_login : '',
						'enrollment_id' => (int) $enrolled_id,
					)
				),
			)
		);
	}

	/**
	 * Log an enrollment being fully deleted.
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   Student user ID.
	 * @return void
	 */
	public function log_enrollment_deleted( $course_id, $user_id ): void {

		$this->log_enrollment_removed( (int) $course_id, (int) $user_id, 'deleted' );
	}

	/**
	 * Log an enrollment being cancelled (status changed, record kept).
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   Student user ID.
	 * @return void
	 */
	public function log_enrollment_cancelled( $course_id, $user_id ): void {

		$this->log_enrollment_removed( (int) $course_id, (int) $user_id, 'cancelled' );
	}

	/**
	 * Shared handler for enrollment deletion/cancellation.
	 *
	 * @param int    $course_id Course ID.
	 * @param int    $user_id   Student user ID.
	 * @param string $verb      'deleted' or 'cancelled', for the message/context.
	 * @return void
	 */
	protected function log_enrollment_removed( int $course_id, int $user_id, string $verb ): void {

		$course  = get_post( $course_id );
		$student = get_userdata( $user_id );

		$this->insert_event_log(
			Events::TUTOR_LMS,
			Actions::ENROLLMENT_REMOVE,
			array(
				'object_type' => 'course',
				'object_id'   => $course_id,
				'user_id'     => $user_id,
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'Enrollment %1$s for student "%2$s" in course "%3$s".',
					$verb,
					$student ? $student->user_login : sprintf( 'user #%d', $user_id ),
					$course instanceof \WP_Post ? $course->post_title : sprintf( 'course #%d', $course_id )
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'student_id'    => $user_id,
						'student_login' => $student ? $student->user_login : '',
						'removal_type'  => $verb,
					)
				),
			)
		);
	}

	/**
	 * Log a student completing a lesson.
	 *
	 * @param int $post_id Lesson post ID.
	 * @param int $user_id Student user ID.
	 * @return void
	 */
	public function log_lesson_completed( $post_id, $user_id ): void {

		$post_id = (int) $post_id;
		$user_id = (int) $user_id;
		$lesson  = get_post( $post_id );
		$student = get_userdata( $user_id );

		$this->insert_event_log(
			Events::TUTOR_LMS,
			Actions::LESSON_COMPLETE,
			array(
				'object_type' => 'lesson',
				'object_id'   => $post_id,
				'user_id'     => $user_id,
				'message'     => sprintf(
					'Student "%1$s" completed lesson "%2$s".',
					$student ? $student->user_login : sprintf( 'user #%d', $user_id ),
					$lesson instanceof \WP_Post ? $lesson->post_title : sprintf( 'lesson #%d', $post_id )
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'student_id'    => $user_id,
						'student_login' => $student ? $student->user_login : '',
					)
				),
			)
		);
	}

	/**
	 * Log a student finishing a quiz attempt.
	 *
	 * @param int $attempt_id Quiz attempt ID.
	 * @param int $quiz_id    Quiz post ID.
	 * @param int $user_id    Student user ID.
	 * @return void
	 */
	public function log_quiz_completed( $attempt_id, $quiz_id, $user_id ): void {

		$quiz_id = (int) $quiz_id;
		$user_id = (int) $user_id;
		$quiz    = get_post( $quiz_id );
		$student = get_userdata( $user_id );

		$this->insert_event_log(
			Events::TUTOR_LMS,
			Actions::QUIZ_COMPLETE,
			array(
				'object_type' => 'quiz',
				'object_id'   => $quiz_id,
				'user_id'     => $user_id,
				'message'     => sprintf(
					'Student "%1$s" completed quiz "%2$s".',
					$student ? $student->user_login : sprintf( 'user #%d', $user_id ),
					$quiz instanceof \WP_Post ? $quiz->post_title : sprintf( 'quiz #%d', $quiz_id )
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'student_id'    => $user_id,
						'student_login' => $student ? $student->user_login : '',
						'attempt_id'    => (int) $attempt_id,
					)
				),
			)
		);
	}

	/**
	 * Log a student completing an entire course.
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   Student user ID.
	 * @return void
	 */
	public function log_course_completed( $course_id, $user_id ): void {

		$course_id = (int) $course_id;
		$user_id   = (int) $user_id;
		$course    = get_post( $course_id );
		$student   = get_userdata( $user_id );

		$this->insert_event_log(
			Events::TUTOR_LMS,
			Actions::COURSE_COMPLETE,
			array(
				'object_type' => 'course',
				'object_id'   => $course_id,
				'user_id'     => $user_id,
				'message'     => sprintf(
					'Student "%1$s" completed course "%2$s".',
					$student ? $student->user_login : sprintf( 'user #%d', $user_id ),
					$course instanceof \WP_Post ? $course->post_title : sprintf( 'course #%d', $course_id )
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'student_id'    => $user_id,
						'student_login' => $student ? $student->user_login : '',
					)
				),
			)
		);
	}
}
