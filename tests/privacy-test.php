<?php
/**
 * Event schema and privacy tests.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'YEAR_IN_SECONDS', 31536000 );

class WP_Error {
	public $code;
	public function __construct( $code ) {
		$this->code = $code;
	}
}

function __( $message ) {
	return $message;
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

function wp_strip_all_tags( $value ) {
	return strip_tags( $value );
}

function sanitize_text_field( $value ) {
	return trim( $value );
}

function assert_true( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/plugin/infinity-metrics/includes/class-privacy.php';

$payload = array(
	'source'     => 'parkersphysics',
	'key'        => 'im_test',
	'event'      => 'galaxy_object_click',
	'visitor_id' => 'anonymous-visitor-456',
	'session_id' => 'anonymous-123',
	'page'       => '/galaxy-map/',
	'referrer'   => 'https://google.com/',
	'timestamp'  => gmdate( 'Y-m-d\TH:i:s\Z' ),
	'properties' => array( 'object_type' => 'spiral_galaxy', 'zoom' => 2.5 ),
);

$valid = Infinity_Metrics_Privacy::validate_event( $payload );
assert_true( ! is_wp_error( $valid ), 'valid event passes' );
assert_true( 'spiral_galaxy' === $valid['properties']['object_type'], 'properties are retained' );
assert_true( 'anonymous-visitor-456' === $valid['visitor_id'], 'anonymous visitor ID is retained' );

$milliseconds              = $payload;
$milliseconds['timestamp'] = gmdate( 'Y-m-d\TH:i:s' ) . '.123Z';
assert_true( ! is_wp_error( Infinity_Metrics_Privacy::validate_event( $milliseconds ) ), 'RFC3339 fractional seconds pass' );

$with_list                           = $payload;
$with_list['properties']['layers'] = array( 'infrared', 'visible' );
assert_true( ! is_wp_error( Infinity_Metrics_Privacy::validate_event( $with_list ) ), 'bounded property lists pass' );

$sensitive                          = $payload;
$sensitive['properties']['email'] = 'visitor@example.com';
assert_true( is_wp_error( Infinity_Metrics_Privacy::validate_event( $sensitive ) ), 'email property is rejected' );

$markup                        = $payload;
$markup['properties']['label'] = '<strong>captured DOM</strong>';
assert_true( is_wp_error( Infinity_Metrics_Privacy::validate_event( $markup ) ), 'markup property is rejected' );

$unknown             = $payload;
$unknown['user_agent'] = 'browser fingerprint';
assert_true( is_wp_error( Infinity_Metrics_Privacy::validate_event( $unknown ) ), 'unknown top-level fields are rejected' );

$bad_time              = $payload;
$bad_time['timestamp'] = 'today';
assert_true( is_wp_error( Infinity_Metrics_Privacy::validate_event( $bad_time ) ), 'non-RFC3339 timestamp is rejected' );

$impossible_time              = $payload;
$impossible_time['timestamp'] = '2026-02-31T12:00:00Z';
assert_true( is_wp_error( Infinity_Metrics_Privacy::validate_event( $impossible_time ) ), 'impossible timestamp is rejected' );

$deep = $payload;
$deep['properties'] = array( 'one' => array( 'two' => array( 'three' => array( 'four' => true ) ) ) );
assert_true( is_wp_error( Infinity_Metrics_Privacy::validate_event( $deep ) ), 'deep properties are rejected' );

echo "Privacy tests passed.\n";
