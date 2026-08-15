<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Action constants.
 */
class Actions {

	/**
	 * Create action.
	 */
	public const CREATE = 'create';

	/**
	 * Update action.
	 */
	public const UPDATE = 'update';

	/**
	 * Delete action.
	 */
	public const DELETE = 'delete';

	/**
	 * Restore action.
	 */
	public const RESTORE = 'restore';

	/**
	 * Login action.
	 */
	public const LOGIN = 'login';

	/**
	 * Logout action.
	 */
	public const LOGOUT = 'logout';

	/**
	 * Failed login action.
	 */
	public const FAILED_LOGIN = 'failed_login';

	/**
	 * Switch user action.
	 */
	public const SWITCH_USER = 'switch_user';

	/**
	 * Register action.
	 */
	public const REGISTER = 'register';

	/**
	 * Role change action.
	 */
	public const ROLE_CHANGE = 'role_change';

	/**
	 * Status change action.
	 */
	public const STATUS_CHANGE = 'status_change';

	/**
	 * Activate action.
	 */
	public const ACTIVATE = 'activate';

	/**
	 * Deactivate action.
	 */
	public const DEACTIVATE = 'deactivate';

	/**
	 * Switch action.
	 */
	public const SWITCH = 'switch';

	/**
	 * Item update action.
	 */
	public const ITEM_UPDATE = 'item_update';

	/**
	 * Update check action.
	 */
	public const UPDATE_CHECK = 'update_check';

	/**
	 * Author/ownership change action.
	 */
	public const AUTHOR_CHANGE = 'author_change';

	/**
	 * Slug/URL change action.
	 */
	public const SLUG_CHANGE = 'slug_change';

	/**
	 * Visibility change action.
	 */
	public const VISIBILITY_CHANGE = 'visibility_change';

	/**
	 * Date change action.
	 */
	public const DATE_CHANGE = 'date_change';

	/**
	 * Sticky change action.
	 */
	public const STICKY_CHANGE = 'sticky_change';

	/**
	 * Parent change action.
	 */
	public const PARENT_CHANGE = 'parent_change';

	/**
	 * Template change action.
	 */
	public const TEMPLATE_CHANGE = 'template_change';

	/**
	 * Content change action.
	 */
	public const CONTENT_CHANGE = 'content_change';

	/**
	 * Reply action.
	 */
	public const REPLY = 'reply';

	/**
	 * Spam status change action.
	 */
	public const SPAM_CHANGE = 'spam_change';

	/**
	 * Password change action.
	 */
	public const PASSWORD_CHANGE = 'password_change';

	/**
	 * Email change action.
	 */
	public const EMAIL_CHANGE = 'email_change';

	/**
	 * Password reset sent action.
	 */
	public const PASSWORD_RESET_SENT = 'password_reset_sent';

	/**
	 * Super Admin change action.
	 */
	public const SUPER_ADMIN_CHANGE = 'super_admin_change';

	/**
	 * Added to site action.
	 */
	public const ADD_TO_SITE = 'add_to_site';

	/**
	 * Removed from site action.
	 */
	public const REMOVE_FROM_SITE = 'remove_from_site';

	/**
	 * User meta field added action.
	 */
	public const USER_META_ADD = 'user_meta_add';

	/**
	 * User meta field updated action.
	 */
	public const USER_META_UPDATE = 'user_meta_update';

	/**
	 * User meta field deleted action.
	 */
	public const USER_META_DELETE = 'user_meta_delete';

	/**
	 * Application password created action.
	 */
	public const APP_PASSWORD_CREATE = 'app_password_create';

	/**
	 * Application password revoked action.
	 */
	public const APP_PASSWORD_REVOKE = 'app_password_revoke';

	/**
	 * Featured image change action.
	 */
	public const FEATURED_IMAGE_CHANGE = 'featured_image_change';

	/**
	 * Site icon change action.
	 */
	public const SITE_ICON_CHANGE = 'site_icon_change';

	/**
	 * Install action.
	 */
	public const INSTALL = 'install';

	/**
	 * Failed install action.
	 */
	public const INSTALL_FAILED = 'install_failed';

	/**
	 * Automatic update setting change action.
	 */
	public const AUTO_UPDATE_CHANGE = 'auto_update_change';

	/**
	 * File edit action.
	 */
	public const FILE_EDIT = 'file_edit';

