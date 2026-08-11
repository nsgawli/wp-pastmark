<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace LogTrail\EventSettings;

use LogTrail\Constants\Actions;
use LogTrail\Constants\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Event presets.
 */
class EventPresets {

	/**
	 * Get presets.
	 *
	 * @return array
	 */
	public static function get_presets(): array {

		$all_events = EventRegistry::get_events();

		$complete = array();

		foreach ( $all_events as $event_key => $event ) {

			$complete[ $event_key ] = array();

			foreach ( $event['actions'] as $action ) {

				$complete[ $event_key ][] = $action['key'];
			}
		}

		$essential   = self::build_essential();
		$recommended = self::build_recommended( $complete );

		return array(

			'essential'   => $essential,

			'recommended' => $recommended,

			'complete'    => $complete,
		);
	}

	/**
	 * Build the "essential" preset.
	 *
	 * A hand-picked set of critical security and site-integrity actions:
	 * authentication and account-takeover vectors, code-level changes
	 * (plugins/themes/core), and the highest-impact WooCommerce events.
	 *
	 * @return array
	 */
	private static function build_essential(): array {

		return array(

			Events::AUTHENTICATION => array(
				Actions::LOGIN,
				Actions::LOGOUT,
				Actions::FAILED_LOGIN,
				Actions::SWITCH_USER,
			),

			Events::USER           => array(
				Actions::CREATE,
				Actions::REGISTER,
				Actions::DELETE,
				Actions::ROLE_CHANGE,
				Actions::PASSWORD_CHANGE,
				Actions::EMAIL_CHANGE,
				Actions::SUPER_ADMIN_CHANGE,
				Actions::APP_PASSWORD_CREATE,
				Actions::APP_PASSWORD_REVOKE,
			),

			Events::CONTENT        => array(
				Actions::DELETE,
				Actions::VISIBILITY_CHANGE,
			),

			Events::PLUGIN         => array(
				Actions::INSTALL,
				Actions::ACTIVATE,
				Actions::DEACTIVATE,
				Actions::DELETE,
			),

			Events::THEME          => array(
				Actions::INSTALL,
				Actions::SWITCH,
				Actions::DELETE,
				Actions::FILE_EDIT,
			),

			Events::SETTINGS       => array(
				Actions::UPDATE,
				Actions::CORE_UPDATE,
			),

			Events::WOOCOMMERCE    => array(
				Actions::ORDER_PLACED,
				Actions::ORDER_REFUND,
				Actions::ORDER_DELETE,
				Actions::PRODUCT_DELETE,
				Actions::COUPON_DELETE,
			),
		);
	}

	/**
	 * Build the "recommended" preset from the complete action list.
	 *
	 * Recommended is the complete list minus fine-grained, high-volume
	 * actions (e.g. per-field content edits, granular WooCommerce stock
	 * bookkeeping) that are mainly useful for deep forensic auditing and
	 * would otherwise flood the log for a typical site. Those actions
	 * remain available under the "complete" preset.
	 *
	 * @param array $complete Complete preset, event key => action keys.
	 * @return array
	 */
	private static function build_recommended( array $complete ): array {

		$complete_only = array(

			Events::USER        => array(
				Actions::USER_META_ADD,
				Actions::USER_META_UPDATE,
				Actions::USER_META_DELETE,
			),

			Events::CONTENT     => array(
				Actions::SLUG_CHANGE,
				Actions::DATE_CHANGE,
				Actions::STICKY_CHANGE,
				Actions::PARENT_CHANGE,
				Actions::TEMPLATE_CHANGE,
				Actions::CONTENT_CHANGE,
			),

			Events::COMMENT     => array(
				Actions::UPDATE,
				Actions::RESTORE,
			),

			Events::MEDIA       => array(
				Actions::UPDATE,
				Actions::FEATURED_IMAGE_CHANGE,
			),

			Events::MENU        => array(
				Actions::UPDATE,
				Actions::ITEM_UPDATE,
			),

			Events::WIDGET      => array(
				Actions::UPDATE,
			),

			Events::WOOCOMMERCE => array(
				Actions::PRODUCT_RESTORE,
				Actions::PRODUCT_RENAME,
				Actions::PRODUCT_CATEGORY_CHANGE,
				Actions::PRODUCT_SKU_CHANGE,
				Actions::PRODUCT_STOCK_STATUS_CHANGE,
				Actions::PRODUCT_STOCK_QTY_CHANGE,
				Actions::PRODUCT_STOCK_AUTO_CHANGE,
				Actions::PRODUCT_CATEGORY_CREATE,
				Actions::ORDER_RESTORE,
				Actions::ORDER_NOTE_ADD,
				Actions::ORDER_NOTE_DELETE,
				Actions::COUPON_RENAME,
				Actions::COUPON_TRASH,
				Actions::COUPON_RESTORE,
				Actions::REVIEW_CREATE,
				Actions::REVIEW_APPROVE,
				Actions::REVIEW_UNAPPROVE,
				Actions::REVIEW_TRASH,
			),
		);

		$recommended = array();

		foreach ( $complete as $event_key => $action_keys ) {

			$excluded = $complete_only[ $event_key ] ?? array();

			$recommended[ $event_key ] = array_values(
				array_diff( $action_keys, $excluded )
			);
		}

		return $recommended;
	}
}
