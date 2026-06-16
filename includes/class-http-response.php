<?php
/**
 * HTTP response wrapper for API calls.
 *
 * @package FastCloudWP
 */

declare( strict_types=1 );

namespace FastCloud\WordPress;

use WP_Error;

/**
 * Wrapper for processing wp_remote_ calls.
 */
class Http_Response {

	/**
	 * Class constructor.
	 *
	 * @param array|WP_Error $raw The raw wp_remote_* response or a WP_Error.
	 */
	public function __construct( protected array|WP_Error $raw ) {
	}

	/**
	 * True only when the transport succeeded AND the HTTP status is 2xx.
	 */
	public function successful(): bool {
		if ( $this->failed_transport() ) {
			return false;
		}

		$status = $this->code();

		return $status >= 200 && $status < 300;
	}

	/**
	 * True if it's not successful.
	 */
	public function failed(): bool {
		return ! $this->successful();
	}

	/**
	 * True if the status returned by the server is between 400 and 499.
	 */
	public function client_error(): bool {
		$status = $this->code();

		return $status >= 400 && $status < 500;
	}

	/**
	 * True when the server rejected the credentials (401 or 403).
	 */
	public function unauthorized(): bool {
		return 401 === $this->code() || 403 === $this->code();
	}

	/**
	 * True if it's a server error with code 500-599.
	 */
	public function server_error(): bool {
		$status = $this->code();

		return $status >= 500 && $status < 600;
	}

	/**
	 * True when wp_remote_* itself failed (DNS, timeout, TLS, etc.).
	 */
	public function failed_transport(): bool {
		return is_wp_error( $this->raw );
	}

	/**
	 * HTTP status code, or 0 if transport failed.
	 */
	public function code(): int {
		if ( $this->failed_transport() ) {
			return 0;
		}

		return (int) wp_remote_retrieve_response_code( $this->raw );
	}

	/**
	 * Raw response body as a string. Empty string on transport failure.
	 */
	public function body(): string {
		if ( $this->failed_transport() ) {
			return '';
		}

		return (string) wp_remote_retrieve_body( $this->raw );
	}

	/**
	 * Decoded JSON body. Returns null when body is absent or not valid JSON.
	 *
	 * @param string|null $key Optional dot-less key to fetch from the decoded array.
	 * @param mixed       $default_value Returned when $key is provided and missing.
	 */
	public function json( ?string $key = null, mixed $default_value = null ): mixed {
		$decoded = json_decode( $this->body(), true );

		if ( ! is_array( $decoded ) ) {
			return null === $key ? null : $default_value;
		}

		if ( null === $key ) {
			return $decoded;
		}

		return $decoded[ $key ] ?? $default_value;
	}

	/**
	 * Single response header (case-insensitive). Empty string if absent.
	 *
	 * @param string $name Name of the header value to get.
	 */
	public function header( string $name ): string {
		if ( $this->failed_transport() ) {
			return '';
		}

		return (string) wp_remote_retrieve_header( $this->raw, $name );
	}

	/**
	 * All response headers as an associative array.
	 */
	public function headers(): array {
		if ( $this->failed_transport() ) {
			return array();
		}

		return (array) wp_remote_retrieve_headers( $this->raw );
	}

	/**
	 * Human-readable error message — preferred source order:
	 *   1. WP_Error message (transport failure)
	 *   2. JSON body "error" field (your Laravel convention)
	 *   3. HTTP status code string
	 */
	public function error_message(): string {
		if ( $this->raw instanceof WP_Error ) {
			return $this->raw->get_error_message();
		}

		$error = $this->json( 'error' );
		if ( is_string( $error ) && '' !== $error ) {
			return $error;
		}

		return sprintf( 'HTTP %d', $this->code() );
	}

	/**
	 * Expose the underlying WP_Error for callers that want it.
	 */
	public function wp_error(): ?WP_Error {
		return $this->raw instanceof WP_Error ? $this->raw : null;
	}

	/**
	 * Expose the raw response if something needs it (rare).
	 */
	public function raw(): array|WP_Error {
		return $this->raw;
	}
}
