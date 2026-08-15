<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\EventSettings;

use Pastmark\Constants\Events;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Event registry.
 */
class EventRegistry {

	/**
	 * Get registered events.
	 *
	 * @return array
	 */
	public static function get_events(): array {

		$events = array(

			Events::AUTHENTICATION => array(
				'label'   => __( 'Authentication', 'pastmark' ),
				'source'  => 'core',
				'actions' => array(

					array(
						'key'            => Actions::LOGIN,
						'label'          => __( 'Login', 'pastmark' ),
						'description'    => __( 'User successfully logged in.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::LOGOUT,
						'label'          => __( 'Logout', 'pastmark' ),
						'description'    => __( 'User logged out.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::FAILED_LOGIN,
						'label'          => __( 'Failed Login', 'pastmark' ),
						'description'    => __( 'Failed authentication attempt.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::SWITCH_USER,
						'label'          => __( 'Switch User', 'pastmark' ),
						'description'    => __( 'Admin switched to, or back from, another user account.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),
				),
			),

			Events::USER           => array(
				'label'   => __( 'Users', 'pastmark' ),
				'source'  => 'core',
				'actions' => array(

					array(
						'key'            => Actions::CREATE,
						'label'          => __( 'Create', 'pastmark' ),
						'description'    => __( 'New user account created by an admin (or a Super Admin on a Multisite network).', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::REGISTER,
						'label'          => __( 'Register', 'pastmark' ),
						'description'    => __( 'Visitor self-registered (including Multisite network signups).', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::UPDATE,
						'label'          => __( 'Profile Update', 'pastmark' ),
						'description'    => __( 'User profile updated.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::DELETE,
						'label'          => __( 'Delete', 'pastmark' ),
						'description'    => __( 'User account deleted by another user.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::ROLE_CHANGE,
						'label'          => __( 'Role Change', 'pastmark' ),
						'description'    => __( 'User role modified.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::PASSWORD_CHANGE,
						'label'          => __( 'Password Change', 'pastmark' ),
						'description'    => __( 'User password changed, by the account owner or another user.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::EMAIL_CHANGE,
						'label'          => __( 'Email Change', 'pastmark' ),
						'description'    => __( 'User email address changed, by the account owner or another user.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::PASSWORD_RESET_SENT,
						'label'          => __( 'Password Reset Sent', 'pastmark' ),
						'description'    => __( 'An admin sent a password reset link to a user.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::SUPER_ADMIN_CHANGE,
						'label'          => __( 'Super Admin Change', 'pastmark' ),
						'description'    => __( 'Super Admin privileges granted or revoked (Multisite only).', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::ADD_TO_SITE,
						'label'          => __( 'Added To Site', 'pastmark' ),
						'description'    => __( 'Existing user added to a site (Multisite only).', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::REMOVE_FROM_SITE,
						'label'          => __( 'Removed From Site', 'pastmark' ),
						'description'    => __( 'User removed from a site (Multisite only).', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::USER_META_ADD,
						'label'          => __( 'Custom Field Added', 'pastmark' ),
						'description'    => __( 'Custom user field added.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::USER_META_UPDATE,
						'label'          => __( 'Custom Field Updated', 'pastmark' ),
						'description'    => __( 'Custom user field updated.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::USER_META_DELETE,
						'label'          => __( 'Custom Field Deleted', 'pastmark' ),
						'description'    => __( 'Custom user field deleted.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::APP_PASSWORD_CREATE,
						'label'          => __( 'Application Password Created', 'pastmark' ),
						'description'    => __( 'Application password created.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::APP_PASSWORD_REVOKE,
						'label'          => __( 'Application Password Revoked', 'pastmark' ),
						'description'    => __( 'Application password revoked.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),
				),
			),

			Events::CONTENT        => array(
				'label'   => __( 'Content', 'pastmark' ),
				'source'  => 'core',
				'actions' => array(

					array(
						'key'            => Actions::CREATE,
						'label'          => __( 'Create', 'pastmark' ),
						'description'    => __( 'Post, page or custom post type created.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::UPDATE,
						'label'          => __( 'Update', 'pastmark' ),
						'description'    => __( 'Content updated.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::DELETE,
						'label'          => __( 'Delete', 'pastmark' ),
						'description'    => __( 'Content deleted.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::RESTORE,
						'label'          => __( 'Restore', 'pastmark' ),
						'description'    => __( 'Content restored.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::STATUS_CHANGE,
						'label'          => __( 'Status Change', 'pastmark' ),
						'description'    => __( 'Content status changed, including submitted for review and scheduled.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::AUTHOR_CHANGE,
						'label'          => __( 'Author Change', 'pastmark' ),
						'description'    => __( 'Post author/ownership reassigned.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::SLUG_CHANGE,
						'label'          => __( 'Slug Change', 'pastmark' ),
						'description'    => __( 'Post URL slug changed.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::VISIBILITY_CHANGE,
						'label'          => __( 'Visibility Change', 'pastmark' ),
						'description'    => __( 'Post visibility changed (public, private or password protected).', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::DATE_CHANGE,
						'label'          => __( 'Date Change', 'pastmark' ),
						'description'    => __( 'Post date changed.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::STICKY_CHANGE,
						'label'          => __( 'Sticky Change', 'pastmark' ),
						'description'    => __( 'Post marked or unmarked as sticky.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PARENT_CHANGE,
						'label'          => __( 'Parent Change', 'pastmark' ),
						'description'    => __( 'Page parent changed.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::TEMPLATE_CHANGE,
						'label'          => __( 'Template Change', 'pastmark' ),
						'description'    => __( 'Page template changed.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::CONTENT_CHANGE,
						'label'          => __( 'Content Change', 'pastmark' ),
						'description'    => __( 'Post content body changed.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),
				),
			),

			Events::COMMENT        => array(
				'label'   => __( 'Comments', 'pastmark' ),
				'source'  => 'core',
				'actions' => array(

					array(
						'key'            => Actions::CREATE,
						'label'          => __( 'Post Comment', 'pastmark' ),
						'description'    => __( 'New comment posted.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::REPLY,
						'label'          => __( 'Reply', 'pastmark' ),
						'description'    => __( 'Reply posted to an existing comment.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::UPDATE,
						'label'          => __( 'Edit Comment', 'pastmark' ),
						'description'    => __( 'Comment content edited.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::STATUS_CHANGE,
						'label'          => __( 'Status Change', 'pastmark' ),
						'description'    => __( 'Comment approved, unapproved, moved to trash, or restored.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::SPAM_CHANGE,
						'label'          => __( 'Spam Change', 'pastmark' ),
						'description'    => __( 'Comment marked as spam or not spam.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::RESTORE,
						'label'          => __( 'Restore', 'pastmark' ),
						'description'    => __( 'Comment restored from trash.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::DELETE,
						'label'          => __( 'Delete', 'pastmark' ),
						'description'    => __( 'Comment permanently deleted.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),
				),
			),

			Events::MEDIA          => array(
				'label'   => __( 'Media', 'pastmark' ),
				'source'  => 'core',
				'actions' => array(

					array(
						'key'            => Actions::CREATE,
						'label'          => __( 'Upload', 'pastmark' ),
						'description'    => __( 'File uploaded to the media library.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::UPDATE,
						'label'          => __( 'Update', 'pastmark' ),
						'description'    => __( 'Media title, caption, description or alt text updated.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::DELETE,
						'label'          => __( 'Delete', 'pastmark' ),
						'description'    => __( 'File deleted from the media library.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::FEATURED_IMAGE_CHANGE,
						'label'          => __( 'Featured Image Change', 'pastmark' ),
						'description'    => __( 'Featured image set, changed or removed on a post.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::SITE_ICON_CHANGE,
						'label'          => __( 'Site Icon Change', 'pastmark' ),
						'description'    => __( 'Site icon added, changed or removed.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),
				),
			),

			Events::PLUGIN         => array(
				'label'   => __( 'Plugins', 'pastmark' ),
				'source'  => 'core',
				'actions' => array(

					array(
						'key'            => Actions::INSTALL,
						'label'          => __( 'Install', 'pastmark' ),
						'description'    => __( 'Plugin installed.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::INSTALL_FAILED,
						'label'          => __( 'Install Failed', 'pastmark' ),
						'description'    => __( 'Plugin installation failed.', 'pastmark' ),
						'severity'       => Severity::ERROR,
						'severity_label' => Severity::resolve_label( Severity::ERROR ),
					),

					array(
						'key'            => Actions::ACTIVATE,
						'label'          => __( 'Activate', 'pastmark' ),
						'description'    => __( 'Plugin activated.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::DEACTIVATE,
						'label'          => __( 'Deactivate', 'pastmark' ),
						'description'    => __( 'Plugin deactivated.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::UPDATE,
						'label'          => __( 'Upgrade', 'pastmark' ),
						'description'    => __( 'Plugin upgraded to a new version.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::DELETE,
						'label'          => __( 'Delete', 'pastmark' ),
						'description'    => __( 'Plugin deleted.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),
				),
			),

			Events::THEME          => array(
				'label'   => __( 'Themes', 'pastmark' ),
				'source'  => 'core',
				'actions' => array(
					array(
						'key'            => Actions::INSTALL,
						'label'          => __( 'Install', 'pastmark' ),
						'description'    => __( 'Theme installed.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::SWITCH,
						'label'          => __( 'Switch Theme', 'pastmark' ),
						'description'    => __( 'Active theme changed.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::UPDATE,
						'label'          => __( 'Upgrade', 'pastmark' ),
						'description'    => __( 'Theme upgraded to a new version.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::DELETE,
						'label'          => __( 'Uninstall', 'pastmark' ),
						'description'    => __( 'Theme uninstalled.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::ACTIVATE,
						'label'          => __( 'Network Activate', 'pastmark' ),
						'description'    => __( 'Theme enabled network-wide (Multisite only).', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::DEACTIVATE,
						'label'          => __( 'Network Deactivate', 'pastmark' ),
						'description'    => __( 'Theme disabled network-wide (Multisite only).', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::FILE_EDIT,
						'label'          => __( 'File Edit', 'pastmark' ),
						'description'    => __( 'Theme file changed using the built-in theme editor.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),
				),
			),

			Events::MENU           => array(
				'label'   => __( 'Menus', 'pastmark' ),
				'source'  => 'core',
				'actions' => array(
					array(
						'key'            => Actions::CREATE,
						'label'          => __( 'Create Menu', 'pastmark' ),
						'description'    => __( 'Navigation menu created.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),
					array(
						'key'            => Actions::UPDATE,
						'label'          => __( 'Update Menu', 'pastmark' ),
						'description'    => __( 'Navigation menu updated.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),
					array(
						'key'            => Actions::DELETE,
						'label'          => __( 'Delete Menu', 'pastmark' ),
						'description'    => __( 'Navigation menu deleted.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),
					array(
						'key'            => Actions::ITEM_UPDATE,
						'label'          => __( 'Update Menu Item', 'pastmark' ),
						'description'    => __( 'Menu item modified.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),
				),
			),

			Events::WIDGET         => array(
				'label'   => __( 'Widgets', 'pastmark' ),
				'source'  => 'core',
				'actions' => array(
					array(
						'key'            => Actions::UPDATE,
						'label'          => __( 'Update Widget', 'pastmark' ),
						'description'    => __( 'Widget configuration updated.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),
				),
			),

			Events::SETTINGS       => array(
				'label'   => __( 'Settings', 'pastmark' ),
				'source'  => 'core',
				'actions' => array(
					array(
						'key'            => Actions::UPDATE,
						'label'          => __( 'Update Setting', 'pastmark' ),
						'description'    => __( 'WordPress setting updated.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::CORE_UPDATE,
						'label'          => __( 'Core Update', 'pastmark' ),
						'description'    => __( 'WordPress core updated to a new version, manually or automatically.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),
				),
			),

			Events::WOOCOMMERCE    => array(
				'label'   => __( 'WooCommerce', 'pastmark' ),
				'source'  => 'core',
				'actions' => array(

					array(
						'key'            => Actions::PRODUCT_CREATE,
						'label'          => __( 'Product Create', 'pastmark' ),
						'description'    => __( 'New product created as a draft.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_PUBLISH,
						'label'          => __( 'Product Publish', 'pastmark' ),
						'description'    => __( 'Product published.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_STATUS_CHANGE,
						'label'          => __( 'Product Status Change', 'pastmark' ),
						'description'    => __( 'Product status changed (e.g. published to draft).', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_TRASH,
						'label'          => __( 'Product Trash', 'pastmark' ),
						'description'    => __( 'Product moved to trash.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::PRODUCT_RESTORE,
						'label'          => __( 'Product Restore', 'pastmark' ),
						'description'    => __( 'Product restored from trash.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_DELETE,
						'label'          => __( 'Product Delete', 'pastmark' ),
						'description'    => __( 'Product permanently deleted.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::PRODUCT_RENAME,
						'label'          => __( 'Product Rename', 'pastmark' ),
						'description'    => __( 'Product title changed.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_CATEGORY_CHANGE,
						'label'          => __( 'Product Category Change', 'pastmark' ),
						'description'    => __( 'Categories assigned to a product changed.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_VISIBILITY_CHANGE,
						'label'          => __( 'Product Visibility Change', 'pastmark' ),
						'description'    => __( 'Catalog visibility of a product changed.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::PRODUCT_SKU_CHANGE,
						'label'          => __( 'Product SKU Change', 'pastmark' ),
						'description'    => __( 'SKU of a product changed.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_PRICE_CHANGE,
						'label'          => __( 'Product Price Change', 'pastmark' ),
						'description'    => __( 'Regular or sale price of a product changed.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::PRODUCT_STOCK_STATUS_CHANGE,
						'label'          => __( 'Product Stock Status Change', 'pastmark' ),
						'description'    => __( 'Stock status of a product changed (in stock, out of stock, on backorder).', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_STOCK_QTY_CHANGE,
						'label'          => __( 'Product Stock Quantity Change', 'pastmark' ),
						'description'    => __( 'Stock quantity of a product changed manually via the WooCommerce dashboard.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_STOCK_AUTO_CHANGE,
						'label'          => __( 'Product Stock Auto-Change', 'pastmark' ),
						'description'    => __( 'Stock quantity of a product changed automatically (e.g. by a customer order or another plugin).', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_CATEGORY_CREATE,
						'label'          => __( 'Product Category Create', 'pastmark' ),
						'description'    => __( 'New product category created.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_CATEGORY_DELETE,
						'label'          => __( 'Product Category Delete', 'pastmark' ),
						'description'    => __( 'Product category deleted.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::ORDER_PLACED,
						'label'          => __( 'Order Placed', 'pastmark' ),
						'description'    => __( 'A new WooCommerce order was placed at checkout.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::ORDER_STATUS_CHANGE,
						'label'          => __( 'Order Status Change', 'pastmark' ),
						'description'    => __( 'Order status changed (e.g. processing to completed).', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::ORDER_TRASH,
						'label'          => __( 'Order Trash', 'pastmark' ),
						'description'    => __( 'Order moved to trash.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::ORDER_RESTORE,
						'label'          => __( 'Order Restore', 'pastmark' ),
						'description'    => __( 'Order restored from trash.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::ORDER_DELETE,
						'label'          => __( 'Order Delete', 'pastmark' ),
						'description'    => __( 'Order permanently deleted.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::ORDER_EDIT,
						'label'          => __( 'Order Edit', 'pastmark' ),
						'description'    => __( 'Order details (billing, shipping or customer note) edited.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::ORDER_REFUND,
						'label'          => __( 'Order Refund', 'pastmark' ),
						'description'    => __( 'Order refunded, in full or in part.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::ORDER_NOTE_ADD,
						'label'          => __( 'Order Note Add', 'pastmark' ),
						'description'    => __( 'Note added to an order.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::ORDER_NOTE_DELETE,
						'label'          => __( 'Order Note Delete', 'pastmark' ),
						'description'    => __( 'Note deleted from an order.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::COUPON_CREATE,
						'label'          => __( 'Coupon Create', 'pastmark' ),
						'description'    => __( 'New coupon published.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::COUPON_AMOUNT_CHANGE,
						'label'          => __( 'Coupon Amount Change', 'pastmark' ),
						'description'    => __( 'Discount amount of a coupon changed.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::COUPON_STATUS_CHANGE,
						'label'          => __( 'Coupon Status Change', 'pastmark' ),
						'description'    => __( 'Status of a coupon changed.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::COUPON_RENAME,
						'label'          => __( 'Coupon Rename', 'pastmark' ),
						'description'    => __( 'Coupon code changed.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::COUPON_TRASH,
						'label'          => __( 'Coupon Trash', 'pastmark' ),
						'description'    => __( 'Coupon moved to trash.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::COUPON_RESTORE,
						'label'          => __( 'Coupon Restore', 'pastmark' ),
						'description'    => __( 'Coupon restored from trash.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::COUPON_DELETE,
						'label'          => __( 'Coupon Delete', 'pastmark' ),
						'description'    => __( 'Coupon permanently deleted.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::REVIEW_CREATE,
						'label'          => __( 'Review Create', 'pastmark' ),
						'description'    => __( 'New product review submitted.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::REVIEW_APPROVE,
						'label'          => __( 'Review Approve', 'pastmark' ),
						'description'    => __( 'Product review approved.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::REVIEW_UNAPPROVE,
						'label'          => __( 'Review Unapprove', 'pastmark' ),
						'description'    => __( 'Product review unapproved.', 'pastmark' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::REVIEW_SPAM,
						'label'          => __( 'Review Spam', 'pastmark' ),
						'description'    => __( 'Product review marked as spam or not spam.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::REVIEW_TRASH,
						'label'          => __( 'Review Trash', 'pastmark' ),
						'description'    => __( 'Product review moved to trash.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::REVIEW_DELETE,
						'label'          => __( 'Review Delete', 'pastmark' ),
						'description'    => __( 'Product review permanently deleted.', 'pastmark' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),
				),
			),
		);

		if ( ! is_multisite() ) {
			self::remove_actions( $events, Events::USER, array( Actions::SUPER_ADMIN_CHANGE, Actions::ADD_TO_SITE, Actions::REMOVE_FROM_SITE ) );
			self::remove_actions( $events, Events::THEME, array( Actions::ACTIVATE, Actions::DEACTIVATE ) );
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			unset( $events[ Events::WOOCOMMERCE ] );
		}

		return apply_filters(
			'pastmark_registered_events',
			$events
		);
	}

	/**
	 * Remove specific actions from an event's action list.
	 *
	 * @param array  $events Events array, passed by reference.
	 * @param string $event_key Event key.
	 * @param array  $action_keys Action keys to remove.
	 * @return void
	 */
	private static function remove_actions( array &$events, string $event_key, array $action_keys ): void {

		if ( ! isset( $events[ $event_key ] ) ) {
			return;
		}

		$events[ $event_key ]['actions'] = array_values(
			array_filter(
				$events[ $event_key ]['actions'],
				function ( $action ) use ( $action_keys ) {
					return ! in_array( $action['key'], $action_keys, true );
				}
			)
		);
	}

	/**
	 * Get single event.
	 *
	 * @param string $event Event key.
	 *
	 * @return array|null
	 */
	public static function get_event( string $event ) {

		$events = self::get_events();

		return $events[ $event ] ?? null;
	}
}
