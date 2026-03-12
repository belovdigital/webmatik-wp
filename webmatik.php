<?php
/**
 * Plugin Name: Webmatik — AI Website Audit
 * Plugin URI: https://webmatik.ai
 * Description: Run AI-powered website audits directly from your WordPress dashboard. Analyzes performance, SEO, UI/UX, conversion, retention, and accessibility with a prioritized action plan.
 * Version: 1.0.0
 * Author: Webmatik
 * Author URI: https://belov.digital
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: webmatik
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEBMATIK_VERSION', '1.0.0' );
define( 'WEBMATIK_API_URL', 'https://webmatik.ai/api/v1' );
define( 'WEBMATIK_CONNECT_URL', 'https://webmatik.ai/connect?source=WordPress' );
define( 'WEBMATIK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WEBMATIK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/* ───────────────────────── Admin menu ───────────────────────── */

add_action( 'admin_menu', 'webmatik_admin_menu' );

function webmatik_admin_menu() {
	add_menu_page(
		'Webmatik',
		'Webmatik',
		'manage_options',
		'webmatik',
		'webmatik_main_page',
		'dashicons-chart-bar',
		100
	);
}

/* ─────────────────────── Register setting ───────────────────── */

add_action( 'admin_init', 'webmatik_register_settings' );

function webmatik_register_settings() {
	register_setting( 'webmatik_settings', 'webmatik_api_key', array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => '',
	) );
}

/* ─────────────────── Enqueue admin scripts ──────────────────── */

add_action( 'admin_enqueue_scripts', 'webmatik_enqueue_scripts' );

function webmatik_enqueue_scripts( $hook ) {
	if ( false === strpos( $hook, 'webmatik' ) ) {
		return;
	}

	wp_enqueue_script( 'wp-util' );

	wp_enqueue_script(
		'webmatik-admin',
		WEBMATIK_PLUGIN_URL . 'assets/js/webmatik-admin.js',
		array( 'wp-util' ),
		WEBMATIK_VERSION,
		true
	);

	wp_localize_script( 'webmatik-admin', 'webmatikAdmin', array(
		'connectUrl' => WEBMATIK_CONNECT_URL,
		'saveNonce'  => wp_create_nonce( 'webmatik_save_key' ),
		'runNonce'   => wp_create_nonce( 'webmatik_run_audit' ),
		'pollNonce'  => wp_create_nonce( 'webmatik_poll_audit' ),
	) );
}

/* ─────────────────── Main page (connect + audit) ────────────── */

