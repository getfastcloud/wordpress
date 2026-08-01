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
	 * Maximum number of duplicate rows resolved for a single file.
	 *
	 * @var int
	 */
	protected const MAX_TWINS = 100;

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
		if ( fastcloudwp_settings()->remove_original() ) {
			return $this->count_attachments(
				array(
					'relation' => 'AND',
					array(
						'key'     => '_fastcloudwp_deleted',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_fastcloudwp_original_deleted',
						'compare' => 'EXISTS',
					),
				)
			);
		}

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
		return $this->count_attachments( $this->pending_delete_meta_query() );
	}

	/**
	 * Returns IDs of offloaded attachments not yet deleted locally.
	 *
	 * @param int $limit The number of posts maximum to get.
	 */
	public function pending_delete_ids( int $limit = 100 ): array {
		return $this->get_attachment_ids( $this->pending_delete_meta_query(), $limit );
	}

	/**
	 * Meta query clause describing "offloaded but not fully deleted locally".
	 *
	 * When remove_original is enabled, both the variations and the original
	 * must be deleted before the attachment leaves this bucket.
	 */
	protected function pending_delete_meta_query(): array {
		if ( fastcloudwp_settings()->remove_original() ) {
			return array(
				'relation' => 'AND',
				array(
					'key'   => '_fastcloudwp_status',
					'value' => 'offloaded',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => '_fastcloudwp_deleted',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_fastcloudwp_original_deleted',
						'compare' => 'NOT EXISTS',
					),
				),
			);
		}

		return array(
			array(
				'key'   => '_fastcloudwp_status',
				'value' => 'offloaded',
			),
			array(
				'key'     => '_fastcloudwp_deleted',
				'compare' => 'NOT EXISTS',
			),
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
						'value'   => array( 'queued', 'offloaded' ),
						'compare' => 'NOT IN',
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
	 * Filters are suppressed so multilingual plugins do not scope the result to
	 * the current language. Offload state tracks files on disk, and the total
	 * from wp_count_attachments() is unfiltered, so every count must match it.
	 *
	 * @param array $meta_query The meta query to use for the count.
	 */
	protected function count_attachments( array $meta_query ): int {
		$query = new \WP_Query(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'fields'           => 'ids',
				'posts_per_page'   => 1,
				'no_found_rows'    => false,
				'suppress_filters' => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'       => $meta_query,
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Returns IDs of attachments that have never been fully offloaded.
	 *
	 * @param int $limit The number of posts maximum to get.
	 * @param int $offset Number of posts to skip.
	 */
	public function not_offloaded_ids( int $limit = 100, int $offset = 0 ): array {
		return $this->get_attachment_ids( $this->not_offloaded_meta_query(), $limit, $offset );
	}

	/**
	 * Returns IDs of other attachments pointing at the same file on disk.
	 *
	 * Repeated content imports create several attachment rows per file, and the
	 * API deduplicates by hash, so it only ever reports one of them back.
	 *
	 * @param int $attachment_id The attachment post ID.
	 */
	public function twin_ids( int $attachment_id ): array {
		$file = get_post_meta( $attachment_id, '_wp_attached_file', true );

		if ( ! $file ) {
			return array();
		}

		$ids = $this->get_attachment_ids(
			array(
				array(
					'key'   => '_wp_attached_file',
					'value' => $file,
				),
			),
			self::MAX_TWINS
		);

		return array_values( array_diff( $ids, array( $attachment_id ) ) );
	}

	/**
	 * Find posts ID based on a meta query with a hard limit.
	 *
	 * Ordering by ID keeps offset pagination stable: duplicate rows share an
	 * identical post_date, so the default ordering is not deterministic.
	 * Filters are suppressed for the reason given on count_attachments().
	 *
	 * @param array $meta_query The meta query.
	 * @param int   $limit Number of posts to return.
	 * @param int   $offset Number of posts to skip.
	 */
	protected function get_attachment_ids( array $meta_query, int $limit, int $offset = 0 ): array {
		$query = new \WP_Query(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'fields'           => 'ids',
				'posts_per_page'   => $limit,
				'offset'           => $offset,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'       => $meta_query,
			)
		);

		return $query->posts;
	}
}
