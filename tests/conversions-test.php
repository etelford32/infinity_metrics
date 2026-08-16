<?php
/**
 * Conversion definition tests.
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {}
function __( $message ) { return $message; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function current_time() { return '2026-08-16 12:00:00'; }
function apply_filters( $name, $value ) { return $value; }

final class Infinity_Metrics_Database {
	public static function conversions_table() { return 'wp_infinity_metrics_conversions'; }
}

final class Infinity_Metrics_Sources {
	public static function get( $source_id ) { return 7 === (int) $source_id ? (object) array( 'id' => 7 ) : null; }
	public static function all() { return array( (object) array( 'id' => 7 ) ); }
}

final class Fake_Conversions_Wpdb {
	public $insert_id = 3;
	public $rows = array();
	public $deleted = false;
	public function insert( $table, $data ) { $data['id'] = 3; $this->rows = array( (object) $data ); return 1; }
	public function update( $table, $data ) { foreach ( $data as $key => $value ) { $this->rows[0]->{$key} = $value; } return 1; }
	public function delete() { $this->deleted = true; return 1; }
	public function get_row() { return isset( $this->rows[0] ) ? $this->rows[0] : null; }
	public function get_results() { return $this->rows; }
	public function prepare( $query ) { return $query; }
}

function assert_true( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}

$wpdb = new Fake_Conversions_Wpdb();
require_once dirname( __DIR__ ) . '/plugin/infinity-metrics/includes/class-conversions.php';

$id = Infinity_Metrics_Conversions::create( array( 'source_id' => 7, 'name' => 'Signup', 'event' => 'signup_complete', 'conversion_type' => 'complete', 'enabled' => true ) );
assert_true( 3 === $id, 'conversion is created' );
assert_true( 'signup_complete' === $wpdb->rows[0]->event, 'event is stored' );

$rules = Infinity_Metrics_Conversions::metric_rules( 7, 'complete' );
assert_true( array( 'signup_complete' ) === $rules[7], 'configured event replaces defaults' );

Infinity_Metrics_Conversions::update( 3, array( 'source_id' => 7, 'name' => 'Signup', 'event' => 'signup_complete', 'conversion_type' => 'complete', 'enabled' => false ) );
$rules = Infinity_Metrics_Conversions::metric_rules( 7, 'complete' );
assert_true( array() === $rules[7], 'disabled-only configuration produces no conversion events' );

$invalid = Infinity_Metrics_Conversions::create( array( 'source_id' => 7, 'name' => 'Bad', 'event' => 'Invalid Event', 'conversion_type' => 'complete' ) );
assert_true( is_wp_error( $invalid ), 'invalid event name is rejected' );
assert_true( Infinity_Metrics_Conversions::delete( 3 ), 'conversion is deleted' );

echo "Conversion tests passed.\n";
