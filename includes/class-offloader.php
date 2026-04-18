<?php
/**
 * Media offloader.
 *
 * Handles building file payloads and sending them to the FastCloud API.
 *
 * @package FastCloudWP
 */

declare( strict_types=1 );

namespace FastCloud\WordPress;

/**
 * Sends attachment files to the FastCloud API for offloading.
 */
class Offloader {

	/**
	 * The FastCloud website UUID.
	 *
	 * @var string
	 */
	protected string $site_uuid;

	/**
	 * Constructs the offloader.
	 *
	 * @param string $site_uuid The FastCloud website UUID.
	 */
	public function __construct( string $site_uuid ) {
		$this->site_uuid = $site_uuid;
	}

	/**
	 * Offloads a list of attachments to the FastCloud API.
	 *
	 * Builds a file payload for each attachment ID, sends them in a single API
	 * request, and updates post meta for successfully queued attachments.
	 *
	 * @param int[] $attachment_ids Array of attachment post IDs to offload.
	 */
	public function offload_attachments( array $attachment_ids ): array {
		$storage          = fastcloudwp_storage();
		$has_quota_cache  = null !== $storage->last_sync();
		$running_free     = $has_quota_cache ? $storage->free() : null;
		$already_exceeded = $has_quota_cache && $storage->is_exceeded();

		$files            = array();
		$locally_exceeded = array();

		foreach ( $attachment_ids as $id ) {
			$payload = $this->build_file_payload( $id );

			if ( empty( $payload ) || empty( $payload['files'] ) ) {
				continue;
			}

			$size = array_sum( array_column( $payload['files'], 'size' ) );

			if ( $already_exceeded || ( null !== $running_free && $size > $running_free ) ) {
				$this->mark_quota_exceeded( $id, $running_free ?? 0 );
				$locally_exceeded[] = $id;
				continue;
			}

			if ( null !== $running_free ) {
				$running_free -= $size;
			}

			$files[] = $payload;
		}

		if ( empty( $files ) ) {
			return array(
				'success'      => false,
				'count'        => 0,
				'api_response' => array(
					'queued'         => array(),
					'quota_exceeded' => $locally_exceeded,
					'space_left'     => $running_free ?? 0,
				),
			);
		}

		$response   = $this->send_payload( $files );
		$space_left = $response['api_response']['space_left'] ?? 0;

		if ( $response['success'] && ! empty( $response['api_response'] ) ) {
			foreach ( $response['api_response']['queued'] as $id ) {
				update_post_meta( $id, '_fastcloudwp_status', 'queued' );
				update_post_meta( $id, '_fastcloudwp_timestamp', time() );
				delete_post_meta( $id, '_fastcloudwp_deleted' );
				delete_post_meta( $id, '_fastcloudwp_dirty' );
			}

			foreach ( $response['api_response']['offloaded'] ?? array() as $id ) {
				update_post_meta( $id, '_fastcloudwp_status', 'offloaded' );
				update_post_meta( $id, '_fastcloudwp_timestamp', time() );
				delete_post_meta( $id, '_fastcloudwp_dirty' );

				if ( fastcloudwp_settings()->delete_media() ) {
					fastcloudwp_delete_files_for_attachment( $id );
				}
			}

			foreach ( $response['api_response']['quota_exceeded'] as $id ) {
				$this->mark_quota_exceeded( $id, $space_left );
			}
		}

		return $response;
	}

	/**
	 * Builds the file payload array for a single attachment.
	 *
	 * Includes the full-size file, the original unscaled image (if present),
	 * and all registered image size variants that exist on disk.
	 *
	 * @param int $attachment_id The attachment post ID.
	 */
	public function build_file_payload( int $attachment_id ): array {
		$upload   = wp_get_upload_dir();
		$path     = get_attached_file( $attachment_id );
		$relative = str_replace( trailingslashit( $upload['basedir'] ), '', $path );
		$url      = trailingslashit( $upload['baseurl'] ) . $relative;

		$path = get_attached_file( $attachment_id );

		if ( ! $url || ! $path || ! file_exists( $path ) ) {
			return array();
		}

		$mime      = get_post_mime_type( $attachment_id );
		$base_url  = trailingslashit( dirname( $url ) );
		$base_path = trailingslashit( dirname( $path ) );

		$files = array();

		$files[] = array(
			'original_url'      => $url,
			'original_filename' => basename( $url ),
			'type'              => 'full',
			'mime'              => $mime,
			'size'              => filesize( $path ),
			'hash'              => sha1_file( $path ),
		);

		$meta = wp_get_attachment_metadata( $attachment_id );

		if ( ! empty( $meta['original_image'] ) ) {
			$orig      = $meta['original_image'];
			$orig_url  = $base_url . $orig;
			$orig_path = $base_path . $orig;

			if ( file_exists( $orig_path ) ) {
				$files[] = array(
					'original_url'      => $orig_url,
					'original_filename' => $orig,
					'type'              => 'original',
					'mime'              => $mime,
					'size'              => filesize( $orig_path ),
					'hash'              => sha1_file( $orig_path ),
				);
			}
		}

		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $variant ) {
				if ( empty( $variant['file'] ) ) {
					continue;
				}

				$file         = $variant['file'];
				$variant_url  = $base_url . $file;
				$variant_path = $base_path . $file;

				if ( file_exists( $variant_path ) ) {
					$files[] = array(
						'original_url'      => $variant_url,
						'original_filename' => $file,
						'type'              => 'variant',
						'mime'              => $mime,
						'size'              => filesize( $variant_path ),
					);
				}
			}
		}

		return array(
			'attachment_id' => $attachment_id,
			'files'         => $files,
		);
	}

	/**
	 * Sends the file payload to the FastCloud API.
	 *
	 * @param array<int, array<string, mixed>> $files Array of file payload entries.
	 */
	protected function send_payload( array $files ): array {
		$response = fastcloudwp_api_client()->post(
			'websites/' . $this->site_uuid . '/files',
			array( 'attachments' => $files )
		);

		if ( $response->failed() ) {
			return array(
				'success' => false,
				'error'   => $response->error_message(),
			);
		}

		return array(
			'success'      => true,
			'api_response' => $response->json(),
			'count'        => count( $files ),
		);
	}

	/**
	 * Marks an attachment as quota_exceeded and fires the public hook.
	 *
	 * @param int $attachment_id The attachment post ID.
	 * @param int $space_left The space left on FastCloud.
	 */
	protected function mark_quota_exceeded( int $attachment_id, int $space_left ): void {
		update_post_meta( $attachment_id, '_fastcloudwp_status', 'quota_exceeded' );
		update_post_meta( $attachment_id, '_fastcloudwp_timestamp', time() );
		delete_post_meta( $attachment_id, '_fastcloudwp_deleted' );
		delete_post_meta( $attachment_id, '_fastcloudwp_dirty' );

		do_action( 'fastcloudwp_attachment_quota_exceeded', $attachment_id, $space_left );
	}
}
