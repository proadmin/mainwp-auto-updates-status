<?php
/**
 * Storage for auto update run history.
 *
 * MainWP only keeps the results of the most recent run, so runs and their
 * per-item results are copied into two tables owned by this extension.
 *
 * @package MainWP_Auto_Updates_Status
 */

namespace ProAdmin\MainWP_AUS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DB access class.
 */
class DB {

	const STATUS_RUNNING   = 'running';
	const STATUS_COMPLETED = 'completed';
	const STATUS_TIMED_OUT = 'timed_out';
	const STATUS_STOPPED   = 'stopped';
	const STATUS_IMPORTED  = 'imported';

	/**
	 * Singleton instance.
	 *
	 * @var DB|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return DB
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Runs table name.
	 *
	 * @return string
	 */
	public function runs_table() {
		global $wpdb;
		return $wpdb->prefix . 'mainwp_aus_runs';
	}

	/**
	 * Items table name.
	 *
	 * @return string
	 */
	public function items_table() {
		global $wpdb;
		return $wpdb->prefix . 'mainwp_aus_items';
	}

	/**
	 * MainWP table name.
	 *
	 * @param string $suffix Table suffix, e.g. "wp" or "wp_sync".
	 * @return string
	 */
	public function mainwp_table( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . 'mainwp_' . $suffix;
	}

	/**
	 * Create or upgrade tables when the stored schema version is behind.
	 */
	public function maybe_upgrade() {
		if ( get_option( 'mainwp_aus_db_version' ) !== MAINWP_AUS_DB_VERSION ) {
			$this->install();
		}
	}

