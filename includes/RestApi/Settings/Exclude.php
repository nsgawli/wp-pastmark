<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\RestApi\Settings;

use Pastmark\Installation\Settings\Exclude as InstallationExclude;
use Pastmark\RestApi\BaseController;
use WP_REST_Request;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exclude settings REST controller.
 */
class Exclude extends BaseController {

	/**
	 * Init.
	 *
	 * @return void
	 */
	public static function init() {

		$instance = new self();

		add_action(
			'rest_api_init',
			array( $instance, 'register_routes' )
		);
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/settings/exclude-settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/settings/exclude-settings/defaults',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_default_settings' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/settings/exclude-settings/options',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_options' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/settings/exclude-settings/users',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'search_users' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/settings/exclude-settings/plugins',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'search_plugins' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/settings/exclude-settings/themes',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'search_themes' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);
	}

	/**
	 * Get settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {

		$settings = wp_parse_args(
			get_option(
				'pastmark_exclude_settings',
				array()
			),
			InstallationExclude::get_default_settings()
		);

		return $this->success_response( $settings );
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ) {

		$settings = array(
			'excludedUsers'       => (array) $request->get_param( 'excludedUsers' ),
			'excludedRoles'       => (array) $request->get_param( 'excludedRoles' ),
			'excludedIPs'         => (array) $request->get_param( 'excludedIPs' ),
			'excludedPostTypes'   => (array) $request->get_param( 'excludedPostTypes' ),
			'excludedStatuses'    => (array) $request->get_param( 'excludedStatuses' ),
			'excludedPostMeta'    => (array) $request->get_param( 'excludedPostMeta' ),
			'excludedUserMeta'    => (array) $request->get_param( 'excludedUserMeta' ),
			'excludedPlugins'     => (array) $request->get_param( 'excludedPlugins' ),
			'excludedThemes'      => (array) $request->get_param( 'excludedThemes' ),
			'excludedWidgets'     => (array) $request->get_param( 'excludedWidgets' ),
			'excludedMenus'       => (array) $request->get_param( 'excludedMenus' ),
			'excludeCronRequests' => (bool) $request->get_param( 'excludeCronRequests' ),
		);

		update_option(
			'pastmark_exclude_settings',
			$settings
		);

		return $this->success_response( $settings );
	}

	/**
	 * Get defaults.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_default_settings() {

		return $this->success_response(
			InstallationExclude::get_default_settings()
		);
	}

	/**
	 * Get exclude setting options.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_options() {

		global $wp_roles;

		$roles = array();
		$post_types = array();
		$statuses = array();

		foreach ( $wp_roles->roles as $role_key => $role ) {
			$roles[] = array(
				'label' => $role['name'],
				'value' => $role_key,
			);
		}

		foreach ( get_post_types( array(), 'objects' ) as $post_type ) {
			$post_types[] = array(
				'label' => $post_type->labels->singular_name,
				'value' => $post_type->name,
			);
		}

		foreach ( get_post_stati( array(), 'objects' ) as $status ) {
			$statuses[] = array(
				'label' => $status->label,
				'value' => $status->name,
			);
		}

		return $this->success_response(
			array(
				'roles'     => $roles,
				'postTypes' => $post_types,
				'statuses'  => $statuses,
			)
		);
	}

	/**
	 * Search users.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function search_users( WP_REST_Request $request ) {

		$search = sanitize_text_field(
			$request->get_param( 'search' )
		);

		$users = get_users(
			array(
				'search'         => '*' . $search . '*',
				'search_columns' => array(
					'user_login',
					'user_email',
					'display_name',
				),
				'number'         => 20,
			)
		);

		$options = array();

		foreach ( $users as $user ) {

			$options[] = array(
				'value' => $user->ID,
				'label' => sprintf(
					'%s (#%d)',
					$user->display_name,
					$user->ID
				),
			);
		}

		return $this->success_response( $options );
	}

	/**
	 * Search installed plugins.
	 *
	 * Loaded on demand instead of all at once, since sites can easily have
	 * 50+ plugins installed and dumping them all into a single dropdown
	 * doesn't scale.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function search_plugins( WP_REST_Request $request ) {

		$search = sanitize_text_field(
			$request->get_param( 'search' )
		);

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$options = array();

		foreach ( get_plugins() as $plugin_file => $plugin ) {

			if ( '' !== $search && false === stripos( $plugin['Name'], $search ) ) {
				continue;
			}

			$options[] = array(
				'value' => $plugin_file,
				'label' => $plugin['Name'],
			);

			if ( count( $options ) >= 20 ) {
				break;
			}
		}

		return $this->success_response( $options );
	}

	/**
	 * Search installed themes.
	 *
	 * Loaded on demand instead of all at once, for the same reason as
	 * {@see self::search_plugins()}.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function search_themes( WP_REST_Request $request ) {

		$search = sanitize_text_field(
			$request->get_param( 'search' )
		);

		$options = array();

		foreach ( wp_get_themes() as $theme_slug => $theme ) {

			$name = $theme->get( 'Name' );

			if ( '' !== $search && false === stripos( $name, $search ) ) {
				continue;
			}

			$options[] = array(
				'value' => $theme_slug,
				'label' => $name,
			);

			if ( count( $options ) >= 20 ) {
				break;
			}
		}

		return $this->success_response( $options );
	}
}
