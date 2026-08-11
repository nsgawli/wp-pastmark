<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace LogTrail\ActivityLoggers;

use LogTrail\Constants\Severity;
use LogTrail\Constants\Events;
use LogTrail\Constants\Actions;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Media activity logger.
 */
class MediaActivityLogger extends AbstractLogger {

	/**
	 * Meta keys this logger cares about, captured before an update so the
	 * before/after values can be diffed.
	 *
	 * @var string[]
	 */
	private const TRACKED_META_KEYS = array( '_thumbnail_id', '_wp_attachment_image_alt' );

	/**
	 * Post meta values captured before an update, keyed by "{object_id}:{meta_key}".
	 *
	 * @var array<string, mixed>
	 */
	protected $pending_meta_values = array();

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

		add_action( 'add_attachment', $this->guarded( array( $this, 'log_attachment_uploaded' ) ) );

		add_action( 'delete_attachment', $this->guarded( array( $this, 'log_attachment_deleted' ) ), 10, 2 );

		add_action( 'attachment_updated', $this->guarded( array( $this, 'log_attachment_updated' ) ), 10, 3 );

		add_filter( 'update_post_metadata', $this->guarded( array( $this, 'capture_meta_before_update' ) ), 10, 4 );

		add_action( 'added_post_meta', $this->guarded( array( $this, 'log_post_meta_added' ) ), 10, 4 );

		add_action( 'updated_post_meta', $this->guarded( array( $this, 'log_post_meta_updated' ) ), 10, 4 );

		add_action( 'deleted_post_meta', $this->guarded( array( $this, 'log_post_meta_deleted' ) ), 10, 4 );

		add_action( 'add_option_site_icon', $this->guarded( array( $this, 'log_site_icon_added' ) ), 10, 2 );

