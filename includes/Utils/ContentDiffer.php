<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Computes a compact, human-readable diff between a "before" and "after"
 * string for large content fields (post content, comment content), so a
 * single edit doesn't duplicate the entire field twice into `before_data`/
 * `after_data`.
 *
 * Deliberately dependency-free pure PHP — no diff/patch library exists in
 * `composer.json` (its `require` is empty), matching this plugin's existing
 * zero-runtime-dependency footprint.
 *
 * `diff()` is a pure computation with an explicit `null` "not worth it"
 * signal; it does not decide *whether* to call itself or what to do with the
 * result — `PostActivityLogger`/`CommentActivityLogger` (PM-142/PM-143) own
 * that wiring, storing the diff string when non-null and falling back to
 * today's full-before/full-after storage otherwise.
 */
class ContentDiffer {

	/**
	 * Below this many lines-on-both-sides product, the LCS table used to
	 * compute the diff is cheap enough to build unconditionally. Above it,
	 * `diff()` bails out to `null` rather than building a table that could
	 * run into tens/hundreds of millions of cells — a safety valve, not one
	 * of the four filterable thresholds (there's no reasonable site-level
	 * reason to raise it; a diff this large wouldn't be "compact" anyway).
	 */
	const MAX_LCS_CELLS = 4_000_000;

	/**
	 * Diff two strings, returning a compact unified-diff-style string when
	 * diffing is worthwhile, or `null` as an explicit "fall back to storing
	 * both full values" signal.
	 *
	 * `null` is returned (in order) when: the strings are identical; either
	 * is malformed (non-UTF-8); either is below the minimum-size threshold;
	 * the content is too large to diff cheaply; the diff isn't meaningfully
	 * smaller than the two full values combined; or the diff output itself
	 * exceeds the output-size cap.
	 *
	 * @param string $before Content before the change.
	 * @param string $after  Content after the change.
	 * @return string|null
	 */
	public static function diff( string $before, string $after ): ?string {

		if ( $before === $after ) {
			return null;
		}

		if ( ! self::is_valid_utf8( $before ) || ! self::is_valid_utf8( $after ) ) {
			return null;
		}

		$min_size = self::get_min_size();

		if ( mb_strlen( $before, 'UTF-8' ) < $min_size || mb_strlen( $after, 'UTF-8' ) < $min_size ) {
			return null;
		}

		$before_lines = self::split_lines( $before );
		$after_lines  = self::split_lines( $after );

		if ( count( $before_lines ) * count( $after_lines ) > self::MAX_LCS_CELLS ) {
			return null;
		}

		$ops       = self::diff_lines( $before_lines, $after_lines );
		$diff_text = self::format_unified_diff( $ops, self::get_context_lines() );

		if ( '' === $diff_text ) {
			// Every op collapsed to "equal" (e.g. line-ending shape changed
			// but no line content actually differs) — nothing worth storing.
			return null;
		}

		$diff_length     = mb_strlen( $diff_text, 'UTF-8' );
		$combined_length = mb_strlen( $before, 'UTF-8' ) + mb_strlen( $after, 'UTF-8' );
		$ratio           = self::get_worthwhile_ratio();

		if ( $combined_length > 0 && $diff_length >= $ratio * $combined_length ) {
			return null;
		}

		if ( $diff_length > self::get_max_output_size() ) {
			return null;
		}

		return $diff_text;
	}

	/**
	 * Minimum length (of *both* inputs) below which diffing is skipped —
	 * the common case of a short post/comment edit sees zero behavior
	 * change from before this feature existed.
	 *
	 * @return int
	 */
	protected static function get_min_size(): int {

		/**
		 * Filters the minimum character length (checked against both the
		 * before and after strings) below which `ContentDiffer::diff()`
		 * skips diffing entirely and returns `null`.
		 *
		 * @param int $min_size Minimum length, in characters. Default 2000.
		 */
		return (int) apply_filters( 'pastmark_content_diff_min_size', 2000 );
	}

