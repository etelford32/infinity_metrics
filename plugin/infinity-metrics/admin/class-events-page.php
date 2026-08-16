<?php
/**
 * Raw event explorer administration screen.
 *
 * @package InfinityMetrics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Events screen.
 */
final class Infinity_Metrics_Events_Page {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
	}

	/**
	 * Add the Events submenu.
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_submenu_page(
			'infinity-metrics',
			__( 'Events', 'infinity-metrics' ),
			__( 'Events', 'infinity-metrics' ),
			'manage_options',
			'infinity-metrics-events',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Render filters, pagination, and raw events.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to view analytics events.', 'infinity-metrics' ) );
		}

		$filters      = self::filters();
		$result       = Infinity_Metrics_Events::query( $filters );
		$sources      = Infinity_Metrics_Sources::all();
		$source_names = array();
		foreach ( $sources as $source ) {
			$source_names[ $source->id ] = $source->name;
		}
		$event_names = Infinity_Metrics_Events::event_names( $filters['source_id'] );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Events', 'infinity-metrics' ); ?></h1>
			<p><?php esc_html_e( 'Inspect the validated event stream before building reports or funnels.', 'infinity-metrics' ); ?></p>
			<?php self::render_filters( $filters, $sources, $event_names ); ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: number of matching events. */
					esc_html( _n( '%s matching event', '%s matching events', $result['total'], 'infinity-metrics' ) ),
					esc_html( number_format_i18n( $result['total'] ) )
				);
				?>
			</p>
			<?php self::render_table( $result['items'], $source_names ); ?>
			<?php self::render_pagination( $result ); ?>
		</div>
		<?php
	}

	/**
	 * Read and sanitize URL filters.
	 *
	 * @return array
	 */
	private static function filters() {
		return array(
			'source_id' => isset( $_GET['source_id'] ) ? absint( $_GET['source_id'] ) : 0,
			'event'     => isset( $_GET['event'] ) ? sanitize_text_field( wp_unslash( $_GET['event'] ) ) : '',
			'start'     => isset( $_GET['start'] ) ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : '',
			'end'       => isset( $_GET['end'] ) ? sanitize_text_field( wp_unslash( $_GET['end'] ) ) : '',
			'page'      => isset( $_GET['event_page'] ) ? sanitize_text_field( wp_unslash( $_GET['event_page'] ) ) : '',
			'paged'     => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
		);
	}

	/**
	 * Render the explorer filter form.
	 *
	 * @param array $filters     Active filters.
	 * @param array $sources     Sources.
	 * @param array $event_names Event names.
	 * @return void
	 */
	private static function render_filters( $filters, $sources, $event_names ) {
		?>
		<form method="get">
			<input type="hidden" name="page" value="infinity-metrics-events">
			<label class="screen-reader-text" for="im-source-filter"><?php esc_html_e( 'Filter by source', 'infinity-metrics' ); ?></label>
			<select id="im-source-filter" name="source_id">
				<option value="0"><?php esc_html_e( 'All sources', 'infinity-metrics' ); ?></option>
				<?php foreach ( $sources as $source ) : ?>
					<option value="<?php echo esc_attr( $source->id ); ?>" <?php selected( $filters['source_id'], $source->id ); ?>><?php echo esc_html( $source->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<label class="screen-reader-text" for="im-event-filter"><?php esc_html_e( 'Filter by event', 'infinity-metrics' ); ?></label>
			<select id="im-event-filter" name="event">
				<option value=""><?php esc_html_e( 'All events', 'infinity-metrics' ); ?></option>
				<?php foreach ( $event_names as $event_name ) : ?>
					<option value="<?php echo esc_attr( $event_name ); ?>" <?php selected( $filters['event'], $event_name ); ?>><?php echo esc_html( $event_name ); ?></option>
				<?php endforeach; ?>
			</select>
			<label for="im-start"><?php esc_html_e( 'From', 'infinity-metrics' ); ?></label>
			<input id="im-start" type="date" name="start" value="<?php echo esc_attr( $filters['start'] ); ?>">
			<label for="im-end"><?php esc_html_e( 'To', 'infinity-metrics' ); ?></label>
			<input id="im-end" type="date" name="end" value="<?php echo esc_attr( $filters['end'] ); ?>">
			<label class="screen-reader-text" for="im-page-filter"><?php esc_html_e( 'Filter by page', 'infinity-metrics' ); ?></label>
			<input id="im-page-filter" type="search" name="event_page" placeholder="<?php esc_attr_e( 'Page contains…', 'infinity-metrics' ); ?>" value="<?php echo esc_attr( $filters['page'] ); ?>">
			<?php submit_button( __( 'Filter', 'infinity-metrics' ), 'secondary', 'filter_action', false ); ?>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=infinity-metrics-events' ) ); ?>"><?php esc_html_e( 'Clear', 'infinity-metrics' ); ?></a>
		</form>
		<?php
	}

	/**
	 * Render raw event rows.
	 *
	 * @param array $events       Events.
	 * @param array $source_names Source names keyed by ID.
	 * @return void
	 */
	private static function render_table( $events, $source_names ) {
		?>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Time', 'infinity-metrics' ); ?></th><th><?php esc_html_e( 'Source', 'infinity-metrics' ); ?></th><th><?php esc_html_e( 'Event', 'infinity-metrics' ); ?></th><th><?php esc_html_e( 'Page', 'infinity-metrics' ); ?></th><th><?php esc_html_e( 'Session', 'infinity-metrics' ); ?></th><th><?php esc_html_e( 'Properties', 'infinity-metrics' ); ?></th></tr></thead>
			<tbody>
			<?php if ( ! $events ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No events match these filters.', 'infinity-metrics' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $events as $event ) : ?>
				<tr>
					<td><?php echo esc_html( get_date_from_gmt( $event->event_timestamp, 'Y-m-d H:i:s' ) ); ?></td>
					<td><?php echo esc_html( isset( $source_names[ $event->source_id ] ) ? $source_names[ $event->source_id ] : __( 'Deleted source', 'infinity-metrics' ) ); ?></td>
					<td><code><?php echo esc_html( $event->event ); ?></code></td>
					<td><?php echo esc_html( $event->page ); ?></td>
					<td><code><?php echo esc_html( $event->session_id ); ?></code></td>
					<td><code><?php echo esc_html( self::format_properties( $event->properties ) ); ?></code></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Pretty-print valid JSON while safely retaining an invalid stored value.
	 *
	 * @param string $properties Stored JSON.
	 * @return string
	 */
	private static function format_properties( $properties ) {
		$decoded = json_decode( $properties, true );

		return JSON_ERROR_NONE === json_last_error() ? wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : $properties;
	}

	/**
	 * Render pagination while preserving active filters.
	 *
	 * @param array $result Query result.
	 * @return void
	 */
	private static function render_pagination( $result ) {
		if ( $result['pages'] <= 1 ) {
			return;
		}
		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo wp_kses_post(
			paginate_links(
				array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'current'   => $result['page'],
					'total'     => $result['pages'],
					'prev_text' => __( 'Previous', 'infinity-metrics' ),
					'next_text' => __( 'Next', 'infinity-metrics' ),
				)
			)
		);
		echo '</div></div>';
	}
}
