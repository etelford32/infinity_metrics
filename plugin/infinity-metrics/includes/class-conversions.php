<?php
/**
 * Conversion definition persistence.
 *
 * @package InfinityMetrics
 */

defined( 'ABSPATH' ) || exit;

/** Manages source-specific conversion event definitions. */
final class Infinity_Metrics_Conversions {

	/** Request-local metric rule cache. */
	private static $rules_cache = array();

	/** Fetch all definitions, optionally for one source. */
	public static function all( $source_id = 0 ) {
		global $wpdb;

		$table = Infinity_Metrics_Database::conversions_table();
		if ( $source_id ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE source_id = %d ORDER BY conversion_type ASC, name ASC", $source_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY source_id ASC, conversion_type ASC, name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** Fetch one definition. */
	public static function get( $conversion_id ) {
		global $wpdb;

		$table = Infinity_Metrics_Database::conversions_table();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $conversion_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** Create a conversion definition. */
	public static function create( $data ) {
		global $wpdb;

		$validated = self::validate( $data );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		$validated['created_at'] = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			Infinity_Metrics_Database::conversions_table(),
			$validated,
			array( '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'infinity_metrics_conversion_insert_failed', __( 'The conversion could not be created. That source, event, and type may already exist.', 'infinity-metrics' ) );
		}
		self::$rules_cache = array();

		return (int) $wpdb->insert_id;
	}

	/** Update a conversion definition. */
	public static function update( $conversion_id, $data ) {
		global $wpdb;

		if ( ! self::get( $conversion_id ) ) {
			return new WP_Error( 'infinity_metrics_conversion_not_found', __( 'Conversion not found.', 'infinity-metrics' ) );
		}
		$validated = self::validate( $data );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		$updated = $wpdb->update(
			Infinity_Metrics_Database::conversions_table(),
			$validated,
			array( 'id' => (int) $conversion_id ),
			array( '%d', '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'infinity_metrics_conversion_update_failed', __( 'The conversion could not be updated. That source, event, and type may already exist.', 'infinity-metrics' ) );
		}
		self::$rules_cache = array();

		return true;
	}

	/** Delete one conversion definition. */
	public static function delete( $conversion_id ) {
		global $wpdb;

		$deleted = $wpdb->delete( Infinity_Metrics_Database::conversions_table(), array( 'id' => (int) $conversion_id ), array( '%d' ) );
		if ( false !== $deleted ) {
			self::$rules_cache = array();
			return true;
		}

		return false;
	}

	/**
	 * Return metric rules keyed by source ID.
	 *
	 * Once a source has any definitions for a type, only its enabled definitions
	 * apply. Sources without definitions retain the MVP defaults.
	 */
	public static function metric_rules( $source_id, $type ) {
		$type       = self::valid_type( $type ) ? $type : 'complete';
		$cache_key  = (int) $source_id . ':' . $type;
		if ( array_key_exists( $cache_key, self::$rules_cache ) ) {
			return self::$rules_cache[ $cache_key ];
		}
		$defaults   = 'start' === $type
			? (array) apply_filters( 'infinity_metrics_conversion_start_events', array( 'signup_start', 'contact_start' ) )
			: (array) apply_filters( 'infinity_metrics_conversion_events', array( 'signup_complete', 'contact_complete' ) );
		$sources    = $source_id ? array_filter( array( Infinity_Metrics_Sources::get( $source_id ) ) ) : Infinity_Metrics_Sources::all();
		$definitions = self::all( $source_id );
		$by_source  = array();

		foreach ( $definitions as $definition ) {
			if ( $definition->conversion_type !== $type ) {
				continue;
			}
			if ( ! isset( $by_source[ $definition->source_id ] ) ) {
				$by_source[ $definition->source_id ] = array();
			}
			if ( $definition->enabled ) {
				$by_source[ $definition->source_id ][] = $definition->event;
			}
		}

		$rules = array();
		foreach ( $sources as $source ) {
			$rules[ $source->id ] = array_key_exists( $source->id, $by_source ) ? $by_source[ $source->id ] : $defaults;
		}

		self::$rules_cache[ $cache_key ] = $rules;

		return $rules;
	}

	/** Validate editable fields. */
	private static function validate( $data ) {
		$source_id = isset( $data['source_id'] ) ? absint( $data['source_id'] ) : 0;
		$name      = sanitize_text_field( isset( $data['name'] ) ? $data['name'] : '' );
		$event     = sanitize_text_field( isset( $data['event'] ) ? $data['event'] : '' );
		$type      = isset( $data['conversion_type'] ) ? sanitize_key( $data['conversion_type'] ) : '';
		$enabled   = empty( $data['enabled'] ) ? 0 : 1;

		if ( ! $source_id || ! Infinity_Metrics_Sources::get( $source_id ) || '' === $name || strlen( $name ) > 190 || ! preg_match( '/^[a-z][a-z0-9_.:-]{0,174}$/', $event ) || ! self::valid_type( $type ) ) {
			return new WP_Error( 'infinity_metrics_invalid_conversion', __( 'A source, name, valid event, and conversion type are required.', 'infinity-metrics' ) );
		}

		return array(
			'source_id'       => $source_id,
			'name'            => $name,
			'event'           => $event,
			'conversion_type' => $type,
			'enabled'         => $enabled,
		);
	}

	/** Check a conversion type. */
	private static function valid_type( $type ) {
		return in_array( $type, array( 'start', 'complete' ), true );
	}
}
