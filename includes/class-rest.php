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
		$this->register_route( '/ping', 'GET', array( $this, 'ping_webhook' ), '__return_true' );
		$this->register_route( '/register', 'POST', array( $this, 'quick_start' ), array( $this, 'authenticate_admin' ) );
		$this->register_route( '/resend-confirmation', 'POST', array( $this, 'resend_confirmation' ), array( $this, 'authenticate_admin' ) );
		$this->register_route( '/connect', 'POST', array( $this, 'connect' ), array( $this, 'authenticate_admin' ) );
		$this->register_route( '/disconnect', 'POST', array( $this, 'disconnect' ), array( $this, 'authenticate_admin' ) );
		$this->register_route( '/remote-disconnect', 'POST', array( $this, 'remote_disconnect_webhook' ), array( $this, 'authenticate_webhook' ) );
		$this->register_route( '/settings', 'POST', array( $this, 'settings' ), array( $this, 'authenticate_admin' ) );
		$this->register_route( '/offload-complete', 'POST', array( $this, 'offload_complete_webook' ), array( $this, 'authenticate_webhook' ) );
		$this->register_route( '/restore-attachment', 'POST', array( $this, 'restore_attachment' ), array( $this, 'authenticate_webhook' ) );
		$this->register_route( '/state', 'GET', array( $this, 'state' ), array( $this, 'authenticate_admin' ) );
		$this->register_route( '/offload', 'POST', array( $this, 'queue_batch' ), array( $this, 'authenticate_admin' ) );
		$this->register_route( '/free-space', 'POST', array( $this, 'free_space' ), array( $this, 'authenticate_admin' ) );
		$this->register_route( '/domain', 'POST', array( $this, 'update_domain_webhook' ), array( $this, 'authenticate_webhook' ) );
		$this->register_route( '/domain', 'DELETE', array( $this, 'delete_domain_webhook' ), array( $this, 'authenticate_webhook' ) );
		$this->register_route( '/cdn-ready', 'POST', array( $this, 'cdn_ready_webhook' ), array( $this, 'authenticate_webhook' ) );
		$this->register_route( '/webhook', 'POST', array( $this, 'webhook' ), array( $this, 'authenticate_webhook' ) );
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
			__( 'You do not have permission to access this endpoint.', 'fastcloud-offload-media' ),
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
			__( 'Invalid or missing authorization.', 'fastcloud-offload-media' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Let FastCloud ping the website back on connect to verify ownership.
	 */
	public function ping_webhook(): array {
		return array(
			'pong' => true,
		);
	}

	/**
	 * Create a FastCloud account and connect in one step.
	 *
	 * @param \WP_REST_Request $request The HTTP request received.
	 */
	public function quick_start( \WP_REST_Request $request ): array|\WP_REST_Response {
		$email = sanitize_email( (string) $request->get_param( 'email' ) );

		if ( ! $email || ! is_email( $email ) ) {
			return new \WP_REST_Response( array( 'error' => __( 'Missing or invalid email address.', 'fastcloud-offload-media' ) ), 400 );
		}

		$response = fastcloudwp_api_client()->post(
			'websites/quick-start',
			array(
				'email'    => $email,
				'site_url' => get_site_url(),
				'sitename' => get_bloginfo( 'name' ),
			)
		);

		if ( $response->failed_transport() ) {
			return new \WP_REST_Response( array( 'error' => __( 'An unexpected error occurred, please try again.', 'fastcloud-offload-media' ) ), 500 );
		}

		$code = $response->code();
		$body = $response->json();

		if ( 409 === $code ) {
			return new \WP_REST_Response( array( 'error' => $body['error'] ?? __( 'This website is already connected to an account.', 'fastcloud-offload-media' ) ), 409 );
		}

		if ( 422 === $code ) {
			return new \WP_REST_Response( array( 'error' => $body['error'] ?? __( 'Domain verification failed. Make sure the site is publicly reachable.', 'fastcloud-offload-media' ) ), 422 );
		}

		if ( $response->failed() ) {
			return new \WP_REST_Response( array( 'error' => $body['error'] ?? __( 'An unexpected error occurred, please try again.', 'fastcloud-offload-media' ) ), $code );
		}

		// Map 'domain' to 'custom_domain' expected by save_site_options().
		if ( isset( $body['domain'] ) ) {
			$body['custom_domain'] = $body['domain'];
		}

		$account_status = $body['account_status'] ?? ( $response->header( 'x-fastcloud-account-status' ) ? $response->header( 'x-fastcloud-account-status' ) : 'active' );

		do_action( 'fastcloudwp_connected', $body, (string) ( $body['public_key'] ?? '' ) );
		update_option( 'fastcloudwp_account_status', $account_status );
		update_option( 'fastcloudwp_account_email', $email );

		return array(
			'success' => true,
			'state'   => fastcloudwp_javascript_state(),
		);
	}

	/**
	 * Resend the account confirmation email for a pending account.
	 *
	 * @param \WP_REST_Request $request The HTTP request received.
	 */
	public function resend_confirmation( \WP_REST_Request $request ): array|\WP_REST_Response {
		$email   = sanitize_email( (string) $request->get_param( 'email' ) );
		$payload = array();

		if ( $email ) {
			if ( ! is_email( $email ) ) {
				return new \WP_REST_Response( array( 'error' => __( 'Invalid email address.', 'fastcloud-offload-media' ) ), 400 );
			}
			$payload['email'] = $email;
		}

		$response = fastcloudwp_api_client()->post( 'websites/resend-confirmation', $payload );

		if ( $response->failed_transport() ) {
			return new \WP_REST_Response( array( 'error' => __( 'An unexpected error occurred, please try again.', 'fastcloud-offload-media' ) ), 500 );
		}

		if ( $response->failed() ) {
			$body = $response->json();
			return new \WP_REST_Response( array( 'error' => $body['error'] ?? __( 'An unexpected error occurred, please try again.', 'fastcloud-offload-media' ) ), $response->code() );
		}

		if ( $email ) {
			update_option( 'fastcloudwp_account_email', $email );
		}

		return array( 'success' => true );
	}

	/**
	 * Connect WordPress to FastCloud.
	 *
	 * @param \WP_REST_Request $request The HTTP request received.
	 */
	public function connect( \WP_REST_Request $request ): array|\WP_REST_Response {
		$sitekey = sanitize_text_field( $request->get_param( 'sitekey' ) );

		if ( ! $sitekey ) {
			return new \WP_REST_Response( array( 'error' => __( 'Missing Site Key', 'fastcloud-offload-media' ) ), 400 );
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
			return new \WP_REST_Response( array( 'error' => __( 'An unexpected error occurred, please try again.', 'fastcloud-offload-media' ) ), 500 );
		}

		$code = $response->code();
		$body = $response->json();

		if ( $response->failed() ) {
			return new \WP_REST_Response( array( 'error' => $body['error'] ?? __( 'Invalid Site Key', 'fastcloud-offload-media' ) ), $code );
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
	 * Handles the /remote-disconnect REST endpoint.
	 *
	 * Removes all stored FastCloud credentials and settings from WordPress options.
	 */
	public function remote_disconnect_webhook(): array {
		do_action( 'fastcloudwp_disconnect' );

		return array(
			'success' => true,
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
					'message' => __( 'Missing attachment_id.', 'fastcloud-offload-media' ),
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
				'message' => __( 'Attachment marked as offloaded.', 'fastcloud-offload-media' ),
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
	 * Restore attachment files from S3 presigned URLs back to local storage.
	 *
	 * Downloads each file variant from its presigned URL, writes it to the correct
	 * path under wp-content/uploads/, and clears all FastCloud offload meta so
	 * WordPress resumes serving the attachment locally.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public function restore_attachment( \WP_REST_Request $request ): \WP_REST_Response {
		$attachment_id = intval( $request->get_param( 'attachment_id' ) );
		$files         = $request->get_param( 'files' );

		if ( ! $attachment_id || empty( $files ) || ! is_array( $files ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Missing attachment_id or files.', 'fastcloud-offload-media' ),
				),
				400
			);
		}

		if ( ! get_post( $attachment_id ) || 'attachment' !== get_post_type( $attachment_id ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Attachment not found.', 'fastcloud-offload-media' ),
				),
				404
			);
		}

		$uploads       = wp_get_upload_dir();
		$base_dir      = trailingslashit( $uploads['basedir'] );
		$attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$subdir_raw    = $attached_file ? dirname( $attached_file ) : '.';
		$subdir        = ( '.' === $subdir_raw ) ? '' : trailingslashit( $subdir_raw );

		foreach ( $files as $file ) {
			$filename = isset( $file['original_filename'] ) ? basename( $file['original_filename'] ) : '';
			$url      = $file['url'] ?? '';

			if ( ! $filename || ! $url ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Invalid file entry.', 'fastcloud-offload-media' ),
					),
					400
				);
			}

			$dest = $base_dir . $subdir . $filename;

			wp_mkdir_p( dirname( $dest ) );

			$hash = isset( $file['hash'] ) ? (string) $file['hash'] : '';
			if ( $hash && file_exists( $dest ) && md5_file( $dest ) === $hash ) {
				continue;
			}

			$response = wp_remote_get( $url, array( 'timeout' => 60 ) );

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						// translators: %s: filename that failed to download.
						'message' => sprintf( __( 'Failed to download file: %s', 'fastcloud-offload-media' ), $filename ),
					),
					502
				);
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			if ( false === file_put_contents( $dest, wp_remote_retrieve_body( $response ) ) ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						// translators: %s: filename that failed to write.
						'message' => sprintf( __( 'Failed to write file: %s', 'fastcloud-offload-media' ), $filename ),
					),
					500
				);
			}
		}

		delete_post_meta( $attachment_id, '_fastcloudwp_status' );
		delete_post_meta( $attachment_id, '_fastcloudwp_timestamp' );
		delete_post_meta( $attachment_id, '_fastcloudwp_deleted' );
		delete_post_meta( $attachment_id, '_fastcloudwp_original_deleted' );
		delete_post_meta( $attachment_id, '_fastcloudwp_dirty' );

		return new \WP_REST_Response( array( 'success' => true ), 200 );
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

	/**
	 * Set the custom CDN domain pushed from the FastCloud SaaS.
	 *
	 * @param \WP_REST_Request $request The incoming REST request.
	 */
	public function update_domain_webhook( \WP_REST_Request $request ): array|\WP_REST_Response {
		$domain = sanitize_text_field( (string) $request->get_param( 'domain' ) );

		if ( ! $domain ) {
			return new \WP_REST_Response( array( 'error' => __( 'Missing domain parameter.', 'fastcloud-offload-media' ) ), 400 );
		}

		if ( ! filter_var( $domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) ) {
			return new \WP_REST_Response( array( 'error' => __( 'Invalid domain.', 'fastcloud-offload-media' ) ), 400 );
		}

		update_option( 'fastcloudwp_custom_domain', $domain );

		return array( 'success' => true );
	}

	/**
	 * Clear the custom CDN domain, reverting to the bucket-based origin.
	 */
	public function delete_domain_webhook(): array {
		delete_option( 'fastcloudwp_custom_domain' );
		return array( 'success' => true );
	}

	/**
	 * Marks the CDN as ready, enabling URL rewriting.
	 *
	 * Called by the app once the bucket certificate is live. Until this webhook
	 * is received, all rewrite methods return original WordPress URLs unchanged.
	 */
	public function cdn_ready_webhook(): array {
		update_option( 'fastcloudwp_cdn_ready', true );
		return array( 'ok' => true );
	}

	/**
	 * Generic inbound webhook dispatcher. Routes by event type.
	 *
	 * Unknown types return 200 silently for forward compatibility.
	 *
	 * @param \WP_REST_Request $request The incoming REST request.
	 */
	public function webhook( \WP_REST_Request $request ): array|\WP_REST_Response {
		$type = sanitize_text_field( (string) $request->get_param( 'type' ) );
		$data = $request->get_param( 'data' );

		if ( ! is_array( $data ) ) {
			return new \WP_REST_Response( array( 'error' => __( 'Missing data payload.', 'fastcloud-offload-media' ) ), 400 );
		}

		switch ( $type ) {
			case 'account_status_changed':
				return $this->handle_account_status_changed( $data );
		}

		return array( 'ok' => true );
	}

	/**
	 * Handle the account_status_changed webhook event.
	 *
	 * @param array $data Event payload from FastCloud.
	 */
	private function handle_account_status_changed( array $data ): array {
		$status = sanitize_text_field( (string) ( $data['account_status'] ?? '' ) );

		if ( $status ) {
			update_option( 'fastcloudwp_account_status', $status );
		}

		if ( isset( $data['quota_total'], $data['quota_used'], $data['quota_exceeded'] ) ) {
			fastcloudwp_storage()->sync_from_webhook(
				(int) $data['quota_total'],
				(int) $data['quota_used'],
				(bool) $data['quota_exceeded']
			);
		}

		return array( 'ok' => true );
	}
}
