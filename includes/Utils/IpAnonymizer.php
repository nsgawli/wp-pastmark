<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Anonymizes an IP address for storage, gated by the `anonymizeIp` Security
 * setting (`pastmark_security_settings`, see `Installation\Settings\Security`
 * and `RestApi\Settings\Security`).
 *
 * Deliberately **not** applied inside `AbstractLogger::get_ip_address()`
 * itself — that method's return value is also what `ExcludeHelper` matches
 * `excludedIPs` rules against, and that match must keep seeing the real
 * address. Anonymization is applied by `AbstractLogger::insert_log()` as a
 * separate, later step, only once exclusion matching has already run — see
 * that method for the exact ordering.
 */
class IpAnonymizer {

	/**
	 * Whether IP anonymization is currently turned on.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {

		$settings = get_option( 'pastmark_security_settings', array() );

		return ! empty( $settings['anonymizeIp'] );
	}

	/**
	 * Anonymize an IP for storage if the setting is on; return it unchanged
	 * otherwise.
	 *
	 * @param string $ip Real IP address (may be empty).
	 * @return string
	 */
	public static function maybe_anonymize( string $ip ): string {

		if ( '' === $ip || ! self::is_enabled() ) {
			return $ip;
		}

		return self::anonymize( $ip );
	}

	/**
	 * Anonymize an IPv4 or IPv6 address.
	 *
	 * IPv4: the last octet is zeroed (`192.168.1.42` → `192.168.1.0`).
	 * IPv6: truncated to its `/64` network prefix, matching the common
	 * "anonymize IP" convention other tools use (a `/64` is the smallest
	 * block normally assigned to a single customer, so this discards
	 * host-level detail the same way the IPv4 rule does, without going so
	 * far as to erase the whole address).
	 *
	 * An address that doesn't parse as either shape is returned unchanged
	 * rather than guessed at.
	 *
	 * @param string $ip Real IP address.
	 * @return string
	 */
	public static function anonymize( string $ip ): string {

		if ( false !== strpos( $ip, ':' ) ) {
			return self::anonymize_ipv6( $ip );
		}

		return self::anonymize_ipv4( $ip );
	}

	/**
	 * Zero the last octet of an IPv4 address.
	 *
	 * @param string $ip IPv4 address.
	 * @return string
	 */
	private static function anonymize_ipv4( string $ip ): string {

		$parts = explode( '.', $ip );

		if ( 4 !== count( $parts ) ) {
			return $ip;
		}

		$parts[3] = '0';

		return implode( '.', $parts );
	}

	/**
	 * Truncate an IPv6 address to its `/64` network prefix (first 8 bytes),
	 * zeroing the remaining 8 bytes (the interface identifier).
	 *
	 * @param string $ip IPv6 address.
	 * @return string
	 */
	private static function anonymize_ipv6( string $ip ): string {

		$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- inet_pton() warns on malformed input; a warning here would be noise, the false-return path already handles it.

		if ( false === $packed || 16 !== strlen( $packed ) ) {
			return $ip;
		}

		$truncated = substr( $packed, 0, 8 ) . str_repeat( "\0", 8 );

		$result = @inet_ntop( $truncated ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- same rationale as inet_pton() above.

		return false !== $result ? $result : $ip;
	}
}