	/**
	 * WordPress core update action.
	 */
	public const CORE_UPDATE = 'core_update';

	/**
	 * Product created (draft) action.
	 */
	public const PRODUCT_CREATE = 'product_create';

	/**
	 * Product published action.
	 */
	public const PRODUCT_PUBLISH = 'product_publish';

	/**
	 * Product moved to trash action.
	 */
	public const PRODUCT_TRASH = 'product_trash';

	/**
	 * Product permanently deleted action.
	 */
	public const PRODUCT_DELETE = 'product_delete';

	/**
	 * Product restored from trash action.
	 */
	public const PRODUCT_RESTORE = 'product_restore';

	/**
	 * Product status change action.
	 */
	public const PRODUCT_STATUS_CHANGE = 'product_status_change';

	/**
	 * Product renamed action.
	 */
	public const PRODUCT_RENAME = 'product_rename';

	/**
	 * Product category change action.
	 */
	public const PRODUCT_CATEGORY_CHANGE = 'product_category_change';

	/**
	 * Product catalog visibility change action.
	 */
	public const PRODUCT_VISIBILITY_CHANGE = 'product_visibility_change';

	/**
	 * Product SKU change action.
	 */
	public const PRODUCT_SKU_CHANGE = 'product_sku_change';

	/**
	 * Product price change action.
	 */
	public const PRODUCT_PRICE_CHANGE = 'product_price_change';

	/**
	 * Product stock status change action.
	 */
	public const PRODUCT_STOCK_STATUS_CHANGE = 'product_stock_status_change';

	/**
	 * Product stock quantity change action (manual, via dashboard).
	 */
	public const PRODUCT_STOCK_QTY_CHANGE = 'product_stock_qty_change';

	/**
	 * Product stock quantity change action (automated, e.g. order or plugin driven).
	 */
	public const PRODUCT_STOCK_AUTO_CHANGE = 'product_stock_auto_change';

	/**
	 * Product category created action.
	 */
	public const PRODUCT_CATEGORY_CREATE = 'product_category_create';

	/**
	 * Product category deleted action.
	 */
	public const PRODUCT_CATEGORY_DELETE = 'product_category_delete';

	/**
	 * Order placed action.
	 */
	public const ORDER_PLACED = 'order_placed';

	/**
	 * Order status change action.
	 */
	public const ORDER_STATUS_CHANGE = 'order_status_change';

	/**
	 * Order moved to trash action.
	 */
	public const ORDER_TRASH = 'order_trash';

	/**
	 * Order restored from trash action.
	 */
	public const ORDER_RESTORE = 'order_restore';

	/**
	 * Order permanently deleted action.
	 */
	public const ORDER_DELETE = 'order_delete';

	/**
	 * Order edited action.
	 */
	public const ORDER_EDIT = 'order_edit';

	/**
	 * Order refunded action.
	 */
	public const ORDER_REFUND = 'order_refund';

	/**
	 * Order note added action.
	 */
	public const ORDER_NOTE_ADD = 'order_note_add';

	/**
	 * Order note deleted action.
	 */
	public const ORDER_NOTE_DELETE = 'order_note_delete';

	/**
	 * Coupon created action.
	 */
	public const COUPON_CREATE = 'coupon_create';

	/**
	 * Coupon amount change action.
	 */
	public const COUPON_AMOUNT_CHANGE = 'coupon_amount_change';

	/**
	 * Coupon status change action.
	 */
	public const COUPON_STATUS_CHANGE = 'coupon_status_change';

	/**
	 * Coupon renamed action.
	 */
	public const COUPON_RENAME = 'coupon_rename';

	/**
	 * Coupon moved to trash action.
	 */
	public const COUPON_TRASH = 'coupon_trash';

	/**
	 * Coupon restored from trash action.
	 */
	public const COUPON_RESTORE = 'coupon_restore';

	/**
	 * Coupon permanently deleted action.
	 */
	public const COUPON_DELETE = 'coupon_delete';

	/**
	 * Product review created action.
	 */
	public const REVIEW_CREATE = 'review_create';

	/**
	 * Product review approved action.
	 */
	public const REVIEW_APPROVE = 'review_approve';

	/**
	 * Product review unapproved action.
	 */
	public const REVIEW_UNAPPROVE = 'review_unapprove';

