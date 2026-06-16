<?php
/**
 * Constants used by the plugin.
 *
 * @package FastCloudWP
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'FASTCLOUDWP_DEBUG' ) ) {
	define( 'FASTCLOUDWP_DEBUG', false );
}

if ( ! defined( 'FASTCLOUDWP_PLUGIN_DIR' ) ) {
	define( 'FASTCLOUDWP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'FASTCLOUDWP_PLUGIN_URL' ) ) {
	define( 'FASTCLOUDWP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'FASTCLOUDWP_ORIGIN_URL' ) ) {
	define( 'FASTCLOUDWP_ORIGIN_URL', 'cdn.fastcloudwp.com' );
}

if ( ! defined( 'FASTCLOUDWP_BASE_URL' ) ) {
	define( 'FASTCLOUDWP_BASE_URL', 'https://app.fastcloudwp.com/api/v1/' );
}

if ( ! defined( 'FASTCLOUDWP_BATCH_SIZE' ) ) {
	define( 'FASTCLOUDWP_BATCH_SIZE', 10 );
}

if ( ! defined( 'FASTCLOUDWP_PLUGIN_VERSION' ) ) {
	define( 'FASTCLOUDWP_PLUGIN_VERSION', '1.0.4' );
}