		add_action( 'update_option_site_icon', $this->guarded( array( $this, 'log_site_icon_updated' ) ), 10, 2 );
	}

	/**
	 * Log a file upload.
	 *
	 * @param int $post_id Attachment ID.
	 * @return void
	 */
	public function log_attachment_uploaded( int $post_id ): void {

		$attachment = get_post( $post_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return;
		}

		$this->insert_event_log(
			Events::MEDIA,
			Actions::CREATE,
			array(
				'object_type' => 'attachment',
				'object_id'   => $post_id,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'File "%s" uploaded.',
					$this->attachment_label( $attachment )
				),
				'after_data'  => wp_json_encode( $this->prepare_attachment_data( $attachment ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'mime_type'      => $attachment->post_mime_type,
						'parent_post_id' => (int) $attachment->post_parent,
					)
				),
			)
		);
	}

	/**
	 * Log a file deletion.
	 *
	 * @param int     $post_id Attachment ID.
	 * @param WP_Post $post Attachment object.
	 * @return void
	 */
	public function log_attachment_deleted( int $post_id, WP_Post $post ): void {

		$this->insert_event_log(
			Events::MEDIA,
			Actions::DELETE,
			array(
				'object_type' => 'attachment',
				'object_id'   => $post_id,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'File "%s" deleted.',
					$this->attachment_label( $post )
				),
				'before_data' => wp_json_encode( $this->prepare_attachment_data( $post ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'mime_type'      => $post->post_mime_type,
						'parent_post_id' => (int) $post->post_parent,
					)
				),
			)
		);
	}

	/**
	 * Log a media title, caption, description or alt text update.
	 *
	 * @param int     $post_id Attachment ID.
	 * @param WP_Post $post_after Attachment object after update.
	 * @param WP_Post $post_before Attachment object before update.
	 * @return void
	 */
	public function log_attachment_updated( int $post_id, WP_Post $post_after, WP_Post $post_before ): void {

		$fields = array(
			'post_title'   => 'title',
			'post_excerpt' => 'caption',
			'post_content' => 'description',
		);

		$before = array();
		$after  = array();

		foreach ( $fields as $property => $label ) {

			if ( $post_before->$property !== $post_after->$property ) {
				$before[ $label ] = $post_before->$property;
				$after[ $label ]  = $post_after->$property;
			}
		}

		$alt_pending_key = $post_id . ':_wp_attachment_image_alt';

		if ( isset( $this->pending_meta_values[ $alt_pending_key ] ) ) {

			$old_alt = $this->pending_meta_values[ $alt_pending_key ];
			unset( $this->pending_meta_values[ $alt_pending_key ] );

			$new_alt = get_post_meta( $post_id, '_wp_attachment_image_alt', true );

			if ( $old_alt !== $new_alt ) {
				$before['alt_text'] = $old_alt;
				$after['alt_text']  = $new_alt;
			}
		}

		if ( empty( $before ) ) {
			return;
		}

		$this->insert_event_log(
			Events::MEDIA,
			Actions::UPDATE,
			array(
				'object_type' => 'attachment',
				'object_id'   => $post_id,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'Media "%s" updated (%s).',
					$this->attachment_label( $post_after ),
					implode( ', ', array_keys( $before ) )
				),
				'before_data' => wp_json_encode( $before ),
				'after_data'  => wp_json_encode( $after ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Capture a tracked post meta value before it's overwritten, for diffing.
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
	public function capture_meta_before_update( $check, $object_id, $meta_key, $meta_value ) {

		if ( ! in_array( $meta_key, self::TRACKED_META_KEYS, true ) ) {
			return $check;
		}

		$this->pending_meta_values[ $object_id . ':' . $meta_key ] = get_post_meta( $object_id, $meta_key, true );

		return $check;
	}

	/**
	 * Log a featured image being set for the first time.
	 *
	 * @param int    $mid Meta ID.
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value New meta value.
	 * @return void
	 */
	public function log_post_meta_added( $mid, $post_id, $meta_key, $meta_value ): void {

		if ( '_thumbnail_id' !== $meta_key ) {
			return;
		}

		$this->log_featured_image_changed( (int) $post_id, '', $meta_value );
	}

	/**
	 * Log a featured image being changed.
	 *
	 * @param int    $meta_id Meta ID.
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value New meta value.
	 * @return void
	 */
	public function log_post_meta_updated( $meta_id, $post_id, $meta_key, $meta_value ): void {

		if ( '_thumbnail_id' !== $meta_key ) {
			return;
		}

		$pending_key = $post_id . ':_thumbnail_id';

		$old_value = $this->pending_meta_values[ $pending_key ] ?? '';

		unset( $this->pending_meta_values[ $pending_key ] );

		if ( (string) $old_value === (string) $meta_value ) {
			return;
		}

		$this->log_featured_image_changed( (int) $post_id, $old_value, $meta_value );
	}

	/**
	 * Log a featured image being removed.
	 *
	 * @param array  $meta_ids Deleted meta IDs.
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value Deleted value.
	 * @return void
	 */
	public function log_post_meta_deleted( $meta_ids, $post_id, $meta_key, $meta_value ): void {

		if ( '_thumbnail_id' !== $meta_key ) {
			return;
		}

		$this->log_featured_image_changed( (int) $post_id, $meta_value, '' );
	}

	/**
	 * Insert a featured image change log entry.
	 *
	 * @param int   $post_id Post ID the featured image belongs to.
	 * @param mixed $old_thumbnail_id Previous attachment ID, or empty.
	 * @param mixed $new_thumbnail_id New attachment ID, or empty.
	 * @return void
	 */
	protected function log_featured_image_changed( int $post_id, $old_thumbnail_id, $new_thumbnail_id ): void {

		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		$old_id = (int) $old_thumbnail_id;
		$new_id = (int) $new_thumbnail_id;

		if ( $old_id === $new_id ) {
			return;
		}

		if ( $new_id && ! $old_id ) {
			$message = sprintf(
				'Featured image set to "%s" for %s "%s".',
				get_the_title( $new_id ),
				$post->post_type,
				$post->post_title
			);
		} elseif ( ! $new_id && $old_id ) {
			$message = sprintf(
				'Featured image removed from %s "%s".',
				$post->post_type,
				$post->post_title
			);
		} else {
			$message = sprintf(
				'Featured image changed from "%s" to "%s" for %s "%s".',
				get_the_title( $old_id ),
				get_the_title( $new_id ),
				$post->post_type,
				$post->post_title
			);
		}

		$this->insert_event_log(
			Events::MEDIA,
			Actions::FEATURED_IMAGE_CHANGE,
			array(
				'object_type' => $post->post_type,
				'object_id'   => $post_id,
				'user_id'     => get_current_user_id(),
				'message'     => $message,
				'before_data' => wp_json_encode( array( 'thumbnail_id' => $old_id ) ),
				'after_data'  => wp_json_encode( array( 'thumbnail_id' => $new_id ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'post_type' => $post->post_type )
				),
			)
		);
	}

	/**
	 * Log the site icon being added for the first time.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value New option value.
	 * @return void
	 */
	public function log_site_icon_added( $option, $value ): void {

		$this->log_site_icon_transition( '', $value );
	}

	/**
	 * Log the site icon being changed or removed.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $value New option value.
	 * @return void
	 */
	public function log_site_icon_updated( $old_value, $value ): void {

		$this->log_site_icon_transition( $old_value, $value );
	}

	/**
	 * Insert a site icon change log entry.
	 *
	 * @param mixed $old_value Previous attachment ID.
	 * @param mixed $value New attachment ID.
	 * @return void
	 */
	protected function log_site_icon_transition( $old_value, $value ): void {

		$old_id = (int) $old_value;
		$new_id = (int) $value;

		if ( $old_id === $new_id ) {
			return;
		}

		if ( $new_id && ! $old_id ) {
			$message = sprintf( 'Site icon set to "%s".', get_the_title( $new_id ) );
		} elseif ( ! $new_id && $old_id ) {
			$message = 'Site icon removed.';
		} else {
			$message = sprintf(
				'Site icon changed from "%s" to "%s".',
				get_the_title( $old_id ),
				get_the_title( $new_id )
			);
		}

		$this->insert_event_log(
			Events::MEDIA,
			Actions::SITE_ICON_CHANGE,
			array(
				'object_type' => 'site_icon',
				'object_id'   => $new_id ? $new_id : $old_id,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => $message,
				'before_data' => wp_json_encode( array( 'site_icon' => $old_id ) ),
				'after_data'  => wp_json_encode( array( 'site_icon' => $new_id ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Prepare attachment data for storage.
	 *
	 * @param WP_Post $attachment Attachment object.
	 * @return array
	 */
	protected function prepare_attachment_data( WP_Post $attachment ): array {

		return array(
			'post_title'     => $attachment->post_title,
			'post_excerpt'   => $attachment->post_excerpt,
			'post_content'   => $attachment->post_content,
			'post_mime_type' => $attachment->post_mime_type,
			'post_parent'    => $attachment->post_parent,
			'guid'           => $attachment->guid,
		);
	}

	/**
	 * Get a human-readable label for an attachment, falling back to the
	 * underlying filename when there's no title.
	 *
	 * @param WP_Post $attachment Attachment object.
	 * @return string
	 */
	protected function attachment_label( WP_Post $attachment ): string {

		if ( '' !== $attachment->post_title ) {
			return $attachment->post_title;
		}

		$file = get_attached_file( $attachment->ID );

		return $file ? basename( $file ) : (string) $attachment->ID;
	}
}
