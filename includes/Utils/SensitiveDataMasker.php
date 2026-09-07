<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Masks sensitive values (passwords, tokens, secrets, API keys, ...) out of
 * log data before it's stored, so a plugin/theme's raw hook data — or an
 * external caller's `pastmark_log_event()`/REST payload — can never land a
 * plaintext secret in `wp_pastmark_logs`.
 *
 * Applied at `AbstractLogger::insert_log()`, the single choke point every
 * native logger and the Public Logging API (`Pastmark\Api\CustomEventLogger`)
 * already funnel through — so this protects both without either needing its
 * own masking logic.
 *
 * See `docs/SECURITY-PRIVACY-BASELINE.md` for the user/developer-facing
 * explanation of exactly what gets masked.
 */
class SensitiveDataMasker {

	/**
	 * Placeholder written in place of a masked value.
	 */
	const REDACTED = '***REDACTED***';

	/**
	 * Default key names treated as sensitive, matched case-insensitively.
	 *
	 * Deliberately a flat, exact-match list rather than substring matching —
	 * substring matching against short, common words like `pass` or `token`
	 * would over-match unrelated fields (e.g. a `passenger_count` field).
	 *
	 * @return string[]
	 */
	protected static function default_keys(): array {

		return array(
			'password',
			'pass',
			'pwd',
			'secret',
			'token',
			'api_key',
			'apikey',
			'access_token',
			'refresh_token',
			'authorization',
			'private_key',
			'client_secret',
		);
	}

	/**
	 * The active sensitive-key list, including any site/integrator additions.
	 *
	 * @return string[]
	 */
	protected static function get_sensitive_keys(): array {

		/**
		 * Filters the key names `SensitiveDataMasker` treats as sensitive.
		 *
		 * Matching is an exact, case-insensitive comparison against array
		 * keys (in `before_data`/`after_data`/`context`) and against
		 * `key=value`/`--key value` pairs embedded in free-form strings —
		 * not a substring match. Add a custom key name here (e.g. a
		 * third-party integration's own token field) without needing to
		 * edit Free's code.
		 *
		 * @param string[] $keys Default sensitive key names (lowercase).
		 */
		$keys = (array) apply_filters( 'pastmark_sensitive_field_keys', self::default_keys() );

		return array_values(
			array_filter(
				array_map(
					static function ( $key ) {
						return strtolower( trim( (string) $key ) );
					},
					$keys
				)
			)
		);
	}

	/**
	 * Mask sensitive values out of a `before_data`/`after_data`/`context`
	 * value, recursively.
	 *
	 * Accepts (and returns the same shape as) whatever a logger already
	 * passes: an array (native loggers' `context`), a JSON-encoded string
	 * (native loggers' `before_data`/`after_data`, always built via
	 * `wp_json_encode()`), or an arbitrary free-form string (the Public
	 * Logging API's `before_data`/`after_data`, which aren't required to be
	 * JSON). Anything else (int, bool, null) is returned unchanged.
	 *
	 * @param mixed $value Value to mask.
	 * @return mixed
	 */
	public static function mask( $value ) {

		$keys = self::get_sensitive_keys();

		if ( empty( $keys ) ) {
			return $value;
		}

		return self::mask_value( $value, $keys );
	}

	/**
	 * Dispatch masking by type.
	 *
	 * @param mixed    $value Value to mask.
	 * @param string[] $keys  Active sensitive key names (already lowercased).
	 * @return mixed
	 */
	protected static function mask_value( $value, array $keys ) {

		if ( is_array( $value ) ) {
			return self::mask_array( $value, $keys );
		}

		if ( is_string( $value ) ) {
			return self::mask_string( $value, $keys );
		}

		return $value;
	}

	/**
	 * Mask an array recursively: a key matching the sensitive list gets its
	 * entire value replaced, regardless of type; every other key's value is
	 * still masked in case it's itself sensitive (nested arrays) or a string
	 * carrying an embedded `key=value` pair.
	 *
	 * @param array    $data Array to mask.
	 * @param string[] $keys Active sensitive key names (already lowercased).
	 * @return array
	 */
	protected static function mask_array( array $data, array $keys ): array {

		$masked = array();

		foreach ( $data as $key => $value ) {

			if ( is_string( $key ) && in_array( strtolower( $key ), $keys, true ) ) {
				$masked[ $key ] = self::REDACTED;
				continue;
			}

			$masked[ $key ] = self::mask_value( $value, $keys );
		}

		return $masked;
	}

	/**
	 * Mask a string value.
	 *
	 * If the string is valid JSON (the shape every native logger's
	 * `before_data`/`after_data` is always built as), decode, mask
	 * recursively, and re-encode — this is the common case. Otherwise, treat
	 * it as free-form text (the Public Logging API's case, or a URL/query
	 * string embedded in `context`) and mask `key=value`/`--key value` pairs
	 * in place without disturbing the rest of the string.
	 *
	 * @param string   $value String to mask.
	 * @param string[] $keys  Active sensitive key names (already lowercased).
	 * @return string
	 */
	protected static function mask_string( string $value, array $keys ): string {

		$trimmed = trim( $value );

		if ( '' !== $trimmed && ( '{' === $trimmed[0] || '[' === $trimmed[0] ) ) {

			$decoded = json_decode( $trimmed, true );

			if ( JSON_ERROR_NONE === json_last_error() && ( is_array( $decoded ) ) ) {

				$masked  = self::mask_value( $decoded, $keys );
				$encoded = wp_json_encode( $masked );

				if ( false !== $encoded ) {
					return $encoded;
				}
			}
		}

		return self::mask_key_value_pairs( $value, $keys );
	}

	/**
	 * Mask `key=value`, `key: value`, `--key=value`, and `--key value`
	 * style pairs embedded in a free-form string — covers query strings and
	 * command-line arguments per the roadmap's explicit requirement that
	 * masking not be limited to top-level field names.
	 *
	 * Deliberately requires a `=`, `:`, or a leading `-`/`--` immediately
	 * before the key to fire — a bare key name appearing in ordinary prose
	 * (e.g. a message like "the password field is required") is left alone,
	 * keeping false positives on human-readable messages low.
	 *
	 * @param string   $value String to mask.
	 * @param string[] $keys  Active sensitive key names (already lowercased).
	 * @return string
	 */
	protected static function mask_key_value_pairs( string $value, array $keys ): string {

		$escaped_keys = array_map(
			static function ( $key ) {
				return preg_quote( $key, '/' );
			},
			$keys
		);

		$key_alternation = implode( '|', $escaped_keys );

		// `key=value` / `key: value` (query strings, "key: value" log lines).
		// The separator itself (`=`, `:`, and any surrounding whitespace) is
		// captured and reused as-is, so masking doesn't alter formatting
		// beyond the value it's actually redacting.
		$value = (string) preg_replace_callback(
			'/\b(' . $key_alternation . ')(\s*[:=]\s*)([^\s&]+)/i',
			static function ( $matches ) {
				return $matches[1] . $matches[2] . self::REDACTED;
			},
			$value
		);

		// `--key=value` / `--key value` (command-line arguments).
		$value = (string) preg_replace_callback(
			'/(--?(?:' . $key_alternation . '))([=\s]+)([^\s&]+)/i',
			static function ( $matches ) {
				return $matches[1] . $matches[2] . self::REDACTED;
			},
			$value
		);

		return $value;
	}
}
