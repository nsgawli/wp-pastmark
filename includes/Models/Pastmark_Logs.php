<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

namespace Pastmark\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;

/**
 * Class Pastmark_Logs
 */
class Pastmark_Logs {


	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->table = $wpdb->prefix . 'pastmark_logs';
	}

	/**
	 * Insert single log.
	 *
	 * @param  array $log Log data.
	 * @return int|false
	 */
	public function insert( array $log ) {

		global $wpdb;

		$defaults = array(
			'timestamp'   => current_time( 'mysql', true ),
			'user_id'     => 0,
			'ip_address'  => '',
			'event_type'  => '',
			'object_type' => '',
			'object_id'   => 0,
			'action'      => '',
			'message'     => '',
			'before_data' => '',
			'after_data'  => '',
			'context'     => '',
			'severity'    => 'info',
			'site_id'     => get_current_blog_id(),
		);

		$log = wp_parse_args( $log, $defaults );

		$inserted = $wpdb->insert(
			$this->table,
			array(
				'timestamp'   => $log['timestamp'],
				'user_id'     => $log['user_id'],
				'ip_address'  => $log['ip_address'],
				'event_type'  => $log['event_type'],
				'object_type' => $log['object_type'],
				'object_id'   => $log['object_id'],
				'action'      => $log['action'],
				'message'     => $log['message'],
				'before_data' => $log['before_data'],
				'after_data'  => $log['after_data'],
				'context'     => $log['context'],
				'severity'    => $log['severity'],
				'site_id'     => $log['site_id'],
			),
			array(
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
			)
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Bulk insert logs.
	 *
	 * @param  array $logs Logs.
	 * @return void
	 */
	public function insert_bulk( array $logs ): void {

		global $wpdb;

		if ( empty( $logs ) ) {
			return;
		}

		foreach ( $logs as $log ) {

			$wpdb->insert(
				$this->table,
				array(
					'timestamp'   => $log['timestamp'],
					'user_id'     => $log['user_id'],
					'ip_address'  => $log['ip_address'],
					'event_type'  => $log['event_type'],
					'object_type' => $log['object_type'],
					'object_id'   => $log['object_id'],
					'action'      => $log['action'],
					'message'     => $log['message'],
					'before_data' => $log['before_data'],
					'after_data'  => $log['after_data'],
					'context'     => $log['context'],
					'severity'    => $log['severity'],
					'site_id'     => $log['site_id'],
				),
				array(
					'%s',
					'%d',
					'%s',
					'%s',
					'%s',
					'%d',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
				)
			);
		}
	}

	/**
	 * Get logs.
	 *
	 * @param  array $args Query args.
	 * @return array
	 */
	public function get_logs( array $args = array() ): array {

		global $wpdb;

		$defaults = array(
			'number'     => 20,
			'offset'     => 0,
			'search'     => '',
			'severity'   => array(),
			'event'      => array(),
			'user_ids'   => array(),
			'ids'        => array(),
			'date_from'  => '',
			'date_to'    => '',
			'ip_address' => '',
			'orderby'    => 'id',
			'order'      => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = $this->build_where_clause( $args );

		$allowed_orderby = array(
			'id',
			'timestamp',
			'event_type',
			'severity',
			'user_id',
		);

		$orderby = in_array(
			$args['orderby'],
			$allowed_orderby,
			true
		)
		? $args['orderby']
		: 'id';

		$order = strtoupper( $args['order'] ) === 'ASC'
		? 'ASC'
		: 'DESC';

		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", $this->table, $args['number'], $args['offset'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where is pre-escaped via build_where_clause(); $orderby/$order are validated against a fixed allow-list above.

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Count logs.
	 *
	 * @param  array $args Query args.
	 * @return int
	 */
	public function count_logs( array $args = array() ): int {

		global $wpdb;

		$defaults = array(
			'search'     => '',
			'severity'   => array(),
			'event'      => array(),
			'user_ids'   => array(),
			'ids'        => array(),
			'date_from'  => '',
			'date_to'    => '',
			'ip_address' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = $this->build_where_clause( $args );

		$total = $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where is pre-escaped via build_where_clause().
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i {$where}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is pre-escaped via build_where_clause().
				$this->table
			)
		);

		return (int) $total;
	}

	/**
	 * Build SQL where clause for log filters.
	 *
	 * @param  array $args Query args.
	 * @return string
	 */
	private function build_where_clause( array $args ): string {

		global $wpdb;

		$where = 'WHERE 1=1';

		if ( ! empty( $args['search'] ) ) {

			$search_value = sanitize_text_field( (string) $args['search'] );
			$search_like  = '%' . $wpdb->esc_like( $search_value ) . '%';

			if ( ctype_digit( $search_value ) ) {

				$where .= $wpdb->prepare(
					' AND (message LIKE %s OR action LIKE %s OR id = %d OR object_id = %d OR user_id = %d)',
					$search_like,
					$search_like,
					(int) $search_value,
					(int) $search_value,
					(int) $search_value
				);
			} else {

				$where .= $wpdb->prepare(
					' AND (message LIKE %s OR action LIKE %s)',
					$search_like,
					$search_like
				);
			}
		}

		$severities = $this->normalize_text_values( $args['severity'] ?? array() );

		if ( ! empty( $severities ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $severities ), '%s' ) );

			$where .= $wpdb->prepare(
				" AND severity IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- IN() length is dynamic; $placeholders holds exactly one %s per value in $severities below.
				...$severities
			);
		}

		$events = $this->normalize_text_values( $args['event'] ?? array() );

		if ( ! empty( $events ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $events ), '%s' ) );

			$where .= $wpdb->prepare(
				" AND event_type IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- IN() length is dynamic; $placeholders holds exactly one %s per value in $events below.
				...$events
			);
		}

		$user_ids = $this->normalize_int_values( $args['user_ids'] ?? array() );

		if ( ! empty( $user_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );

			$where .= $wpdb->prepare(
				" AND user_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- IN() length is dynamic; $placeholders holds exactly one %d per value in $user_ids below.
				...$user_ids
			);
		}

		$ids = $this->normalize_int_values( $args['ids'] ?? array() );

		if ( ! empty( $ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

			$where .= $wpdb->prepare(
				" AND id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- IN() length is dynamic; $placeholders holds exactly one %d per value in $ids below.
				...$ids
			);
		}

		$date_from = ! empty( $args['date_from'] )
		? sanitize_text_field( (string) $args['date_from'] )
		: '';

		$date_to = ! empty( $args['date_to'] )
		? sanitize_text_field( (string) $args['date_to'] )
		: '';

		if ( '' !== $date_from && '' !== $date_to ) {
			$where .= $wpdb->prepare(
				' AND timestamp BETWEEN %s AND %s',
				$date_from,
				$date_to
			);
		}

		if ( ! empty( $args['ip_address'] ) ) {
			$where .= $wpdb->prepare(
				' AND ip_address = %s',
				sanitize_text_field( (string) $args['ip_address'] )
			);
		}

		return $where;
	}

	/**
	 * Normalize list of text values.
	 *
	 * @param  mixed $value Source value.
	 * @return array
	 */
	private function normalize_text_values( $value ): array {

		$values = is_array( $value )
		? $value
		: explode( ',', (string) $value );

		$values = array_map(
			static function ( $item ) {
				return sanitize_text_field( trim( (string) $item ) );
			},
			$values
		);

		$values = array_values(
			array_unique(
				array_filter( $values )
			)
		);

		return $values;
	}

	/**
	 * Normalize list of integer values.
	 *
	 * @param  mixed $value Source value.
	 * @return array
	 */
	private function normalize_int_values( $value ): array {

		$values = is_array( $value )
		? $value
		: explode( ',', (string) $value );

		$values = array_map( 'absint', $values );

		$values = array_values(
			array_unique(
				array_filter( $values )
			)
		);

		return $values;
	}

	/**
	 * Get a single log entry by ID.
	 *
	 * @param  int $id Log ID.
	 * @return object|null
	 */
	public function get_log_by_id( int $id ) {

		global $wpdb;

		$item = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$this->table,
				$id
			)
		);

		return $item ? $item : null;
	}

	/**
	 * Get distinct users who have log entries, optionally filtered by search term.
	 *
	 * @param  string $search Search term matched against display name, login, or user ID.
	 * @param  int    $limit  Result limit.
	 * @return array
	 */
	public function get_distinct_users( string $search = '', int $limit = 50 ): array {

		global $wpdb;

		$where = 'WHERE l.user_id > 0';

		if ( '' !== $search ) {

			$like = '%' . $wpdb->esc_like( $search ) . '%';

			$where .= $wpdb->prepare(
				' AND (u.display_name LIKE %s OR u.user_login LIKE %s OR CAST(l.user_id AS CHAR) LIKE %s)',
				$like,
				$like,
				$like
			);
		}

		$results = $wpdb->get_results( $wpdb->prepare( 'SELECT DISTINCT l.user_id, u.display_name, u.user_login FROM %i l LEFT JOIN %i u ON u.ID = l.user_id ' . $where . ' ORDER BY l.user_id DESC LIMIT %d', $this->table, $wpdb->users, $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where is pre-escaped via $wpdb->prepare() above.

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get users matching a specific set of user IDs who have log entries.
	 *
	 * Used to resolve display labels for already-selected filter values
	 * (e.g. re-hydrating the advanced filters form) regardless of whether
	 * those users would appear within the default/search-limited result set.
	 *
	 * @param  array $user_ids User IDs to resolve.
	 * @return array
	 */
	public function get_users_by_ids( array $user_ids ): array {

		global $wpdb;

		$user_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $user_ids )
				)
			)
		);

		if ( empty( $user_ids ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $user_ids ), '%d' ) );

		$in_clause = $wpdb->prepare( "l.user_id IN ({$placeholders})", ...$user_ids ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- IN() length is dynamic; $placeholders holds exactly one %d per value in $user_ids above.

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT l.user_id, u.display_name, u.user_login FROM %i l LEFT JOIN %i u ON u.ID = l.user_id WHERE {$in_clause}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $in_clause is pre-escaped via $wpdb->prepare() above.
				$this->table,
				$wpdb->users
			)
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get distinct event types, optionally filtered by search term.
	 *
	 * @param  string $search Search term.
	 * @param  int    $limit  Result limit.
	 * @return array
	 */
	public function get_distinct_event_types( string $search = '', int $limit = 100 ): array {

		global $wpdb;

		$where = "WHERE event_type <> ''";

		if ( '' !== $search ) {

			$like = '%' . $wpdb->esc_like( $search ) . '%';

			$where .= $wpdb->prepare( ' AND event_type LIKE %s', $like );
		}

		$results = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT event_type FROM %i ' . $where . ' ORDER BY event_type ASC LIMIT %d', $this->table, $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where is pre-escaped via $wpdb->prepare() above.

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get distinct log IDs, optionally filtered by search term.
	 *
	 * @param  string $search Search term.
	 * @param  int    $limit  Result limit.
	 * @return array
	 */
	public function get_distinct_ids( string $search = '', int $limit = 100 ): array {

		global $wpdb;

		$where = '';

		if ( '' !== $search ) {

			$like  = '%' . $wpdb->esc_like( $search ) . '%';
			$where = $wpdb->prepare( 'WHERE CAST(id AS CHAR) LIKE %s', $like );
		}

		$results = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i ' . $where . ' ORDER BY id DESC LIMIT %d', $this->table, $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where is pre-escaped via $wpdb->prepare() above.

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get log IDs matching a specific set of IDs, restricted to IDs that exist.
	 *
	 * Used to resolve already-selected filter values (e.g. re-hydrating the
	 * advanced filters form) regardless of the default result-set limit.
	 *
	 * @param  array $ids Log IDs to resolve.
	 * @return array
	 */
	public function get_ids_that_exist( array $ids ): array {

		global $wpdb;

		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $ids )
				)
			)
		);

		if ( empty( $ids ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		$results = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE id IN (' . $placeholders . ') ORDER BY id DESC', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- placeholders are `%d` tokens consumed by $wpdb->prepare() below.
				array_merge( array( $this->table ), $ids )
			)
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Delete logs older than a UTC datetime.
	 *
	 * @param  string $utc_datetime UTC datetime in MySQL format.
	 * @return int
	 */
	public function delete_logs_older_than( string $utc_datetime ): int {

		global $wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE timestamp < %s',
				$this->table,
				$utc_datetime
			)
		);

		return is_numeric( $deleted )
		? (int) $deleted
		: 0;
	}

	/**
	 * Anonymize the IP address recorded against a user's log entries.
	 *
	 * Used to fulfil WordPress "Erase Personal Data" requests. The log
	 * entries themselves are retained for security/audit purposes; only
	 * the identifying IP address is cleared.
	 *
	 * @param  int $user_id User ID.
	 * @return int Number of rows updated.
	 */
	public function anonymize_logs_for_user( int $user_id ): int {

		global $wpdb;

		$updated = $wpdb->update(
			$this->table,
			array( 'ip_address' => '' ),
			array( 'user_id' => $user_id ),
			array( '%s' ),
			array( '%d' )
		);

		return is_numeric( $updated ) ? (int) $updated : 0;
	}

	/**
	 * Delete all logs.
	 *
	 * @return int
	 */
	public function delete_all_logs(): int {

		global $wpdb;

		$deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $this->table ) );

		return is_numeric( $deleted )
		? (int) $deleted
		: 0;
	}

	/**
	 * Drop the plugin logs table.
	 *
	 * Used during plugin uninstall cleanup.
	 *
	 * @return void
	 */
	public function drop_table(): void {

		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $this->table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	/**
	 * Get dashboard statistics.
	 *
	 * @return array
	 */
	public function get_stats(): array {

		global $wpdb;

		$total_logs = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $this->table )
		);

		$warnings = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE severity = %s',
				$this->table,
				'warning'
			)
		);

		$users = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(DISTINCT user_id) FROM %i WHERE user_id > 0', $this->table )
		);

		$security_events = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE event_type = %s',
				$this->table,
				'authentication'
			)
		);

		return array(
			'total_logs'      => $total_logs,
			'warnings'        => $warnings,
			'users'           => $users,
			'security_events' => $security_events,
		);
	}

	/**
	 * Get dashboard summary.
	 *
	 * @param  string $start_date Start date.
	 * @param  string $end_date   End date.
	 * @return array
	 */
	public function get_dashboard_summary(
		string $start_date,
		string $end_date
	): array {

		global $wpdb;

		$where = $wpdb->prepare(
			' WHERE timestamp BETWEEN %s AND %s ',
			$start_date,
			$end_date
		);

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i {$where}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is pre-escaped via $wpdb->prepare() above.
				$this->table
			)
		);

		$critical = (int) $wpdb->get_var(
			$wpdb->prepare(
				'
				SELECT COUNT(*)
				FROM %i
				WHERE timestamp BETWEEN %s AND %s
				AND severity=\'critical\'
				',
				$this->table,
				$start_date,
				$end_date
			)
		);

		$users = (int) $wpdb->get_var(
			$wpdb->prepare(
				'
				SELECT COUNT(DISTINCT user_id)
				FROM %i
				WHERE timestamp BETWEEN %s AND %s
				AND user_id > 0
				',
				$this->table,
				$start_date,
				$end_date
			)
		);

		return array(
			'total_events'    => $total,
			'critical_events' => $critical,
			'active_users'    => $users,
		);
	}

	/**
	 * Get activity timeline.
	 *
	 * @param  string $start_date Start date.
	 * @param  string $end_date   End date.
	 * @return array
	 */
	public function get_activity_timeline(
		string $start_date,
		string $end_date
	): array {

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'
				SELECT
					DATE(timestamp) AS label,
					COUNT(*) AS total
				FROM %i
				WHERE timestamp BETWEEN %s AND %s
				GROUP BY DATE(timestamp)
				ORDER BY DATE(timestamp)
				',
				$this->table,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$counts_by_date = array();

		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				$counts_by_date[ $row['label'] ] = (int) $row['total'];
			}
		}

		/*
		 * Zero-fill every day in the range so the widget always renders a
		 * continuous line, even when logs only exist on one day (e.g. a
		 * fresh install) or the range would otherwise return a single row.
		 */

		$timeline = array();

		$cursor = strtotime( gmdate( 'Y-m-d', strtotime( $start_date ) ) );
		$last   = strtotime( gmdate( 'Y-m-d', strtotime( $end_date ) ) );

		while ( $cursor <= $last ) {

			$date = gmdate( 'Y-m-d', $cursor );

			$timeline[] = array(
				'label' => $date,
				'total' => $counts_by_date[ $date ] ?? 0,
			);

			$cursor = strtotime( '+1 day', $cursor );
		}

		return $timeline;
	}

	/**
	 * Get severity distribution.
	 *
	 * @param  string $start_date Start date.
	 * @param  string $end_date   End date.
	 * @return array
	 */
	public function get_severity_distribution(
		string $start_date,
		string $end_date
	): array {

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'
				SELECT
					severity AS label,
					COUNT(*) AS total
				FROM %i
				WHERE timestamp BETWEEN %s AND %s
				GROUP BY severity
				ORDER BY total DESC
				',
				$this->table,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		if ( ! is_array( $results ) ) {
			return array();
		}

		foreach ( $results as &$result ) {

			$result['value'] = $result['label'];
			$result['label'] = Severity::resolve_label( (string) $result['label'] );
		}

		return is_array( $results )
		? $results
		: array();
	}

	/**
	 * Get top event types.
	 *
	 * @param  string $start_date Start date.
	 * @param  string $end_date   End date.
	 * @param  int    $limit      Limit.
	 * @return array
	 */
	public function get_top_event_types(
		string $start_date,
		string $end_date,
		int $limit = 10
	): array {

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'
				SELECT
					event_type,
					COUNT(*) AS total
				FROM %i
				WHERE timestamp BETWEEN %s AND %s
				GROUP BY event_type
				ORDER BY total DESC
				LIMIT %d
				',
				$this->table,
				$start_date,
				$end_date,
				$limit
			),
			ARRAY_A
		);

		return is_array( $results )
		? $results
		: array();
	}

	/**
	 * Get category distribution.
	 *
	 * @param  string $start_date Start date.
	 * @param  string $end_date   End date.
	 * @return array
	 */
	public function get_top_categories(
		string $start_date,
		string $end_date
	): array {

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'
				SELECT
					event_type AS label,
					COUNT(*) AS total
				FROM %i
				WHERE timestamp BETWEEN %s AND %s
				GROUP BY event_type
				ORDER BY total DESC
				LIMIT 8
				',
				$this->table,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		if ( ! is_array( $results ) ) {
			return array();
		}

		foreach ( $results as &$result ) {

			$result['value'] = $result['label'];
			$result['label'] = Events::resolve_label( (string) $result['label'] );
		}

		return is_array( $results )
		? $results
		: array();
	}

	/**
	 * Get most active users.
	 *
	 * @param  string $start_date Start date.
	 * @param  string $end_date   End date.
	 * @param  int    $limit      Limit.
	 * @return array
	 */
	public function get_top_users(
		string $start_date,
		string $end_date,
		int $limit = 5
	): array {

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'
				SELECT
					user_id,
					COUNT(*) AS total
				FROM %i
				WHERE
					timestamp BETWEEN %s AND %s
					AND user_id > 0
				GROUP BY user_id
				ORDER BY total DESC
				LIMIT %d
				',
				$this->table,
				$start_date,
				$end_date,
				$limit
			),
			ARRAY_A
		);

		if ( ! is_array( $results ) ) {
			return array();
		}

		foreach ( $results as &$row ) {

			$user = get_userdata(
				(int) $row['user_id']
			);

			$row['name']       = $user
			? $user->display_name
			: __( 'Unknown User', 'pastmark' );
			$row['avatar_url'] = get_avatar_url( (int) $row['user_id'] );
		}

		return $results;
	}

	/**
	 * Get most common events.
	 *
	 * Grouped by category + specific action together (e.g. "Content
	 * Update", "User Role Change") rather than either alone, since the
	 * action by itself (e.g. "Update") doesn't say what was updated, and
	 * the category alone would just duplicate get_top_categories().
	 *
	 * @param  string $start_date Start date.
	 * @param  string $end_date   End date.
	 * @param  int    $limit      Limit.
	 * @return array
	 */
	public function get_top_events(
		string $start_date,
		string $end_date,
		int $limit = 5
	): array {

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'
				SELECT
					event_type,
					action,
					COUNT(*) AS total
				FROM %i
				WHERE timestamp BETWEEN %s AND %s
				GROUP BY event_type, action
				ORDER BY total DESC
				LIMIT %d
				',
				$this->table,
				$start_date,
				$end_date,
				$limit
			),
			ARRAY_A
		);

		if ( ! is_array( $results ) ) {
			return array();
		}

		foreach ( $results as &$result ) {

			$result['value'] = $result['event_type'] . ':' . $result['action'];
			$result['label'] = sprintf(
				'%s %s',
				Events::resolve_label( (string) $result['event_type'] ),
				Actions::resolve_label( (string) $result['action'] )
			);

			unset( $result['event_type'], $result['action'] );
		}

		return is_array( $results )
		? $results
		: array();
	}

	/**
	 * Get recent alerts.
	 *
	 * @param  int $limit Limit.
	 * @return array
	 */
	public function get_recent_high_severity_events(
		int $limit = 5
	): array {

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'
				SELECT
					l.id,
					l.user_id,
					l.event_type as event_value,
					l.severity,
					l.message,
					l.timestamp,
					u.display_name AS user_name
				FROM %i l
				LEFT JOIN %i u
					ON u.ID = l.user_id
				WHERE l.severity IN (\'warning\',\'error\',\'critical\')
				ORDER BY l.timestamp DESC
				LIMIT %d
				',
				$this->table,
				$wpdb->users,
				$limit
			),
			ARRAY_A
		);

		if ( ! is_array( $results ) ) {
			return array();
		}

		foreach ( $results as &$result ) {

			$result['event_label'] = Events::resolve_label( (string) $result['event_value'] );
			$result['avatar_url']  = ( (int) $result['user_id'] > 0 )
			? get_avatar_url( (int) $result['user_id'] )
			: '';
		}

		return is_array( $results )
		? $results
		: array();
	}

	/**
	 * Get top source IPs behind failed login attempts.
	 *
	 * @param  string $start_date Start date.
	 * @param  string $end_date   End date.
	 * @param  int    $limit      Limit.
	 * @return array
	 */
	public function get_top_failed_login_ips(
		string $start_date,
		string $end_date,
		int $limit = 5
	): array {

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'
				SELECT
					ip_address AS label,
					COALESCE(
						NULLIF( JSON_UNQUOTE( JSON_EXTRACT( context, \'$.attempted_display_name\' ) ), \'\' ),
						NULLIF( JSON_UNQUOTE( JSON_EXTRACT( context, \'$.attempted_username\' ) ), \'\' )
					) AS username,
					COUNT(*) AS total
				FROM %i
				WHERE
					timestamp BETWEEN %s AND %s
					AND event_type = %s
					AND action = %s
					AND ip_address IS NOT NULL
					AND ip_address != \'\'
				GROUP BY ip_address, username
				ORDER BY total DESC
				LIMIT %d
				',
				$this->table,
				$start_date,
				$end_date,
				Events::AUTHENTICATION,
				Actions::FAILED_LOGIN,
				$limit
			),
			ARRAY_A
		);

		return is_array( $results )
		? $results
		: array();
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching