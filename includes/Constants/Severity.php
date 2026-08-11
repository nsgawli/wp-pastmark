<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace LogTrail\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Severity constants.
 */
class Severity {

	/**
	 * Info severity.
	 */
	public const INFO = 'info';

	/**
	 * Warning severity.
	 */
	public const WARNING = 'warning';

	/**
	 * Error severity.
	 */
	public const ERROR = 'error';

	/**
	 * Critical severity.
	 */
	public const CRITICAL = 'critical';

	/**
	 * Debug severity.
	 */
	public const DEBUG = 'debug';

	/**
	 * Resolve severity label from known keys with fallback.
	 *
	 * @param string $severity_key Severity key.
	 * @return string
	 */
	public static function resolve_label( string $severity_key ): string {

		$labels = self::get_labels();

		if ( isset( $labels[ $severity_key ] ) ) {
			return $labels[ $severity_key ];
		}

		return ucwords(
			str_replace(
				'_',
				' ',
				$severity_key
			)
		);
	}

	/**
	 * Get memoized severity labels map.
	 *
	 * @return array
	 */
	private static function get_labels(): array {

		static $labels = null;

		if ( null !== $labels ) {
			return $labels;
		}

		$labels = array(
			self::INFO     => __( 'Info', 'logtrail' ),
			self::WARNING  => __( 'Warning', 'logtrail' ),
			self::ERROR    => __( 'Error', 'logtrail' ),
			self::CRITICAL => __( 'Critical', 'logtrail' ),
			self::DEBUG    => __( 'Debug', 'logtrail' ),
		);

		return $labels;
	}
}
