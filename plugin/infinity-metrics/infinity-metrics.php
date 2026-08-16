<?php
/**
 * Plugin Name: Infinity Metrics
 * Plugin URI:  https://github.com/elliottelford/infinity-metrics
 * Description: Lightweight, self-hosted product analytics for multiple sites and applications.
 * Version:     0.8.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author:      Infinity Metrics contributors
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: infinity-metrics
 */

defined( 'ABSPATH' ) || exit;

define( 'INFINITY_METRICS_VERSION', '0.8.0' );
define( 'INFINITY_METRICS_DATABASE_VERSION', '3' );
define( 'INFINITY_METRICS_PLUGIN_FILE', __FILE__ );
define( 'INFINITY_METRICS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once INFINITY_METRICS_PLUGIN_DIR . 'includes/class-database.php';
require_once INFINITY_METRICS_PLUGIN_DIR . 'includes/class-sources.php';
require_once INFINITY_METRICS_PLUGIN_DIR . 'includes/class-events.php';
require_once INFINITY_METRICS_PLUGIN_DIR . 'includes/class-metrics.php';
require_once INFINITY_METRICS_PLUGIN_DIR . 'includes/class-conversions.php';
require_once INFINITY_METRICS_PLUGIN_DIR . 'includes/class-privacy.php';
require_once INFINITY_METRICS_PLUGIN_DIR . 'includes/class-rate-limiter.php';
require_once INFINITY_METRICS_PLUGIN_DIR . 'includes/class-event-api.php';

if ( is_admin() ) {
	require_once INFINITY_METRICS_PLUGIN_DIR . 'admin/class-dashboard.php';
	require_once INFINITY_METRICS_PLUGIN_DIR . 'admin/class-sources-page.php';
	require_once INFINITY_METRICS_PLUGIN_DIR . 'admin/class-events-page.php';
	require_once INFINITY_METRICS_PLUGIN_DIR . 'admin/class-conversions-page.php';
}

register_activation_hook( INFINITY_METRICS_PLUGIN_FILE, array( 'Infinity_Metrics_Database', 'install' ) );

/**
 * Upgrade the schema when plugin files are updated without reactivation.
 *
 * @return void
 */
function infinity_metrics_maybe_upgrade_database() {
	if ( INFINITY_METRICS_DATABASE_VERSION !== get_option( 'infinity_metrics_database_version' ) ) {
		Infinity_Metrics_Database::install();
	}
}
add_action( 'plugins_loaded', 'infinity_metrics_maybe_upgrade_database' );

/**
 * Register the WordPress administration UI.
 *
 * @return void
 */
function infinity_metrics_boot_admin() {
	if ( is_admin() ) {
		Infinity_Metrics_Dashboard::register();
		Infinity_Metrics_Sources_Page::register();
		Infinity_Metrics_Events_Page::register();
		Infinity_Metrics_Conversions_Page::register();
	}
}
add_action( 'plugins_loaded', 'infinity_metrics_boot_admin' );

Infinity_Metrics_Event_API::register();
