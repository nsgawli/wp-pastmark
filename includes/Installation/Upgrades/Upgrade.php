<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark\Installation\Upgrades;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Upgrade {

	/**
	 * Run the upgrade process.
	 *
	 * Each version-gated step below runs at most once per site: it only
	 * fires while the site's stored version is still behind the version
	 * that introduced it, and `Autoloader::set_installation_complete()`
	 * (called right after this) persists the new version so the same step
	 * won't run again on the next request.
	 *
	 * @param string $current_version Current version of the plugin.
	 * @version 1.0.0
	 * @return void
	 */
	public static function run( $current_version ) {

		if ( version_compare( $current_version, '1.0.1', '<' ) ) {
			self::add_actor_type_and_integration_columns();
			self::backfill_actor_type_and_integration();
		}
	}

	/**
	 * Add the `actor_type` and `integration` columns (plus their indexes)
	 * to `wp_pastmark_logs` for sites that installed the plugin before
	 * these fields existed.
	 *
	 * Uses explicit `ALTER TABLE` statements (rather than re-running
	 * `dbDelta()` against the full `CREATE TABLE` statement) so the added
	 * column position is under our control: each column is inserted with
	 * `AFTER`, matching the order it appears in
	 * `Autoloader::create_db_tables()`, so upgraded installs end up with a
	 * `DESCRIBE wp_pastmark_logs` output identical to a fresh install.
	 * Every step first checks whether the column/index already exists so
	 * this stays safe to run more than once.
	 *
	 * @return void
	 */
	private static function add_actor_type_and_integration_columns() {

		global $wpdb;

		$table = $wpdb->prefix . 'pastmark_logs';

		$columns = $wpdb->get_col( "DESCRIBE {$table}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! in_array( 'actor_type', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN actor_type varchar(20) DEFAULT 'human' AFTER severity" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( ! in_array( 'integration', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN integration varchar(50) DEFAULT 'core' AFTER actor_type" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$indexes = $wpdb->get_col( "SHOW INDEX FROM {$table}", 2 ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! in_array( 'idx_actor_type', $indexes, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_actor_type (actor_type)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( ! in_array( 'idx_integration', $indexes, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_integration (integration)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * Backfill `actor_type`/`integration` on rows written before those
	 * columns existed.
	 *
	 * `ALTER TABLE ... ADD COLUMN ... DEFAULT 'human'/'core'` (above) already
	 * leaves every pre-existing row with *a* value, so this is a data-quality
	 * pass, not a correctness requirement — a site that skips it still has a
	 * fully populated, just less accurate, column. Best-effort only: there's
	 * no way to recover a retroactive `scheduled` classification (WP-Cron
	 * doesn't leave a trace on the row), so this only distinguishes:
	 * - `actor_type`: `human` where `user_id` is recorded, otherwise `system`.
	 * - `integration`: `woocommerce` where `object_type` is one of the known
	 *   WooCommerce object types, otherwise `core`.
	 *
	 * Each rule runs as a loop of small `UPDATE ... LIMIT` batches (rather
	 * than one unbounded `UPDATE`) so it doesn't hold a long lock on a large
	 * log table on a busy site; each batch only touches rows that don't
	 * already have the target value, so the loop is self-terminating.
	 *
	 * @return void
	 */
	private static function backfill_actor_type_and_integration() {

		global $wpdb;

		$table      = $wpdb->prefix . 'pastmark_logs';
		$batch_size = 500;

		// Object types recorded by the WooCommerce loggers
		// (Order/Product/Coupon/ProductCategory/Review); everything else is
		// treated as core.
		$woocommerce_object_types = array(
			'shop_order',
			'product',
			'product_variation',
			'shop_coupon',
			'product_cat',
			'review',
		);

		$placeholders = implode( ',', array_fill( 0, count( $woocommerce_object_types ), '%s' ) );

		self::backfill_in_batches(
			$wpdb->prepare(
				"UPDATE {$table} SET actor_type = 'human' WHERE user_id > 0 AND actor_type != 'human' LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a fixed literal; %d is the only placeholder.
				$batch_size
			)
		);

		self::backfill_in_batches(
			$wpdb->prepare(
				"UPDATE {$table} SET actor_type = 'system' WHERE ( user_id IS NULL OR user_id = 0 ) AND actor_type != 'system' LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a fixed literal; %d is the only placeholder.
				$batch_size
			)
		);

		self::backfill_in_batches(
			$wpdb->prepare(
				"UPDATE {$table} SET integration = 'woocommerce' WHERE object_type IN ({$placeholders}) AND integration != 'woocommerce' LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- IN() length is dynamic; $placeholders holds exactly one %s per value in $woocommerce_object_types, followed by the %d batch-size placeholder.
				...array_merge( $woocommerce_object_types, array( $batch_size ) )
			)
		);

		self::backfill_in_batches(
			$wpdb->prepare(
				"UPDATE {$table} SET integration = 'core' WHERE ( object_type IS NULL OR object_type NOT IN ({$placeholders}) ) AND integration != 'core' LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- IN() length is dynamic; $placeholders holds exactly one %s per value in $woocommerce_object_types, followed by the %d batch-size placeholder.
				...array_merge( $woocommerce_object_types, array( $batch_size ) )
			)
		);
	}

	/**
	 * Repeat a single-batch `UPDATE ... LIMIT` query until a batch affects
	 * zero rows.
	 *
	 * Relies on the caller's query itself excluding rows that already hold
	 * the target value (e.g. `AND actor_type != 'human'`), so each batch
	 * only ever matches rows still needing the update and the loop is
	 * guaranteed to terminate.
	 *
	 * @param string $prepared_query Fully prepared `UPDATE ... LIMIT %d` query.
	 * @return void
	 */
	private static function backfill_in_batches( string $prepared_query ) {

		global $wpdb;

		do {
			$affected = $wpdb->query( $prepared_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- caller passes an already-prepared query.
		} while ( is_numeric( $affected ) && $affected > 0 );
	}
}
