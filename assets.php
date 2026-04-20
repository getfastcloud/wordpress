<?php
/**
 * Vite asset integration helpers.
 *
 * Provides functions to load Vite-built assets in both development (HMR) and
 * production (manifest) modes, and injects the initial plugin data for the
 * Vue application.
 *
 * @package FastCloudWP
 */

declare( strict_types=1 );

// phpcs:ignore Universal.Namespaces
namespace {

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Retrieves and caches the Vite build manifest.
	 *
	 * Reads the manifest.json from the assets directory, caching it in WordPress
	 * options keyed by an MD5 checksum of the file to detect changes.
	 */
	function fastcloudwp_vite_manifest(): array {
		static $manifest;

		if ( isset( $manifest ) ) {
			return $manifest;
		}

		$file = FASTCLOUDWP_PLUGIN_DIR . 'assets/.vite/manifest.json';
		if ( ! file_exists( $file ) ) {
			return array();
		}

		$checksum   = md5_file( $file );
		$cache      = get_option( 'fastcloudwp_vite_manifest_cache' );
		$cache_hash = get_option( 'fastcloudwp_vite_manifest_hash' );

		if ( $cache && $cache_hash === $checksum ) {
			$manifest = $cache;

			return $manifest;
		}

		ob_start();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $file );
		$json = ob_get_clean();
		$data = json_decode( $json, true );

		update_option( 'fastcloudwp_vite_manifest_cache', $data, false );
		update_option( 'fastcloudwp_vite_manifest_hash', $checksum, false );

		$manifest = $data;

		return $data;
	}


	/**
	 * Enqueues Vite entry point assets for the given entry file.
	 *
	 * In development mode (when a .hot file is present), loads assets directly
	 * from the Vite dev server with HMR support. In production, uses the Vite
	 * manifest to enqueue versioned script and style assets.
	 *
	 * @param string $entry The Vite entry point path (e.g. 'src/main.ts').
	 * @param array  $deps Optional. List of dependencies.
	 */
	function fastcloudwp_vite_enqueue( $entry, array $deps = array() ): void {
		$hot_file = FASTCLOUDWP_PLUGIN_DIR . '.hot';

		if ( file_exists( $hot_file ) ) {
			ob_start();
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
			readfile( $hot_file );
			$dev = trim( ob_get_clean() );

			wp_enqueue_script_module( 'fastcloudwp-vite-client', $dev . '/@vite/client', array(), null );
			wp_enqueue_script_module( 'fastcloudwp-vite-entry', $dev . '/' . $entry, array(), null );

			return;
		}

		$manifest = fastcloudwp_vite_manifest();
		if ( ! isset( $manifest[ $entry ] ) ) {
			return;
		}

		$plugin_data = get_plugin_data( FASTCLOUDWP_PLUGIN_DIR . 'fastcloudwp.php' );

		$asset = $manifest[ $entry ];
		$base  = FASTCLOUDWP_PLUGIN_URL . 'assets/';

		if ( ! empty( $asset['css'] ) ) {
			foreach ( $asset['css'] as $css ) {
				wp_enqueue_style( 'fastcloudwp-vite-' . md5( $css ), $base . $css, array(), $plugin_data['Version'] );
			}
		}

		$import_deps = array();
		if ( ! empty( $asset['imports'] ) ) {
			foreach ( $asset['imports'] as $import_key ) {
				if ( ! isset( $manifest[ $import_key ] ) ) {
					continue;
				}
				$chunk        = $manifest[ $import_key ];
				$chunk_handle = 'fastcloudwp-vite-' . md5( $chunk['file'] );

				wp_enqueue_script_module( $chunk_handle, $base . $chunk['file'], array(), $plugin_data['Version'] );

				$import_deps[] = array(
					'id'     => $chunk_handle,
					'import' => 'static',
				);
			}
		}

		$handle = 'fastcloudwp-vite-' . md5( $asset['file'] );
		wp_enqueue_script_module( $handle, $base . $asset['file'], array_merge( $deps, $import_deps ), $plugin_data['Version'] );
	}

	/**
	 * Merge all per-locale JSON translation files for the current locale into one data object.
	 */
	function fastcloudwp_load_js_translations(): array {
		$locale   = determine_locale();
		$lang_dir = FASTCLOUDWP_PLUGIN_DIR . 'languages/';
		$merged   = array();

		$json_files = glob( $lang_dir . 'fastcloudwp-' . $locale . '-*.json' );
		foreach ( $json_files ? $json_files : array() as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$data = json_decode( file_get_contents( $file ), true );
			if ( ! isset( $data['locale_data']['messages'] ) ) {
				continue;
			}
			if ( empty( $merged ) ) {
				$merged = $data['locale_data']['messages'];
			} else {
				foreach ( $data['locale_data']['messages'] as $key => $value ) {
					if ( '' !== $key ) {
						$merged[ $key ] = $value;
					}
				}
			}
		}

		return $merged;
	}

	/**
	 * Load the plugin state in the window.FastCloudWP global object.
	 */
	function fastcloudwp_enqueue_app_state(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || 'fastcloudwp' !== $_GET['page'] ) {
			return;
		}

		$plugin_data = get_plugin_data( FASTCLOUDWP_PLUGIN_DIR . 'fastcloudwp.php' );

		wp_register_script( 'fastcloudwp-state', '', array(), $plugin_data['Version'], false );
		wp_enqueue_script( 'fastcloudwp-state' );

		$translations = fastcloudwp_load_js_translations();
		if ( ! empty( $translations ) ) {
			wp_add_inline_script(
				'fastcloudwp-state',
				'window.__fastcloudwpI18n = ' . wp_json_encode( $translations ) . ';',
				'before'
			);
		}

		$state          = fastcloudwp_javascript_state();
		$state['nonce'] = wp_create_nonce( 'wp_rest' );

		wp_add_inline_script(
			'fastcloudwp-state',
			'window.FastCloudWP = ' . wp_json_encode( $state ) . ';'
		);
	}

}