	/**
	 * Ratio of `diff output length` to `combined before+after length` at or
	 * above which the diff is judged not worth storing (guards against
	 * near-total rewrites, where a diff adds overhead instead of saving
	 * space).
	 *
	 * @return float
	 */
	protected static function get_worthwhile_ratio(): float {

		/**
		 * Filters the "not worth it" ratio: when the diff output's length is
		 * at least this fraction of the combined before+after length,
		 * `ContentDiffer::diff()` falls back to `null`.
		 *
		 * @param float $ratio Ratio between 0 and 1. Default 0.8 (80%).
		 */
		return (float) apply_filters( 'pastmark_content_diff_worthwhile_ratio', 0.8 );
	}

	/**
	 * Hard cap on the diff output's own length — an extreme pathological
	 * diff falls back to `null` rather than producing an unbounded string.
	 *
	 * @return int
	 */
	protected static function get_max_output_size(): int {

		/**
		 * Filters the maximum length, in characters, of the diff string
		 * `ContentDiffer::diff()` will return. Longer output falls back to
		 * `null`.
		 *
		 * @param int $max_size Maximum length, in characters. Default 50000.
		 */
		return (int) apply_filters( 'pastmark_content_diff_max_output_size', 50000 );
	}

	/**
	 * Number of unchanged context lines kept around each change, matching
	 * the standard unified-diff convention.
	 *
	 * @return int
	 */
	protected static function get_context_lines(): int {

		/**
		 * Filters the number of unchanged context lines `ContentDiffer`
		 * keeps around each changed line, matching the standard
		 * unified-diff convention.
		 *
		 * @param int $context_lines Number of context lines. Default 3.
		 */
		return max( 0, (int) apply_filters( 'pastmark_content_diff_context_lines', 3 ) );
	}

	/**
	 * Whether a string is valid, well-formed UTF-8.
	 *
	 * @param string $value Value to check.
	 * @return bool
	 */
	protected static function is_valid_utf8( string $value ): bool {

		return mb_check_encoding( $value, 'UTF-8' );
	}

	/**
	 * Split a string into lines the way `diff` tools do: on `\n`, keeping
	 * empty trailing lines meaningful (so a trailing-newline-only change
	 * still shows up as a real diff instead of silently vanishing).
	 *
	 * @param string $value Value to split.
	 * @return string[]
	 */
	protected static function split_lines( string $value ): array {

		return explode( "\n", $value );
	}

	/**
	 * Compute a line-level edit script (equal/delete/insert operations)
	 * between two line arrays via the standard LCS (longest common
	 * subsequence) backtracking algorithm.
	 *
	 * @param string[] $before_lines Lines before the change.
	 * @param string[] $after_lines  Lines after the change.
	 * @return array<int, array{type: string, text: string}>
	 */
	protected static function diff_lines( array $before_lines, array $after_lines ): array {

		$n = count( $before_lines );
		$m = count( $after_lines );

		// LCS length table: $lcs[$i][$j] = length of the LCS of
		// before_lines[$i..] and after_lines[$j..]. Built bottom-up so the
		// backtrack below can walk forward from (0, 0).
		$lcs = array_fill( 0, $n + 1, array_fill( 0, $m + 1, 0 ) );

		for ( $i = $n - 1; $i >= 0; $i-- ) {
			for ( $j = $m - 1; $j >= 0; $j-- ) {
				if ( $before_lines[ $i ] === $after_lines[ $j ] ) {
					$lcs[ $i ][ $j ] = $lcs[ $i + 1 ][ $j + 1 ] + 1;
				} else {
					$lcs[ $i ][ $j ] = max( $lcs[ $i + 1 ][ $j ], $lcs[ $i ][ $j + 1 ] );
				}
			}
		}

		$ops = array();
		$i   = 0;
		$j   = 0;

		while ( $i < $n && $j < $m ) {

			if ( $before_lines[ $i ] === $after_lines[ $j ] ) {
				$ops[] = array(
					'type' => 'equal',
					'text' => $before_lines[ $i ],
				);
				++$i;
				++$j;
				continue;
			}

			if ( $lcs[ $i + 1 ][ $j ] >= $lcs[ $i ][ $j + 1 ] ) {
				$ops[] = array(
					'type' => 'delete',
					'text' => $before_lines[ $i ],
				);
				++$i;
			} else {
				$ops[] = array(
					'type' => 'insert',
					'text' => $after_lines[ $j ],
				);
				++$j;
			}
		}

		while ( $i < $n ) {
			$ops[] = array(
				'type' => 'delete',
				'text' => $before_lines[ $i ],
			);
			++$i;
		}

		while ( $j < $m ) {
			$ops[] = array(
				'type' => 'insert',
				'text' => $after_lines[ $j ],
			);
			++$j;
		}

		return $ops;
	}

