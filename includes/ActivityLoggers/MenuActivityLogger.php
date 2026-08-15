<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers;

use Pastmark\Constants\Severity;
use Pastmark\Constants\Events;
use Pastmark\Constants\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Menu activity logger.
 */
class MenuActivityLogger extends AbstractLogger {

	/**
	 * `_menu_item_*` postmeta keys tracked for the menu item diff, mapped
	 * to the field name used in `prepare_menu_item_data()`.
	 *
	 * @var array<string, string>
	 */
	private const TRACKED_MENU_ITEM_META_KEYS = array(
		'_menu_item_url'              => 'url',
		'_menu_item_object'           => 'object',
		'_menu_item_object_id'        => 'object_id',
		'_menu_item_menu_item_parent' => 'menu_item_parent',
	);

	/**
	 * Menu name + slug captured before an update, keyed by menu (term) ID.
	 *
	 * `wp_update_nav_menu` fires after the term row is already saved, so
	 * the previous name/slug has to be captured earlier - via the generic
	 * `edit_terms` action, which fires for every taxonomy - to allow a
	 * before/after diff. Both fields are captured (not just name) so
	 * `before_data`/`after_data` always carry the same key set as
	 * `prepare_menu_data()` - a before_data missing a key that after_data
	 * has renders as a false "added" change in the admin UI's diff table.
	 *
	 * @var array<int, array{name: string, slug: string}>
	 */
	protected $pending_menu_names = array();

	/**
	 * Menu item field values captured before an update, keyed by menu
	 * item (post) ID, then by the field name from `prepare_menu_item_data()`.
	 *
	 * `wp_update_nav_menu_item` fires only after the item's post row AND
	 * all its `_menu_item_*` postmeta are already saved, so every tracked
	 * field's previous value has to be captured earlier - core columns via
	 * `wp_insert_post_data`, postmeta via `update_post_metadata` - to allow
	 * a before/after diff.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected $pending_menu_item_values = array();

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

		add_action(
			'wp_create_nav_menu',
			$this->guarded( array( $this, 'log_menu_created' ) ),
			10,
			2
		);

		add_action(
			'edit_terms',
			$this->guarded( array( $this, 'capture_menu_name_before_update' ) ),
			10,
			2
		);

		add_action(
			'wp_update_nav_menu',
			$this->guarded( array( $this, 'log_menu_updated' ) ),
			10,
			2
		);

		add_action(
			'delete_nav_menu',
			$this->guarded( array( $this, 'log_menu_deleted' ) ),
			10,
			3
		);

		add_filter(
			'wp_insert_post_data',
			$this->guarded( array( $this, 'capture_menu_item_core_before_update' ) ),
			10,
			2
		);

		add_filter(
			'update_post_metadata',
			$this->guarded( array( $this, 'capture_menu_item_meta_before_update' ) ),
			10,
			5
		);

		add_action(
			'wp_update_nav_menu_item',
			$this->guarded( array( $this, 'log_menu_item_updated' ) ),
			10,
			3
		);
	}

	/**
	 * Log menu creation.
	 *
	 * @param int   $menu_id Menu ID.
	 * @param array $menu_data Menu data.
	 * @return void
	 */
	public function log_menu_created(
		int $menu_id,
		array $menu_data
	): void {

		$menu = wp_get_nav_menu_object( $menu_id );

		if ( ! $menu ) {
			return;
		}

		$this->insert_event_log(
			Events::MENU,
			Actions::CREATE,
			array(
				'object_type' => 'nav_menu',
				'object_id'   => $menu_id,
				'user_id'     => get_current_user_id(),
				'action'      => Actions::CREATE,
				'message'     => sprintf(
					'Menu "%s" created.',
					$menu->name
				),
				'after_data'  => wp_json_encode( $this->prepare_menu_data( $menu ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'menu_id' => $menu_id,
					)
				),
			)
		);
	}

