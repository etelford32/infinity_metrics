<?php
/**
 * Database installation and table-name helpers.
 *
 * @package InfinityMetrics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Owns the Infinity Metrics database schema.
 */
final class Infinity_Metrics_Database {

	/**
	 * Return the fully prefixed sources table name.
	 *
	 * @return string
	 */
	public static function sources_table() {
		global $wpdb;

		return $wpdb->prefix . 'infinity_metrics_sources';
	}

	/**
	 * Return the fully prefixed events table name.
	 *
	 * @return string
	 */
	public static function events_table() {
		global $wpdb;

		return $wpdb->prefix . 'infinity_metrics_events';
	}

	/** Return the fully prefixed conversions table name. */
	public static function conversions_table() {
		global $wpdb;

		return $wpdb->prefix . 'infinity_metrics_conversions';
	}

	/**
	 * Create or upgrade plugin tables.
	 *
	 * dbDelta makes this operation safe to run for both activation and upgrades.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$sources_table   = self::sources_table();
		$events_table    = self::events_table();
		$conversions_table = self::conversions_table();

		$sources_sql = "CREATE TABLE {$sources_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			slug varchar(190) NOT NULL,
			domain varchar(255) NOT NULL DEFAULT '',
			api_key char(64) NOT NULL,
			created_at datetime NOT NULL,
			enabled tinyint(1) unsigned NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			UNIQUE KEY api_key (api_key),
			KEY enabled (enabled)
		) {$charset_collate};";

		$events_sql = "CREATE TABLE {$events_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_id bigint(20) unsigned NOT NULL,
			event varchar(190) NOT NULL,
			visitor_id varchar(190) NOT NULL DEFAULT '',
			session_id varchar(190) NOT NULL,
			page text NOT NULL,
			referrer text NOT NULL,
			event_timestamp datetime NOT NULL,
			properties longtext NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY source_timestamp (source_id,event_timestamp),
			KEY event_timestamp (event,event_timestamp),
			KEY visitor_id (visitor_id),
			KEY session_id (session_id)
		) {$charset_collate};";

		$conversions_sql = "CREATE TABLE {$conversions_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_id bigint(20) unsigned NOT NULL,
			name varchar(190) NOT NULL,
			event varchar(175) NOT NULL,
			conversion_type varchar(10) NOT NULL,
			enabled tinyint(1) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_event_type (source_id,event,conversion_type),
			KEY source_enabled_type (source_id,enabled,conversion_type)
		) {$charset_collate};";

		dbDelta( $sources_sql );
		dbDelta( $events_sql );
		dbDelta( $conversions_sql );

		update_option( 'infinity_metrics_database_version', INFINITY_METRICS_DATABASE_VERSION, false );
	}
}
