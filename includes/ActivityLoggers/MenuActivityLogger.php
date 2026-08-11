<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace LogTrail\ActivityLoggers;

use LogTrail\Constants\Severity;
use LogTrail\Constants\Events;
use LogTrail\Constants\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Menu activity logger.
 */
class MenuActivityLogger extends AbstractLogger {

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
				'after_data'  => wp_json_encode( $menu ),
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
				'after_data'  => wp_json_encode( $menu ),
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
				'before_data' => wp_json_encode( $deleted_term ),
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
	 * Log menu item updates.
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
				'after_data'  => wp_json_encode( $menu_item ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'menu_id' => $menu_id,
						'args'    => $args,
					)
				),
			)
		);
	}
}
