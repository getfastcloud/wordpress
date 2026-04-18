<?php
/**
 * Storage class.
 *
 * @package FastCloud\WordPress
 */

declare( strict_types=1 );

namespace FastCloud\WordPress;

use WP_Error;
use WP_REST_Request;

/**
 * Handle storage usage in sync with FastCloud.
 *
 * Caches quota state received from the FastCloud API via response headers
 * (X-FC-Quota-Total, X-FC-Quota-Used, X-FC-Quota-Exceeded, X-FC-Quota-Timestamp)
 * and webhook payloads. Updates older than the currently cached timestamp
 * are rejected to avoid stale overwrites when responses arrive out of order.
 */
class Storage {

	/**
	 * Cached data loaded from options.
	 *
	 * @var array<string, mixed>
	 */
	protected array $data;

	/**
	 * Load cached storage state from WordPress options.
	 */
	public function __construct() {
		$this->data = wp_parse_args(
			(array) get_option( 'fastcloudwp_storage', array() ),
			$this->default_values(),
		);
	}

	/**
	 * Total quota in bytes.
	 */
	public function total(): int {
		return (int) $this->data['total'];
	}

	/**
	 * Used quota in bytes.
	 */
	public function used(): int {
		return (int) $this->data['used'];
	}

	/**
	 * Free space remaining in bytes.
	 */
	public function free(): int {
		return max( 0, $this->total() - $this->used() );
	}

	/**
	 * Whether the site has hit its quota on FastCloud side.
	 */
	public function is_exceeded(): bool {
		return (bool) $this->data['exceeded'];
	}

	/**
	 * ISO 8601 timestamp of the last sync from FastCloud, or null if never synced.
	 */
	public function last_sync(): ?string {
		return $this->data['timestamp'];
	}

	/**
	 * Percentage of quota used (0 to 100, capped).
	 */
	public function percent_used(): float {
		if ( $this->total() <= 0 ) {
			return 0.0;
		}

		return min( 100.0, round( ( $this->used() / $this->total() ) * 100, 2 ) );
	}

	/**
	 * Check whether a file of the given size would fit in remaining quota.
	 *
	 * @param int $size Size in bytes.
	 */
	public function fits( int $size ): bool {
		return ! $this->is_exceeded() && $size <= $this->free();
	}

	/**
	 * Apply an update from FastCloud (headers or webhook payload).
	 *
	 * Rejects updates with a timestamp older than or equal to what is already
	 * cached, so out-of-order responses cannot overwrite fresher state.
	 *
	 * @param array $data The new storage information to sync.
	 */
	public function update( array $data ): bool {
		if ( empty( $data['timestamp'] ) ) {
			return false;
		}

		$incoming = strtotime( (string) $data['timestamp'] );
		if ( false === $incoming ) {
			return false;
		}

		$current = $this->last_sync() ? strtotime( $this->last_sync() ) : 0;
		if ( $incoming <= $current ) {
			return false;
		}

		$this->data = array(
			'total'     => isset( $data['total'] ) ? (int) $data['total'] : $this->total(),
			'used'      => isset( $data['used'] ) ? (int) $data['used'] : $this->used(),
			'exceeded'  => isset( $data['exceeded'] ) ? (bool) (int) $data['exceeded'] : $this->is_exceeded(),
			'timestamp' => (string) $data['timestamp'],
		);

		$this->save();

		return true;
	}

	/**
	 * Persist current state to WordPress options.
	 */
	public function save(): void {
		update_option( 'fastcloudwp_storage', $this->data, false );
	}

	/**
	 * Reset cached state (e.g. on plugin disconnect).
	 */
	public function reset(): void {
		$this->data = $this->default_values();
		delete_option( 'fastcloudwp_storage' );
	}

	/**
	 * Raw data array (for debug / dashboard output).
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'total'        => $this->total(),
			'used'         => $this->used(),
			'free'         => $this->free(),
			'exceeded'     => $this->is_exceeded(),
			'percent_used' => $this->percent_used(),
			'last_sync'    => $this->last_sync(),
		);
	}

	/**
	 * Sync cached state from a FastCloud request or response.
	 *
	 * @param WP_REST_Request|array|WP_Error $source Headers from FastCloud.
	 */
	public function sync( WP_REST_Request|array|WP_Error $source ): bool {
		if ( $source instanceof WP_Error ) {
			return false;
		}

		if ( $source instanceof WP_REST_Request ) {
			$total     = $source->get_header( 'x-fastcloud-quota-total' );
			$used      = $source->get_header( 'x-fastcloud-quota-used' );
			$exceeded  = $source->get_header( 'x-fastcloud-quota-exceeded' );
			$timestamp = $source->get_header( 'x-fastcloud-quota-timestamp' );
		} elseif ( is_array( $source ) ) {
			$total     = wp_remote_retrieve_header( $source, 'x-fastcloud-quota-total' );
			$used      = wp_remote_retrieve_header( $source, 'x-fastcloud-quota-used' );
			$exceeded  = wp_remote_retrieve_header( $source, 'x-fastcloud-quota-exceeded' );
			$timestamp = wp_remote_retrieve_header( $source, 'x-fastcloud-quota-timestamp' );
		} else {
			return false;
		}

		if ( empty( $timestamp ) ) {
			return false;
		}

		return $this->update(
			array(
				'total'     => $total,
				'used'      => $used,
				'exceeded'  => $exceeded,
				'timestamp' => $timestamp,
			)
		);
	}

	/**
	 * Default values if the option does not exist.
	 */
	protected function default_values(): array {
		return array(
			'total'     => 0,
			'used'      => 0,
			'exceeded'  => false,
			'timestamp' => null,
		);
	}
}
