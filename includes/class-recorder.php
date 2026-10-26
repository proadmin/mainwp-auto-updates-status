<?php
/**
 * Records auto update runs and results as MainWP produces them.
 *
 * MainWP writes each result into the mainwp_updates_report_storage option
 * (one update_option() call per item) and clears that option when the next
 * run starts. Watching the option lets us copy every item, including core
 * updates, the moment it is saved, without patching MainWP.
 *
 * @package   MainWP_Auto_Updates_Status
 * @author    ProAdmin <https://proadmin.com>
 * @copyright Copyright (c) 2026 ProAdmin <https://proadmin.com>
 * @license   GPL-2.0-or-later
 */

namespace ProAdmin\MainWP_AUS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recorder class.
 */
class Recorder {

	const STORAGE_OPTION = 'mainwp_updates_report_storage';
	const RUNNING_OPTION = 'mainwp_automatic_updates_is_running';
	const START_OPTION   = 'mainwp_automatic_updates_start_lasttime';
	const RECENT_OPTION  = 'mainwp_automatic_updates_recent_running';

	/**
	 * MainWP storage keys mapped to our item types.
	 */
	const TYPES = array(
		'core'         => 'core',
		'plugins'      => 'plugin',
		'themes'       => 'theme',
		'translations' => 'translation',
	);

	/**
	 * Singleton instance.
	 *
	 * @var Recorder|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Recorder
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		add_filter( 'pre_update_option_' . self::STORAGE_OPTION, array( $this, 'on_storage_update' ), 10, 2 );
		add_action( 'add_option_' . self::STORAGE_OPTION, array( $this, 'on_storage_add' ), 10, 2 );

		add_action( 'update_option_' . self::RUNNING_OPTION, array( $this, 'on_running_update' ), 10, 2 );
		add_action( 'add_option_' . self::RUNNING_OPTION, array( $this, 'on_running_add' ), 10, 2 );

		// Older MainWP builds without the report storage still fire this action for plugin/theme/translation updates.
		if ( ! class_exists( '\MainWP\Dashboard\MainWP_Updates_Report_Manager' ) ) {
			add_action( 'mainwp_install_update_actions', array( $this, 'on_install_update_action' ), 10, 7 );
		}
	}

	/**
	 * Copy items appended to the storage option.
	 *
	 * @param mixed $value     New value.
	 * @param mixed $old_value Old value.
	 * @return mixed Unchanged new value.
	 */
	public function on_storage_update( $value, $old_value ) {
		$this->record_new_items( $old_value, $value );
		return $value;
	}

	/**
	 * Copy items when the storage option is created for the first time.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 */
	public function on_storage_add( $option, $value ) {
		$this->record_new_items( array(), $value );
	}

	/**
	 * Track run start and finish.
	 *
	 * @param mixed $old_value Old value.
	 * @param mixed $value     New value.
	 */
	public function on_running_update( $old_value, $value ) {
		$was = (int) $old_value;
		$now = (int) $value;
		if ( 0 === $was && 1 === $now ) {
			$this->run_started();
		} elseif ( 1 === $was && 0 === $now ) {
			$this->run_finished();
		}
	}

	/**
	 * Track a run starting when the option is first created.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 */
	public function on_running_add( $option, $value ) {
		if ( 1 === (int) $value ) {
			$this->run_started();
		}
	}

	/**
	 * Fallback recorder for MainWP builds without the report storage.
	 *
	 * @param object $website        Child site.
	 * @param string $action         Install action.
	 * @param array  $data           Result data.
	 * @param string $type           plugin|theme|trans|core.
	 * @param array  $post_data      Unused.
	 * @param bool   $upload         Unused.
	 * @param bool   $is_auto_update Whether this was an automatic update.
	 */
	public function on_install_update_action( $website, $action, $data, $type, $post_data = array(), $upload = false, $is_auto_update = false ) {
		if ( 'updated' !== $action || ! $is_auto_update || empty( $website->id ) || ! is_array( $data ) ) {
			return;
		}
		$map = array(
			'plugin' => 'plugins',
			'theme'  => 'themes',
			'trans'  => 'translations',
		);
		if ( ! isset( $map[ $type ] ) || empty( $data['updated_data'] ) || ! is_array( $data['updated_data'] ) ) {
			return;
		}
		$items = array();
		foreach ( $data['updated_data'] as $item ) {
			if ( is_array( $item ) ) {
				$item['site_id'] = $website->id;
				$items[]         = $item;
			}
		}
		$this->record_new_items( array(), array( $map[ $type ] => $items ) );
	}

	/**
	 * Current run start according to MainWP.
	 *
	 * @return int
	 */
	private function current_run_start() {
		return (int) get_option( self::START_OPTION, 0 );
	}

	/**
	 * Insert items present in $new but not in $old.
	 *
	 * @param mixed    $old      Old storage value.
	 * @param mixed    $new      New storage value.
	 * @param int|null $recorded Recorded timestamp for the items. Defaults to now.
	 */
	private function record_new_items( $old, $new, $recorded = null ) {
		if ( ! is_array( $new ) ) {
			return;
		}
		if ( ! is_array( $old ) ) {
			$old = array();
		}

		$run_start = $this->current_run_start();
		if ( $run_start <= 0 ) {
			return;
		}

		$now     = null === $recorded ? Status::now() : (int) $recorded;
		$created = false;

		foreach ( self::TYPES as $key => $type ) {
			$new_items = isset( $new[ $key ] ) && is_array( $new[ $key ] ) ? array_values( $new[ $key ] ) : array();
			$old_items = isset( $old[ $key ] ) && is_array( $old[ $key ] ) ? array_values( $old[ $key ] ) : array();

			// MainWP only ever appends, or resets to empty; anything shorter is a reset.
			if ( count( $new_items ) <= count( $old_items ) ) {
				continue;
			}

			foreach ( array_slice( $new_items, count( $old_items ) ) as $raw ) {
				$item = self::normalize_item( $raw, $type, $now );
				if ( null === $item ) {
					continue;
				}
				if ( ! $created ) {
					DB::instance()->ensure_run( $run_start );
					$created = true;
				}
				DB::instance()->insert_item( $run_start, $item );
			}
		}
	}

