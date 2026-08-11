<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace LogTrail\EventSettings;

use LogTrail\Constants\Events;
use LogTrail\Constants\Actions;
use LogTrail\Constants\Severity;

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
				'label'   => __( 'Authentication', 'logtrail' ),
				'source'  => 'core',
				'actions' => array(

					array(
						'key'            => Actions::LOGIN,
						'label'          => __( 'Login', 'logtrail' ),
						'description'    => __( 'User successfully logged in.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::LOGOUT,
						'label'          => __( 'Logout', 'logtrail' ),
						'description'    => __( 'User logged out.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::FAILED_LOGIN,
						'label'          => __( 'Failed Login', 'logtrail' ),
						'description'    => __( 'Failed authentication attempt.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::SWITCH_USER,
						'label'          => __( 'Switch User', 'logtrail' ),
						'description'    => __( 'Admin switched to, or back from, another user account.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),
				),
			),

			Events::USER           => array(
				'label'   => __( 'Users', 'logtrail' ),
				'source'  => 'core',
				'actions' => array(

					array(
						'key'            => Actions::CREATE,
						'label'          => __( 'Create', 'logtrail' ),
						'description'    => __( 'New user account created by an admin (or a Super Admin on a Multisite network).', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::REGISTER,
						'label'          => __( 'Register', 'logtrail' ),
						'description'    => __( 'Visitor self-registered (including Multisite network signups).', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::UPDATE,
						'label'          => __( 'Profile Update', 'logtrail' ),
						'description'    => __( 'User profile updated.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::DELETE,
						'label'          => __( 'Delete', 'logtrail' ),
						'description'    => __( 'User account deleted by another user.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::ROLE_CHANGE,
						'label'          => __( 'Role Change', 'logtrail' ),
						'description'    => __( 'User role modified.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::PASSWORD_CHANGE,
						'label'          => __( 'Password Change', 'logtrail' ),
						'description'    => __( 'User password changed, by the account owner or another user.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::EMAIL_CHANGE,
						'label'          => __( 'Email Change', 'logtrail' ),
						'description'    => __( 'User email address changed, by the account owner or another user.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::PASSWORD_RESET_SENT,
						'label'          => __( 'Password Reset Sent', 'logtrail' ),
						'description'    => __( 'An admin sent a password reset link to a user.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::SUPER_ADMIN_CHANGE,
						'label'          => __( 'Super Admin Change', 'logtrail' ),
						'description'    => __( 'Super Admin privileges granted or revoked (Multisite only).', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::ADD_TO_SITE,
						'label'          => __( 'Added To Site', 'logtrail' ),
						'description'    => __( 'Existing user added to a site (Multisite only).', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::REMOVE_FROM_SITE,
						'label'          => __( 'Removed From Site', 'logtrail' ),
						'description'    => __( 'User removed from a site (Multisite only).', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::USER_META_ADD,
						'label'          => __( 'Custom Field Added', 'logtrail' ),
						'description'    => __( 'Custom user field added.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::USER_META_UPDATE,
						'label'          => __( 'Custom Field Updated', 'logtrail' ),
						'description'    => __( 'Custom user field updated.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::USER_META_DELETE,
						'label'          => __( 'Custom Field Deleted', 'logtrail' ),
						'description'    => __( 'Custom user field deleted.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::APP_PASSWORD_CREATE,
						'label'          => __( 'Application Password Created', 'logtrail' ),
						'description'    => __( 'Application password created.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::APP_PASSWORD_REVOKE,
						'label'          => __( 'Application Password Revoked', 'logtrail' ),
						'description'    => __( 'Application password revoked.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),
				),
			),

			Events::CONTENT        => array(
				'label'   => __( 'Content', 'logtrail' ),
				'source'  => 'core',
				'actions' => array(

					array(
						'key'            => Actions::CREATE,
						'label'          => __( 'Create', 'logtrail' ),
						'description'    => __( 'Post, page or custom post type created.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::UPDATE,
						'label'          => __( 'Update', 'logtrail' ),
						'description'    => __( 'Content updated.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::DELETE,
						'label'          => __( 'Delete', 'logtrail' ),
						'description'    => __( 'Content deleted.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::RESTORE,
						'label'          => __( 'Restore', 'logtrail' ),
						'description'    => __( 'Content restored.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::STATUS_CHANGE,
						'label'          => __( 'Status Change', 'logtrail' ),
						'description'    => __( 'Content status changed, including submitted for review and scheduled.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::AUTHOR_CHANGE,
						'label'          => __( 'Author Change', 'logtrail' ),
						'description'    => __( 'Post author/ownership reassigned.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::SLUG_CHANGE,
						'label'          => __( 'Slug Change', 'logtrail' ),
						'description'    => __( 'Post URL slug changed.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::VISIBILITY_CHANGE,
						'label'          => __( 'Visibility Change', 'logtrail' ),
						'description'    => __( 'Post visibility changed (public, private or password protected).', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::DATE_CHANGE,
						'label'          => __( 'Date Change', 'logtrail' ),
						'description'    => __( 'Post date changed.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::STICKY_CHANGE,
						'label'          => __( 'Sticky Change', 'logtrail' ),
						'description'    => __( 'Post marked or unmarked as sticky.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PARENT_CHANGE,
						'label'          => __( 'Parent Change', 'logtrail' ),
						'description'    => __( 'Page parent changed.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::TEMPLATE_CHANGE,
						'label'          => __( 'Template Change', 'logtrail' ),
						'description'    => __( 'Page template changed.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::CONTENT_CHANGE,
						'label'          => __( 'Content Change', 'logtrail' ),
						'description'    => __( 'Post content body changed.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),
				),
			),

			Events::COMMENT        => array(
				'label'   => __( 'Comments', 'logtrail' ),
				'source'  => 'core',
				'actions' => array(

					array(
						'key'            => Actions::CREATE,
						'label'          => __( 'Post Comment', 'logtrail' ),
						'description'    => __( 'New comment posted.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::REPLY,
						'label'          => __( 'Reply', 'logtrail' ),
						'description'    => __( 'Reply posted to an existing comment.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::UPDATE,
						'label'          => __( 'Edit Comment', 'logtrail' ),
						'description'    => __( 'Comment content edited.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::STATUS_CHANGE,
						'label'          => __( 'Status Change', 'logtrail' ),
						'description'    => __( 'Comment approved, unapproved, moved to trash, or restored.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::SPAM_CHANGE,
						'label'          => __( 'Spam Change', 'logtrail' ),
						'description'    => __( 'Comment marked as spam or not spam.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::RESTORE,
						'label'          => __( 'Restore', 'logtrail' ),
						'description'    => __( 'Comment restored from trash.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::DELETE,
						'label'          => __( 'Delete', 'logtrail' ),
						'description'    => __( 'Comment permanently deleted.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),
				),
			),

			Events::MEDIA          => array(
				'label'   => __( 'Media', 'logtrail' ),
				'source'  => 'core',
				'actions' => array(

					array(
						'key'            => Actions::CREATE,
						'label'          => __( 'Upload', 'logtrail' ),
						'description'    => __( 'File uploaded to the media library.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::UPDATE,
						'label'          => __( 'Update', 'logtrail' ),
						'description'    => __( 'Media title, caption, description or alt text updated.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::DELETE,
						'label'          => __( 'Delete', 'logtrail' ),
						'description'    => __( 'File deleted from the media library.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::FEATURED_IMAGE_CHANGE,
						'label'          => __( 'Featured Image Change', 'logtrail' ),
						'description'    => __( 'Featured image set, changed or removed on a post.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::SITE_ICON_CHANGE,
						'label'          => __( 'Site Icon Change', 'logtrail' ),
						'description'    => __( 'Site icon added, changed or removed.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),
				),
			),

			Events::PLUGIN         => array(
				'label'   => __( 'Plugins', 'logtrail' ),
				'source'  => 'core',
				'actions' => array(

					array(
						'key'            => Actions::INSTALL,
						'label'          => __( 'Install', 'logtrail' ),
						'description'    => __( 'Plugin installed.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::INSTALL_FAILED,
						'label'          => __( 'Install Failed', 'logtrail' ),
						'description'    => __( 'Plugin installation failed.', 'logtrail' ),
						'severity'       => Severity::ERROR,
						'severity_label' => Severity::resolve_label( Severity::ERROR ),
					),

					array(
						'key'            => Actions::ACTIVATE,
						'label'          => __( 'Activate', 'logtrail' ),
						'description'    => __( 'Plugin activated.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::DEACTIVATE,
						'label'          => __( 'Deactivate', 'logtrail' ),
						'description'    => __( 'Plugin deactivated.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::UPDATE,
						'label'          => __( 'Upgrade', 'logtrail' ),
						'description'    => __( 'Plugin upgraded to a new version.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::DELETE,
						'label'          => __( 'Delete', 'logtrail' ),
						'description'    => __( 'Plugin deleted.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),
				),
			),

			Events::THEME          => array(
				'label'   => __( 'Themes', 'logtrail' ),
				'source'  => 'core',
				'actions' => array(
					array(
						'key'            => Actions::INSTALL,
						'label'          => __( 'Install', 'logtrail' ),
						'description'    => __( 'Theme installed.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::SWITCH,
						'label'          => __( 'Switch Theme', 'logtrail' ),
						'description'    => __( 'Active theme changed.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::UPDATE,
						'label'          => __( 'Upgrade', 'logtrail' ),
						'description'    => __( 'Theme upgraded to a new version.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::DELETE,
						'label'          => __( 'Uninstall', 'logtrail' ),
						'description'    => __( 'Theme uninstalled.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::ACTIVATE,
						'label'          => __( 'Network Activate', 'logtrail' ),
						'description'    => __( 'Theme enabled network-wide (Multisite only).', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::DEACTIVATE,
						'label'          => __( 'Network Deactivate', 'logtrail' ),
						'description'    => __( 'Theme disabled network-wide (Multisite only).', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::FILE_EDIT,
						'label'          => __( 'File Edit', 'logtrail' ),
						'description'    => __( 'Theme file changed using the built-in theme editor.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),
				),
			),

			Events::MENU           => array(
				'label'   => __( 'Menus', 'logtrail' ),
				'source'  => 'core',
				'actions' => array(
					array(
						'key'            => Actions::CREATE,
						'label'          => __( 'Create Menu', 'logtrail' ),
						'description'    => __( 'Navigation menu created.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),
					array(
						'key'            => Actions::UPDATE,
						'label'          => __( 'Update Menu', 'logtrail' ),
						'description'    => __( 'Navigation menu updated.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),
					array(
						'key'            => Actions::DELETE,
						'label'          => __( 'Delete Menu', 'logtrail' ),
						'description'    => __( 'Navigation menu deleted.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),
					array(
						'key'            => Actions::ITEM_UPDATE,
						'label'          => __( 'Update Menu Item', 'logtrail' ),
						'description'    => __( 'Menu item modified.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),
				),
			),

			Events::WIDGET         => array(
				'label'   => __( 'Widgets', 'logtrail' ),
				'source'  => 'core',
				'actions' => array(
					array(
						'key'            => Actions::UPDATE,
						'label'          => __( 'Update Widget', 'logtrail' ),
						'description'    => __( 'Widget configuration updated.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),
				),
			),

			Events::SETTINGS       => array(
				'label'   => __( 'Settings', 'logtrail' ),
				'source'  => 'core',
				'actions' => array(
					array(
						'key'            => Actions::UPDATE,
						'label'          => __( 'Update Setting', 'logtrail' ),
						'description'    => __( 'WordPress setting updated.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::CORE_UPDATE,
						'label'          => __( 'Core Update', 'logtrail' ),
						'description'    => __( 'WordPress core updated to a new version, manually or automatically.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),
				),
			),

			Events::WOOCOMMERCE    => array(
				'label'   => __( 'WooCommerce', 'logtrail' ),
				'source'  => 'core',
				'actions' => array(

					array(
						'key'            => Actions::PRODUCT_CREATE,
						'label'          => __( 'Product Create', 'logtrail' ),
						'description'    => __( 'New product created as a draft.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_PUBLISH,
						'label'          => __( 'Product Publish', 'logtrail' ),
						'description'    => __( 'Product published.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_STATUS_CHANGE,
						'label'          => __( 'Product Status Change', 'logtrail' ),
						'description'    => __( 'Product status changed (e.g. published to draft).', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_TRASH,
						'label'          => __( 'Product Trash', 'logtrail' ),
						'description'    => __( 'Product moved to trash.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::PRODUCT_RESTORE,
						'label'          => __( 'Product Restore', 'logtrail' ),
						'description'    => __( 'Product restored from trash.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_DELETE,
						'label'          => __( 'Product Delete', 'logtrail' ),
						'description'    => __( 'Product permanently deleted.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::PRODUCT_RENAME,
						'label'          => __( 'Product Rename', 'logtrail' ),
						'description'    => __( 'Product title changed.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_CATEGORY_CHANGE,
						'label'          => __( 'Product Category Change', 'logtrail' ),
						'description'    => __( 'Categories assigned to a product changed.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_VISIBILITY_CHANGE,
						'label'          => __( 'Product Visibility Change', 'logtrail' ),
						'description'    => __( 'Catalog visibility of a product changed.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::PRODUCT_SKU_CHANGE,
						'label'          => __( 'Product SKU Change', 'logtrail' ),
						'description'    => __( 'SKU of a product changed.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_PRICE_CHANGE,
						'label'          => __( 'Product Price Change', 'logtrail' ),
						'description'    => __( 'Regular or sale price of a product changed.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::PRODUCT_STOCK_STATUS_CHANGE,
						'label'          => __( 'Product Stock Status Change', 'logtrail' ),
						'description'    => __( 'Stock status of a product changed (in stock, out of stock, on backorder).', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_STOCK_QTY_CHANGE,
						'label'          => __( 'Product Stock Quantity Change', 'logtrail' ),
						'description'    => __( 'Stock quantity of a product changed manually via the WooCommerce dashboard.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_STOCK_AUTO_CHANGE,
						'label'          => __( 'Product Stock Auto-Change', 'logtrail' ),
						'description'    => __( 'Stock quantity of a product changed automatically (e.g. by a customer order or another plugin).', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_CATEGORY_CREATE,
						'label'          => __( 'Product Category Create', 'logtrail' ),
						'description'    => __( 'New product category created.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::PRODUCT_CATEGORY_DELETE,
						'label'          => __( 'Product Category Delete', 'logtrail' ),
						'description'    => __( 'Product category deleted.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::ORDER_PLACED,
						'label'          => __( 'Order Placed', 'logtrail' ),
						'description'    => __( 'A new WooCommerce order was placed at checkout.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::ORDER_STATUS_CHANGE,
						'label'          => __( 'Order Status Change', 'logtrail' ),
						'description'    => __( 'Order status changed (e.g. processing to completed).', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::ORDER_TRASH,
						'label'          => __( 'Order Trash', 'logtrail' ),
						'description'    => __( 'Order moved to trash.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::ORDER_RESTORE,
						'label'          => __( 'Order Restore', 'logtrail' ),
						'description'    => __( 'Order restored from trash.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::ORDER_DELETE,
						'label'          => __( 'Order Delete', 'logtrail' ),
						'description'    => __( 'Order permanently deleted.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::ORDER_EDIT,
						'label'          => __( 'Order Edit', 'logtrail' ),
						'description'    => __( 'Order details (billing, shipping or customer note) edited.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::ORDER_REFUND,
						'label'          => __( 'Order Refund', 'logtrail' ),
						'description'    => __( 'Order refunded, in full or in part.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::ORDER_NOTE_ADD,
						'label'          => __( 'Order Note Add', 'logtrail' ),
						'description'    => __( 'Note added to an order.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::ORDER_NOTE_DELETE,
						'label'          => __( 'Order Note Delete', 'logtrail' ),
						'description'    => __( 'Note deleted from an order.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::COUPON_CREATE,
						'label'          => __( 'Coupon Create', 'logtrail' ),
						'description'    => __( 'New coupon published.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::COUPON_AMOUNT_CHANGE,
						'label'          => __( 'Coupon Amount Change', 'logtrail' ),
						'description'    => __( 'Discount amount of a coupon changed.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::COUPON_STATUS_CHANGE,
						'label'          => __( 'Coupon Status Change', 'logtrail' ),
						'description'    => __( 'Status of a coupon changed.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::COUPON_RENAME,
						'label'          => __( 'Coupon Rename', 'logtrail' ),
						'description'    => __( 'Coupon code changed.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::COUPON_TRASH,
						'label'          => __( 'Coupon Trash', 'logtrail' ),
						'description'    => __( 'Coupon moved to trash.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::COUPON_RESTORE,
						'label'          => __( 'Coupon Restore', 'logtrail' ),
						'description'    => __( 'Coupon restored from trash.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::COUPON_DELETE,
						'label'          => __( 'Coupon Delete', 'logtrail' ),
						'description'    => __( 'Coupon permanently deleted.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::REVIEW_CREATE,
						'label'          => __( 'Review Create', 'logtrail' ),
						'description'    => __( 'New product review submitted.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::REVIEW_APPROVE,
						'label'          => __( 'Review Approve', 'logtrail' ),
						'description'    => __( 'Product review approved.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::REVIEW_UNAPPROVE,
						'label'          => __( 'Review Unapprove', 'logtrail' ),
						'description'    => __( 'Product review unapproved.', 'logtrail' ),
						'severity'       => Severity::INFO,
						'severity_label' => Severity::resolve_label( Severity::INFO ),
					),

					array(
						'key'            => Actions::REVIEW_SPAM,
						'label'          => __( 'Review Spam', 'logtrail' ),
						'description'    => __( 'Product review marked as spam or not spam.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::REVIEW_TRASH,
						'label'          => __( 'Review Trash', 'logtrail' ),
						'description'    => __( 'Product review moved to trash.', 'logtrail' ),
						'severity'       => Severity::WARNING,
						'severity_label' => Severity::resolve_label( Severity::WARNING ),
					),

					array(
						'key'            => Actions::REVIEW_DELETE,
						'label'          => __( 'Review Delete', 'logtrail' ),
						'description'    => __( 'Product review permanently deleted.', 'logtrail' ),
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
			'logtrail_registered_events',
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
