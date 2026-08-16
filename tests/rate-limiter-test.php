<?php
/**
 * Collector rate limiter tests.
 */

define( 'ABSPATH', __DIR__ . '/' );

$transients = array();

function apply_filters( $name, $value ) {
	return 2;
}

function wp_salt() {
	return 'test-only-salt';
}

function get_transient( $key ) {
	global $transients;
	return isset( $transients[ $key ] ) ? $transients[ $key ] : false;
}

function set_transient( $key, $value ) {
	global $transients;
	$transients[ $key ] = $value;
	return true;
}

function assert_true( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/plugin/infinity-metrics/includes/class-rate-limiter.php';

assert_true( Infinity_Metrics_Rate_Limiter::allow( 7, '192.0.2.1' ), 'first event is allowed' );
assert_true( Infinity_Metrics_Rate_Limiter::allow( 7, '192.0.2.1' ), 'event at the limit is allowed' );
assert_true( ! Infinity_Metrics_Rate_Limiter::allow( 7, '192.0.2.1' ), 'event over the limit is rejected' );

foreach ( array_keys( $transients ) as $key ) {
	assert_true( false === strpos( $key, '192.0.2.1' ), 'raw client IP is not used in transient keys' );
}

echo "Rate limiter tests passed.\n";