	/**
	 * Create tables.
	 */
	public function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		dbDelta(
			'CREATE TABLE ' . $this->runs_table() . " (
  run_start bigint(20) unsigned NOT NULL,
  run_end bigint(20) unsigned NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'running',
  sites_total int(11) NOT NULL DEFAULT 0,
  details_recorded tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (run_start)
) $charset_collate;"
		);

		dbDelta(
			'CREATE TABLE ' . $this->items_table() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  run_start bigint(20) unsigned NOT NULL DEFAULT 0,
  site_id int(11) NOT NULL DEFAULT 0,
  item_type varchar(20) NOT NULL DEFAULT '',
  name varchar(255) NOT NULL DEFAULT '',
  slug varchar(255) NOT NULL DEFAULT '',
  old_version varchar(64) NOT NULL DEFAULT '',
  new_version varchar(64) NOT NULL DEFAULT '',
  success tinyint(1) NOT NULL DEFAULT 0,
  rolled_back tinyint(1) NOT NULL DEFAULT 0,
  error text NULL,
  recorded bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY run_start (run_start),
  KEY site_id (site_id)
) $charset_collate;"
		);

		update_option( 'mainwp_aus_db_version', MAINWP_AUS_DB_VERSION, false );
	}

	/**
	 * Make sure a run row exists.
	 *
	 * @param int    $run_start Run start (MainWP local timestamp).
	 * @param string $status    Status for a new row.
	 * @param bool   $details   Whether item details are recorded for this run.
	 */
	public function ensure_run( $run_start, $status = self::STATUS_RUNNING, $details = true ) {
		global $wpdb;
		$run_start = (int) $run_start;
		if ( $run_start <= 0 ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . $this->runs_table() . ' (run_start, status, details_recorded) VALUES (%d, %s, %d)', $run_start, $status, $details ? 1 : 0 ) );
	}

	/**
	 * Update a run row.
	 *
	 * @param int   $run_start Run start.
	 * @param array $data      Columns to update.
	 */
	public function update_run( $run_start, $data ) {
		global $wpdb;
		$this->ensure_run( $run_start );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $this->runs_table(), $data, array( 'run_start' => (int) $run_start ) );
	}

	/**
	 * Insert one item result.
	 *
	 * @param int   $run_start Run start.
	 * @param array $item      Normalized item.
	 */
	public function insert_item( $run_start, $item ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->items_table(),
			array(
				'run_start'   => (int) $run_start,
				'site_id'     => (int) $item['site_id'],
				'item_type'   => $item['item_type'],
				'name'        => $item['name'],
				'slug'        => $item['slug'],
				'old_version' => $item['old_version'],
				'new_version' => $item['new_version'],
				'success'     => $item['success'] ? 1 : 0,
				'rolled_back' => $item['rolled_back'] ? 1 : 0,
				'error'       => $item['error'],
				'recorded'    => (int) $item['recorded'],
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d' )
		);
	}

	/**
	 * Whether a run already has items recorded.
	 *
	 * @param int $run_start Run start.
	 * @return bool
	 */
	public function run_has_items( $run_start ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $this->items_table() . ' WHERE run_start = %d LIMIT 1', (int) $run_start ) );
	}

	/**
	 * Get one run.
	 *
	 * @param int $run_start Run start.
	 * @return object|null
	 */
	public function get_run( $run_start ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$run = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->runs_table() . ' WHERE run_start = %d', (int) $run_start ) );
		return $run ? $this->add_run_counts( array( $run ) )[0] : null;
	}

	/**
	 * Get runs, newest first, with item counts attached.
	 *
	 * @param int $limit  Max rows.
	 * @param int $offset Offset.
	 * @return array
	 */
	public function get_runs( $limit = 10, $offset = 0 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$runs = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $this->runs_table() . ' ORDER BY run_start DESC LIMIT %d OFFSET %d', (int) $limit, (int) $offset ) );
		return $this->add_run_counts( is_array( $runs ) ? $runs : array() );
	}

	/**
	 * Total number of runs.
	 *
	 * @return int
	 */
	public function count_runs() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->runs_table() );
	}

	/**
	 * Attach updated/failed/site counts to run rows.
	 *
	 * @param array $runs Run rows.
	 * @return array
	 */
	private function add_run_counts( $runs ) {
		global $wpdb;
		if ( empty( $runs ) ) {
			return $runs;
		}
		$starts       = array_map( 'intval', wp_list_pluck( $runs, 'run_start' ) );
		$placeholders = implode( ',', array_fill( 0, count( $starts ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT run_start, SUM(success = 1) AS updated, SUM(success = 0) AS failed, SUM(rolled_back = 1) AS rolled_back, COUNT(DISTINCT site_id) AS sites_updated FROM ' . $this->items_table() . " WHERE run_start IN ($placeholders) GROUP BY run_start", $starts ), OBJECT_K );
		foreach ( $runs as $run ) {
			$counts             = isset( $rows[ $run->run_start ] ) ? $rows[ $run->run_start ] : null;
			$run->updated       = $counts ? (int) $counts->updated : 0;
			$run->failed        = $counts ? (int) $counts->failed : 0;
			$run->rolled_back   = $counts ? (int) $counts->rolled_back : 0;
			$run->sites_updated = $counts ? (int) $counts->sites_updated : 0;
		}
		return $runs;
	}

	/**
	 * Get items for a run.
	 *
	 * @param int  $run_start   Run start.
	 * @param bool $failed_only Only failed items.
	 * @return array
	 */
	public function get_items( $run_start, $failed_only = false ) {
		global $wpdb;
		$where = $failed_only ? ' AND success = 0' : '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $this->items_table() . ' WHERE run_start = %d' . $where . ' ORDER BY success ASC, site_id ASC, item_type ASC, name ASC', (int) $run_start ) );
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Recent failed items across runs.
	 *
	 * @param int $limit Max rows.
	 * @return array
	 */
	public function get_recent_failures( $limit = 20 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $this->items_table() . ' WHERE success = 0 ORDER BY recorded DESC, id DESC LIMIT %d', (int) $limit ) );
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Progress of a run in MainWP's sync table.
	 *
	 * @param int $run_start Run start.
	 * @return array{total:int,done:int}
	 */
	public function get_run_progress( $run_start ) {
		global $wpdb;
		$sql = 'SELECT COUNT(*) AS total, SUM(s.dtsAutomaticSync >= s.dtsAutomaticSyncStart) AS done FROM ' . $this->mainwp_table( 'wp_sync' ) . ' s JOIN ' . $this->mainwp_table( 'wp' ) . ' w ON w.id = s.wpid WHERE w.suspended = 0 AND s.dtsAutomaticSyncStart = %d';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( $sql, (int) $run_start ) );
		return array(
			'total' => $row ? (int) $row->total : 0,
			'done'  => $row ? (int) $row->done : 0,
		);
	}

	/**
	 * Look up child site names and URLs.
	 *
	 * @param array $site_ids Site IDs.
	 * @return array id => object{id,name,url}
	 */
	public function get_sites( $site_ids ) {
		global $wpdb;
		$site_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $site_ids ) ) ) );
		if ( empty( $site_ids ) ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $site_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, name, url FROM ' . $this->mainwp_table( 'wp' ) . " WHERE id IN ($placeholders)", $site_ids ), OBJECT_K );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete runs and items older than the retention window.
	 *
	 * @param int $days Days to keep. 0 keeps everything.
	 */
	public function prune( $days ) {
		global $wpdb;
		$days = (int) $days;
		if ( $days <= 0 ) {
			return;
		}
		$cutoff = Status::now() - $days * DAY_IN_SECONDS;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $this->items_table() . ' WHERE run_start < %d', $cutoff ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $this->runs_table() . ' WHERE run_start < %d', $cutoff ) );
	}

	/**
	 * Drop tables (uninstall).
	 */
	public function drop() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $this->items_table() . ', ' . $this->runs_table() );
	}
}
