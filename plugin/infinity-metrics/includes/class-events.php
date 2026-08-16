<?php
/**
 * Event persistence.
 *
 * @package InfinityMetrics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores validated analytics events.
 */
final class Infinity_Metrics_Events {

	const PER_PAGE = 50;

	/**
	 * Insert one normalized event.
	 *
	 * @param int   $source_id Source ID.
	 * @param array $event     Validated event fields.
	 * @return int|WP_Error
	 */
	public static function create( $source_id, $event ) {
		global $wpdb;

		$properties = wp_json_encode( $event['properties'] );
		if ( false === $properties ) {
			return new WP_Error( 'infinity_metrics_properties_encoding_failed', __( 'Event properties could not be encoded.', 'infinity-metrics' ) );
		}

		$inserted = $wpdb->insert(
			Infinity_Metrics_Database::events_table(),
			array(
				'source_id'      => (int) $source_id,
				'event'          => $event['event'],
				'visitor_id'     => isset( $event['visitor_id'] ) ? $event['visitor_id'] : '',
				'session_id'     => $event['session_id'],
				'page'           => $event['page'],
				'referrer'       => $event['referrer'],
				'event_timestamp' => gmdate( 'Y-m-d H:i:s', $event['timestamp'] ),
				'properties'     => $properties,
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'infinity_metrics_event_insert_failed', __( 'The event could not be stored.', 'infinity-metrics' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Query raw events for the administrator explorer.
	 *
	 * @param array $filters Source, event, page, start, end, and paged filters.
	 * @return array{items: array, total: int, pages: int, page: int}
	 */
	public static function query( $filters = array() ) {
		global $wpdb;

		$where  = self::build_where( $filters );
		$table  = Infinity_Metrics_Database::events_table();
		$page   = max( 1, isset( $filters['paged'] ) ? (int) $filters['paged'] : 1 );
		$count  = "SELECT COUNT(*) FROM {$table} {$where['sql']}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$select = "SELECT * FROM {$table} {$where['sql']} ORDER BY event_timestamp DESC, id DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$total       = (int) $wpdb->get_var( self::prepare( $count, $where['args'] ) );
		$pages       = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$page        = min( $page, $pages );
		$offset      = ( $page - 1 ) * self::PER_PAGE;
		$select_args = array_merge( $where['args'], array( self::PER_PAGE, $offset ) );
		$items       = $wpdb->get_results( self::prepare( $select, $select_args ) );

		return array(
			'items' => $items,
			'total' => $total,
			'pages' => $pages,
			'page'  => $page,
		);
	}

	/**
	 * Return event names for the filter dropdown.
	 *
	 * @param int $source_id Optional source ID.
	 * @return array
	 */
	public static function event_names( $source_id = 0 ) {
		global $wpdb;

		$table = Infinity_Metrics_Database::events_table();
		if ( $source_id ) {
			$sql = $wpdb->prepare( "SELECT DISTINCT event FROM {$table} WHERE source_id = %d ORDER BY event ASC LIMIT 200", $source_id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$sql = "SELECT DISTINCT event FROM {$table} ORDER BY event ASC LIMIT 200"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		return $wpdb->get_col( $sql );
	}

	/**
	 * Build a prepared WHERE clause from explorer filters.
	 *
	 * @param array $filters Filters.
	 * @return array{sql: string, args: array}
	 */
	private static function build_where( $filters ) {
		global $wpdb;

		$clauses = array();
		$args    = array();
		if ( ! empty( $filters['source_id'] ) ) {
			$clauses[] = 'source_id = %d';
			$args[]    = (int) $filters['source_id'];
		}
		if ( ! empty( $filters['event'] ) ) {
			$event = sanitize_text_field( $filters['event'] );
			if ( preg_match( '/^[a-z][a-z0-9_.:-]{0,189}$/', $event ) ) {
				$clauses[] = 'event = %s';
				$args[]    = $event;
			}
		}
		if ( ! empty( $filters['page'] ) ) {
			$clauses[] = 'page LIKE %s';
			$args[]    = '%' . $wpdb->esc_like( sanitize_text_field( $filters['page'] ) ) . '%';
		}
		if ( ! empty( $filters['start'] ) && self::valid_date( $filters['start'] ) ) {
			$clauses[] = 'event_timestamp >= %s';
			$args[]    = get_gmt_from_date( $filters['start'] . ' 00:00:00' );
		}
		if ( ! empty( $filters['end'] ) && self::valid_date( $filters['end'] ) ) {
			$clauses[] = 'event_timestamp < %s';
			$next_day  = gmdate( 'Y-m-d 00:00:00', strtotime( $filters['end'] . ' +1 day' ) );
			$args[]    = get_gmt_from_date( $next_day );
		}

		return array(
			'sql'  => $clauses ? 'WHERE ' . implode( ' AND ', $clauses ) : '',
			'args' => $args,
		);
	}

	/**
	 * Prepare SQL only when it contains dynamic arguments.
	 *
	 * @param string $sql  SQL statement.
	 * @param array  $args Values.
	 * @return string
	 */
	private static function prepare( $sql, $args ) {
		global $wpdb;

		return $args ? $wpdb->prepare( $sql, $args ) : $sql;
	}

	/**
	 * Validate an ISO calendar date without normalizing impossible dates.
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	private static function valid_date( $date ) {
		$parsed = date_parse_from_format( 'Y-m-d', $date );

		return 0 === $parsed['warning_count'] && 0 === $parsed['error_count'] && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date );
	}
}
