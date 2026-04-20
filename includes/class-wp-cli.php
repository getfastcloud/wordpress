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

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Manages FastCloudWP media offloading via WP-CLI.
	 */
	class Wp_Cli {

		/**
		 * Offload all pending media to FastCloud.
		 */
		public function offload(): void {
			if ( ! fastcloudwp_settings()->enabled() ) {
				\WP_CLI::error( 'FastCloud WP is not enabled.' );
			}

			$offloader = fastcloudwp_offloader();
			$states    = fastcloudwp_attachment_states();
			$total     = max( 0, $states->not_offloaded() - $states->quota_exceeded() );

			if ( 0 === $total ) {
				\WP_CLI::success( 'No media to offload.' );
				return;
			}

			$progress = \WP_CLI\Utils\make_progress_bar( 'Offloading media', $total );
			$queued   = 0;

			while ( true ) {
				$ids = $states->not_offloaded_ids( FASTCLOUDWP_BATCH_SIZE );

				if ( empty( $ids ) ) {
					break;
				}

				$result         = $offloader->offload_attachments( $ids );
				$batch_queued   = (int) ( $result['count'] ?? 0 );
				$quota_exceeded = $states->quota_exceeded();
				$success        = $result['success'] ?? false;
				$processable    = max( 0, $states->not_offloaded() - $quota_exceeded );

				$queued += $batch_queued;

				for ( $i = 0; $i < $batch_queued; $i++ ) {
					$progress->tick();
				}

				if ( ! $success || 0 === $processable ) {
					if ( $quota_exceeded > 0 ) {
						\WP_CLI::warning( "Quota exceeded. {$quota_exceeded} attachment(s) skipped." );
					}
					break;
				}
			}

			$progress->finish();
			\WP_CLI::success( "Queued {$queued} attachment(s) for offload." );
		}

		/**
		 * Delete local files for offloaded media to free up disk space.
		 *
		 * @subcommand free-space
		 */
		public function free_space(): void {
			if ( ! fastcloudwp_settings()->enabled() ) {
				\WP_CLI::error( 'FastCloud WP is not enabled.' );
			}

			if ( ! fastcloudwp_settings()->delete_media() ) {
				\WP_CLI::error( 'Deleting local media is not enabled in settings.' );
			}

			$states = fastcloudwp_attachment_states();
			$total  = $states->pending_delete();

			if ( 0 === $total ) {
				\WP_CLI::success( 'No media files to delete.' );
				return;
			}

			$progress = \WP_CLI\Utils\make_progress_bar( 'Freeing space', $total );
			$deleted  = 0;
			$failed   = 0;

			while ( true ) {
				$ids = $states->pending_delete_ids( FASTCLOUDWP_BATCH_SIZE );

				if ( empty( $ids ) ) {
					break;
				}

				foreach ( $ids as $attachment_id ) {
					if ( fastcloudwp_delete_files_for_attachment( $attachment_id ) ) {
						++$deleted;
					} else {
						++$failed;
					}
					$progress->tick();
				}

				if ( $states->pending_delete() <= 0 ) {
					break;
				}
			}

			$progress->finish();

			if ( $failed > 0 ) {
				\WP_CLI::warning( "Deleted {$deleted} file(s). Failed to delete {$failed} file(s)." );
			} else {
				\WP_CLI::success( "Deleted {$deleted} file(s)." );
			}
		}
	}

	\WP_CLI::add_command( 'fastcloud', Wp_Cli::class );

}
