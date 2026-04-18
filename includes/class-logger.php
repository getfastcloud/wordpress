<?php
/**
 * Custom logger utility.
 *
 * @package FastCloud\WordPress
 */

// phpcs:disable WordPress.DB
declare( strict_types=1 );

namespace FastCloud\WordPress;

/**
 * Database-backed logger for capturing and querying plugin activity across severity levels.
 */
class Logger {

	/**
	 * Database version.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.1';

	/**
	 * Time before a duplicate log can be saved again.
	 *
	 * @var int
	 */
	const DEDUP_MINUTES = 5;

	/**
	 * Table name to store logs.
	 *
	 * @var string
	 */
	const TABLE_SUFFIX = 'fastcloudwp_logs';

	/**
	 * Number of logs to keep in the database.
	 *
	 * @var int
	 */
	const MAX_ENTRIES = 1000;

	/**
	 * Logging Level.
	 *
	 * @var string
	 */
	const DEBUG = 'debug';

	/**
	 * Logging Level.
	 *
	 * @var string
	 */
	const INFO = 'info';

	/**
	 * Logging Level.
	 *
	 * @var string
	 */
	const SUCCESS = 'success';

	/**
	 * Logging Level.
	 *
	 * @var string
	 */
	const WARNING = 'warning';

	/**
	 * Logging Level.
	 *
	 * @var string
	 */
	const ERROR = 'error';

	/**
	 * Table name with WordPress prefix.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Construct the logger object and generate the full table name for queries.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Register the cron hook and schedule daily cleanup.
	 */
	public function init(): void {
		add_action( 'plugins_loaded', array( $this, 'install' ) );
		add_action( 'fastcloudwp_logs_cleanup', array( $this, 'cleanup' ) );

		if ( ! wp_next_scheduled( 'fastcloudwp_logs_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'fastcloudwp_logs_cleanup' );
		}

		register_activation_hook( __FILE__, array( $this, 'install' ) );
		register_uninstall_hook( __FILE__, array( self::class, 'uninstall' ) );
	}

	/**
	 * Create (or upgrade) the log table.
	 * Called via register_activation_hook().
	 *
	 * Index strategy:
	 *  - PRIMARY KEY (id)                → fast DELETE by id range, pagination
	 *  - idx_level (level)               → fast WHERE level = ?  filter
	 *  - idx_created_at (created_at)     → fast ORDER BY / date-range queries
	 *  - idx_level_date (level, created_at) → covering index for filtered + sorted queries
	 */
	public function install(): void {
		if ( get_option( 'fastcloudwp_logs_db_version' ) === self::DB_VERSION ) {
			return;
		}

		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table} (
			id         BIGINT(20) UNSIGNED  NOT NULL AUTO_INCREMENT,
			level      VARCHAR(20)          NOT NULL DEFAULT 'info',
			source     VARCHAR(100)         NOT NULL DEFAULT '',
			message    TEXT                 NOT NULL,
			context    LONGTEXT                      NULL,
			hash       VARCHAR(32)          NOT NULL DEFAULT '',
			created_at DATETIME             NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_level      (level),
			KEY idx_created_at (created_at),
			KEY idx_level_date (level, created_at),
			KEY idx_hash_date  (hash, created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'fastcloudwp_logs_db_version', self::DB_VERSION );
	}

	/**
	 * Remove table and cancel the cron schedule on plugin uninstall.
	 */
	public static function uninstall(): void {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_SUFFIX;

		$wpdb->query( 'DROP TABLE IF EXISTS ' . $table );

		wp_clear_scheduled_hook( 'fastcloudwp_logs_cleanup' );
		delete_option( 'fastcloudwp_logs_db_version' );
	}

	/**
	 * Low-level details useful only when troubleshooting.
	 *
	 * @param string $message Human-readable description.
	 * @param string $source The plugin component writing this entry (e.g. 'sync', 'api').
	 * @param array  $context Key/value pairs surfaced in the admin UI (order IDs, HTTP codes…).
	 */
	public function debug( string $message, string $source = '', array $context = array() ): void {
		$this->write( self::DEBUG, $message, $source, $context );
	}

	/**
	 * General informational events (background task started, settings saved…).
	 *
	 * @param string $message Human-readable description.
	 * @param string $source The plugin component writing this entry (e.g. 'sync', 'api').
	 * @param array  $context Key/value pairs surfaced in the admin UI (order IDs, HTTP codes…).
	 */
	public function info( string $message, string $source = '', array $context = array() ): void {
		$this->write( self::INFO, $message, $source, $context );
	}

	/**
	 * A task completed successfully (media offloaded, email sent…).
	 *
	 * @param string $message Human-readable description.
	 * @param string $source The plugin component writing this entry (e.g. 'sync', 'api').
	 * @param array  $context Key/value pairs surfaced in the admin UI (order IDs, HTTP codes…).
	 */
	public function success( string $message, string $source = '', array $context = array() ): void {
		$this->write( self::SUCCESS, $message, $source, $context );
	}

	/**
	 * Something unexpected but non-fatal (deprecated API, near rate-limit…).
	 *
	 * @param string $message Human-readable description.
	 * @param string $source The plugin component writing this entry (e.g. 'sync', 'api').
	 * @param array  $context Key/value pairs surfaced in the admin UI (order IDs, HTTP codes…).
	 */
	public function warning( string $message, string $source = '', array $context = array() ): void {
		$this->write( self::WARNING, $message, $source, $context );
	}

	/**
	 * A failure that requires attention (API error, DB write failed, webhook rejected…).
	 *
	 * @param string $message Human-readable description.
	 * @param string $source The plugin component writing this entry (e.g. 'sync', 'api').
	 * @param array  $context Key/value pairs surfaced in the admin UI (order IDs, HTTP codes…).
	 */
	public function error( string $message, string $source = '', array $context = array() ): void {
		$this->write( self::ERROR, $message, $source, $context );
	}

	/**
	 * Delete all rows beyond MAX_ENTRIES, keeping the most recent ones.
	 *
	 * Called automatically by WP-Cron daily. Safe to call manually at any time.
	 */
	public function cleanup(): void {
		global $wpdb;

		$limit = self::MAX_ENTRIES;

		$wpdb->query(
			"DELETE FROM {$this->table}
			 WHERE id NOT IN (
			     SELECT id FROM (
			         SELECT id FROM {$this->table} ORDER BY id DESC LIMIT {$limit}
			     ) AS keep_ids
			 )"
		);
	}

	/**
	 * Fetch paginated log entries.
	 *
	 * @param array{level?: string, limit?: int, offset?: int} $args Filter arguments.
	 */
	public function get_logs( array $args = array() ): array {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'level'  => '',
				'limit'  => 50,
				'offset' => 0,
			)
		);

