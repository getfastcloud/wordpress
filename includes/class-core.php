<?php
/**
 * Plugin core bootstrap and hook orchestrator.
 *
 * @package FastCloudWP
 */

declare( strict_types=1 );

namespace FastCloud\WordPress;

/**
 * Bootstraps the plugin and wires up all WordPress hooks.
 *
 * Registers all WordPress actions and filters that drive the plugin:
 * attachment offloading, remote file deletion, CDN URL rewriting,
 * site connection lifecycle, and quota event logging.
 */
class Core {

	/**
	 * User to register the rest routes.
	 *
	 * @var Rest
	 */
	protected Rest $rest;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->rest = new Rest();

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'fastcloudwp_connected', array( $this, 'save_site_options' ), 10, 2 );
		add_action( 'fastcloudwp_disconnect', 'fastcloudwp_remove_site_options' );
		add_action( 'fastcloudwp_attachment_quota_exceeded', array( $this, 'log_quota_exceeded' ) );
		add_filter( 'wp_get_attachment_url', array( $this, 'rewrite_attachment_url' ) );
		add_filter( 'wp_get_attachment_image_src', array( $this, 'rewrite_attachment_image_src' ) );
		add_filter( 'wp_calculate_image_srcset', array( $this, 'rewrite_srcset_url' ) );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'rewrite_attachment_for_js' ) );
		add_filter( 'render_block', array( $this, 'rewrite_block_content' ) );
		add_action( 'rest_api_init', array( $this->rest, 'register_routes' ) );
		add_action( 'fastcloudwp_health_check', array( $this, 'health_check' ) );

		register_uninstall_hook( __FILE__, array( self::class, 'uninstall' ) );
	}

	/**
	 * Anything related to creating tables or initial setup of the plugin.
	 */
	public function bootstrap(): void {
		fastcloudwp_logger()->init();
	}

	/**
	 * Rewrite attachments URL and delete media of FastCloud if deleted on WordPress.
	 */
	public function init(): void {
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'offload_attachment' ), 999, 2 );
		add_filter( 'wp_save_image_editor_file', array( $this, 'offload_image_edit' ), 10, 5 );
		add_action( 'delete_attachment', array( $this, 'delete_attachment' ), 20 );
		load_plugin_textdomain( 'fastcloudwp', false, plugin_basename( FASTCLOUDWP_PLUGIN_DIR ) . 'languages' );

		if ( get_option( 'fastcloudwp_website_uuid' ) && ! wp_next_scheduled( 'fastcloudwp_health_check' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'fastcloudwp_health_check' );
		}
	}

	/**
	 * Offload new uploaded attachment.
	 *
	 * @param array $meta Image information.
	 * @param int   $attachment_id Attachment post ID.
	 */
	public function offload_attachment( array $meta, int $attachment_id ): array {
		if ( ! fastcloudwp_settings()->enabled() ) {
			return $meta;
		}

		$is_new_upload = fastcloudwp_is_uploading_new_attachment();
		$was_tracked   = (bool) get_post_meta( $attachment_id, '_fastcloudwp_status', true );

		if ( ! $is_new_upload && ! $was_tracked ) {
			return $meta;
		}

		if ( fastcloudwp_settings()->autosync() ) {
			fastcloudwp_offloader()->offload_attachments( array( $attachment_id ) );

			return $meta;
		}

		if ( $was_tracked ) {
			update_post_meta( $attachment_id, '_fastcloudwp_dirty', 1 );
			delete_post_meta( $attachment_id, '_fastcloudwp_deleted' );
		}

		return $meta;
	}

	/**
	 * When a media is edited with the image editor, offload the new version.
	 *
	 * @param bool|null        $override Value to return instead of saving. Default null.
	 * @param string           $filename Name of the file to be saved.
	 * @param \WP_Image_Editor $image The image editor instance.
	 * @param string           $mime_type The mime type of the image.
	 * @param int              $post_id Attachment post ID.
	 */
	public function offload_image_edit( ?bool $override, string $filename, \WP_Image_Editor $image, string $mime_type, int $post_id ): ?bool {
		if ( ! $post_id ) {
			return $override;
		}

		static $queued = array();

		if ( isset( $queued[ $post_id ] ) ) {
			return $override;
		}
		$queued[ $post_id ] = true;

		add_action(
			'shutdown',
			function () use ( $post_id ) {
				if ( fastcloudwp_settings()->autosync() ) {
					fastcloudwp_offloader()->offload_attachments( array( $post_id ) );

					return;
				}

				if ( get_post_meta( $post_id, '_fastcloudwp_status', true ) ) {
					update_post_meta( $post_id, '_fastcloudwp_dirty', 1 );
					delete_post_meta( $post_id, '_fastcloudwp_deleted' );
				}
			}
		);

		return $override;
	}

	/**
	 * Remove the media from FastCloud storage.
	 *
	 * @param int $attachment_id Attachment post ID.
	 */
	public function delete_attachment( int $attachment_id ): void {
		$status = get_post_meta( $attachment_id, '_fastcloudwp_status', true );

		if ( ! in_array( $status, array( 'offloaded', 'queued' ), true ) ) {
			return;
		}

		$uuid = get_option( 'fastcloudwp_website_uuid' );

		fastcloudwp_api_client()->delete( "websites/{$uuid}/files/{$attachment_id}" );
	}

	/**
	 * Once the website is connected to FastCloud, we save the information to let the plugin works.
	 *
	 * @param array  $body API response from FastCloud.
	 * @param string $sitekey Site key.
	 */
	public function save_site_options( array $body, string $sitekey ): void {
		$previous_uuid = get_option( 'fastcloudwp_previous_website_uuid' );

		if ( $previous_uuid && $previous_uuid !== $body['website_uuid'] ) {
			fastcloudwp_reset_attachment_states();
		}

		delete_option( 'fastcloudwp_previous_website_uuid' );
		update_option( 'fastcloudwp_website_name', $body['name'] );
		update_option( 'fastcloudwp_bucket_name', $body['bucket_name'] );
		update_option( 'fastcloudwp_website_uuid', $body['website_uuid'] );
		update_option( 'fastcloudwp_token', $body['token'] );
		update_option( 'fastcloudwp_callback_secret', $body['callback_secret'] );
		update_option( 'fastcloudwp_sitekey', $sitekey );
		update_option( 'fastcloudwp_short_id', $body['short_id'] );
		fastcloudwp_settings()->save();
	}

	/**
	 * When a media connect be offloaded, log a warning with media information.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @param int $space_left Space left in the account on FastCloud.
	 */
	public function log_quota_exceeded( int $attachment_id, int $space_left ): void {
		fastcloudwp_logger()->warning(
			'Attachment {filename} quota exceeded, {space_left} MB remaining',
			'api',
			array(
				'attachment_id' => $attachment_id,
				'filename'      => basename( get_attached_file( $attachment_id ) ),
				'space_left'    => round( ( $space_left ?? 0 ) / 1048576, 2 ),
			)
		);
	}

	/**
	 * Rewrite media URL to use the FastCloud CDN/origin.
	 *
	 * @param string $url The WordPress URL to rewrite.
	 */
	public function rewrite_attachment_url( string $url ): string {
		if ( ! fastcloudwp_settings()->enabled() ) {
			return $url;
		}

		$uploads = wp_get_upload_dir();

		return str_replace( $uploads['baseurl'], $this->cdn_origin(), $url );
	}

	/**
	 * Build the CDN url with the bucket name.
	 */
	public function cdn_origin(): string {
		$bucket = get_option( 'fastcloudwp_bucket_name' );
		$origin = 'https://' . $bucket . '.' . FASTCLOUDWP_ORIGIN_URL;

		/**
		 * Filters the CDN origin URL used to rewrite media URLs.
		 *
		 * @param string $origin The default CDN origin URL (e.g. https://bucket.origin.fastcloudwp.com).
		 */
		return (string) apply_filters( 'fastcloudwp_cdn_origin', $origin );
	}

	/**
	 * Rewrite srcset URL of a media to use the FastCloud CDN/origin.
	 *
	 * @param array $sources Image variations URL.
	 */
	public function rewrite_srcset_url( array $sources ): array {
		if ( ! fastcloudwp_settings()->enabled() ) {
			return $sources;
		}

		$uploads = wp_get_upload_dir();

		foreach ( $sources as &$source ) {
			$source['url'] = str_replace( $uploads['baseurl'], $this->cdn_origin(), $source['url'] );
		}

		return $sources;
	}

	/**
	 * Rewrite the URL in a wp_get_attachment_image_src() result to the CDN origin.
	 *
	 * Covers direct PHP calls that bypass wp_get_attachment_url, such as
	 * intermediate image sizes resolved from attachment metadata.
	 *
	 * @param array|false $image Array of [ url, width, height, is_intermediate ], or false.
	 */
	public function rewrite_attachment_image_src( array|false $image ): array|false {
		if ( ! fastcloudwp_settings()->enabled() || ! is_array( $image ) ) {
			return $image;
		}

		$uploads  = wp_get_upload_dir();
		$image[0] = str_replace( $uploads['baseurl'], $this->cdn_origin(), $image[0] );

		return $image;
	}

	/**
	 * Rewrite URLs in the attachment data passed to the media library modal.
	 *
	 * The wp_prepare_attachment_for_js() builds its own URL set from attachment
	 * metadata, so neither wp_get_attachment_url nor the output buffer covers it.
	 *
	 * @param array $response Attachment data prepared for JavaScript.
	 */
	public function rewrite_attachment_for_js( array $response ): array {
		if ( ! fastcloudwp_settings()->enabled() ) {
			return $response;
		}

		$uploads  = wp_get_upload_dir();
		$original = $uploads['baseurl'];
		$origin   = $this->cdn_origin();

		if ( ! empty( $response['url'] ) ) {
			$response['url'] = str_replace( $original, $origin, $response['url'] );
		}

		if ( ! empty( $response['sizes'] ) ) {
			foreach ( $response['sizes'] as &$size ) {
				if ( ! empty( $size['url'] ) ) {
					$size['url'] = str_replace( $original, $origin, $size['url'] );
				}
			}
		}

		return $response;
	}

	/**
	 * Rewrite local media URLs inside rendered Gutenberg block HTML.
	 *
	 * The render_block fires for every block on both the frontend and inside REST
	 * API responses (e.g. post content.rendered), which the output buffer
	 * does not cover.
	 *
	 * @param string $block_content The rendered HTML for a single block.
	 */
	public function rewrite_block_content( string $block_content ): string {
		if ( ! fastcloudwp_settings()->enabled() ) {
			return $block_content;
		}

		$uploads = wp_get_upload_dir();

		return str_replace( $uploads['baseurl'], $this->cdn_origin(), $block_content );
	}

	/**
	 * Hourly cron callback: ping the FastCloud API to verify the token is still valid.
	 *
	 * A 401/403 response is handled inside Client::request(), which fires
	 * fastcloudwp_disconnect automatically. This method only needs to trigger
	 * an authenticated request; the client takes care of the rest.
	 */
	public function health_check(): void {
		$uuid = get_option( 'fastcloudwp_website_uuid' );

		if ( ! $uuid ) {
			wp_clear_scheduled_hook( 'fastcloudwp_health_check' );
			return;
		}

		fastcloudwp_api_client()->head( "websites/{$uuid}/health" );
	}

	/**
	 * When the plugin is removed, remove all FastCloud data in the database.
	 */
	public static function uninstall(): void {
		fastcloudwp_remove_site_options();
		fastcloudwp_reset_attachment_states();
	}
}
