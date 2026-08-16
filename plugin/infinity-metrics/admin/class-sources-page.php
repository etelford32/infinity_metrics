<?php
/**
 * Sources administration screen.
 *
 * @package InfinityMetrics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders and processes the Sources screen.
 */
final class Infinity_Metrics_Sources_Page {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_infinity_metrics_save_source', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_infinity_metrics_delete_source', array( __CLASS__, 'delete' ) );
		add_action( 'admin_post_infinity_metrics_regenerate_key', array( __CLASS__, 'regenerate_key' ) );
	}

	/**
	 * Add the Analytics menu.
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_submenu_page(
			'infinity-metrics',
			__( 'Sources', 'infinity-metrics' ),
			__( 'Sources', 'infinity-metrics' ),
			'manage_options',
			'infinity-metrics-sources',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Render the source list and editor.
	 *
	 * @return void
	 */
	public static function render() {
		self::authorize();

		$source_id = isset( $_GET['source_id'] ) ? absint( $_GET['source_id'] ) : 0;
		$editing   = $source_id ? Infinity_Metrics_Sources::get( $source_id ) : null;
		$sources   = Infinity_Metrics_Sources::all();
		$notice    = isset( $_GET['im_notice'] ) ? sanitize_key( wp_unslash( $_GET['im_notice'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sources', 'infinity-metrics' ); ?></h1>
			<?php self::render_notice( $notice ); ?>

			<h2><?php echo $editing ? esc_html__( 'Edit source', 'infinity-metrics' ) : esc_html__( 'Add source', 'infinity-metrics' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="infinity_metrics_save_source">
				<input type="hidden" name="source_id" value="<?php echo esc_attr( $source_id ); ?>">
				<?php wp_nonce_field( 'infinity_metrics_save_source_' . $source_id ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="im-name"><?php esc_html_e( 'Name', 'infinity-metrics' ); ?></label></th><td><input class="regular-text" id="im-name" name="name" required value="<?php echo esc_attr( $editing ? $editing->name : '' ); ?>"></td></tr>
					<tr><th scope="row"><label for="im-slug"><?php esc_html_e( 'Slug', 'infinity-metrics' ); ?></label></th><td><input class="regular-text" id="im-slug" name="slug" required value="<?php echo esc_attr( $editing ? $editing->slug : '' ); ?>"><p class="description"><?php esc_html_e( 'Stable identifier used by the tracker.', 'infinity-metrics' ); ?></p></td></tr>
					<tr><th scope="row"><label for="im-domain"><?php esc_html_e( 'Domain', 'infinity-metrics' ); ?></label></th><td><input class="regular-text" id="im-domain" name="domain" required placeholder="example.com" value="<?php echo esc_attr( $editing ? $editing->domain : '' ); ?>"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Status', 'infinity-metrics' ); ?></th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( ! $editing || $editing->enabled ); ?>> <?php esc_html_e( 'Active', 'infinity-metrics' ); ?></label></td></tr>
				</table>
				<?php submit_button( $editing ? __( 'Update source', 'infinity-metrics' ) : __( 'Add source', 'infinity-metrics' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Configured sources', 'infinity-metrics' ); ?></h2>
			<?php self::render_sources( $sources ); ?>
		</div>
		<?php
	}

	/**
	 * Process source creation or editing.
	 *
	 * @return void
	 */
	public static function save() {
		self::authorize();
		$source_id = isset( $_POST['source_id'] ) ? absint( $_POST['source_id'] ) : 0;
		check_admin_referer( 'infinity_metrics_save_source_' . $source_id );

		$data = array(
			'name'    => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
			'slug'    => isset( $_POST['slug'] ) ? wp_unslash( $_POST['slug'] ) : '',
			'domain'  => isset( $_POST['domain'] ) ? wp_unslash( $_POST['domain'] ) : '',
			'enabled' => isset( $_POST['enabled'] ),
		);
		$result = $source_id ? Infinity_Metrics_Sources::update( $source_id, $data ) : Infinity_Metrics_Sources::create( $data );

		self::redirect( is_wp_error( $result ) ? 'error' : 'saved' );
	}

	/**
	 * Process deletion.
	 *
	 * @return void
	 */
	public static function delete() {
		self::authorize();
		$source_id = isset( $_POST['source_id'] ) ? absint( $_POST['source_id'] ) : 0;
		check_admin_referer( 'infinity_metrics_delete_source_' . $source_id );

		self::redirect( $source_id && Infinity_Metrics_Sources::delete( $source_id ) ? 'deleted' : 'error' );
	}

	/**
	 * Process key regeneration.
	 *
	 * @return void
	 */
	public static function regenerate_key() {
		self::authorize();
		$source_id = isset( $_POST['source_id'] ) ? absint( $_POST['source_id'] ) : 0;
		check_admin_referer( 'infinity_metrics_regenerate_key_' . $source_id );
		$result = Infinity_Metrics_Sources::regenerate_api_key( $source_id );

		self::redirect( is_wp_error( $result ) ? 'error' : 'regenerated' );
	}

	/**
	 * Render configured sources.
	 *
	 * @param array $sources Sources.
	 * @return void
	 */
	private static function render_sources( $sources ) {
		if ( ! $sources ) {
			echo '<p>' . esc_html__( 'No sources configured yet.', 'infinity-metrics' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Name', 'infinity-metrics' ); ?></th><th><?php esc_html_e( 'Domain', 'infinity-metrics' ); ?></th><th><?php esc_html_e( 'API key', 'infinity-metrics' ); ?></th><th><?php esc_html_e( 'Status', 'infinity-metrics' ); ?></th><th><?php esc_html_e( 'Actions', 'infinity-metrics' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $sources as $source ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $source->name ); ?></strong><br><code><?php echo esc_html( $source->slug ); ?></code></td>
					<td><?php echo esc_html( $source->domain ); ?></td>
					<td><code><?php echo esc_html( $source->api_key ); ?></code></td>
					<td><?php echo $source->enabled ? esc_html__( 'Active', 'infinity-metrics' ) : esc_html__( 'Disabled', 'infinity-metrics' ); ?></td>
					<td>
						<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'infinity-metrics-sources', 'source_id' => $source->id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'infinity-metrics' ); ?></a>
						<?php self::action_form( 'infinity_metrics_regenerate_key', 'infinity_metrics_regenerate_key_' . $source->id, $source->id, __( 'Regenerate key', 'infinity-metrics' ), __( 'Regenerate this key? Existing tracker installations will stop working.', 'infinity-metrics' ) ); ?>
						<?php self::action_form( 'infinity_metrics_delete_source', 'infinity_metrics_delete_source_' . $source->id, $source->id, __( 'Delete', 'infinity-metrics' ), __( 'Delete this source and all of its events? This cannot be undone.', 'infinity-metrics' ) ); ?>
					</td>
				</tr>
				<tr><td colspan="5"><strong><?php esc_html_e( 'Installation', 'infinity-metrics' ); ?></strong><br><code><?php echo esc_html( self::installation_snippet( $source ) ); ?></code></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render a small POST action form.
	 *
	 * @param string $action    Admin action.
	 * @param string $nonce     Nonce action.
	 * @param int    $source_id Source ID.
	 * @param string $label     Button label.
	 * @param string $confirm   Confirmation message.
	 * @return void
	 */
	private static function action_form( $action, $nonce, $source_id, $label, $confirm ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_js( $confirm ); ?>');">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
			<input type="hidden" name="source_id" value="<?php echo esc_attr( $source_id ); ?>">
			<?php wp_nonce_field( $nonce ); ?>
			<button class="button button-small" type="submit"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	/**
	 * Build the future tracker installation snippet.
	 *
	 * @param object $source Source.
	 * @return string
	 */
	private static function installation_snippet( $source ) {
		return sprintf(
			'<script src="%s" data-source="%s" data-key="%s"></script>',
			plugins_url( 'assets/tracker.js', INFINITY_METRICS_PLUGIN_FILE ),
			$source->slug,
			$source->api_key
		);
	}

	/**
	 * Render a redirect notice.
	 *
	 * @param string $notice Notice key.
	 * @return void
	 */
	private static function render_notice( $notice ) {
		$messages = array(
			'saved'       => __( 'Source saved.', 'infinity-metrics' ),
			'deleted'     => __( 'Source and its events deleted.', 'infinity-metrics' ),
			'regenerated' => __( 'API key regenerated. Update existing tracker installations.', 'infinity-metrics' ),
			'error'       => __( 'The requested source change could not be completed.', 'infinity-metrics' ),
		);
		if ( isset( $messages[ $notice ] ) ) {
			$class = 'error' === $notice ? 'notice notice-error' : 'notice notice-success';
			printf( '<div class="%s"><p>%s</p></div>', esc_attr( $class ), esc_html( $messages[ $notice ] ) );
		}
	}

	/**
	 * Require an administrator capability.
	 *
	 * @return void
	 */
	private static function authorize() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage analytics sources.', 'infinity-metrics' ) );
		}
	}

	/**
	 * Redirect back to the Sources screen.
	 *
	 * @param string $notice Notice key.
	 * @return void
	 */
	private static function redirect( $notice ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'infinity-metrics-sources', 'im_notice' => $notice ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
