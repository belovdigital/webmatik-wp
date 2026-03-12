<?php
/**
 * Plugin Name: Webmatik — AI Website Audit
 * Plugin URI: https://webmatik.ai
 * Description: Run AI-powered website audits directly from your WordPress dashboard. Analyzes performance, SEO, UI/UX, conversion, retention, and accessibility with a prioritized action plan.
 * Version: 1.0.0
 * Author: Webmatik
 * Author URI: https://webmatik.ai
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
define( 'WEBMATIK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Admin menu
 */
add_action( 'admin_menu', 'webmatik_admin_menu' );

function webmatik_admin_menu() {
	add_menu_page(
		'Webmatik',
		'Webmatik',
		'manage_options',
		'webmatik',
		'webmatik_dashboard_page',
		'dashicons-chart-bar',
		100
	);

	add_submenu_page(
		'webmatik',
		'Webmatik Settings',
		'Settings',
		'manage_options',
		'webmatik-settings',
		'webmatik_settings_page'
	);
}

/**
 * Settings — register
 */
add_action( 'admin_init', 'webmatik_register_settings' );

function webmatik_register_settings() {
	register_setting( 'webmatik_settings', 'webmatik_api_key', array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => '',
	) );
}

/**
 * Settings page
 */
function webmatik_settings_page() {
	$api_key = get_option( 'webmatik_api_key', '' );
	$connected = ! empty( $api_key );
	?>
	<div class="wrap">
		<h1>Webmatik Settings</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'webmatik_settings' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="webmatik_api_key">API Key</label></th>
					<td>
						<input type="password" id="webmatik_api_key" name="webmatik_api_key"
							value="<?php echo esc_attr( $api_key ); ?>"
							class="regular-text" placeholder="wmk_..." />
						<p class="description">
							<?php if ( $connected ) : ?>
								<span style="color: #46b450;">&#10003; Connected.</span>
							<?php endif; ?>
							Get your API key from <a href="https://webmatik.ai/account" target="_blank">webmatik.ai/account</a>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save Settings' ); ?>
		</form>
	</div>
	<?php
}

/**
 * Dashboard page
 */
function webmatik_dashboard_page() {
	$api_key = get_option( 'webmatik_api_key', '' );

	if ( empty( $api_key ) ) {
		echo '<div class="wrap"><h1>Webmatik</h1>';
		echo '<div class="notice notice-warning"><p>';
		echo 'Please <a href="' . esc_url( admin_url( 'admin.php?page=webmatik-settings' ) ) . '">add your API key</a> to get started.';
		echo '</p></div></div>';
		return;
	}

	$site_url = home_url();
	$last_audit = get_option( 'webmatik_last_audit', null );
	?>
	<div class="wrap">
		<h1>Webmatik — AI Website Audit</h1>

		<div class="webmatik-card" style="background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:20px;margin:20px 0;max-width:600px;">
			<p style="margin:0 0 15px;color:#50575e;">
				Run a full Growth Audit on <strong><?php echo esc_html( wp_parse_url( $site_url, PHP_URL_HOST ) ); ?></strong>
			</p>

			<button id="webmatik-run-audit" class="button button-primary button-large">
				Run Audit
			</button>
			<span id="webmatik-status" style="margin-left:12px;"></span>

			<?php if ( $last_audit ) : ?>
				<div id="webmatik-result" style="margin-top:20px;padding:15px;background:#f0f0f1;border-radius:6px;">
					<div style="display:flex;align-items:center;gap:15px;">
						<div style="font-size:36px;font-weight:700;color:#2271b1;">
							<?php echo esc_html( $last_audit['score'] ); ?>
						</div>
						<div>
							<div style="font-size:14px;font-weight:600;">Growth Score</div>
							<div style="color:#50575e;">Grade: <?php echo esc_html( $last_audit['grade'] ); ?></div>
						</div>
					</div>
					<?php if ( ! empty( $last_audit['report_url'] ) ) : ?>
						<p style="margin:10px 0 0;">
							<a href="<?php echo esc_url( $last_audit['report_url'] ); ?>" target="_blank" class="button">
								View Full Report &rarr;
							</a>
						</p>
					<?php endif; ?>
					<p style="margin:8px 0 0;color:#787c82;font-size:12px;">
						Last audit: <?php echo esc_html( $last_audit['date'] ); ?>
					</p>
				</div>
			<?php else : ?>
				<div id="webmatik-result" style="display:none;margin-top:20px;padding:15px;background:#f0f0f1;border-radius:6px;"></div>
			<?php endif; ?>
		</div>
	</div>

	<script>
	(function() {
		var btn = document.getElementById('webmatik-run-audit');
		var status = document.getElementById('webmatik-status');
		var result = document.getElementById('webmatik-result');

		btn.addEventListener('click', function() {
			btn.disabled = true;
			status.textContent = 'Starting audit...';
			result.style.display = 'none';

			wp.ajax.post('webmatik_run_audit', {
				nonce: '<?php echo esc_js( wp_create_nonce( 'webmatik_run_audit' ) ); ?>'
			}).done(function(data) {
				if (data.auditId) {
					status.textContent = 'Analyzing... this takes 2–3 minutes.';
					pollAudit(data.auditId);
				} else {
					status.textContent = 'Error: ' + (data.error || 'Unknown error');
					btn.disabled = false;
				}
			}).fail(function(err) {
				status.textContent = 'Error: ' + (err || 'Request failed');
				btn.disabled = false;
			});
		});

		function pollAudit(auditId) {
			wp.ajax.post('webmatik_poll_audit', {
				nonce: '<?php echo esc_js( wp_create_nonce( 'webmatik_poll_audit' ) ); ?>',
				audit_id: auditId
			}).done(function(data) {
				if (data.status === 'processing') {
					setTimeout(function() { pollAudit(auditId); }, 5000);
				} else if (data.status === 'completed') {
					status.textContent = '';
					btn.disabled = false;
					result.style.display = 'block';
					result.innerHTML =
						'<div style="display:flex;align-items:center;gap:15px;">' +
							'<div style="font-size:36px;font-weight:700;color:#2271b1;">' + data.score + '</div>' +
							'<div><div style="font-size:14px;font-weight:600;">Growth Score</div>' +
							'<div style="color:#50575e;">Grade: ' + data.grade + '</div></div>' +
						'</div>' +
						(data.reportUrl ? '<p style="margin:10px 0 0;"><a href="' + data.reportUrl + '" target="_blank" class="button">View Full Report &rarr;</a></p>' : '') +
						'<p style="margin:8px 0 0;color:#787c82;font-size:12px;">Just now</p>';
				} else {
					status.textContent = 'Audit failed. Please try again.';
					btn.disabled = false;
				}
			}).fail(function() {
				setTimeout(function() { pollAudit(auditId); }, 10000);
			});
		}
	})();
	</script>
	<?php
}