	/**
	 * Normalize a raw MainWP storage item.
	 *
	 * @param mixed  $raw  Raw item.
	 * @param string $type Item type.
	 * @param int    $now  Recorded timestamp.
	 * @return array|null
	 */
	public static function normalize_item( $raw, $type, $now ) {
		if ( ! is_array( $raw ) || empty( $raw['site_id'] ) ) {
			return null;
		}

		$name = isset( $raw['name'] ) ? (string) $raw['name'] : '';
		if ( 'core' === $type && '' === $name ) {
			$name = 'WordPress';
		}

		$error = '';
		if ( ! empty( $raw['error'] ) ) {
			$error = is_scalar( $raw['error'] ) ? (string) $raw['error'] : wp_json_encode( $raw['error'] );
		}

		return array(
			'site_id'     => (int) $raw['site_id'],
			'item_type'   => $type,
			'name'        => self::clip( sanitize_text_field( $name ), 255 ),
			'slug'        => isset( $raw['slug'] ) ? self::clip( sanitize_text_field( (string) $raw['slug'] ), 255 ) : '',
			'old_version' => isset( $raw['old_version'] ) ? self::clip( sanitize_text_field( (string) $raw['old_version'] ), 64 ) : '',
			'new_version' => isset( $raw['version'] ) ? self::clip( sanitize_text_field( (string) $raw['version'] ), 64 ) : '',
			'success'     => ! empty( $raw['success'] ),
			'rolled_back' => ! empty( $raw['rollback'] ),
			'error'       => self::clip( sanitize_textarea_field( wp_strip_all_tags( $error ) ), 2000 ),
			'recorded'    => (int) $now,
		);
	}

	/**
	 * Shorten text to a number of characters without splitting a multibyte character.
	 *
	 * @param string $text   Text.
	 * @param int    $length Max characters.
	 * @return string
	 */
	public static function clip( $text, $length ) {
		return mb_strlen( $text, 'UTF-8' ) > $length ? mb_substr( $text, 0, $length, 'UTF-8' ) : $text;
	}

	/**
	 * A run has started.
	 */
	private function run_started() {
		$run_start = $this->current_run_start();
		if ( $run_start > 0 ) {
			DB::instance()->close_stale_runs( $run_start );
			DB::instance()->ensure_run( $run_start );
		}
	}

	/**
	 * A run has finished, timed out or been stopped.
	 */
	private function run_finished() {
		$run_start = $this->current_run_start();
		if ( $run_start <= 0 ) {
			return;
		}

		$now = Status::now();

		if ( ! Status::any_type_enabled() ) {
			$status = DB::STATUS_STOPPED;
		} elseif ( $now > $run_start + 4 * HOUR_IN_SECONDS ) {
			$status = DB::STATUS_TIMED_OUT;
		} else {
			$status = DB::STATUS_COMPLETED;
		}

		$progress = DB::instance()->get_run_progress( $run_start );

		DB::instance()->update_run(
			$run_start,
			array(
				'run_end'     => $now,
				'status'      => $status,
				'sites_total' => $progress['total'],
			)
		);

		DB::instance()->prune( (int) get_option( 'mainwp_aus_retention_days', 90 ) );

		/**
		 * Fires after a MainWP auto update run has been recorded as finished.
		 *
		 * @param int    $run_start Run start (MainWP local timestamp).
		 * @param string $status    completed|timed_out|stopped.
		 */
		do_action( 'mainwp_aus_run_finished', $run_start, $status );
	}

	/**
	 * Import what MainWP already holds: the latest run's results and the
	 * list of recent start times (start times only, no item details).
	 *
	 * Safe to repeat: runs already recorded are left alone.
	 */
	public function import_existing() {
		$now       = Status::now();
		$run_start = $this->current_run_start();

		$recent = get_option( self::RECENT_OPTION );
		$recent = is_string( $recent ) ? json_decode( $recent, true ) : array();
		if ( is_array( $recent ) ) {
			foreach ( $recent as $started ) {
				// MainWP stores these with gmdate() on a local timestamp, so read them back as UTC.
				$ts = is_string( $started ) ? strtotime( $started . ' UTC' ) : false;
				// The latest entry is logged a moment before the run's own start time; skip it.
				if ( $ts && abs( $ts - $run_start ) > 120 && ! DB::instance()->run_exists_near( $ts ) ) {
					DB::instance()->ensure_run( $ts, DB::STATUS_IMPORTED, false );
				}
			}
		}

		$storage = get_option( self::STORAGE_OPTION, array() );
		if ( $run_start > 0 ) {
			$running = (int) get_option( self::RUNNING_OPTION, 0 );
			DB::instance()->ensure_run( $run_start, $running ? DB::STATUS_RUNNING : DB::STATUS_IMPORTED );
			DB::instance()->update_run( $run_start, array( 'details_recorded' => 1 ) );
			if ( is_array( $storage ) && ! DB::instance()->run_has_items( $run_start ) ) {
				// Exact update times are not kept by MainWP, so use the run start.
				$this->record_new_items( array(), $storage, $run_start );
			}
		}

		DB::instance()->prune( (int) get_option( 'mainwp_aus_retention_days', 90 ) );

		update_option( 'mainwp_aus_imported', $now, true );
	}
}
