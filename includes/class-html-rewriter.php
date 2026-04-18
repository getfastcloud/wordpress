<?php
/**
 * Frontend HTML rewriter.
 *
 * Buffers frontend output and rewrites local upload URLs to the CDN origin URL.
 *
 * @package FastCloudWP
 */

declare(strict_types=1);

namespace FastCloud\WordPress;

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
	 * @param Settings $settings    Plugin settings instance.
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
		if ( ! $settings->enabled() ) {
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
				ob_start( array( $rewriter, 'rewrite' ) );
			}
		);
	}

	/**
	 * Rewrites local upload URLs in the buffered HTML to the CDN origin.
	 *
	 * @param string $html The raw HTML output to process.
	 */
	public function rewrite( string $html ): string {
		$uploads  = wp_get_upload_dir();
		$original = $uploads['baseurl'];
		$origin   = FASTCLOUDWP_ORIGIN_URL . $this->bucket_name;

		$html = $this->rewrite_src( $html, $original, $origin );
		$html = $this->rewrite_srcset( $html, $original, $origin );

		return $html;
	}

	/**
	 * Replaces the base URL in plain src attribute values.
	 *
	 * @param string $html     The HTML to process.
	 * @param string $original The local upload base URL to replace.
	 * @param string $origin   The CDN origin URL to substitute.
	 */
	protected function rewrite_src( string $html, string $original, string $origin ): string {
		$pattern = '#' . preg_quote( $original, '#' ) . '([^"\'\s]+)#';

		return preg_replace( $pattern, $origin . '$1', $html );
	}

	/**
	 * Replaces the base URL in srcset attribute values.
	 *
	 * @param string $html     The HTML to process.
	 * @param string $original The local upload base URL to replace.
	 * @param string $origin   The CDN origin URL to substitute.
	 */
	protected function rewrite_srcset( string $html, string $original, string $origin ): string {
		$pattern = '#' . preg_quote( $original, '#' ) . '([^,\s]+)#';

		return preg_replace( $pattern, $origin . '$1', $html );
	}
}
