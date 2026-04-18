<?php
/**
 * Stats class.
 *
 * @package FastCloud\WordPress
 */

declare( strict_types=1 );

namespace FastCloud\WordPress;

/**
 * Provides attachment counts by offload status.
 */
class Attachment_State_Repository {

	/**
	 * Returns the total number of attachments (excluding trash).
	 */
	public function attachments_count(): int {
		$counts = (array) wp_count_attachments();
		unset( $counts['trash'] );

		return (int) array_sum( $counts );
	}

	/**
	 * Returns the number of attachments with status "queued".
	 */
	public function queued(): int {
		return $this->count_by_status( 'queued' );
	}

	/**
	 * Returns the number of attachments with status "offloaded".
	 */
	public function offloaded(): int {
		return $this->count_attachments(
			array(
				'relation' => 'AND',
				array(
					'key'   => '_fastcloudwp_status',
					'value' => 'offloaded',
				),
				array(
					'key'     => '_fastcloudwp_dirty',
					'compare' => 'NOT EXISTS',
				),
			)
		);
	}

	/**
	 * Returns the number of attachments with status "quota_exceeded".
	 */
	public function quota_exceeded(): int {
		return $this->count_by_status( 'quota_exceeded' );
	}

	/**
	 * Returns the number of attachments whose local files have been deleted
	 * post-offload.
	 */
	public function deleted(): int {
		return $this->count_attachments(
			array(
				array(
					'key'     => '_fastcloudwp_deleted',
					'compare' => 'EXISTS',
				),
			)
		);
	}

	/**
	 * Returns the number of offloaded attachments not yet deleted locally.
	 */
	public function pending_delete(): int {
		return $this->count_attachments(
			array(
				array(
					'key'   => '_fastcloudwp_status',
					'value' => 'offloaded',
				),
				array(
					'key'     => '_fastcloudwp_deleted',
					'compare' => 'NOT EXISTS',
				),
			)
		);
	}

	/**
	 * Returns the number of attachments flagged as locally modified after a
	 * successful offload.
	 */
	public function dirty(): int {
		return $this->count_attachments(
			array(
				array(
					'key'     => '_fastcloudwp_dirty',
					'value'   => '1',
					'compare' => '=',
				),
			)
		);
	}

	/**
	 * Returns the number of attachments that have never been fully offloaded.
	 *
	 * Covers attachments the plugin has never seen and attachments that
	 * previously failed with a quota-exceeded response.
	 */
	public function not_offloaded(): int {
		return $this->count_attachments( $this->not_offloaded_meta_query() );
	}

	/**
	 * Meta query clause describing "never fully offloaded".
	 *
	 * Extracted so `not_offloaded()` and `needs_sync()` share exactly one
	 * source of truth.
	 */
	protected function not_offloaded_meta_query(): array {
		return array(
			'relation' => 'OR',

			array(
				'relation' => 'AND',
				array(
					'key'     => '_fastcloudwp_deleted',
					'compare' => 'NOT EXISTS',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => '_fastcloudwp_status',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_fastcloudwp_status',
						'value'   => 'quota_exceeded',
						'compare' => '=',
					),
				),
			),

			array(
				'key'     => '_fastcloudwp_dirty',
				'value'   => '1',
				'compare' => '=',
			),
		);
	}

	/**
	 * Counts attachments whose `_fastcloudwp_status` meta equals a given value.
	 *
	 * @param string $status The status of the attachment with FastCloud.
	 */
	protected function count_by_status( string $status ): int {
		return $this->count_attachments(
			array(
				array(
					'key'   => '_fastcloudwp_status',
					'value' => $status,
				),
			)
		);
	}

	/**
	 * Shared WP_Query runner for attachment counts.
	 *
	 * @param array $meta_query The meta query to use for the count.
	 */
	protected function count_attachments( array $meta_query ): int {
		$query = new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => $meta_query,
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Returns IDs of attachments that have never been fully offloaded.
	 *
	 * @param int $limit The number of posts maximum to get.
	 */
	public function not_offloaded_ids( int $limit = 100 ): array {
		return $this->get_attachment_ids( $this->not_offloaded_meta_query(), $limit );
	}

	/**
	 * Find posts ID based on a meta query with a hard limit.
	 *
	 * @param array $meta_query The meta query.
	 * @param int   $limit Number of posts to return.
	 */
	protected function get_attachment_ids( array $meta_query, int $limit ): array {
		$query = new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'fields'         => 'ids',
				'posts_per_page' => $limit,
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => $meta_query,
			)
		);

		return $query->posts;
	}
}
