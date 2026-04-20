<?php
/**
 * Plugin Name: FastCloud – Offload & Serve WordPress Media
 * Plugin URI: https://wordpress.org/plugins/fastcloudwp/
 * Description: Automatically offload your WordPress media library to FastCloud. Reduces server load, frees up disk space, and keeps your site fast.
 * Version: 1.0.0
 * Requires at least: 6.1
 * Requires PHP: 8.1
 * Author: FastCloud WP
 * Author URI: https://fastcloudwp.com/
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fastcloudwp
 *
 * @package FastCloudWP
 */

declare( strict_types=1 );

use FastCloud\WordPress\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/includes/class-wp-cli.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/assets.php';
require_once __DIR__ . '/includes/functions.php';

if ( ! function_exists( 'fastcloudwp' ) ) {

	/**
	 * Singleton of the plugin.
	 */
	function fastcloudwp(): Core {
		static $core;

		if ( ! isset( $core ) ) {
			$core = new Core();
		}

		return $core;
	}

	fastcloudwp()->bootstrap();

}

add_action(
	'init',
	function () {
		if ( is_admin() ) {
			require_once __DIR__ . '/admin.php';
		} else {
			require_once __DIR__ . '/frontend.php';
		}
	}
);