/**
 * AJAX: start audit
 */
add_action( 'wp_ajax_webmatik_run_audit', 'webmatik_ajax_run_audit' );

function webmatik_ajax_run_audit() {
	check_ajax_referer( 'webmatik_run_audit', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	$api_key = get_option( 'webmatik_api_key', '' );
	if ( empty( $api_key ) ) {
		wp_send_json_error( 'API key not configured' );
	}

	$response = wp_remote_post( WEBMATIK_API_URL . '/audit', array(
		'timeout' => 30,
		'headers' => array(
			'Content-Type' => 'application/json',
			'X-API-Key'    => $api_key,
		),
		'body'    => wp_json_encode( array(
			'url' => home_url(),
		) ),
	) );

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( $response->get_error_message() );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	$code = wp_remote_retrieve_response_code( $response );

	if ( $code !== 200 || empty( $body['auditId'] ) ) {
		wp_send_json_error( $body['error'] ?? 'API error (HTTP ' . $code . ')' );
	}

	wp_send_json_success( $body );
}

/**
 * AJAX: poll audit status
 */
add_action( 'wp_ajax_webmatik_poll_audit', 'webmatik_ajax_poll_audit' );

function webmatik_ajax_poll_audit() {
	check_ajax_referer( 'webmatik_poll_audit', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	$api_key  = get_option( 'webmatik_api_key', '' );
	$audit_id = sanitize_text_field( $_POST['audit_id'] ?? '' );

	if ( empty( $api_key ) || empty( $audit_id ) ) {
		wp_send_json_error( 'Missing API key or audit ID' );
	}

	$response = wp_remote_get( WEBMATIK_API_URL . '/audit/' . $audit_id, array(
		'timeout' => 15,
		'headers' => array(
			'X-API-Key' => $api_key,
		),
	) );

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( $response->get_error_message() );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	// Save last audit result
	if ( ! empty( $body['status'] ) && $body['status'] === 'completed' ) {
		update_option( 'webmatik_last_audit', array(
			'score'      => $body['score'] ?? 0,
			'grade'      => $body['grade'] ?? '',
			'report_url' => $body['reportUrl'] ?? '',
			'date'       => current_time( 'Y-m-d H:i' ),
		) );
	}

	wp_send_json_success( $body );
}

/**
 * Dashboard widget
 */
add_action( 'wp_dashboard_setup', 'webmatik_dashboard_widget' );

function webmatik_dashboard_widget() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_add_dashboard_widget(
		'webmatik_widget',
		'Webmatik — Growth Score',
		'webmatik_widget_render'
	);
}

function webmatik_widget_render() {
	$api_key    = get_option( 'webmatik_api_key', '' );
	$last_audit = get_option( 'webmatik_last_audit', null );

	if ( empty( $api_key ) ) {
		echo '<p>Connect your Webmatik account: <a href="' . esc_url( admin_url( 'admin.php?page=webmatik-settings' ) ) . '">Settings</a></p>';
		return;
	}

	if ( $last_audit ) {
		echo '<div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">';
		echo '<div style="font-size:28px;font-weight:700;color:#2271b1;">' . esc_html( $last_audit['score'] ) . '</div>';
		echo '<div><strong>Growth Score</strong><br><span style="color:#787c82;">Grade: ' . esc_html( $last_audit['grade'] ) . '</span></div>';
		echo '</div>';
		if ( ! empty( $last_audit['report_url'] ) ) {
			echo '<a href="' . esc_url( $last_audit['report_url'] ) . '" target="_blank">View Full Report &rarr;</a>';
		}
		echo '<p style="color:#787c82;font-size:12px;margin-top:5px;">Last audit: ' . esc_html( $last_audit['date'] ) . '</p>';
	} else {
		echo '<p>No audit yet. <a href="' . esc_url( admin_url( 'admin.php?page=webmatik' ) ) . '">Run your first audit</a></p>';
	}
}

/**
 * Enqueue wp.ajax for our admin pages
 */
add_action( 'admin_enqueue_scripts', 'webmatik_enqueue_scripts' );

function webmatik_enqueue_scripts( $hook ) {
	if ( strpos( $hook, 'webmatik' ) === false ) {
		return;
	}
	wp_enqueue_script( 'wp-util' );
}
