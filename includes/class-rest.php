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
		$this->register_route( '/ping', 'GET', array( $this, 'ping_webhook' ), array( $this, 'authenticate_webhook' ) );
		$this->register_route( '/connect', 'POST', array( $this, 'connect' ), array( $this, 'authenticate_admin' ) );
		$this->register_route( '/disconnect', 'POST', array( $this, 'disconnect' ), array( $this, 'authenticate_admin' ) );
		$this->register_route( '/settings', 'POST', array( $this, 'settings' ), array( $this, 'authenticate_admin' ) );
		$this->register_route( '/offload-complete', 'POST', array( $this, 'offload_complete_webook' ), array( $this, 'authenticate_webhook' ) );
		$this->register_route( '/state', 'GET', array( $this, 'state' ), array( $this, 'authenticate_admin' ) );
		$this->register_route( '/offload', 'POST', array( $this, 'queue_batch' ), array( $this, 'authenticate_admin' ) );
		$this->register_route( '/free-space', 'POST', array( $this, 'free_space' ), array( $this, 'authenticate_admin' ) );
	}

	/**
	 * Permission callback for admin-only endpoints.
	 *
	 * Requires the current user to be logged in and have the manage_options capability.
	 */
	public function authenticate_admin(): bool|\WP_Error {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new \WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to access this endpoint.', 'fastcloudwp' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Permission callback for webhook endpoints.
	 *
	 * Validates the Authorization Bearer token against the stored callback secret.
	 *
	 * @param \WP_REST_Request $request The incoming REST request.
	 */
	public function authenticate_webhook( \WP_REST_Request $request ): bool|\WP_Error {
		$secret = get_option( 'fastcloudwp_callback_secret' );
		$auth   = $request->get_header( 'authorization' );

		if ( $secret && $auth && str_starts_with( $auth, 'Bearer ' ) && substr( $auth, 7 ) === $secret ) {
			return true;
		}

		return new \WP_Error(
			'rest_forbidden',
			__( 'Invalid or missing authorization.', 'fastcloudwp' ),
			array( 'status' => 401 )
		);
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
			return new \WP_REST_Response( array( 'error' => __( 'Missing Site Key', 'fastcloudwp' ) ), 400 );
		}

		$response = fastcloudwp_api_client()->post(
			'websites/connect',
			array(
				'public_key' => $sitekey,
				'sitename'   => get_bloginfo( 'name' ),
				'site_url'   => get_site_url(),
			)
		);

		if ( $response->failed_transport() ) {
			return new \WP_REST_Response( array( 'error' => __( 'An unexpected error occurred, please try again.', 'fastcloudwp' ) ), 500 );
		}

		$code = $response->code();
		$body = $response->json();

		if ( $response->failed() ) {
			return new \WP_REST_Response( array( 'error' => __( 'Invalid Site Key', 'fastcloudwp' ) ), $code );
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

		/**
		 * Fires after FastCloud plugin settings have been saved.
		 *
		 * @param array $settings The saved settings as a plain array.
		 */
		do_action( 'fastcloudwp_after_settings_update', $settings->to_array() );

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
		fastcloudwp_storage()->sync( $request );
		$attachment_id = intval( $request->get_param( 'attachment_id' ) );

		if ( ! $attachment_id ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Missing attachment_id.', 'fastcloudwp' ),
				),
				400
			);
		}

		$status = $request->get_param( 'status' );
		update_post_meta( $attachment_id, '_fastcloudwp_status', $status );
		update_post_meta( $attachment_id, '_fastcloudwp_timestamp', time() );

		if ( 'offloaded' === $status ) {
			/** This action is documented in includes/class-offloader.php */
			do_action( 'fastcloudwp_attachment_offloaded', $attachment_id );
		}

		if ( fastcloudwp_settings()->delete_media() ) {
			fastcloudwp_delete_files_for_attachment( $attachment_id );
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Attachment marked as offloaded.', 'fastcloudwp' ),
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
		$ids       = fastcloudwp_attachment_states()->pending_delete_ids( FASTCLOUDWP_BATCH_SIZE );
		$remaining = fastcloudwp_attachment_states()->pending_delete();

		$deleted = 0;
		$failed  = 0;

		foreach ( $ids as $attachment_id ) {
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
				'remaining' => max( 0, $remaining - $deleted ),
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
	 * @param string   $path               Path to append after fastcloudwp/v1/.
	 * @param string   $method             HTTP Method.
	 * @param callable $callback           Callback to call when a request is received.
	 * @param callable $permission_callback Permission callback to validate the request.
	 */
	protected function register_route( string $path, string $method, callable $callback, callable $permission_callback ): void {
		register_rest_route(
			'fastcloudwp/v1',
			$path,
			array(
				'methods'             => $method,
				'callback'            => function ( \WP_REST_Request $request ) use ( $callback ) {
					$result    = $callback( $request );
					$connected = (bool) get_option( 'fastcloudwp_website_uuid' );

					if ( is_array( $result ) ) {
						$result = new \WP_REST_Response( $result );
					}

					if ( $result instanceof \WP_REST_Response ) {
						$result->header( 'X-FastCloud-Connected', $connected ? '1' : '0' );
					}

					return $result;
				},
				'permission_callback' => $permission_callback,
			)
		);
	}
}
