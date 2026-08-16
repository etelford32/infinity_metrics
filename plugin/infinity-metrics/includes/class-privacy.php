<?php
/**
 * Event schema validation and privacy controls.
 *
 * @package InfinityMetrics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates the only data the collector is allowed to persist.
 */
final class Infinity_Metrics_Privacy {

	const MAX_PROPERTIES_BYTES = 8192;
	const MAX_PROPERTY_ITEMS   = 50;
	const MAX_PROPERTY_DEPTH   = 3;
	const MAX_PROPERTY_STRING  = 500;

	/**
	 * Validate and normalize an event payload.
	 *
	 * @param array $payload Decoded request payload.
	 * @return array|WP_Error
	 */
	public static function validate_event( $payload ) {
		if ( ! is_array( $payload ) ) {
			return self::error( 'invalid_payload' );
		}

		$allowed = array( 'source', 'key', 'event', 'visitor_id', 'session_id', 'page', 'referrer', 'timestamp', 'properties' );
		if ( array_diff( array_keys( $payload ), $allowed ) ) {
			return self::error( 'unknown_fields' );
		}

		$event      = isset( $payload['event'] ) && is_string( $payload['event'] ) ? $payload['event'] : '';
		$visitor_id = isset( $payload['visitor_id'] ) && is_string( $payload['visitor_id'] ) ? $payload['visitor_id'] : '';
		$session_id = isset( $payload['session_id'] ) && is_string( $payload['session_id'] ) ? $payload['session_id'] : '';
		$timestamp_value = isset( $payload['timestamp'] ) && is_string( $payload['timestamp'] ) ? $payload['timestamp'] : '';
		$parsed_time     = date_parse( $timestamp_value );
		$timestamp       = preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $timestamp_value )
			&& 0 === $parsed_time['warning_count'] && 0 === $parsed_time['error_count'] ? strtotime( $timestamp_value ) : false;

		if ( ! preg_match( '/^[a-z][a-z0-9_.:-]{0,189}$/', $event ) ) {
			return self::error( 'invalid_event_name' );
		}
		if ( '' === $session_id || strlen( $session_id ) > 190 || ! preg_match( '/^[A-Za-z0-9_.:-]+$/', $session_id ) ) {
			return self::error( 'invalid_session' );
		}
		if ( '' !== $visitor_id && ( strlen( $visitor_id ) > 190 || ! preg_match( '/^[A-Za-z0-9_.:-]+$/', $visitor_id ) ) ) {
			return self::error( 'invalid_visitor' );
		}
		if ( false === $timestamp || $timestamp > time() + 300 || $timestamp < time() - YEAR_IN_SECONDS ) {
			return self::error( 'invalid_timestamp' );
		}

		$page     = self::validate_string( isset( $payload['page'] ) ? $payload['page'] : '', 2048 );
		$referrer = self::validate_string( isset( $payload['referrer'] ) ? $payload['referrer'] : '', 2048 );
		if ( is_wp_error( $page ) || is_wp_error( $referrer ) ) {
			return self::error( 'invalid_location' );
		}

		$properties = isset( $payload['properties'] ) ? $payload['properties'] : array();
		if ( ! is_array( $properties ) ) {
			return self::error( 'invalid_properties' );
		}

		$item_count = 0;
		$properties = self::validate_properties( $properties, 1, $item_count, false );
		$encoded    = is_wp_error( $properties ) ? false : wp_json_encode( $properties );
		if ( is_wp_error( $properties ) || false === $encoded || strlen( $encoded ) > self::MAX_PROPERTIES_BYTES ) {
			return self::error( 'invalid_properties' );
		}

		return array(
			'event'      => $event,
			'visitor_id' => $visitor_id,
			'session_id' => $session_id,
			'page'       => $page,
			'referrer'   => $referrer,
			'timestamp'  => $timestamp,
			'properties' => $properties,
		);
	}

	/**
	 * Recursively validate property keys and scalar values.
	 *
	 * @param array $properties Properties.
	 * @param int   $depth      Current nesting depth.
	 * @param int   $item_count Running item count.
	 * @param bool  $allow_numeric_keys Whether this value is a JSON-style list.
	 * @return array|WP_Error
	 */
	private static function validate_properties( $properties, $depth, &$item_count, $allow_numeric_keys ) {
		if ( $depth > self::MAX_PROPERTY_DEPTH ) {
			return self::error( 'properties_too_deep' );
		}

		$result = array();
		$expected_index = 0;
		foreach ( $properties as $key => $value ) {
			++$item_count;
			$is_list_key = $allow_numeric_keys && is_int( $key ) && $key === $expected_index;
			$is_map_key  = is_string( $key ) && preg_match( '/^[a-z][a-z0-9_]{0,63}$/', $key ) && ! self::is_sensitive_key( $key );
			if ( $item_count > self::MAX_PROPERTY_ITEMS || ( ! $is_list_key && ! $is_map_key ) ) {
				return self::error( 'invalid_property_key' );
			}
			++$expected_index;

			if ( is_array( $value ) ) {
				$value = self::validate_properties( $value, $depth + 1, $item_count, self::is_list( $value ) );
			} elseif ( is_string( $value ) ) {
				$value = self::validate_string( $value, self::MAX_PROPERTY_STRING );
			} elseif ( is_float( $value ) && ! is_finite( $value ) ) {
				return self::error( 'invalid_property_value' );
			} elseif ( ! is_int( $value ) && ! is_float( $value ) && ! is_bool( $value ) && null !== $value ) {
				return self::error( 'invalid_property_value' );
			}

			if ( is_wp_error( $value ) ) {
				return $value;
			}
			$result[ $key ] = $value;
		}

		return $result;
	}

	/**
	 * Determine whether an array has sequential numeric keys.
	 *
	 * @param array $value Array value.
	 * @return bool
	 */
	private static function is_list( $value ) {
		return empty( $value ) || array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Reject property keys likely to contain personal or captured content.
	 *
	 * @param string $key Property key.
	 * @return bool
	 */
	private static function is_sensitive_key( $key ) {
		$blocked = array( 'email', 'password', 'passwd', 'name', 'phone', 'address', 'cookie', 'authorization', 'form', 'html', 'dom', 'content', 'message' );
		foreach ( $blocked as $term ) {
			if ( false !== strpos( $key, $term ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Validate a plain, bounded string without accepting markup.
	 *
	 * @param mixed $value Value.
	 * @param int   $limit Byte limit.
	 * @return string|WP_Error
	 */
	private static function validate_string( $value, $limit ) {
		if ( ! is_string( $value ) || strlen( $value ) > $limit || $value !== wp_strip_all_tags( $value ) ) {
			return self::error( 'invalid_string' );
		}
		return sanitize_text_field( $value );
	}

	/**
	 * Create a generic validation error.
	 *
	 * @param string $code Internal code.
	 * @return WP_Error
	 */
	private static function error( $code ) {
		return new WP_Error( 'infinity_metrics_' . $code, __( 'The event payload is invalid.', 'infinity-metrics' ) );
	}
}
