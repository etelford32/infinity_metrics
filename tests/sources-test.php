<?php
/**
 * Lightweight source persistence tests without a WordPress installation.
 */

define( 'ABSPATH', __DIR__ . '/' );

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

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_title( $value ) {
	$value = strtolower( sanitize_text_field( $value ) );
	return trim( preg_replace( '/[^a-z0-9]+/', '-', $value ), '-' );
}

function wp_parse_url( $url, $component ) {
	return parse_url( $url, $component );
}

function current_time() {
	return '2026-08-16 12:00:00';
}

final class Infinity_Metrics_Database {
	public static function sources_table() {
		return 'wp_infinity_metrics_sources';
	}

	public static function events_table() {
		return 'wp_infinity_metrics_events';
	}

	public static function conversions_table() {
		return 'wp_infinity_metrics_conversions';
	}
}

final class Fake_Wpdb {
	public $insert_id = 0;
	public $rows = array();
	public $deletes = array();

	public function insert( $table, $data ) {
		$this->insert_id = count( $this->rows ) + 1;
		$data['id']       = $this->insert_id;
		$this->rows[]     = (object) $data;
		return 1;
	}

	public function get_row() {
		return isset( $this->rows[0] ) ? $this->rows[0] : null;
	}

	public function update( $table, $data ) {
		foreach ( $data as $key => $value ) {
			$this->rows[0]->{$key} = $value;
		}
		return 1;
	}

	public function delete( $table ) {
		$this->deletes[] = $table;
		return 1;
	}

	public function prepare( $query ) {
		return $query;
	}
}

function assert_true( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$wpdb = new Fake_Wpdb();
require_once dirname( __DIR__ ) . '/plugin/infinity-metrics/includes/class-sources.php';

$source_id = Infinity_Metrics_Sources::create(
	array(
		'name'    => ' Parker’s Physics ',
		'slug'    => 'Parkers Physics',
		'domain'  => 'https://PARKERSPHYSICS.com/path',
		'enabled' => true,
	)
);

assert_true( 1 === $source_id, 'create returns the inserted ID' );
assert_true( 'parkers-physics' === $wpdb->rows[0]->slug, 'slug is normalized' );
assert_true( 'parkersphysics.com' === $wpdb->rows[0]->domain, 'domain is normalized to its host' );
assert_true( 1 === $wpdb->rows[0]->enabled, 'enabled is stored as an integer' );
assert_true( 1 === preg_match( '/^im_[a-f0-9]{40}$/', $wpdb->rows[0]->api_key ), 'API key has the expected secure format' );

$invalid = Infinity_Metrics_Sources::update(
	$source_id,
	array(
		'name'   => '',
		'slug'   => '',
		'domain' => 'not a domain/path',
	)
);
assert_true( is_wp_error( $invalid ), 'invalid updates return WP_Error' );

assert_true( Infinity_Metrics_Sources::delete( $source_id ), 'delete succeeds' );
assert_true(
	array( 'wp_infinity_metrics_events', 'wp_infinity_metrics_conversions', 'wp_infinity_metrics_sources' ) === $wpdb->deletes,
	'delete removes events before the source'
);

echo "Source tests passed.\n";
