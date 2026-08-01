<?php
/**
 * Fired when the plugin is deleted.
 *
 * Removes all plugin options, post meta, scheduled events, and custom tables.
 *
 * @package FastCloudWP
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Runs all uninstall cleanup for the FastCloudWP plugin.
 */
function fastcloudwp_uninstall() {
	global $wpdb;

	$uuid  = get_option( 'fastcloudwp_website_uuid' );
	$token = get_option( 'fastcloudwp_token' );

	if ( $uuid && $token ) {
		require_once __DIR__ . '/constants.php';
		wp_remote_post(
			FASTCLOUDWP_BASE_URL . 'websites/disconnect',
			array(
				'headers'   => array(
					'Content-Type'     => 'application/json',
					'Accept'           => 'application/json',
					'Authorization'    => 'Bearer ' . $token,
					'X-FastCloud-Site' => get_site_url(),
				),
				'timeout'   => 15,
				'sslverify' => ! ( defined( 'FASTCLOUDWP_DEBUG' ) && FASTCLOUDWP_DEBUG ),
			)
		);
	}

	$fastcloudwp_options = array(
		'fastcloudwp_settings',
		'fastcloudwp_website_uuid',
		'fastcloudwp_previous_website_uuid',
		'fastcloudwp_website_name',
		'fastcloudwp_bucket_name',
		'fastcloudwp_token',
		'fastcloudwp_callback_secret',
		'fastcloudwp_sitekey',
		'fastcloudwp_short_id',
		'fastcloudwp_storage',
		'fastcloudwp_custom_domain',
		'fastcloudwp_cdn_ready',
		'fastcloudwp_plugin_version',
		'fastcloudwp_logs_db_version',
	);

	foreach ( $fastcloudwp_options as $fastcloudwp_option ) {
		delete_option( $fastcloudwp_option );
	}

	$fastcloudwp_meta_keys = array(
		'_fastcloudwp_status',
		'_fastcloudwp_timestamp',
		'_fastcloudwp_deleted',
		'_fastcloudwp_original_deleted',
		'_fastcloudwp_dirty',
	);

	foreach ( $fastcloudwp_meta_keys as $fastcloudwp_meta_key ) {
		delete_post_meta_by_key( $fastcloudwp_meta_key );
	}

	wp_clear_scheduled_hook( 'fastcloudwp_health_check' );
	wp_clear_scheduled_hook( 'fastcloudwp_logs_cleanup' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'fastcloudwp_logs' );
}

fastcloudwp_uninstall();
