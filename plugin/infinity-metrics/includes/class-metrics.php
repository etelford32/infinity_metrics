<?php
/**
 * Server-side metric aggregation.
 *
 * @package InfinityMetrics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides stable metric queries over the event stream.
 */
final class Infinity_Metrics_Metrics {

	/**
	 * Count anonymous visitors, falling back to sessions for legacy events.
	 *
	 * @param int    $source_id Source ID, or zero for all sources.
	 * @param string $start     Local YYYY-MM-DD start date.
	 * @param string $end       Local YYYY-MM-DD inclusive end date.
	 * @return int
	 */
	public static function visitors( $source_id, $start, $end ) {
		return self::scalar(
			"COUNT(DISTINCT CASE WHEN visitor_id <> '' THEN CONCAT(source_id, ':visitor:', visitor_id) ELSE CONCAT(source_id, ':session:', session_id) END)",
			$source_id,
			$start,
			$end
		);
	}

	/** Count sessions. */
	public static function sessions( $source_id, $start, $end ) {
		return self::scalar( "COUNT(DISTINCT CONCAT(source_id, ':', session_id))", $source_id, $start, $end );
	}

	/** Count page views. */
	public static function page_views( $source_id, $start, $end ) {
		return self::scalar( 'COUNT(*)', $source_id, $start, $end, array( 'event = %s' ), array( 'page_view' ) );
	}

	/** Count sessions that reached 30 seconds of visible engagement. */
	public static function engaged_sessions( $source_id, $start, $end ) {
		return self::scalar( "COUNT(DISTINCT CONCAT(source_id, ':', session_id))", $source_id, $start, $end, array( 'event = %s' ), array( 'engaged_30s' ) );
	}

	/** Count non-automatic product interactions. */
	public static function interactions( $source_id, $start, $end ) {
		$automatic   = array( 'page_view', 'engaged_30s', 'scroll_50', 'scroll_90', 'second_page' );
		$placeholders = implode( ',', array_fill( 0, count( $automatic ), '%s' ) );

		return self::scalar( 'COUNT(*)', $source_id, $start, $end, array( "event NOT IN ({$placeholders})" ), $automatic );
	}

	/** Count sessions containing at least one non-automatic interaction. */
	public static function interacting_sessions( $source_id, $start, $end ) {
		$automatic    = array( 'page_view', 'engaged_30s', 'scroll_50', 'scroll_90', 'second_page' );
		$placeholders = implode( ',', array_fill( 0, count( $automatic ), '%s' ) );

		return self::scalar( "COUNT(DISTINCT CONCAT(source_id, ':', session_id))", $source_id, $start, $end, array( "event NOT IN ({$placeholders})" ), $automatic );
	}

	/** Count default conversion-start events. */
	public static function conversion_starts( $source_id, $start, $end ) {
		return self::configured_conversion_count( 'COUNT(*)', $source_id, $start, $end, 'start' );
	}

	/** Count default conversion-complete events. */
	public static function conversions( $source_id, $start, $end ) {
		return self::configured_conversion_count( 'COUNT(*)', $source_id, $start, $end, 'complete' );
	}

	/** Count sessions containing a default conversion-start event. */
	public static function conversion_start_sessions( $source_id, $start, $end ) {
		return self::configured_conversion_count( "COUNT(DISTINCT CONCAT(source_id, ':', session_id))", $source_id, $start, $end, 'start' );
	}

	/** Count sessions containing a default conversion event. */
	public static function converted_sessions( $source_id, $start, $end ) {
		return self::configured_conversion_count( "COUNT(DISTINCT CONCAT(source_id, ':', session_id))", $source_id, $start, $end, 'complete' );
	}

