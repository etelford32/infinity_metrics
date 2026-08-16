<?php
/**
 * Conversion configuration administration screen.
 *
 * @package InfinityMetrics
 */

defined( 'ABSPATH' ) || exit;

/** Renders and processes conversion definitions. */
final class Infinity_Metrics_Conversions_Page {

	/** Register admin hooks. */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_infinity_metrics_save_conversion', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_infinity_metrics_delete_conversion', array( __CLASS__, 'delete' ) );
	}

	/** Add the Conversions submenu. */
	public static function add_menu() {
		add_submenu_page(
			'infinity-metrics',
			__( 'Conversions', 'infinity-metrics' ),
			__( 'Conversions', 'infinity-metrics' ),
			'manage_options',
			'infinity-metrics-conversions',
			array( __CLASS__, 'render' )
		);
	}

	/** Render the definition form and list. */
	public static function render() {
		self::authorize();
		$conversion_id = isset( $_GET['conversion_id'] ) ? absint( $_GET['conversion_id'] ) : 0;
		$editing       = $conversion_id ? Infinity_Metrics_Conversions::get( $conversion_id ) : null;
		$sources       = Infinity_Metrics_Sources::all();
		$definitions   = Infinity_Metrics_Conversions::all();
		$notice        = isset( $_GET['im_notice'] ) ? sanitize_key( wp_unslash( $_GET['im_notice'] ) ) : '';
		$source_names  = array();
		foreach ( $sources as $source ) {
			$source_names[ $source->id ] = $source->name;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Conversions', 'infinity-metrics' ); ?></h1>
			<p><?php esc_html_e( 'Define which events represent intent and completed outcomes for each source.', 'infinity-metrics' ); ?></p>
			<?php self::render_notice( $notice ); ?>
			<h2><?php echo $editing ? esc_html__( 'Edit conversion', 'infinity-metrics' ) : esc_html__( 'Add conversion', 'infinity-metrics' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="infinity_metrics_save_conversion">
				<input type="hidden" name="conversion_id" value="<?php echo esc_attr( $conversion_id ); ?>">
				<?php wp_nonce_field( 'infinity_metrics_save_conversion_' . $conversion_id ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="im-conversion-source"><?php esc_html_e( 'Source', 'infinity-metrics' ); ?></label></th><td><select id="im-conversion-source" name="source_id" required><option value=""><?php esc_html_e( 'Select a source', 'infinity-metrics' ); ?></option><?php foreach ( $sources as $source ) : ?><option value="<?php echo esc_attr( $source->id ); ?>" <?php selected( $editing ? $editing->source_id : 0, $source->id ); ?>><?php echo esc_html( $source->name ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th scope="row"><label for="im-conversion-name"><?php esc_html_e( 'Name', 'infinity-metrics' ); ?></label></th><td><input class="regular-text" id="im-conversion-name" name="name" required maxlength="190" value="<?php echo esc_attr( $editing ? $editing->name : '' ); ?>" placeholder="<?php esc_attr_e( 'Signup completed', 'infinity-metrics' ); ?>"></td></tr>
					<tr><th scope="row"><label for="im-conversion-event"><?php esc_html_e( 'Event', 'infinity-metrics' ); ?></label></th><td><input class="regular-text" id="im-conversion-event" name="event" required maxlength="175" pattern="[a-z][a-z0-9_.:-]*" value="<?php echo esc_attr( $editing ? $editing->event : '' ); ?>" placeholder="signup_complete"><p class="description"><?php esc_html_e( 'Use the exact event name sent by the tracker.', 'infinity-metrics' ); ?></p></td></tr>
					<tr><th scope="row"><label for="im-conversion-type"><?php esc_html_e( 'Stage', 'infinity-metrics' ); ?></label></th><td><select id="im-conversion-type" name="conversion_type"><option value="start" <?php selected( $editing ? $editing->conversion_type : '', 'start' ); ?>><?php esc_html_e( 'Intent / start', 'infinity-metrics' ); ?></option><option value="complete" <?php selected( $editing ? $editing->conversion_type : 'complete', 'complete' ); ?>><?php esc_html_e( 'Converted / complete', 'infinity-metrics' ); ?></option></select></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Status', 'infinity-metrics' ); ?></th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( ! $editing || $editing->enabled ); ?>> <?php esc_html_e( 'Active', 'infinity-metrics' ); ?></label></td></tr>
				</table>
				<?php submit_button( $editing ? __( 'Update conversion', 'infinity-metrics' ) : __( 'Add conversion', 'infinity-metrics' ) ); ?>
			</form>
			<h2><?php esc_html_e( 'Configured conversions', 'infinity-metrics' ); ?></h2>
			<?php self::render_definitions( $definitions, $source_names ); ?>
		</div>
		<?php
	}

	/** Save a definition. */
	public static function save() {
		self::authorize();
		$conversion_id = isset( $_POST['conversion_id'] ) ? absint( $_POST['conversion_id'] ) : 0;
		check_admin_referer( 'infinity_metrics_save_conversion_' . $conversion_id );
		$data = array(
			'source_id'       => isset( $_POST['source_id'] ) ? absint( $_POST['source_id'] ) : 0,
			'name'            => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
			'event'           => isset( $_POST['event'] ) ? wp_unslash( $_POST['event'] ) : '',
			'conversion_type' => isset( $_POST['conversion_type'] ) ? wp_unslash( $_POST['conversion_type'] ) : '',
			'enabled'         => isset( $_POST['enabled'] ),
		);
		$result = $conversion_id ? Infinity_Metrics_Conversions::update( $conversion_id, $data ) : Infinity_Metrics_Conversions::create( $data );
		self::redirect( is_wp_error( $result ) ? 'error' : 'saved' );
	}

	/** Delete a definition. */
	public static function delete() {
		self::authorize();
		$conversion_id = isset( $_POST['conversion_id'] ) ? absint( $_POST['conversion_id'] ) : 0;
		check_admin_referer( 'infinity_metrics_delete_conversion_' . $conversion_id );
		self::redirect( $conversion_id && Infinity_Metrics_Conversions::delete( $conversion_id ) ? 'deleted' : 'error' );
	}

	/** Render configured definitions. */
	private static function render_definitions( $definitions, $source_names ) {
		if ( ! $definitions ) {
			echo '<p>' . esc_html__( 'No conversion definitions yet. MVP defaults remain active until a source is configured.', 'infinity-metrics' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Name', 'infinity-metrics' ); ?></th><th><?php esc_html_e( 'Source', 'infinity-metrics' ); ?></th><th><?php esc_html_e( 'Event', 'infinity-metrics' ); ?></th><th><?php esc_html_e( 'Stage', 'infinity-metrics' ); ?></th><th><?php esc_html_e( 'Status', 'infinity-metrics' ); ?></th><th><?php esc_html_e( 'Actions', 'infinity-metrics' ); ?></th></tr></thead><tbody>
		<?php foreach ( $definitions as $definition ) : ?>
			<tr><td><strong><?php echo esc_html( $definition->name ); ?></strong></td><td><?php echo esc_html( isset( $source_names[ $definition->source_id ] ) ? $source_names[ $definition->source_id ] : __( 'Deleted source', 'infinity-metrics' ) ); ?></td><td><code><?php echo esc_html( $definition->event ); ?></code></td><td><?php echo 'start' === $definition->conversion_type ? esc_html__( 'Intent', 'infinity-metrics' ) : esc_html__( 'Converted', 'infinity-metrics' ); ?></td><td><?php echo $definition->enabled ? esc_html__( 'Active', 'infinity-metrics' ) : esc_html__( 'Disabled', 'infinity-metrics' ); ?></td><td><a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'infinity-metrics-conversions', 'conversion_id' => $definition->id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'infinity-metrics' ); ?></a> <?php self::delete_form( $definition->id ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php
	}

	/** Render a nonce-protected delete form. */
	private static function delete_form( $conversion_id ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this conversion definition?', 'infinity-metrics' ) ); ?>');"><input type="hidden" name="action" value="infinity_metrics_delete_conversion"><input type="hidden" name="conversion_id" value="<?php echo esc_attr( $conversion_id ); ?>"><?php wp_nonce_field( 'infinity_metrics_delete_conversion_' . $conversion_id ); ?><button class="button button-small" type="submit"><?php esc_html_e( 'Delete', 'infinity-metrics' ); ?></button></form>
		<?php
	}

	/** Render an operation notice. */
	private static function render_notice( $notice ) {
		$messages = array( 'saved' => __( 'Conversion saved.', 'infinity-metrics' ), 'deleted' => __( 'Conversion deleted.', 'infinity-metrics' ), 'error' => __( 'The conversion change could not be completed.', 'infinity-metrics' ) );
		if ( isset( $messages[ $notice ] ) ) {
			printf( '<div class="notice %s"><p>%s</p></div>', 'error' === $notice ? 'notice-error' : 'notice-success', esc_html( $messages[ $notice ] ) );
		}
	}

	/** Require administrator access. */
	private static function authorize() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage conversions.', 'infinity-metrics' ) );
		}
	}

	/** Return to the conversion screen. */
	private static function redirect( $notice ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'infinity-metrics-conversions', 'im_notice' => $notice ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
