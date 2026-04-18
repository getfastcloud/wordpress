<?php
/**
 * Global functions helpers.
 *
 * @package FastCloudWP
 */

declare( strict_types=1 );

// phpcs:ignore Universal.Namespaces
namespace {

	use FastCloud\WordPress\Attachment_State_Repository;
	use FastCloud\WordPress\Client;
	use FastCloud\WordPress\Logger;
	use FastCloud\WordPress\Offloader;
	use FastCloud\WordPress\Settings;
	use FastCloud\WordPress\Storage;

	/**
	 * Determines whether a new attachment is currently being uploaded.
	 */
	function fastcloudwp_is_uploading_new_attachment(): bool {
		global $pagenow, $wp;

		if ( 'async-upload.php' === $pagenow || 'media-new.php' === $pagenow ) {
			return true;
		}
		if ( 'index.php' === $pagenow && isset( $wp->query_vars['rest_route'] ) && '/wp/v2/media' === $wp->query_vars['rest_route'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Deletes all local files associated with a given attachment.
	 *
	 * Removes the full-size image, all registered image size variants, the original
	 * unscaled image, and any editor backup sizes from disk. Records a timestamp in
	 * post meta once deletion is complete.
	 *
	 * @param int $attachment_id The ID of the attachment whose files should be deleted.
	 */
	function fastcloudwp_delete_files_for_attachment( int $attachment_id ): bool {
		$uploads     = wp_get_upload_dir();
		$base        = trailingslashit( $uploads['basedir'] );
		$remove_orig = fastcloudwp_settings()->remove_original();

		$meta   = wp_get_attachment_metadata( $attachment_id );
		$backup = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );

		$attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$current_dir   = $attached_file ? dirname( $attached_file ) : null;

		$files = array();

		$main_file = get_attached_file( $attachment_id );
		if ( $main_file ) {
			$files[] = $main_file;
		}

		if ( $current_dir && ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) ) {
					$files[] = $base . $current_dir . '/' . $size['file'];
				}
			}
		}

		if ( $remove_orig && $current_dir && ! empty( $meta['original_image'] ) ) {
			$files[] = $base . $current_dir . '/' . $meta['original_image'];
		}

		if ( $remove_orig && $current_dir && is_array( $backup ) ) {
			foreach ( $backup as $entry ) {
				if ( ! empty( $entry['file'] ) ) {
					$files[] = $base . $current_dir . '/' . $entry['file'];
				}
			}
		}

		$files = array_unique( array_filter( $files, 'file_exists' ) );

		$success = true;

