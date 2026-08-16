<?php
/**
 * REST collector orchestration tests.
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	public $code;
	public $data;
	public function __construct( $code, $message = '', $data = array() ) {
		$this->code = $code;
		$this->data = $data;
	}
	public function add_data( $data ) {
		$this->data = $data;
	}
}

class WP_REST_Response {
	public $data;
	public $status;
	public function __construct( $data, $status ) {
		$this->data   = $data;
		$this->status = $status;
	}
}

class Fake_REST_Request {
	private $payload;
	private $headers;
	private $body;
	public function __construct( $payload, $headers = array(), $body = null ) {
		$this->payload = $payload;
		$this->headers = $headers;
		$this->body    = null === $body ? json_encode( $payload ) : $body;
	}
	public function get_body() {
		return $this->body;
	}
	public function get_json_params() {
		return $this->payload;
	}
	public function get_header( $name ) {
		return isset( $this->headers[ $name ] ) ? $this->headers[ $name ] : '';
	}
}

function __( $message ) {
	return $message;
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function sanitize_text_field( $value ) {
	return trim( $value );
}

function wp_unslash( $value ) {
	return $value;
}

function wp_parse_url( $url, $component ) {
	return parse_url( $url, $component );
}

function apply_filters( $name, $value ) {
	return $value;
}

final class Infinity_Metrics_Sources {
	public static $source;
	public static function get_enabled_by_slug() {
		return self::$source;
	}
}

final class Infinity_Metrics_Rate_Limiter {
	public static $allowed = true;
	public static function allow() {
		return self::$allowed;
	}
}

final class Infinity_Metrics_Privacy {
	public static function validate_event( $payload ) {
		return array(
			'event' => $payload['event'], 'session_id' => $payload['session_id'], 'page' => '',
			'referrer' => '', 'timestamp' => time(), 'properties' => array(),
		);
	}
}

final class Infinity_Metrics_Events {
	public static $created = 0;
	public static function create() {
		++self::$created;
		return self::$created;
	}
}

function assert_true( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/plugin/infinity-metrics/includes/class-event-api.php';

$payload = array(
	'source' => 'parkersphysics', 'key' => 'im_correct', 'event' => 'page_view',
	'session_id' => 'anonymous-123', 'timestamp' => gmdate( 'c' ),
);
Infinity_Metrics_Sources::$source = (object) array(
	'id' => 7, 'api_key' => 'im_correct', 'domain' => 'parkersphysics.com',
);

$accepted = Infinity_Metrics_Event_API::collect( new Fake_REST_Request( $payload, array( 'origin' => 'https://parkersphysics.com' ) ) );
assert_true( $accepted instanceof WP_REST_Response && 202 === $accepted->status, 'valid event returns 202' );
assert_true( 1 === Infinity_Metrics_Events::$created, 'valid event is stored once' );

$wrong_key        = $payload;
$wrong_key['key'] = 'im_wrong';
$forbidden        = Infinity_Metrics_Event_API::collect( new Fake_REST_Request( $wrong_key, array( 'origin' => 'https://parkersphysics.com' ) ) );
assert_true( $forbidden instanceof WP_Error && 403 === $forbidden->data['status'], 'wrong key returns 403' );
assert_true( 1 === Infinity_Metrics_Events::$created, 'rejected event is not stored' );

$wrong_origin = Infinity_Metrics_Event_API::collect( new Fake_REST_Request( $payload, array( 'origin' => 'https://attacker.example' ) ) );
assert_true( $wrong_origin instanceof WP_Error && 403 === $wrong_origin->data['status'], 'wrong origin returns 403' );

$oversized = Infinity_Metrics_Event_API::collect( new Fake_REST_Request( $payload, array(), str_repeat( 'x', 16385 ) ) );
assert_true( $oversized instanceof WP_Error && 413 === $oversized->data['status'], 'oversized body returns 413' );

Infinity_Metrics_Rate_Limiter::$allowed = false;
$limited = Infinity_Metrics_Event_API::collect( new Fake_REST_Request( $payload, array( 'origin' => 'https://parkersphysics.com' ) ) );
assert_true( $limited instanceof WP_Error && 429 === $limited->data['status'], 'rate limit returns 429' );

echo "Event API tests passed.\n";