		$conditions = '1=1';
		$values     = array();

		if ( ! empty( $args['level'] ) ) {
			$conditions .= ' AND level = %s';
			$values[]    = $args['level'];
		}

		$values[] = (int) $args['limit'];
		$values[] = (int) $args['offset'];

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE {$conditions} ORDER BY id DESC LIMIT %d OFFSET %d",
			...$values
		);

		$results = $wpdb->get_results( $sql ) ?? array();

		foreach ( $results as $row ) {
			$row->context = ! empty( $row->context ) ? json_decode( $row->context, true ) : null;
		}

		return $results;
	}

	/**
	 * Total row count, optionally filtered by level.
	 *
	 * @param string $level Optional level to count.
	 */
	public function count( string $level = '' ): int {
		global $wpdb;

		if ( $level ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE level = %s", $level )
			);
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
	}

	/**
	 * Truncate the entire log table (used by the "Clear all logs" admin action).
	 */
	public function clear(): void {
		global $wpdb;

		$wpdb->query( 'TRUNCATE TABLE ' . $this->table );
	}

	/**
	 * Insert a single log row.
	 *
	 * @param string $level The level of the log.
	 * @param string $message Human-readable description.
	 * @param string $source The plugin component writing this entry (e.g. 'sync', 'api').
	 * @param array  $context Key/value pairs surfaced in the admin UI (order IDs, HTTP codes…).
	 */
	private function write( string $level, string $message, string $source, array $context ): void {
		global $wpdb;

		$entry = array(
			'level'   => $level,
			'source'  => $source,
			'message' => $message,
			'context' => $context,
		);

		$entry = apply_filters( 'fastcloudwp_log_entry', $entry, $level, $source );

		if ( false === $entry ) {
			return;
		}

		$context_for_hash = $entry['context'];
		ksort( $context_for_hash );

		$hash = md5( $entry['level'] . $entry['source'] . $entry['message'] . wp_json_encode( $context_for_hash ) );

		$minutes  = self::DEDUP_MINUTES;
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table}
				 WHERE hash = %s
				   AND created_at >= DATE_SUB( UTC_TIMESTAMP(), INTERVAL %d MINUTE )
				 LIMIT 1",
				$hash,
				$minutes
			)
		);

		if ( $existing ) {
			return;
		}

		$wpdb->insert(
			$this->table,
			array(
				'level'      => $entry['level'],
				'source'     => $entry['source'],
				'message'    => $entry['message'],
				'context'    => ! empty( $entry['context'] ) ? wp_json_encode( $entry['context'] ) : null,
				'hash'       => $hash,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		do_action( 'fastcloudwp_logged', $entry['level'], $entry['message'], $entry['source'], $entry['context'] );
	}
}