	/**
	 * Product review marked as spam action.
	 */
	public const REVIEW_SPAM = 'review_spam';

	/**
	 * Product review moved to trash action.
	 */
	public const REVIEW_TRASH = 'review_trash';

	/**
	 * Product review permanently deleted action.
	 */
	public const REVIEW_DELETE = 'review_delete';

	/**
	 * Resolve action label from known keys with fallback.
	 *
	 * @param string $action_key Action key.
	 * @return string
	 */
	public static function resolve_label( string $action_key ): string {

		$labels = self::get_labels();

		if ( isset( $labels[ $action_key ] ) ) {
			return $labels[ $action_key ];
		}

		return ucwords(
			str_replace(
				'_',
				' ',
				$action_key
			)
		);
	}

	/**
	 * Get memoized action labels map.
	 *
	 * @return array
	 */
	private static function get_labels(): array {

		static $labels = null;

		if ( null !== $labels ) {
			return $labels;
		}

		$labels = array(
			self::CREATE                      => __( 'Create', 'pastmark' ),
			self::UPDATE                      => __( 'Update', 'pastmark' ),
			self::DELETE                      => __( 'Delete', 'pastmark' ),
			self::RESTORE                     => __( 'Restore', 'pastmark' ),
			self::LOGIN                       => __( 'Login', 'pastmark' ),
			self::LOGOUT                      => __( 'Logout', 'pastmark' ),
			self::FAILED_LOGIN                => __( 'Failed Login', 'pastmark' ),
			self::SWITCH_USER                 => __( 'Switch User', 'pastmark' ),
			self::REGISTER                    => __( 'Register', 'pastmark' ),
			self::ROLE_CHANGE                 => __( 'Role Change', 'pastmark' ),
			self::STATUS_CHANGE               => __( 'Status Change', 'pastmark' ),
			self::ACTIVATE                    => __( 'Activate', 'pastmark' ),
			self::DEACTIVATE                  => __( 'Deactivate', 'pastmark' ),
			self::SWITCH                      => __( 'Switch', 'pastmark' ),
			self::ITEM_UPDATE                 => __( 'Item Update', 'pastmark' ),
			self::UPDATE_CHECK                => __( 'Update Check', 'pastmark' ),
			self::AUTHOR_CHANGE               => __( 'Author Change', 'pastmark' ),
			self::SLUG_CHANGE                 => __( 'Slug Change', 'pastmark' ),
			self::VISIBILITY_CHANGE           => __( 'Visibility Change', 'pastmark' ),
			self::DATE_CHANGE                 => __( 'Date Change', 'pastmark' ),
			self::STICKY_CHANGE               => __( 'Sticky Change', 'pastmark' ),
			self::PARENT_CHANGE               => __( 'Parent Change', 'pastmark' ),
			self::TEMPLATE_CHANGE             => __( 'Template Change', 'pastmark' ),
			self::CONTENT_CHANGE              => __( 'Content Change', 'pastmark' ),
			self::REPLY                       => __( 'Reply', 'pastmark' ),
			self::SPAM_CHANGE                 => __( 'Spam Change', 'pastmark' ),
			self::PASSWORD_CHANGE             => __( 'Password Change', 'pastmark' ),
			self::EMAIL_CHANGE                => __( 'Email Change', 'pastmark' ),
			self::PASSWORD_RESET_SENT         => __( 'Password Reset Sent', 'pastmark' ),
			self::SUPER_ADMIN_CHANGE          => __( 'Super Admin Change', 'pastmark' ),
			self::ADD_TO_SITE                 => __( 'Added To Site', 'pastmark' ),
			self::REMOVE_FROM_SITE            => __( 'Removed From Site', 'pastmark' ),
			self::USER_META_ADD               => __( 'Custom Field Added', 'pastmark' ),
			self::USER_META_UPDATE            => __( 'Custom Field Updated', 'pastmark' ),
			self::USER_META_DELETE            => __( 'Custom Field Deleted', 'pastmark' ),
			self::APP_PASSWORD_CREATE         => __( 'Application Password Created', 'pastmark' ),
			self::APP_PASSWORD_REVOKE         => __( 'Application Password Revoked', 'pastmark' ),
			self::FEATURED_IMAGE_CHANGE       => __( 'Featured Image Change', 'pastmark' ),
			self::SITE_ICON_CHANGE            => __( 'Site Icon Change', 'pastmark' ),
			self::INSTALL                     => __( 'Install', 'pastmark' ),
			self::INSTALL_FAILED              => __( 'Install Failed', 'pastmark' ),
			self::AUTO_UPDATE_CHANGE          => __( 'Automatic Update Change', 'pastmark' ),
			self::FILE_EDIT                   => __( 'File Edit', 'pastmark' ),
			self::CORE_UPDATE                 => __( 'Core Update', 'pastmark' ),

			self::PRODUCT_CREATE              => __( 'Product Create', 'pastmark' ),
			self::PRODUCT_PUBLISH             => __( 'Product Publish', 'pastmark' ),
			self::PRODUCT_TRASH               => __( 'Product Trash', 'pastmark' ),
			self::PRODUCT_DELETE              => __( 'Product Delete', 'pastmark' ),
			self::PRODUCT_RESTORE             => __( 'Product Restore', 'pastmark' ),
			self::PRODUCT_STATUS_CHANGE       => __( 'Product Status Change', 'pastmark' ),
			self::PRODUCT_RENAME              => __( 'Product Rename', 'pastmark' ),
			self::PRODUCT_CATEGORY_CHANGE     => __( 'Product Category Change', 'pastmark' ),
			self::PRODUCT_VISIBILITY_CHANGE   => __( 'Product Visibility Change', 'pastmark' ),
			self::PRODUCT_SKU_CHANGE          => __( 'Product SKU Change', 'pastmark' ),
			self::PRODUCT_PRICE_CHANGE        => __( 'Product Price Change', 'pastmark' ),
			self::PRODUCT_STOCK_STATUS_CHANGE => __( 'Product Stock Status Change', 'pastmark' ),
			self::PRODUCT_STOCK_QTY_CHANGE    => __( 'Product Stock Quantity Change', 'pastmark' ),
			self::PRODUCT_STOCK_AUTO_CHANGE   => __( 'Product Stock Auto-Change', 'pastmark' ),
			self::PRODUCT_CATEGORY_CREATE     => __( 'Product Category Create', 'pastmark' ),
			self::PRODUCT_CATEGORY_DELETE     => __( 'Product Category Delete', 'pastmark' ),

			self::ORDER_PLACED                => __( 'Order Placed', 'pastmark' ),
			self::ORDER_STATUS_CHANGE         => __( 'Order Status Change', 'pastmark' ),
			self::ORDER_TRASH                 => __( 'Order Trash', 'pastmark' ),
			self::ORDER_RESTORE               => __( 'Order Restore', 'pastmark' ),
			self::ORDER_DELETE                => __( 'Order Delete', 'pastmark' ),
			self::ORDER_EDIT                  => __( 'Order Edit', 'pastmark' ),
			self::ORDER_REFUND                => __( 'Order Refund', 'pastmark' ),
			self::ORDER_NOTE_ADD              => __( 'Order Note Add', 'pastmark' ),
			self::ORDER_NOTE_DELETE           => __( 'Order Note Delete', 'pastmark' ),

			self::COUPON_CREATE               => __( 'Coupon Create', 'pastmark' ),
			self::COUPON_AMOUNT_CHANGE        => __( 'Coupon Amount Change', 'pastmark' ),
			self::COUPON_STATUS_CHANGE        => __( 'Coupon Status Change', 'pastmark' ),
			self::COUPON_RENAME               => __( 'Coupon Rename', 'pastmark' ),
			self::COUPON_TRASH                => __( 'Coupon Trash', 'pastmark' ),
			self::COUPON_RESTORE              => __( 'Coupon Restore', 'pastmark' ),
			self::COUPON_DELETE               => __( 'Coupon Delete', 'pastmark' ),

			self::REVIEW_CREATE               => __( 'Review Create', 'pastmark' ),
			self::REVIEW_APPROVE              => __( 'Review Approve', 'pastmark' ),
			self::REVIEW_UNAPPROVE            => __( 'Review Unapprove', 'pastmark' ),
			self::REVIEW_SPAM                 => __( 'Review Spam', 'pastmark' ),
			self::REVIEW_TRASH                => __( 'Review Trash', 'pastmark' ),
			self::REVIEW_DELETE               => __( 'Review Delete', 'pastmark' ),
		);

		return $labels;
	}
}