	/**
	 * Count sessions belonging to visitors with multiple sessions in the period.
	 */
	public static function returning_sessions( $source_id, $start, $end ) {
		global $wpdb;

		$table = Infinity_Metrics_Database::events_table();
		$where = self::period_where( $source_id, $start, $end );
		$sql   = "SELECT COALESCE(SUM(visitor_sessions.session_count), 0) FROM (
			SELECT source_id, visitor_id, COUNT(DISTINCT session_id) AS session_count
			FROM {$table} {$where['sql']} AND visitor_id <> ''
			GROUP BY source_id, visitor_id
			HAVING COUNT(DISTINCT session_id) > 1
		) AS visitor_sessions"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $where['args'] ) );
	}

	/**
	 * Return the most frequent event names.
	 *
	 * @param int    $source_id Source ID, or zero.
	 * @param string $start     Local start date.
	 * @param string $end       Local inclusive end date.
	 * @param int    $limit     Result limit.
	 * @return array
	 */
	public static function top_events( $source_id, $start, $end, $limit = 10 ) {
		global $wpdb;

		$table = Infinity_Metrics_Database::events_table();
		$where = self::period_where( $source_id, $start, $end );
		$limit = max( 1, min( 100, (int) $limit ) );
		$sql   = "SELECT event, COUNT(*) AS total FROM {$table} {$where['sql']} GROUP BY event ORDER BY total DESC, event ASC LIMIT %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$args  = array_merge( $where['args'], array( $limit ) );

		return $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
	}

	/**
	 * Return daily page-view totals for the traffic chart.
	 *
	 * @return array
	 */
	public static function daily_page_views( $source_id, $start, $end ) {
		global $wpdb;

		$table = Infinity_Metrics_Database::events_table();
		$where = self::period_where( $source_id, $start, $end, array( 'event = %s' ), array( 'page_view' ) );
		$sql   = "SELECT DATE(event_timestamp) AS event_date, COUNT(*) AS total FROM {$table} {$where['sql']} GROUP BY DATE(event_timestamp) ORDER BY event_date ASC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $wpdb->get_results( $wpdb->prepare( $sql, $where['args'] ) );
	}

	/**
	 * Return visitor totals grouped by source.
	 *
	 * @return array
	 */
	public static function source_breakdown( $start, $end ) {
		global $wpdb;

		$events  = Infinity_Metrics_Database::events_table();
		$sources = Infinity_Metrics_Database::sources_table();
		$where   = self::period_where( 0, $start, $end );
		$sql     = "SELECT s.id, s.name,
			COUNT(DISTINCT CASE WHEN e.visitor_id <> '' THEN e.visitor_id ELSE CONCAT('session:', e.session_id) END) AS total
			FROM {$events} e INNER JOIN {$sources} s ON s.id = e.source_id
			{$where['sql']} GROUP BY s.id, s.name ORDER BY total DESC, s.name ASC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $wpdb->get_results( $wpdb->prepare( $sql, $where['args'] ) );
	}

	/** Count events or sessions using each source's conversion definitions. */
	private static function configured_conversion_count( $expression, $source_id, $start, $end, $type ) {
		$rules   = Infinity_Metrics_Conversions::metric_rules( $source_id, $type );
		$clauses = array();
		$args    = array();
		foreach ( $rules as $rule_source_id => $events ) {
			$events = array_values( array_filter( array_map( 'sanitize_text_field', $events ) ) );
			if ( ! $events ) {
				continue;
			}
			$placeholders = implode( ',', array_fill( 0, count( $events ), '%s' ) );
			$clauses[]    = "(source_id = %d AND event IN ({$placeholders}))";
			$args[]       = (int) $rule_source_id;
			$args          = array_merge( $args, $events );
		}

		if ( ! $clauses ) {
			return 0;
		}

		return self::scalar( $expression, $source_id, $start, $end, array( '(' . implode( ' OR ', $clauses ) . ')' ), $args );
	}

	/** Run a scalar aggregate over a period. */
	private static function scalar( $expression, $source_id, $start, $end, $extra_clauses = array(), $extra_args = array() ) {
		global $wpdb;

		$table = Infinity_Metrics_Database::events_table();
		$where = self::period_where( $source_id, $start, $end, $extra_clauses, $extra_args );
		$sql   = "SELECT {$expression} FROM {$table} {$where['sql']}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $where['args'] ) );
	}

	/** Build the common UTC period and source clause. */
	private static function period_where( $source_id, $start, $end, $extra_clauses = array(), $extra_args = array() ) {
		$start_gmt = get_gmt_from_date( $start . ' 00:00:00' );
		$next_day  = gmdate( 'Y-m-d 00:00:00', strtotime( $end . ' +1 day' ) );
		$end_gmt   = get_gmt_from_date( $next_day );
		$clauses   = array( 'event_timestamp >= %s', 'event_timestamp < %s' );
		$args      = array( $start_gmt, $end_gmt );

		if ( $source_id ) {
			$clauses[] = 'source_id = %d';
			$args[]    = (int) $source_id;
		}

		return array(
			'sql'  => 'WHERE ' . implode( ' AND ', array_merge( $clauses, $extra_clauses ) ),
			'args' => array_merge( $args, $extra_args ),
		);
	}
}
