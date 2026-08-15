<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Pastmark;

/**
 * Pastmark Functions
 *
 * @package Pastmark
 */
class Functions {
	/**
	 * Get sanitized IP address.
	 *
	 * @return string
	 */
	public static function get_ip_address() {
		$ip = '';
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return $ip;
	}

	/**
	 * Get sanitized user agent.
	 *
	 * @return string
	 */
	public static function get_user_agent() {
		$user_agent = '';
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		}
		return $user_agent;
	}
}
