<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace LogTrail\Installation;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Autoloader {

	/**
	 * Currently installed version
	 *
	 * @var integer
	 */
	public static $current_version;

	/**
	 * For checking whether upgrade available or not
	 *
	 * @var boolean
	 */
	public static $is_upgrade = false;

	/**
	 * Initialize the installation process.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function run() {

		self::get_current_version();
		self::check_upgrade();

		if ( self::$is_upgrade ) {

			// Do not allow parallel process to run.
			if ( 'yes' === get_transient( 'logtrail_installing' ) ) {
				return;
			}

			// Set transient.
			set_transient( 'logtrail_installing', 'yes', MINUTE_IN_SECONDS * 10 );

			// Run installation.
			if ( self::$current_version == 0 ) {
				add_action( 'init', array( __CLASS__, 'initial_setup' ), 1 );
			} else {
				add_action( 'init', array( __CLASS__, 'upgrade' ), 1 );
			}

			// Delete transient.
			delete_transient( 'logtrail_installing' );
		}
	}

	/**
	 * Check version
	 */
	public static function get_current_version() {
		self::$current_version = get_option( 'logtrail_current_version', 0 );
	}

	/**
	 * Check for upgrade
	 */
	public static function check_upgrade() {
		if ( self::$current_version != WPLT_VERSION ) {
			self::$is_upgrade = true;
		}
	}

	/**
	 * First time installation
	 */
	public static function initial_setup() {

		self::create_db_tables();

		// General settings.
		Settings\General::install();

		// Exclude settings.
		Settings\Exclude::install();

		// Email report settings.
		Settings\EmailReports::install();

		// Data Management settings.
		Settings\DataManagement::install();

		// Event settings.
		Settings\Events::install();

		self::set_installation_complete();
	}

	/**
	 * Create or update database tables.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function create_db_tables() {

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$collate = '';
		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$sql = "CREATE TABLE {$wpdb->prefix}logtrail_logs (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				timestamp datetime NOT NULL,
				user_id bigint(20) unsigned DEFAULT NULL,
				ip_address varchar(45) DEFAULT NULL,
				event_type varchar(100) NOT NULL,
				object_type varchar(100) DEFAULT NULL,
				object_id bigint(20) unsigned DEFAULT NULL,
				action varchar(100) NOT NULL,
				message text DEFAULT NULL,
				before_data longtext DEFAULT NULL,
				after_data longtext DEFAULT NULL,
				context text DEFAULT NULL,
				severity varchar(20) DEFAULT 'info',
				site_id INT DEFAULT 1,
				PRIMARY KEY (id),
				KEY idx_user_id (user_id),
				KEY idx_event_type (event_type),
				KEY idx_timestamp (timestamp),
				KEY idx_object_type (object_type),
				KEY idx_action (action),
				KEY idx_site_id (site_id)
		) $collate;";

		dbDelta( $sql );
	}

	/**
	 * Upgrade the version
	 */
	public static function upgrade() {

		Upgrades\Upgrade::run( self::$current_version );
		self::set_installation_complete();
	}

	/**
	 * Mark installation as complete
	 */
	public static function set_installation_complete() {

		update_option( 'logtrail_current_version', WPLT_VERSION );
		self::$current_version = WPLT_VERSION;
		self::$is_upgrade      = false;
	}
}
