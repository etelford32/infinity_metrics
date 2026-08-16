<?php
/**
 * Metrics query tests.
 */

define( 'ABSPATH', __DIR__ . '/' );

function get_gmt_from_date( $value ) {
	return $value;
}

function sanitize_text_field( $value ) {
	return trim( $value );
}

function apply_filters( $name, $value ) {
	return $value;
}

final class Infinity_Metrics_Database {
	public static function events_table() {
		return 'wp_infinity_metrics_events';
	}

	public static function sources_table() {
		return 'wp_infinity_metrics_sources';
	}
}

final class Infinity_Metrics_Conversions {
	public static function metric_rules( $source_id, $type ) {
		if ( 'start' === $type ) {
			return array( 7 => array( 'signup_start', 'contact_start' ) );
		}
		return array( 7 => array( 'signup_complete', 'contact_complete' ) );
	}
}

final class Fake_Metrics_Wpdb {
	public $queries = array();

	public function prepare( $query, $args ) {
		foreach ( $args as $value ) {
			$replacement = is_int( $value ) ? (string) $value : "'" . $value . "'";
			$query       = preg_replace( '/%[ds]/', $replacement, $query, 1 );
		}
		return $query;
	}

	public function get_var( $query ) {
		$this->queries[] = $query;
		return 12;
	}

	public function get_results( $query ) {
		$this->queries[] = $query;
		return array( (object) array( 'event' => 'page_view', 'total' => 20 ) );
	}
}

function assert_true( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$wpdb = new Fake_Metrics_Wpdb();
require_once dirname( __DIR__ ) . '/plugin/infinity-metrics/includes/class-metrics.php';

assert_true( 12 === Infinity_Metrics_Metrics::visitors( 7, '2026-08-01', '2026-08-16' ), 'visitor count is returned' );
assert_true( false !== strpos( $wpdb->queries[0], "visitor_id <> ''" ), 'visitors use persistent anonymous IDs' );
assert_true( false !== strpos( $wpdb->queries[0], 'source_id = 7' ), 'source filter is applied' );
assert_true( false !== strpos( $wpdb->queries[0], "event_timestamp < '2026-08-17 00:00:00'" ), 'end date is inclusive' );

Infinity_Metrics_Metrics::engaged_sessions( 0, '2026-08-01', '2026-08-16' );
assert_true( false !== strpos( end( $wpdb->queries ), "event = 'engaged_30s'" ), 'engagement counts engaged sessions' );

Infinity_Metrics_Metrics::interactions( 7, '2026-08-01', '2026-08-16' );
assert_true( false !== strpos( end( $wpdb->queries ), 'event NOT IN' ), 'interactions exclude automatic events' );

Infinity_Metrics_Metrics::interacting_sessions( 7, '2026-08-01', '2026-08-16' );
assert_true( false !== strpos( end( $wpdb->queries ), 'COUNT(DISTINCT CONCAT' ), 'funnel interactions count sessions' );

Infinity_Metrics_Metrics::conversions( 7, '2026-08-01', '2026-08-16' );
assert_true( false !== strpos( end( $wpdb->queries ), "'signup_complete','contact_complete'" ), 'default conversions are explicit' );

Infinity_Metrics_Metrics::returning_sessions( 7, '2026-08-01', '2026-08-16' );
assert_true( false !== strpos( end( $wpdb->queries ), 'HAVING COUNT(DISTINCT session_id) > 1' ), 'returning sessions require repeat visitor sessions' );

$top = Infinity_Metrics_Metrics::top_events( 7, '2026-08-01', '2026-08-16', 5 );
assert_true( 'page_view' === $top[0]->event, 'top events are returned' );
assert_true( false !== strpos( end( $wpdb->queries ), 'LIMIT 5' ), 'top events limit is applied' );

Infinity_Metrics_Metrics::daily_page_views( 7, '2026-08-01', '2026-08-16' );
assert_true( false !== strpos( end( $wpdb->queries ), 'GROUP BY DATE(event_timestamp)' ), 'daily traffic is grouped for the chart' );

Infinity_Metrics_Metrics::source_breakdown( '2026-08-01', '2026-08-16' );
assert_true( false !== strpos( end( $wpdb->queries ), 'INNER JOIN wp_infinity_metrics_sources' ), 'source breakdown joins source names' );

echo "Metrics tests passed.\n";
