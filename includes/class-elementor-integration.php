<?php
/**
 * Elementor integration.
 *
 * @package FastCloudWP
 */

declare( strict_types=1 );

namespace FastCloud\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flushes Elementor's CSS cache so cached stylesheets pick up CDN URLs.
 *
 * Elementor resolves background-image URLs through wp_get_attachment_image_url()
 * at CSS-build time, which Core already rewrites to the CDN. Its generated CSS
 * files are cached, though, so this only forces a rebuild after media is
 * offloaded or the connection/settings change.
 */
class Elementor_Integration {

	/**
	 * Whether a CSS cache flush has already been scheduled this request.
	 *
	 * @var bool
	 */
	protected bool $flush_scheduled = false;

	/**
	 * Wire hooks when Elementor is active. Invoked by Core on `init`.
	 */
	public function register_hooks(): void {
		if ( ! did_action( 'elementor/loaded' ) && ! defined( 'ELEMENTOR_VERSION' ) ) {
			return;
		}

		add_action( 'fastcloudwp_attachment_offloaded', array( $this, 'schedule_flush' ) );
		add_action( 'fastcloudwp_connected', array( $this, 'schedule_flush' ) );
		add_action( 'fastcloudwp_after_settings_update', array( $this, 'schedule_flush' ) );
		add_action( 'fastcloudwp_disconnect', array( $this, 'schedule_flush' ) );
	}

	/**
	 * Schedule a single Elementor CSS cache flush at request shutdown.
	 */
	public function schedule_flush(): void {
		if ( $this->flush_scheduled ) {
			return;
		}

		$this->flush_scheduled = true;
		add_action( 'shutdown', array( $this, 'flush_elementor_css' ) );
	}

	/**
	 * Clear Elementor's generated CSS cache so stylesheets are rebuilt.
	 */
	public function flush_elementor_css(): void {
		if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}
}
