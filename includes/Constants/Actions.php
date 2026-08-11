<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace LogTrail\Constants;

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
			self::CREATE                      => __( 'Create', 'logtrail' ),
			self::UPDATE                      => __( 'Update', 'logtrail' ),
			self::DELETE                      => __( 'Delete', 'logtrail' ),
			self::RESTORE                     => __( 'Restore', 'logtrail' ),
			self::LOGIN                       => __( 'Login', 'logtrail' ),
			self::LOGOUT                      => __( 'Logout', 'logtrail' ),
			self::FAILED_LOGIN                => __( 'Failed Login', 'logtrail' ),
			self::SWITCH_USER                 => __( 'Switch User', 'logtrail' ),
			self::REGISTER                    => __( 'Register', 'logtrail' ),
			self::ROLE_CHANGE                 => __( 'Role Change', 'logtrail' ),
			self::STATUS_CHANGE               => __( 'Status Change', 'logtrail' ),
			self::ACTIVATE                    => __( 'Activate', 'logtrail' ),
			self::DEACTIVATE                  => __( 'Deactivate', 'logtrail' ),
			self::SWITCH                      => __( 'Switch', 'logtrail' ),
			self::ITEM_UPDATE                 => __( 'Item Update', 'logtrail' ),
			self::UPDATE_CHECK                => __( 'Update Check', 'logtrail' ),
			self::AUTHOR_CHANGE               => __( 'Author Change', 'logtrail' ),
			self::SLUG_CHANGE                 => __( 'Slug Change', 'logtrail' ),
			self::VISIBILITY_CHANGE           => __( 'Visibility Change', 'logtrail' ),
			self::DATE_CHANGE                 => __( 'Date Change', 'logtrail' ),
			self::STICKY_CHANGE               => __( 'Sticky Change', 'logtrail' ),
			self::PARENT_CHANGE               => __( 'Parent Change', 'logtrail' ),
			self::TEMPLATE_CHANGE             => __( 'Template Change', 'logtrail' ),
			self::CONTENT_CHANGE              => __( 'Content Change', 'logtrail' ),
			self::REPLY                       => __( 'Reply', 'logtrail' ),
			self::SPAM_CHANGE                 => __( 'Spam Change', 'logtrail' ),
			self::PASSWORD_CHANGE             => __( 'Password Change', 'logtrail' ),
			self::EMAIL_CHANGE                => __( 'Email Change', 'logtrail' ),
			self::PASSWORD_RESET_SENT         => __( 'Password Reset Sent', 'logtrail' ),
			self::SUPER_ADMIN_CHANGE          => __( 'Super Admin Change', 'logtrail' ),
			self::ADD_TO_SITE                 => __( 'Added To Site', 'logtrail' ),
			self::REMOVE_FROM_SITE            => __( 'Removed From Site', 'logtrail' ),
			self::USER_META_ADD               => __( 'Custom Field Added', 'logtrail' ),
			self::USER_META_UPDATE            => __( 'Custom Field Updated', 'logtrail' ),
			self::USER_META_DELETE            => __( 'Custom Field Deleted', 'logtrail' ),
			self::APP_PASSWORD_CREATE         => __( 'Application Password Created', 'logtrail' ),
			self::APP_PASSWORD_REVOKE         => __( 'Application Password Revoked', 'logtrail' ),
			self::FEATURED_IMAGE_CHANGE       => __( 'Featured Image Change', 'logtrail' ),
			self::SITE_ICON_CHANGE            => __( 'Site Icon Change', 'logtrail' ),
			self::INSTALL                     => __( 'Install', 'logtrail' ),
			self::INSTALL_FAILED              => __( 'Install Failed', 'logtrail' ),
			self::AUTO_UPDATE_CHANGE          => __( 'Automatic Update Change', 'logtrail' ),
			self::FILE_EDIT                   => __( 'File Edit', 'logtrail' ),
			self::CORE_UPDATE                 => __( 'Core Update', 'logtrail' ),

			self::PRODUCT_CREATE              => __( 'Product Create', 'logtrail' ),
			self::PRODUCT_PUBLISH             => __( 'Product Publish', 'logtrail' ),
			self::PRODUCT_TRASH               => __( 'Product Trash', 'logtrail' ),
			self::PRODUCT_DELETE              => __( 'Product Delete', 'logtrail' ),
			self::PRODUCT_RESTORE             => __( 'Product Restore', 'logtrail' ),
			self::PRODUCT_STATUS_CHANGE       => __( 'Product Status Change', 'logtrail' ),
			self::PRODUCT_RENAME              => __( 'Product Rename', 'logtrail' ),
			self::PRODUCT_CATEGORY_CHANGE     => __( 'Product Category Change', 'logtrail' ),
			self::PRODUCT_VISIBILITY_CHANGE   => __( 'Product Visibility Change', 'logtrail' ),
			self::PRODUCT_SKU_CHANGE          => __( 'Product SKU Change', 'logtrail' ),
			self::PRODUCT_PRICE_CHANGE        => __( 'Product Price Change', 'logtrail' ),
			self::PRODUCT_STOCK_STATUS_CHANGE => __( 'Product Stock Status Change', 'logtrail' ),
			self::PRODUCT_STOCK_QTY_CHANGE    => __( 'Product Stock Quantity Change', 'logtrail' ),
			self::PRODUCT_STOCK_AUTO_CHANGE   => __( 'Product Stock Auto-Change', 'logtrail' ),
			self::PRODUCT_CATEGORY_CREATE     => __( 'Product Category Create', 'logtrail' ),
			self::PRODUCT_CATEGORY_DELETE     => __( 'Product Category Delete', 'logtrail' ),

			self::ORDER_PLACED                => __( 'Order Placed', 'logtrail' ),
			self::ORDER_STATUS_CHANGE         => __( 'Order Status Change', 'logtrail' ),
			self::ORDER_TRASH                 => __( 'Order Trash', 'logtrail' ),
			self::ORDER_RESTORE               => __( 'Order Restore', 'logtrail' ),
			self::ORDER_DELETE                => __( 'Order Delete', 'logtrail' ),
			self::ORDER_EDIT                  => __( 'Order Edit', 'logtrail' ),
			self::ORDER_REFUND                => __( 'Order Refund', 'logtrail' ),
			self::ORDER_NOTE_ADD              => __( 'Order Note Add', 'logtrail' ),
			self::ORDER_NOTE_DELETE           => __( 'Order Note Delete', 'logtrail' ),

			self::COUPON_CREATE               => __( 'Coupon Create', 'logtrail' ),
			self::COUPON_AMOUNT_CHANGE        => __( 'Coupon Amount Change', 'logtrail' ),
			self::COUPON_STATUS_CHANGE        => __( 'Coupon Status Change', 'logtrail' ),
			self::COUPON_RENAME               => __( 'Coupon Rename', 'logtrail' ),
			self::COUPON_TRASH                => __( 'Coupon Trash', 'logtrail' ),
			self::COUPON_RESTORE              => __( 'Coupon Restore', 'logtrail' ),
			self::COUPON_DELETE               => __( 'Coupon Delete', 'logtrail' ),

			self::REVIEW_CREATE               => __( 'Review Create', 'logtrail' ),
			self::REVIEW_APPROVE              => __( 'Review Approve', 'logtrail' ),
			self::REVIEW_UNAPPROVE            => __( 'Review Unapprove', 'logtrail' ),
			self::REVIEW_SPAM                 => __( 'Review Spam', 'logtrail' ),
			self::REVIEW_TRASH                => __( 'Review Trash', 'logtrail' ),
			self::REVIEW_DELETE               => __( 'Review Delete', 'logtrail' ),
		);

		return $labels;
	}
}
