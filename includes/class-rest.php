<?php
/**
 * REST API endpoint definitions and handlers.
 *
 * Registers all fastcloudwp/v1 routes and their callback functions.
 *
 * @package FastCloudWP
 */

declare( strict_types=1 );

namespace FastCloud\WordPress;

/**
 * Register all REST API routes to connect with UI and FastCloud.
 */
class Rest {

	/**
	 * Register endpoints.
	 */
	public function register_routes(): void {
		$this->register_route( '/ping', 'GET', array( $this, 'ping_webhook' ) );
		$this->register_route( '/connect', 'POST', array( $this, 'connect' ) );
		$this->register_route( '/disconnect', 'POST', array( $this, 'disconnect' ) );
		$this->register_route( '/settings', 'POST', array( $this, 'settings' ) );
		$this->register_route( '/offload-complete', 'POST', array( $this, 'offload_complete_webook' ) );
		$this->register_route( '/state', 'GET', array( $this, 'state' ) );
		$this->register_route( '/offload', 'POST', array( $this, 'queue_batch' ) );
		$this->register_route( '/free-space', 'POST', array( $this, 'free_space' ) );
	}

	/**
	 * Let FastCloud ping the website back on connect to verify ownership.
	 *
	 * @param \WP_REST_Request $request The HTTP request received.
	 */
	public function ping_webhook( \WP_REST_Request $request ): array {
		fastcloudwp_storage()->sync( $request );

		return array(
			'pong' => true,
		);
	}

	/**
	 * Connect WordPress to FastCloud.
	 *
	 * @param \WP_REST_Request $request The HTTP request received.
	 */
	public function connect( \WP_REST_Request $request ): array|\WP_REST_Response {
		$sitekey = sanitize_text_field( $request->get_param( 'sitekey' ) );

		if ( ! $sitekey ) {
			return new \WP_REST_Response( array( 'error' => 'Missing Site Key' ), 400 );
		}

		$response = fastcloudwp_api_client()->post(
			'websites/connect',
			array(
				'public_key' => $sitekey,
				'site_url'   => get_site_url(),
			)
		);

		if ( $response->failed_transport() ) {
			return new \WP_REST_Response( array( 'error' => 'An unexpected error occurred, please try again.' ), 500 );
		}

		$code = $response->code();
		$body = $response->json();

		if ( $response->failed() ) {
			return new \WP_REST_Response( array( 'error' => 'Invalid Site Key' ), $code );
		}

		do_action( 'fastcloudwp_connected', $body, $sitekey );

		return array(
			'success' => true,
			'state'   => fastcloudwp_javascript_state(),
		);
	}

	/**
	 * Handles the /disconnect REST endpoint.
	 *
	 * Removes all stored FastCloud credentials and settings from WordPress options.
	 */
	public function disconnect(): array {
		$response = fastcloudwp_api_client()->post( 'websites/disconnect' );

		if ( $response->failed() ) {
			return array(
				'success' => false,
				'state'   => fastcloudwp_javascript_state(),
			);
		}

		do_action( 'fastcloudwp_disconnect' );

		return array(
			'success' => true,
			'state'   => fastcloudwp_javascript_state(),
		);
	}

	/**
	 * Handles the /settings REST endpoint.
	 *
	 * Updates the enabled and delete_media plugin settings.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public function settings( \WP_REST_Request $request ): array {
		$settings = fastcloudwp_settings();
		$settings->update( $request->get_json_params() );

		if ( ! $settings->enabled() ) {
			$settings->disable();
		}

		$settings->save();

		return array(
			'success'  => true,
			'settings' => $settings->to_array(),
		);
	}

	/**
	 * Once a batch has been offloaded, update post meta to track the state.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public function offload_complete_webook( \WP_REST_Request $request ): \WP_REST_Response {
		$secret = get_option( 'fastcloudwp_callback_secret' );
		$auth   = $request->get_header( 'authorization' );

		if ( ! $auth || ! str_starts_with( $auth, 'Bearer ' ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Missing authorization.',
				),
				401
			);
		}

		$provided = substr( $auth, 7 );

		if ( $provided !== $secret ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Invalid authorization.',
				),
				403
			);
		}

		fastcloudwp_storage()->sync( $request );
		$attachment_id = intval( $request->get_param( 'attachment_id' ) );

		if ( ! $attachment_id ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Missing attachment_id.',
				),
				400
			);
		}

		update_post_meta( $attachment_id, '_fastcloudwp_status', $request->get_param( 'status' ) );
		update_post_meta( $attachment_id, '_fastcloudwp_timestamp', time() );

		if ( fastcloudwp_settings()->delete_media() ) {
			fastcloudwp_delete_files_for_attachment( $attachment_id );
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Attachment marked as offloaded.',
			),
			200
		);
	}

	/**
	 * Plugin state used by the UI.
	 */
	public function state(): array {
		return array(
			'success' => true,
			'state'   => fastcloudwp_javascript_state(),
		);
	}

	/**
	 * Find existing media with not deleted files and remove them. Might remove the original based on the settings.
	 */
	public function free_space(): \WP_REST_Response {
		$query = new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'fields'         => 'ids',
				'posts_per_page' => FASTCLOUDWP_BATCH_SIZE,
				'no_found_rows'  => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'   => '_fastcloudwp_status',
						'value' => 'offloaded',
					),
					array(
						'key'     => '_fastcloudwp_deleted',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$deleted = 0;
		$failed  = 0;

		foreach ( $query->posts as $attachment_id ) {
			if ( fastcloudwp_delete_files_for_attachment( $attachment_id ) ) {
				++$deleted;
			} else {
				++$failed;
			}
		}

		return new \WP_REST_Response(
			array(
				'deleted'   => $deleted,
				'failed'    => $failed,
				'success'   => 0 === $failed,
				'remaining' => max( 0, $query->found_posts - $deleted ),
			),
			200
		);
	}

	/**
	 * Batch offload media.
	 */
	public function queue_batch(): array {
		$offloader = fastcloudwp_offloader();
		$ids       = fastcloudwp_attachment_states()->not_offloaded_ids( FASTCLOUDWP_BATCH_SIZE );

		if ( empty( $ids ) ) {
			return array(
				'success' => true,
				'queued'  => 0,
				'left'    => 0,
				'done'    => true,
			);
		}

		$result         = $offloader->offload_attachments( $ids );
		$queued         = (int) ( $result['count'] ?? 0 );
		$not_offloaded  = fastcloudwp_attachment_states()->not_offloaded();
		$quota_exceeded = fastcloudwp_attachment_states()->quota_exceeded();
		$success        = $result['success'] ?? false;

		$processable = max( 0, $not_offloaded - $quota_exceeded );

		return array(
			'success'        => $success,
			'queued'         => $queued,
			'left'           => $processable,
			'quota_exceeded' => $quota_exceeded,
			'done'           => 0 === $processable || ! $success,
		);
	}

	/**
	 * Register REST route utility.
	 *
	 * @param string   $path Path to append after fastcloudwp/v1/.
	 * @param string   $method HTTP Method.
	 * @param callable $callback Callback to call when a request is received.
	 */
	protected function register_route( string $path, string $method, callable $callback ): void {
		register_rest_route(
			'fastcloudwp/v1',
			$path,
			array(
				'methods'             => $method,
				'callback'            => $callback,
				'permission_callback' => '__return_true',
			)
		);
	}
}
