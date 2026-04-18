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
		add_action( 'fastcloudwp_disconnect', array( $this, 'remove_site_options' ) );
		add_action( 'fastcloudwp_attachment_quota_exceeded', array( $this, 'log_quota_exceeded' ) );
		add_filter( 'wp_get_attachment_url', array( $this, 'rewrite_attachment_url' ) );
		add_filter( 'wp_calculate_image_srcset', array( $this, 'rewrite_srcset_url' ) );
		add_action( 'rest_api_init', array( $this->rest, 'register_routes' ) );
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
	 * Remove the plugin options when it's disconnected from FastCloud.
	 */
	public function remove_site_options(): void {
		$current = get_option( 'fastcloudwp_website_uuid' );
		if ( $current ) {
			update_option( 'fastcloudwp_previous_website_uuid', $current, false );
		}

		delete_option( 'fastcloudwp_website_name' );
		delete_option( 'fastcloudwp_bucket_name' );
		delete_option( 'fastcloudwp_website_uuid' );
		delete_option( 'fastcloudwp_token' );
		delete_option( 'fastcloudwp_callback_secret' );
		delete_option( 'fastcloudwp_sitekey' );
		delete_option( 'fastcloudwp_settings' );
		delete_option( 'fastcloudwp_short_id' );
		delete_option( 'fastcloudwp_storage' );
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

		$bucket  = get_option( 'fastcloudwp_bucket_name' );
		$uploads = wp_get_upload_dir();
		$origin  = FASTCLOUDWP_ORIGIN_URL . $bucket;

		return str_replace( $uploads['baseurl'], $origin, $url );
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

		$bucket  = get_option( 'fastcloudwp_bucket_name' );
		$uploads = wp_get_upload_dir();
		$origin  = FASTCLOUDWP_ORIGIN_URL . $bucket;

		foreach ( $sources as &$source ) {
			$source['url'] = str_replace( $uploads['baseurl'], $origin, $source['url'] );
		}

		return $sources;
	}
}
