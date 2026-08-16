<?php
/**
 * REST event collector.
 *
 * @package InfinityMetrics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Accepts authenticated, validated analytics events.
 */
final class Infinity_Metrics_Event_API {

	const MAX_PAYLOAD_BYTES = 16384;

	/**
	 * Register REST hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register collector routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'infinity_metrics/v1',
			'/event',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'collect' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Validate, authenticate, and store an event.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function collect( $request ) {
		if ( strlen( $request->get_body() ) > self::MAX_PAYLOAD_BYTES ) {
			return self::rest_error( 'payload_too_large', __( 'The event payload is too large.', 'infinity-metrics' ), 413 );
		}

		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			return self::rest_error( 'invalid_json', __( 'A valid JSON event payload is required.', 'infinity-metrics' ), 400 );
		}

		$slug   = isset( $payload['source'] ) && is_string( $payload['source'] ) ? $payload['source'] : '';
		$key    = $request->get_header( 'x-infinity-metrics-key' );
		$key    = $key ? $key : ( isset( $payload['key'] ) && is_string( $payload['key'] ) ? $payload['key'] : '' );
		$source = Infinity_Metrics_Sources::get_enabled_by_slug( $slug );

		if ( ! $source || ! is_string( $key ) || ! hash_equals( $source->api_key, $key ) || ! self::origin_allowed( $request, $source ) ) {
			return self::rest_error( 'forbidden', __( 'The event could not be accepted.', 'infinity-metrics' ), 403 );
		}

		$client_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( ! Infinity_Metrics_Rate_Limiter::allow( $source->id, $client_ip ) ) {
			$error = self::rest_error( 'rate_limited', __( 'Too many events. Try again shortly.', 'infinity-metrics' ), 429 );
			$error->add_data( array( 'status' => 429, 'retry_after' => 60 ) );
			return $error;
		}

		$event = Infinity_Metrics_Privacy::validate_event( $payload );
		if ( is_wp_error( $event ) ) {
			$event->add_data( array( 'status' => 400 ) );
			return $event;
		}

		$stored = Infinity_Metrics_Events::create( $source->id, $event );
		if ( is_wp_error( $stored ) ) {
			return self::rest_error( 'storage_failed', __( 'The event could not be accepted.', 'infinity-metrics' ), 500 );
		}

		return new WP_REST_Response( array( 'accepted' => true ), 202 );
	}

	/**
	 * Check an explicit browser origin against the configured source host.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param object          $source  Source.
	 * @return bool
	 */
	private static function origin_allowed( $request, $source ) {
		$origin = $request->get_header( 'origin' );
		if ( '' === $origin ) {
			return (bool) apply_filters( 'infinity_metrics_allow_missing_origin', false, $source );
		}

		$host = wp_parse_url( $origin, PHP_URL_HOST );
		return $host && strtolower( $host ) === strtolower( $source->domain );
	}

	/**
	 * Build a REST error with an HTTP status.
	 *
	 * @param string $code    Error code.
	 * @param string $message Safe public message.
	 * @param int    $status  HTTP status.
	 * @return WP_Error
	 */
	private static function rest_error( $code, $message, $status ) {
		return new WP_Error( 'infinity_metrics_' . $code, $message, array( 'status' => $status ) );
	}
}