function webmatik_main_page() {
	$api_key    = get_option( 'webmatik_api_key', '' );
	$connected  = ! empty( $api_key );
	$host       = wp_parse_url( home_url(), PHP_URL_HOST );
	$last_audit = get_option( 'webmatik_last_audit', null );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Webmatik', 'webmatik' ); ?></h1>

		<!-- Connection status -->
		<div id="webmatik-connection" style="background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:20px;margin:20px 0;max-width:600px;">
			<?php if ( $connected ) : ?>
				<div style="display:flex;align-items:center;justify-content:space-between;">
					<div style="display:flex;align-items:center;gap:8px;">
						<span style="color:#46b450;font-size:18px;">&#10003;</span>
						<span>
							<strong><?php esc_html_e( 'Connected', 'webmatik' ); ?></strong>
							<span style="color:#787c82;font-size:13px;">(<?php echo esc_html( substr( $api_key, 0, 12 ) ); ?>...)</span>
						</span>
					</div>
					<button id="webmatik-disconnect" class="button button-link" style="color:#d63638;text-decoration:none;">
						<?php esc_html_e( 'Disconnect', 'webmatik' ); ?>
					</button>
				</div>
			<?php else : ?>
				<div style="display:flex;align-items:center;justify-content:space-between;">
					<span style="color:#787c82;"><?php esc_html_e( 'Not connected', 'webmatik' ); ?></span>
					<button id="webmatik-connect" class="button button-primary">
						<?php esc_html_e( 'Connect to Webmatik', 'webmatik' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>

		<!-- Audit -->
		<div style="background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:20px;margin:20px 0;max-width:600px;">
			<p style="margin:0 0 15px;color:#50575e;">
				<?php
				printf(
					/* translators: %s: site hostname */
					esc_html__( 'Run a full AI Growth Audit on %s', 'webmatik' ),
					'<strong>' . esc_html( $host ) . '</strong>'
				);
				?>
			</p>

			<button id="webmatik-run-audit" class="button button-primary button-large" <?php disabled( ! $connected ); ?>>
				<?php esc_html_e( 'Run Audit', 'webmatik' ); ?>
			</button>
			<span id="webmatik-status" style="margin-left:12px;"></span>

			<?php if ( ! $connected ) : ?>
				<p id="webmatik-connect-hint" style="margin:10px 0 0;color:#787c82;font-size:13px;">
					<?php esc_html_e( 'Connect your Webmatik account above to run audits.', 'webmatik' ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $last_audit ) : ?>
				<div id="webmatik-result" style="margin-top:20px;padding:15px;background:#f0f0f1;border-radius:6px;">
					<div style="display:flex;align-items:center;gap:15px;">
						<div style="font-size:36px;font-weight:700;color:#2271b1;">
							<?php echo esc_html( $last_audit['score'] ); ?>
						</div>
						<div>
							<div style="font-size:14px;font-weight:600;"><?php esc_html_e( 'Growth Score', 'webmatik' ); ?></div>
							<div style="color:#50575e;"><?php echo esc_html( 'Grade: ' . $last_audit['grade'] ); ?></div>
						</div>
					</div>
					<?php if ( ! empty( $last_audit['report_url'] ) ) : ?>
						<p style="margin:10px 0 0;">
							<a href="<?php echo esc_url( $last_audit['report_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="button">
								<?php esc_html_e( 'View Full Report', 'webmatik' ); ?> &rarr;
							</a>
						</p>
					<?php endif; ?>
					<p style="margin:8px 0 0;color:#787c82;font-size:12px;">
						<?php echo esc_html( 'Last audit: ' . $last_audit['date'] ); ?>
					</p>
				</div>
			<?php else : ?>
				<div id="webmatik-result" style="display:none;margin-top:20px;padding:15px;background:#f0f0f1;border-radius:6px;"></div>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/* ──────────────────────── AJAX handlers ─────────────────────── */

add_action( 'wp_ajax_webmatik_save_key', 'webmatik_ajax_save_key' );

function webmatik_ajax_save_key() {
	check_ajax_referer( 'webmatik_save_key', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	$key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
	update_option( 'webmatik_api_key', $key );
	wp_send_json_success();
}

add_action( 'wp_ajax_webmatik_run_audit', 'webmatik_ajax_run_audit' );

function webmatik_ajax_run_audit() {
	check_ajax_referer( 'webmatik_run_audit', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	$api_key = get_option( 'webmatik_api_key', '' );
	if ( empty( $api_key ) ) {
		wp_send_json_error( 'Not connected' );
	}

	$response = wp_remote_post( WEBMATIK_API_URL . '/audit', array(
		'timeout' => 30,
		'headers' => array(
			'Content-Type' => 'application/json',
			'X-API-Key'    => $api_key,
		),
		'body' => wp_json_encode( array( 'url' => home_url() ) ),
	) );

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( $response->get_error_message() );
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 !== $code || empty( $body['auditId'] ) ) {
		wp_send_json_error( isset( $body['error'] ) ? $body['error'] : 'API error (HTTP ' . $code . ')' );
	}

	wp_send_json_success( $body );
}

add_action( 'wp_ajax_webmatik_poll_audit', 'webmatik_ajax_poll_audit' );

function webmatik_ajax_poll_audit() {
	check_ajax_referer( 'webmatik_poll_audit', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	$api_key  = get_option( 'webmatik_api_key', '' );
	$audit_id = sanitize_text_field( wp_unslash( $_POST['audit_id'] ?? '' ) );

	// Validate UUID format.
	if ( empty( $api_key ) || ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $audit_id ) ) {
		wp_send_json_error( 'Invalid request' );
	}

	$response = wp_remote_get( WEBMATIK_API_URL . '/audit/' . $audit_id, array(
		'timeout' => 15,
		'headers' => array( 'X-API-Key' => $api_key ),
	) );

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( $response->get_error_message() );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! empty( $body['status'] ) && 'completed' === $body['status'] ) {
		update_option( 'webmatik_last_audit', array(
			'score'      => isset( $body['score'] ) ? floatval( $body['score'] ) : 0,
			'grade'      => sanitize_text_field( isset( $body['grade'] ) ? $body['grade'] : '' ),
			'report_url' => esc_url_raw( isset( $body['reportUrl'] ) ? $body['reportUrl'] : '' ),
			'date'       => current_time( 'Y-m-d H:i' ),
		) );
	}

	wp_send_json_success( $body );
}

/* ──────────────────── Dashboard widget ──────────────────────── */

add_action( 'wp_dashboard_setup', 'webmatik_dashboard_widget' );

function webmatik_dashboard_widget() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_add_dashboard_widget( 'webmatik_widget', 'Webmatik — Growth Score', 'webmatik_widget_render' );
}

function webmatik_widget_render() {
	$api_key    = get_option( 'webmatik_api_key', '' );
	$last_audit = get_option( 'webmatik_last_audit', null );

	if ( empty( $api_key ) ) {
		printf(
			'<p><a href="%s">%s</a></p>',
			esc_url( admin_url( 'admin.php?page=webmatik' ) ),
			esc_html__( 'Connect to Webmatik to see your Growth Score.', 'webmatik' )
		);
		return;
	}

	if ( $last_audit ) {
		echo '<div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">';
		echo '<div style="font-size:28px;font-weight:700;color:#2271b1;">' . esc_html( $last_audit['score'] ) . '</div>';
		echo '<div><strong>' . esc_html__( 'Growth Score', 'webmatik' ) . '</strong><br>';
		echo '<span style="color:#787c82;">' . esc_html( 'Grade: ' . $last_audit['grade'] ) . '</span></div>';
		echo '</div>';
		if ( ! empty( $last_audit['report_url'] ) ) {
			printf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s &rarr;</a>',
				esc_url( $last_audit['report_url'] ),
				esc_html__( 'View Full Report', 'webmatik' )
			);
		}
		echo '<p style="color:#787c82;font-size:12px;margin-top:5px;">' . esc_html( 'Last audit: ' . $last_audit['date'] ) . '</p>';
	} else {
		printf(
			'<p><a href="%s">%s</a></p>',
			esc_url( admin_url( 'admin.php?page=webmatik' ) ),
			esc_html__( 'Run your first audit', 'webmatik' )
		);
	}
}
