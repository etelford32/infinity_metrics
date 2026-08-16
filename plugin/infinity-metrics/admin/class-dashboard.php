<?php
/**
 * Analytics dashboard.
 *
 * @package InfinityMetrics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the v0.1 overview dashboard.
 */
final class Infinity_Metrics_Dashboard {

	/** Register admin hooks. */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/** Add the top-level Analytics menu and Overview submenu. */
	public static function add_menu() {
		add_menu_page(
			__( 'Infinity Metrics', 'infinity-metrics' ),
			__( 'Analytics', 'infinity-metrics' ),
			'manage_options',
			'infinity-metrics',
			array( __CLASS__, 'render' ),
			'dashicons-chart-area',
			30
		);
		add_submenu_page(
			'infinity-metrics',
			__( 'Overview', 'infinity-metrics' ),
			__( 'Overview', 'infinity-metrics' ),
			'manage_options',
			'infinity-metrics',
			array( __CLASS__, 'render' )
		);
	}

	/** Load dashboard styles only on the overview screen. */
	public static function enqueue_assets( $hook ) {
		if ( 'toplevel_page_infinity-metrics' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'infinity-metrics-admin', plugins_url( 'assets/admin.css', INFINITY_METRICS_PLUGIN_FILE ), array(), INFINITY_METRICS_VERSION );
	}

