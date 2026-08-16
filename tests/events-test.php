<?php
/**
 * Event persistence tests.
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {}

function __( $message ) {
	return $message;
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

function current_time() {
	return '2026-08-16 12:00:01';
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( $value ) );
}

function get_gmt_from_date( $value ) {
	return $value;
}

final class Infinity_Metrics_Database {
	public static function events_table() {
		return 'wp_infinity_metrics_events';
	}
}

final class Fake_Events_Wpdb {
	public $insert_id = 42;
	public $table;
	public $data;
	public $queries = array();

	public function insert( $table, $data ) {
		$this->table = $table;
		$this->data  = $data;
		return 1;
	}

	public function esc_like( $value ) {
		return addcslashes( $value, '_%\\' );
	}

	public function prepare( $query, $args ) {
		if ( ! is_array( $args ) ) {
			$args = array_slice( func_get_args(), 1 );
		}
		foreach ( $args as $value ) {
			$replacement = is_int( $value ) ? (string) $value : "'" . $value . "'";
			$query       = preg_replace( '/%[ds]/', $replacement, $query, 1 );
		}
		return $query;
	}

	public function get_var( $query ) {
		$this->queries[] = $query;
		return 51;
	}

	public function get_results( $query ) {
		$this->queries[] = $query;
		return array( (object) array( 'id' => 9 ) );
	}

	public function get_col( $query ) {
		$this->queries[] = $query;
		return array( 'cta_click', 'page_view' );
	}
}

function assert_true( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$wpdb = new Fake_Events_Wpdb();
require_once dirname( __DIR__ ) . '/plugin/infinity-metrics/includes/class-events.php';

$event_id = Infinity_Metrics_Events::create(
	7,
	array(
		'event'      => 'cta_click',
		'visitor_id' => 'visitor-456',
		'session_id' => 'anonymous-123',
		'page'       => '/',
		'referrer'   => '',
		'timestamp'  => 1786881600,
		'properties' => array( 'target' => 'signup' ),
	)
);

assert_true( 42 === $event_id, 'insert ID is returned' );
assert_true( 'wp_infinity_metrics_events' === $wpdb->table, 'event table is used' );
assert_true( 7 === $wpdb->data['source_id'], 'source ID is stored' );
assert_true( 'visitor-456' === $wpdb->data['visitor_id'], 'anonymous visitor ID is stored' );
assert_true( '{"target":"signup"}' === $wpdb->data['properties'], 'properties are JSON encoded' );
assert_true( '2026-08-16 12:00:01' === $wpdb->data['created_at'], 'UTC receipt time is stored' );

$result = Infinity_Metrics_Events::query(
	array(
		'source_id' => 7,
		'event'     => 'product:open',
		'page'      => '/work',
		'start'     => '2026-08-01',
		'end'       => '2026-08-16',
		'paged'     => 2,
	)
);
assert_true( 51 === $result['total'] && 2 === $result['pages'], 'query returns totals and page count' );
assert_true( false !== strpos( $wpdb->queries[0], "event = 'product:open'" ), 'event filter preserves valid punctuation' );
assert_true( false !== strpos( $wpdb->queries[0], "page LIKE '%/work%'" ), 'page substring filter is included' );
assert_true( false !== strpos( $wpdb->queries[0], "event_timestamp < '2026-08-17 00:00:00'" ), 'end date is inclusive' );
assert_true( false !== strpos( $wpdb->queries[1], 'LIMIT 50 OFFSET 50' ), 'pagination limit and offset are applied' );

$names = Infinity_Metrics_Events::event_names( 7 );
assert_true( array( 'cta_click', 'page_view' ) === $names, 'event filter names are returned' );
assert_true( false !== strpos( end( $wpdb->queries ), 'source_id = 7' ), 'event names can be source scoped' );

echo "Event persistence tests passed.\n";
