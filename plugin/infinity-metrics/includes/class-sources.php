<?php
/**
 * Source persistence.
 *
 * @package InfinityMetrics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and manages analytics sources.
 */
final class Infinity_Metrics_Sources {

	/**
	 * Fetch all sources.
	 *
	 * @return array
	 */
	public static function all() {
		global $wpdb;

		$table = Infinity_Metrics_Database::sources_table();

		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Fetch one source.
	 *
	 * @param int $source_id Source ID.
	 * @return object|null
	 */
	public static function get( $source_id ) {
		global $wpdb;

		$table = Infinity_Metrics_Database::sources_table();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $source_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Fetch an enabled source by its public slug.
	 *
	 * @param string $slug Source slug.
	 * @return object|null
	 */
	public static function get_enabled_by_slug( $slug ) {
		global $wpdb;

		$table = Infinity_Metrics_Database::sources_table();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s AND enabled = 1", sanitize_title( $slug ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Create a source.
	 *
	 * @param array $data Source fields.
	 * @return int|WP_Error
	 */
	public static function create( $data ) {
		global $wpdb;

		$validated = self::validate( $data );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$validated['api_key']   = self::generate_api_key();
		$validated['created_at'] = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			Infinity_Metrics_Database::sources_table(),
			$validated,
			array( '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'infinity_metrics_source_insert_failed', __( 'The source could not be created. Its slug may already be in use.', 'infinity-metrics' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a source without changing its API key.
	 *
	 * @param int   $source_id Source ID.
	 * @param array $data      Source fields.
	 * @return true|WP_Error
	 */
	public static function update( $source_id, $data ) {
		global $wpdb;

		if ( ! self::get( $source_id ) ) {
			return new WP_Error( 'infinity_metrics_source_not_found', __( 'Source not found.', 'infinity-metrics' ) );
		}

		$validated = self::validate( $data );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$updated = $wpdb->update(
			Infinity_Metrics_Database::sources_table(),
			$validated,
			array( 'id' => (int) $source_id ),
			array( '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'infinity_metrics_source_update_failed', __( 'The source could not be updated. Its slug may already be in use.', 'infinity-metrics' ) );
		}

		return true;
	}

	/**
	 * Replace a source API key.
	 *
	 * @param int $source_id Source ID.
	 * @return string|WP_Error New API key on success.
	 */
	public static function regenerate_api_key( $source_id ) {
		global $wpdb;

		if ( ! self::get( $source_id ) ) {
			return new WP_Error( 'infinity_metrics_source_not_found', __( 'Source not found.', 'infinity-metrics' ) );
		}

		$api_key = self::generate_api_key();
		$updated = $wpdb->update(
			Infinity_Metrics_Database::sources_table(),
			array( 'api_key' => $api_key ),
			array( 'id' => (int) $source_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'infinity_metrics_key_update_failed', __( 'The API key could not be regenerated.', 'infinity-metrics' ) );
		}

		return $api_key;
	}

	/**
	 * Delete a source and its events.
	 *
	 * @param int $source_id Source ID.
	 * @return bool
	 */
	public static function delete( $source_id ) {
		global $wpdb;

		$wpdb->delete( Infinity_Metrics_Database::events_table(), array( 'source_id' => (int) $source_id ), array( '%d' ) );
		$wpdb->delete( Infinity_Metrics_Database::conversions_table(), array( 'source_id' => (int) $source_id ), array( '%d' ) );

		return false !== $wpdb->delete( Infinity_Metrics_Database::sources_table(), array( 'id' => (int) $source_id ), array( '%d' ) );
	}

	/**
	 * Validate and normalize editable fields.
	 *
	 * @param array $data Source fields.
	 * @return array|WP_Error
	 */
	private static function validate( $data ) {
		$name    = sanitize_text_field( isset( $data['name'] ) ? $data['name'] : '' );
		$slug    = sanitize_title( isset( $data['slug'] ) ? $data['slug'] : '' );
		$domain  = self::normalize_domain( isset( $data['domain'] ) ? $data['domain'] : '' );
		$enabled = empty( $data['enabled'] ) ? 0 : 1;

		if ( '' === $name || '' === $slug || '' === $domain ) {
			return new WP_Error( 'infinity_metrics_invalid_source', __( 'Name, slug, and a valid domain are required.', 'infinity-metrics' ) );
		}

		return compact( 'name', 'slug', 'domain', 'enabled' );
	}

	/**
	 * Normalize a domain or URL to a lowercase host name.
	 *
	 * @param string $value Domain or URL.
	 * @return string
	 */
	private static function normalize_domain( $value ) {
		$value = trim( sanitize_text_field( $value ) );
		if ( '' === $value ) {
			return '';
		}

		$url  = false === strpos( $value, '://' ) ? 'https://' . $value : $value;
		$host = wp_parse_url( $url, PHP_URL_HOST );

		return $host ? strtolower( $host ) : '';
	}

	/**
	 * Generate a cryptographically secure public collector key.
	 *
	 * @return string
	 */
	private static function generate_api_key() {
		return 'im_' . bin2hex( random_bytes( 20 ) );
	}
}