	/** Render the dashboard. */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to view analytics.', 'infinity-metrics' ) );
		}

		$source_id = isset( $_GET['source_id'] ) ? absint( $_GET['source_id'] ) : 0;
		$end       = wp_date( 'Y-m-d' );
		$start     = wp_date( 'Y-m-d', time() - ( 29 * DAY_IN_SECONDS ) );
		$sources   = Infinity_Metrics_Sources::all();
		$metrics   = array(
			'visitors'    => Infinity_Metrics_Metrics::visitors( $source_id, $start, $end ),
			'engaged'     => Infinity_Metrics_Metrics::engaged_sessions( $source_id, $start, $end ),
			'interacted'  => Infinity_Metrics_Metrics::interacting_sessions( $source_id, $start, $end ),
			'intent'      => Infinity_Metrics_Metrics::conversion_start_sessions( $source_id, $start, $end ),
			'converted'   => Infinity_Metrics_Metrics::converted_sessions( $source_id, $start, $end ),
			'conversions' => Infinity_Metrics_Metrics::conversions( $source_id, $start, $end ),
		);
		$traffic   = Infinity_Metrics_Metrics::daily_page_views( $source_id, $start, $end );
		$top_events = Infinity_Metrics_Metrics::top_events( $source_id, $start, $end, 8 );
		$breakdown = $source_id ? array() : Infinity_Metrics_Metrics::source_breakdown( $start, $end );
		?>
		<div class="wrap infinity-metrics-dashboard">
			<div class="im-dashboard-heading">
				<div><h1><?php esc_html_e( 'Analytics Overview', 'infinity-metrics' ); ?></h1><p><?php echo esc_html( sprintf( __( '%1$s to %2$s', 'infinity-metrics' ), $start, $end ) ); ?></p></div>
				<?php self::render_source_filter( $sources, $source_id ); ?>
			</div>
			<div class="im-stat-grid">
				<?php self::stat( __( 'Visitors', 'infinity-metrics' ), $metrics['visitors'] ); ?>
				<?php self::stat( __( 'Engaged sessions', 'infinity-metrics' ), $metrics['engaged'] ); ?>
				<?php self::stat( __( 'Conversions', 'infinity-metrics' ), $metrics['conversions'] ); ?>
			</div>
			<section class="im-panel"><h2><?php esc_html_e( 'Traffic', 'infinity-metrics' ); ?></h2><?php self::render_traffic( $traffic ); ?></section>
			<div class="im-dashboard-columns">
				<section class="im-panel"><h2><?php esc_html_e( 'Funnel', 'infinity-metrics' ); ?></h2><?php self::render_funnel( $metrics ); ?></section>
				<section class="im-panel"><h2><?php echo $source_id ? esc_html__( 'Top events', 'infinity-metrics' ) : esc_html__( 'Sources', 'infinity-metrics' ); ?></h2><?php $source_id ? self::render_ranking( $top_events, 'event' ) : self::render_ranking( $breakdown, 'name' ); ?></section>
			</div>
			<?php if ( ! $source_id ) : ?><section class="im-panel"><h2><?php esc_html_e( 'Top events', 'infinity-metrics' ); ?></h2><?php self::render_ranking( $top_events, 'event' ); ?></section><?php endif; ?>
		</div>
		<?php
	}

	/** Render the source selector. */
	private static function render_source_filter( $sources, $source_id ) {
		?>
		<form method="get"><input type="hidden" name="page" value="infinity-metrics"><label for="im-dashboard-source" class="screen-reader-text"><?php esc_html_e( 'Source', 'infinity-metrics' ); ?></label><select id="im-dashboard-source" name="source_id" onchange="this.form.submit()"><option value="0"><?php esc_html_e( 'All sources', 'infinity-metrics' ); ?></option><?php foreach ( $sources as $source ) : ?><option value="<?php echo esc_attr( $source->id ); ?>" <?php selected( $source_id, $source->id ); ?>><?php echo esc_html( $source->name ); ?></option><?php endforeach; ?></select><noscript><?php submit_button( __( 'Apply', 'infinity-metrics' ), 'secondary', '', false ); ?></noscript></form>
		<?php
	}

	/** Render one headline statistic. */
	private static function stat( $label, $value ) {
		printf( '<div class="im-stat"><span>%s</span><strong>%s</strong></div>', esc_html( $label ), esc_html( number_format_i18n( $value ) ) );
	}

	/** Render a compact CSS traffic chart. */
	private static function render_traffic( $rows ) {
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No page views in this period.', 'infinity-metrics' ) . '</p>';
			return;
		}
		$maximum = max( array_map( function ( $row ) { return (int) $row->total; }, $rows ) );
		echo '<div class="im-traffic-chart" role="img" aria-label="' . esc_attr__( 'Daily page views', 'infinity-metrics' ) . '">';
		foreach ( $rows as $row ) {
			$height = $maximum ? max( 4, round( ( (int) $row->total / $maximum ) * 100 ) ) : 4;
			printf( '<span style="height:%1$d%%" title="%2$s: %3$s"></span>', (int) $height, esc_attr( $row->event_date ), esc_attr( number_format_i18n( $row->total ) ) );
		}
		echo '</div>';
	}

	/** Render funnel stages relative to visitors. */
	private static function render_funnel( $metrics ) {
		$stages = array( 'visitors' => __( 'Visitors', 'infinity-metrics' ), 'engaged' => __( 'Engaged', 'infinity-metrics' ), 'interacted' => __( 'Interacted', 'infinity-metrics' ), 'intent' => __( 'Intent', 'infinity-metrics' ), 'converted' => __( 'Converted', 'infinity-metrics' ) );
		foreach ( $stages as $key => $label ) {
			$width = $metrics['visitors'] ? min( 100, round( ( $metrics[ $key ] / $metrics['visitors'] ) * 100 ) ) : 0;
			printf( '<div class="im-funnel-row"><div><span>%s</span><strong>%s</strong></div><div class="im-funnel-track"><span style="width:%d%%"></span></div></div>', esc_html( $label ), esc_html( number_format_i18n( $metrics[ $key ] ) ), (int) $width );
		}
	}

	/** Render source or event rankings. */
	private static function render_ranking( $rows, $label_key ) {
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No matching data.', 'infinity-metrics' ) . '</p>';
			return;
		}
		echo '<ol class="im-ranking">';
		foreach ( $rows as $row ) {
			printf( '<li><span>%s</span><strong>%s</strong></li>', esc_html( $row->{$label_key} ), esc_html( number_format_i18n( $row->total ) ) );
		}
		echo '</ol>';
	}
}