		foreach ( $files as $path ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! @unlink( $path ) ) {
				$success = false;
			}
		}

		if ( $success ) {
			if ( $remove_orig && is_array( $backup ) ) {
				delete_post_meta( $attachment_id, '_wp_attachment_backup_sizes' );
			}

			update_post_meta( $attachment_id, '_fastcloudwp_deleted', time() );
		}

		return $success;
	}

	/**
	 * Retrieves attachment IDs that have been offloaded but not yet deleted locally.
	 *
	 * @TODO: Remove this method, maybe rename statistics to something else.
	 */
	function fastcloudwp_get_attachments_pending_deletion(): array {
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					'relation' => 'AND',

					array(
						'key'     => '_fastcloudwp_status',
						'value'   => 'offloaded',
						'compare' => '=',
					),

					array(
						'key'     => '_fastcloudwp_deleted',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		return $query->posts;
	}

	/**
	 * Returns the singleton instance of Fastcloudwp_Settings.
	 *
	 * Lazy-loads settings from the WordPress options table on first call.
	 */
	function fastcloudwp_settings(): Settings {
		static $settings;

		if ( ! isset( $settings ) ) {
			$settings = new Settings( get_option( 'fastcloudwp_settings', array() ) );
		}

		return $settings;
	}

	/**
	 * Global state of the plugin used by the frontend to show real time information.
	 */
	function fastcloudwp_javascript_state(): array {
		$offloaded_count = fastcloudwp_attachment_states()->offloaded();
		$deleted_count   = fastcloudwp_attachment_states()->deleted();
		$total           = fastcloudwp_attachment_states()->attachments_count();

		return array(
			'state'      => array(
				'connected' => (bool) get_option( 'fastcloudwp_website_uuid' ),
				'uuid'      => get_option( 'fastcloudwp_website_uuid' ),
				'name'      => get_option( 'fastcloudwp_website_name' ),
				'short_id'  => get_option( 'fastcloudwp_short_id' ),
				'sitekey'   => get_option( 'fastcloudwp_sitekey', '' ),
				'settings'  => fastcloudwp_settings()->to_array(),
				'domain'    => wp_parse_url( home_url(), PHP_URL_HOST ),
				'storage'   => fastcloudwp_storage()->to_array(),
			),
			'statistics' => array(
				'total'              => $total,
				'queued'             => fastcloudwp_attachment_states()->queued(),
				'offloaded'          => $offloaded_count,
				'deleted'            => $deleted_count,
				'pending_delete'     => fastcloudwp_attachment_states()->pending_delete(),
				'quota_exceeded'     => fastcloudwp_attachment_states()->quota_exceeded(),
				'missing'            => fastcloudwp_attachment_states()->not_offloaded(),
				'dirty'              => fastcloudwp_attachment_states()->dirty(),
				'offloaded_progress' => $total > 0 ? round( ( $offloaded_count / $total ) * 100 * 2 ) / 2 : 0,
				'deleted_progress'   => $offloaded_count > 0 ? round( ( $deleted_count / $offloaded_count ) * 100 * 2 ) / 2 : 0,
			),
			'logs'       => fastcloudwp_logger()->get_logs(
				array(
					'limit' => 10,
				)
			),
		);
	}

	/**
	 * Statistics singleton to get stats about the media state.
	 */
	function fastcloudwp_attachment_states(): Attachment_State_Repository {
		static $attachment_states;

		if ( ! isset( $attachment_states ) ) {
			$attachment_states = new Attachment_State_Repository();
		}

		return $attachment_states;
	}

	/**
	 * Returns the singleton instance of Fastcloudwp_Offloader.
	 *
	 * Lazy-loads the offloader with the stored site UUID and token on first call.
	 */
	function fastcloudwp_offloader(): Offloader {
		static $offloader;

		if ( ! isset( $offloader ) ) {
			$offloader = new Offloader(
				get_option( 'fastcloudwp_website_uuid' )
			);
		}

		return $offloader;
	}

	/**
	 * Logger singleton to write logs into the database.
	 */
	function fastcloudwp_logger(): Logger {
		static $logger;

		if ( ! isset( $logger ) ) {
			$logger = new Logger();
		}

		return $logger;
	}

	/**
	 * Singleton for the API client to talk with FastCloud.
	 */
	function fastcloudwp_api_client(): Client {
		static $client;

		if ( ! isset( $client ) ) {
			$client = new Client(
				get_option( 'fastcloudwp_website_uuid', null ),
				get_option( 'fastcloudwp_token', null ),
			);
		}

		return $client;
	}

	/**
	 * Singleton for Storage helper to sync usage.
	 */
	function fastcloudwp_storage(): Storage {
		static $storage;

		if ( ! isset( $storage ) ) {
			$storage = new Storage();
		}

		return $storage;
	}

	/**
	 * Wipes all FastCloud-managed attachment meta.
	 *
	 * Called when the connected website UUID changes, so statistics and
	 * offload state reflect only the currently-connected bucket.
	 */
	function fastcloudwp_reset_attachment_states(): void {
		delete_post_meta_by_key( '_fastcloudwp_status' );
		delete_post_meta_by_key( '_fastcloudwp_timestamp' );
		delete_post_meta_by_key( '_fastcloudwp_deleted' );
		delete_post_meta_by_key( '_fastcloudwp_dirty' );
	}

}