	/**
	 * Capture a term's name before it's overwritten, for diffing.
	 *
	 * Fires for every taxonomy's `wp_update_term()` call, so filters down
	 * to nav menus - which are just terms in the `nav_menu` taxonomy -
	 * before recording anything.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return void
	 */
	public function capture_menu_name_before_update( $term_id, $taxonomy ): void {

		if ( 'nav_menu' !== $taxonomy ) {
			return;
		}

		$menu = wp_get_nav_menu_object( $term_id );

		if ( $menu ) {
			$this->pending_menu_names[ $term_id ] = $this->prepare_menu_data( $menu );
		}
	}

	/**
	 * Log menu updates.
	 *
	 * @param int   $menu_id Menu ID.
	 * @param array $menu_data Menu data.
	 * @return void
	 */
	public function log_menu_updated(
		int $menu_id,
		array $menu_data
	): void {

		$menu = wp_get_nav_menu_object( $menu_id );

		if ( ! $menu ) {
			return;
		}

		$after_data = $this->prepare_menu_data( $menu );

		$before_data = $this->pending_menu_names[ $menu_id ] ?? $after_data;

		unset( $this->pending_menu_names[ $menu_id ] );

		$this->insert_event_log(
			Events::MENU,
			Actions::UPDATE,
			array(
				'object_type' => 'nav_menu',
				'object_id'   => $menu_id,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'Menu "%s" updated.',
					$menu->name
				),
				// Always the same field set (name + slug) on both sides -
				// the admin UI's diff table already drops rows where
				// before/after match, so there's no need to pre-filter here.
				'before_data' => wp_json_encode( $before_data ),
				'after_data'  => wp_json_encode( $after_data ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'menu_id' => $menu_id,
					)
				),
			)
		);
	}

	/**
	 * Log menu deletion.
	 *
	 * @param int    $term_id Term ID.
	 * @param int    $tt_id TT ID.
	 * @param object $deleted_term Deleted term.
	 * @return void
	 */
	public function log_menu_deleted(
		int $term_id,
		int $tt_id,
		$deleted_term
	): void {

		$this->insert_event_log(
			Events::MENU,
			Actions::DELETE,
			array(
				'object_type' => 'nav_menu',
				'object_id'   => $term_id,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'Menu "%s" deleted.',
					$deleted_term->name
				),
				'before_data' => wp_json_encode( $this->prepare_menu_data( $deleted_term ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'menu_id' => $term_id,
					)
				),
				'severity'    => Severity::WARNING,
			)
		);
	}

	/**
	 * Capture a menu item's core columns (title, order) before they're
	 * overwritten, for diffing.
	 *
	 * `wp_update_nav_menu_item()` always re-saves every item in the menu
	 * on every "Save Menu" click, not just the ones actually touched -
	 * this filter fires for all of them, but `log_menu_item_updated()`
	 * only logs the ones where something genuinely changed.
	 *
	 * Must return `$data` unmodified so the actual post save isn't
	 * short-circuited.
	 *
	 * @param array $data Sanitized post data about to be saved.
	 * @param array $postarr Raw post data, including the post ID being updated.
	 * @return array
	 */
	public function capture_menu_item_core_before_update( $data, $postarr ) {

		if ( 'nav_menu_item' !== ( $data['post_type'] ?? '' ) ) {
			return $data;
		}

		$item_id = (int) ( $postarr['ID'] ?? 0 );

		if ( ! $item_id ) {
			return $data;
		}

		$existing = get_post( $item_id );

		if ( $existing ) {
			$this->pending_menu_item_values[ $item_id ]['title']      = $existing->post_title;
			$this->pending_menu_item_values[ $item_id ]['menu_order'] = (int) $existing->menu_order;
		}

		return $data;
	}

	/**
	 * Capture a menu item's `_menu_item_*` postmeta before it's
	 * overwritten, for diffing.
	 *
	 * Must return `$check` unmodified so the actual meta save isn't
	 * short-circuited.
	 *
	 * @param mixed  $check Whether to short-circuit the meta update.
	 * @param int    $object_id Post (menu item) ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value New meta value.
	 * @param mixed  $prev_value Previous value to match, if any.
	 * @return mixed
	 */
	public function capture_menu_item_meta_before_update( $check, $object_id, $meta_key, $meta_value, $prev_value ) {

		if ( ! isset( self::TRACKED_MENU_ITEM_META_KEYS[ $meta_key ] ) ) {
			return $check;
		}

		$field = self::TRACKED_MENU_ITEM_META_KEYS[ $meta_key ];

		$this->pending_menu_item_values[ $object_id ][ $field ] = get_post_meta( $object_id, $meta_key, true );

		return $check;
	}

	/**
	 * Log menu item updates.
	 *
	 * Since every item in a menu gets re-saved on every "Save Menu" click
	 * (see `capture_menu_item_core_before_update()`), this only logs an
	 * entry for items where a tracked field actually changed - otherwise
	 * moving one item would produce a noisy log entry for every other
	 * untouched item in the same menu.
	 *
	 * @param int   $menu_id Menu ID.
	 * @param int   $menu_item_db_id Menu item ID.
	 * @param array $args Arguments.
	 * @return void
	 */
	public function log_menu_item_updated(
		int $menu_id,
		int $menu_item_db_id,
		array $args
	): void {

		$menu_item = wp_setup_nav_menu_item(
			get_post( $menu_item_db_id )
		);

		if ( ! $menu_item ) {
			return;
		}

		$before_snapshot = $this->pending_menu_item_values[ $menu_item_db_id ] ?? array();

		unset( $this->pending_menu_item_values[ $menu_item_db_id ] );

		$after_data = $this->prepare_menu_item_data( $menu_item );

		$changed = false;

		foreach ( $before_snapshot as $field => $old_value ) {

			$new_value = $after_data[ $field ] ?? null;

			if ( (string) $old_value !== (string) $new_value ) {
				$changed = true;
				break;
			}
		}

		// Nothing captured (e.g. called directly by another plugin,
		// bypassing the usual save flow) is treated as "unknown before
		// state" rather than "nothing changed" - still log it, just
		// without a diff. Otherwise, no tracked field actually changed,
		// so skip logging this item entirely.
		if ( ! empty( $before_snapshot ) && ! $changed ) {
			return;
		}

		$this->insert_event_log(
			Events::MENU,
			Actions::ITEM_UPDATE,
			array(
				'object_type' => 'nav_menu_item',
				'object_id'   => $menu_item_db_id,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'Menu item "%s" updated.',
					$menu_item->title
				),
				// Send the full field set on both sides (falling back to
				// the after value for anything not captured), keyed the
				// same as $after_data - pre-filtering before_data down to
				// only the changed fields left it missing keys that
				// after_data still had, which rendered every untouched
				// field as a false "changed" row in the admin UI's diff
				// table (e.g. moving one item falsely showed title/url/
				// object as changed too). The diff table already drops
				// rows where before/after match, so no pre-filtering is
				// needed here.
				'before_data' => ! empty( $before_snapshot ) ? wp_json_encode( array_merge( $after_data, $before_snapshot ) ) : '',
				'after_data'  => wp_json_encode( $after_data ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'menu_id' => $menu_id,
					)
				),
			)
		);
	}

	/**
	 * Prepare menu data for storage - just the fields worth showing in
	 * the details view, not the full `WP_Term` object (count, taxonomy,
	 * filter, term_group, ... are WordPress-internal noise here).
	 *
	 * @param \WP_Term $menu Menu term object.
	 * @return array
	 */
	protected function prepare_menu_data( $menu ): array {

		return array(
			'name' => $menu->name,
			'slug' => $menu->slug,
		);
	}

	/**
	 * Prepare menu item data for storage - just the fields worth showing
	 * in the details view, not the full nav-menu-item object (which is a
	 * `WP_Post` with dozens of properties, most of them irrelevant here).
	 *
	 * @param object $menu_item Nav menu item object from `wp_setup_nav_menu_item()`.
	 * @return array
	 */
	protected function prepare_menu_item_data( $menu_item ): array {

		return array(
			'title'            => $menu_item->title,
			'url'              => $menu_item->url,
			'object'           => $menu_item->object,
			'object_id'        => (int) $menu_item->object_id,
			'menu_item_parent' => (int) $menu_item->menu_item_parent,
			'menu_order'       => (int) $menu_item->menu_order,
		);
	}
}
