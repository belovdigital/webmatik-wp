<?php
/**
 * Webmatik uninstall — clean up options when plugin is deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'webmatik_api_key' );
delete_option( 'webmatik_last_audit' );