	/**
	 * Render an edit script as a compact unified-diff-style string: only
	 * hunks around actual changes are included (each padded with up to
	 * `$context_lines` of unchanged lines on either side), not the entire
	 * content — this is what keeps the output "compact" for a small change
	 * inside otherwise-large content.
	 *
	 * @param array<int, array{type: string, text: string}> $ops           Edit script from `diff_lines()`.
	 * @param int                                           $context_lines Unchanged lines to keep around each change.
	 * @return string Empty string if there are no changed lines at all.
	 */
	protected static function format_unified_diff( array $ops, int $context_lines ): string {

		$total = count( $ops );

		$change_indices = array();

		foreach ( $ops as $index => $op ) {
			if ( 'equal' !== $op['type'] ) {
				$change_indices[] = $index;
			}
		}

		if ( empty( $change_indices ) ) {
			return '';
		}

		// Group changes into hunks, expanding each by $context_lines and
		// merging hunks whose expanded ranges touch or overlap.
		$ranges      = array();
		$range_start = max( 0, $change_indices[0] - $context_lines );
		$range_end   = min( $total - 1, $change_indices[0] + $context_lines );

		for ( $k = 1, $count = count( $change_indices ); $k < $count; $k++ ) {

			$index     = $change_indices[ $k ];
			$new_start = max( 0, $index - $context_lines );

			if ( $new_start <= $range_end + 1 ) {
				$range_end = min( $total - 1, $index + $context_lines );
				continue;
			}

			$ranges[]    = array( $range_start, $range_end );
			$range_start = $new_start;
			$range_end   = min( $total - 1, $index + $context_lines );
		}

		$ranges[] = array( $range_start, $range_end );

		// Track each op's 1-based line number in the before/after sequence,
		// needed for the "@@ -a,b +c,d @@" hunk headers.
		$before_line_no = array();
		$after_line_no  = array();
		$b              = 1;
		$a              = 1;

		foreach ( $ops as $index => $op ) {

			$before_line_no[ $index ] = $b;
			$after_line_no[ $index ]  = $a;

			if ( 'equal' === $op['type'] ) {
				++$b;
				++$a;
			} elseif ( 'delete' === $op['type'] ) {
				++$b;
			} else {
				++$a;
			}
		}

		$output = array();

		foreach ( $ranges as $range ) {

			list( $start, $end ) = $range;

			$before_count = 0;
			$after_count  = 0;
			$lines        = array();

			for ( $index = $start; $index <= $end; $index++ ) {

				$op = $ops[ $index ];

				if ( 'equal' === $op['type'] ) {
					$lines[] = ' ' . $op['text'];
					++$before_count;
					++$after_count;
				} elseif ( 'delete' === $op['type'] ) {
					$lines[] = '-' . $op['text'];
					++$before_count;
				} else {
					$lines[] = '+' . $op['text'];
					++$after_count;
				}
			}

			$output[] = sprintf(
				'@@ -%d,%d +%d,%d @@',
				$before_line_no[ $start ],
				$before_count,
				$after_line_no[ $start ],
				$after_count
			);

			array_push( $output, ...$lines );
		}

		return implode( "\n", $output );
	}
}
