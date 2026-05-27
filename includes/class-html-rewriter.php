<?php
/**
 * Frontend HTML rewriter.
 *
 * Buffers frontend output and rewrites local upload URLs to the CDN origin URL.
 *
 * @package FastCloudWP
 */

declare( strict_types=1 );

namespace FastCloud\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrites local media URLs in HTML output to point to the FastCloud CDN origin.
 */
class Html_Rewriter {

	/**
	 * The storage bucket name used to construct the CDN origin URL.
	 *
	 * @var string
	 */
	protected string $bucket_name;

	/**
	 * Plugin settings instance.
	 *
	 * @var Settings
	 */
	protected Settings $settings;

	/**
	 * Constructs the HTML rewriter.
	 *
	 * @param string   $bucket_name The storage bucket name.
	 * @param Settings $settings Plugin settings instance.
	 */
	public function __construct( string $bucket_name, Settings $settings ) {
		$this->bucket_name = $bucket_name;
		$this->settings    = $settings;
	}

	/**
	 * Registers the output buffer rewriter on the template_redirect hook.
	 *
	 * Bails early if the plugin is disabled or no bucket is configured.
	 */
	public static function register() {
		$settings = fastcloudwp_settings();
		if ( ! $settings->enabled() || ! $settings->cdn_ready() ) {
			return;
		}

		$bucket = get_option( 'fastcloudwp_bucket_name' );
		if ( ! $bucket ) {
			return;
		}

		add_action(
			'template_redirect',
			function () use ( $bucket, $settings ) {
				$rewriter = new self( $bucket, $settings );
				$level    = ob_get_level();
				ob_start( array( $rewriter, 'rewrite' ) );

				add_action(
					'shutdown',
					function () use ( $level ) {
						while ( ob_get_level() > $level ) {
							ob_end_flush();
						}
					},
					0
				);
			}
		);
	}

	/**
	 * Rewrites local upload URLs in the buffered HTML to the CDN origin.
	 *
	 * @param string $html The raw HTML output to process.
	 */
	public function rewrite( string $html ): string {
		if ( ! fastcloudwp_settings()->cdn_ready() ) {
			return $html;
		}

		$uploads  = wp_get_upload_dir();
		$original = $uploads['baseurl'];
		$origin   = fastcloudwp()->cdn_origin();

		$html = $this->rewrite_src( $html, $original, $origin );
		$html = $this->rewrite_srcset( $html, $original, $origin );

		return $html;
	}

	/**
	 * Replaces the base URL in plain src attribute values.
	 *
	 * Skips URLs that match a blocked path segment or a blocked file extension
	 * so that non-offloaded files (e.g. Elementor CSS/JS) are never pointed at
	 * the CDN where they do not exist.
	 *
	 * @param string $html The HTML to process.
	 * @param string $original The local upload base URL to replace.
	 * @param string $origin The CDN origin URL to substitute.
	 */
	protected function rewrite_src( string $html, string $original, string $origin ): string {
		$pattern = '#' . preg_quote( $original, '#' ) . '([^"\'\s]+)#';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $origin ) {
				if ( $this->is_blocked( $matches[1] ) ) {
					return $matches[0];
				}
				return $origin . $matches[1];
			},
			$html
		);
	}

	/**
	 * Replaces the base URL in srcset attribute values.
	 *
	 * Skips URLs that match a blocked path segment or a blocked file extension.
	 *
	 * @param string $html The HTML to process.
	 * @param string $original The local upload base URL to replace.
	 * @param string $origin The CDN origin URL to substitute.
	 */
	protected function rewrite_srcset( string $html, string $original, string $origin ): string {
		$pattern = '#' . preg_quote( $original, '#' ) . '([^,\s]+)#';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $origin ) {
				if ( $this->is_blocked( $matches[1] ) ) {
					return $matches[0];
				}
				return $origin . $matches[1];
			},
			$html
		);
	}

	/**
	 * Returns true if the URL path should NOT be rewritten.
	 *
	 * @param string $path Path captured after the upload base URL.
	 */
	protected function is_blocked( string $path ): bool {
		return $this->has_blocked_path( $path ) || $this->has_blocked_extension( $path );
	}

	/**
	 * Returns true if the path starts with a blocked root directory.
	 *
	 * Only the first directory segment after the uploads base URL is checked,
	 * so `/2025/06/elementor/bob.png` is never blocked.
	 *
	 * @param string $path Path captured after the upload base URL (starts with /).
	 */
	protected function has_blocked_path( string $path ): bool {
		foreach ( $this->get_blocked_paths() as $blocked ) {
			if ( str_starts_with( $path, $blocked ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns true if the path has a blocked file extension.
	 *
	 * @param string $path Path captured after the upload base URL.
	 */
	protected function has_blocked_extension( string $path ): bool {
		$extension = strtolower( pathinfo( strtok( $path, '?' ), PATHINFO_EXTENSION ) );

		if ( empty( $extension ) ) {
			return false;
		}

		return in_array( $extension, $this->get_blocked_extensions(), true );
	}

	/**
	 * Returns the blocked path segments list.
	 *
	 * Each entry includes surrounding slashes to avoid partial-name false positives.
	 * Filterable via `fastcloudwp_blocked_paths`. Cached per request.
	 *
	 * @return string[]
	 */
	protected function get_blocked_paths(): array {
		static $paths;

		if ( ! isset( $paths ) ) {
			$paths = apply_filters(
				'fastcloudwp_blocked_paths',
				array(
					'/elementor/',
					'/elementor-custom-icons/',
					'/cache/',
					'/wp-rocket/',
					'/litespeed/',
					'/breeze/',
					'/autoptimize/',
					'/wp-fastest-cache/',
					'/w3tc/',
				)
			);
		}

		return $paths;
	}

	/**
	 * Returns the blocked file extensions list.
	 *
	 * Covers generated assets and non-attachment file types written to uploads
	 * by plugins. Filterable via `fastcloudwp_blocked_extensions`. Cached per request.
	 *
	 * @return string[]
	 */
	protected function get_blocked_extensions(): array {
		static $extensions;

		if ( ! isset( $extensions ) ) {
			$extensions = apply_filters(
				'fastcloudwp_blocked_extensions',
				array(
					'css',
					'js',
					'php',
					'html',
					'htm',
					'json',
					'xml',
					'txt',
					'md',
					'map',
				)
			);
		}

		return $extensions;
	}
}
