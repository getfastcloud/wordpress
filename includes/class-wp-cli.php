<?php
/**
 * WP CLI Commands
 *
 * Register commands to use FastCloud with wp-cli.
 *
 * @package FastCloudWP
 */

declare( strict_types=1 );

namespace FastCloud\WordPress;

/**
 * WP-CLI command registration.
 *
 * Provides the `wp fastcloud` CLI command for managing offloaded media.
 *
 * @package FastCloudWP
 */

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Manages FastCloudWP media offloading via WP-CLI.
	 */
	class Wp_Cli {

		/**
		 * Delete offloaded media image files from wp-content/uploads.
		 */
		public function delete(): void {
			if ( ! fastcloudwp_settings()->enabled() ) {
				\WP_CLI::warning( 'FastCloud WP is not enabled.' );

				return;
			}

			if ( ! fastcloudwp_settings()->delete_media() ) {
				\WP_CLI::warning( 'Deleting local media is not enabled.' );

				return;
			}

			$pending = fastcloudwp_get_attachments_pending_deletion();
			$total   = count( $pending );

			if ( 0 === $total ) {
				\WP_CLI::success( 'No media to delete.' );

				return;
			}

			$progress = \WP_CLI\Utils\make_progress_bar( 'Deleting media', $total );

			foreach ( $pending as $attachment_id ) {
				fastcloudwp_delete_files_for_attachment( $attachment_id );
				$progress->tick();
			}

			$progress->finish();

			\WP_CLI::success( "Deleted {$total} attachments." );
		}
	}

	\WP_CLI::add_command( 'fastcloud', Wp_Cli::class );

}
