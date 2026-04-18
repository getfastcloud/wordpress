<?php
/**
 * API Client for FastCloud server.
 *
 * @package FastCloudWP
 */

declare( strict_types=1 );

namespace FastCloud\WordPress;

/**
 * Client class.
 */
class Client {

	/**
	 * The FastCloud website UUID.
	 *
	 * @var string
	 */
	protected ?string $site_uuid;

	/**
	 * The API bearer token.
	 *
	 * @var string
	 */
	protected ?string $token;

	/**
	 * Construct the API client.
	 *
	 * @param string|null $site_uuid Site UUID.
	 * @param string|null $token Site token for authenticated requests.
	 */
	public function __construct( ?string $site_uuid, ?string $token ) {
		$this->site_uuid = $site_uuid;
		$this->token     = $token;
	}

	/**
	 * Sent a HTTP DELETE request.
	 *
	 * @param string $endpoint The API endpoint.
	 */
	public function delete( string $endpoint ): Http_Response {
		return $this->request( 'DELETE', $endpoint );
	}

	/**
	 * Sent a HTTP POST request.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $body The payload to send to FastCloud.
	 */
	public function post( string $endpoint, array $body = array() ): Http_Response {
		return $this->request(
			'POST',
			$endpoint,
			array(
				'body' => wp_json_encode( $body ),
			)
		);
	}

	/**
	 * Send request method with appropriate headers and settings.
	 *
	 * @param string $method HTTP method of the request.
	 * @param string $endpoint Endpoint to call on FastCloud server.
	 * @param array  $args Arguments to add to the request.
	 */
	protected function request( string $method, string $endpoint, array $args = array() ): Http_Response {
		$args = array_merge(
			array(
				'method'    => strtoupper( $method ),
				'sslverify' => ! ( defined( 'FASTCLOUDWP_DEBUG' ) && FASTCLOUDWP_DEBUG ),
				'headers'   => $this->get_headers(),
				'timeout'   => 15,
			),
			$args
		);

		$raw      = wp_remote_request( FASTCLOUDWP_BASE_URL . $endpoint, $args );
		$response = new Http_Response( $raw );

		if ( $response->successful() ) {
			fastcloudwp_storage()->sync( $response->raw() );
		}

		return $response;
	}

	/**
	 * All requests must be authenticated Bearer and return or accept JSON.
	 */
	protected function get_headers() {
		$headers = array(
			'Content-Type'     => 'application/json',
			'Accept'           => 'application/json',
			'X-FastCloud-Site' => get_site_url(),
		);

		if ( ! empty( $this->token ) ) {
			$headers['Authorization'] = 'Bearer ' . $this->token;
		}

		return $headers;
	}
}
