<?php
/**
 * Reads the current Advanced Automatic Updates state from MainWP.
 *
 * @package   MainWP_Auto_Updates_Status
 * @author    ProAdmin <https://proadminwp.net/>
 * @copyright Copyright (c) 2026 ProAdmin
 * @license   GPL-2.0-or-later
 */

namespace ProAdmin\MainWP_AUS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Status helper.
 */
class Status {

	/**
	 * MainWP "local" timestamp (time() shifted by the site's GMT offset).
	 * All of MainWP's auto update timestamps use this clock.
	 *
	 * @return int
	 */
	public static function now() {
		if ( class_exists( '\MainWP\Dashboard\MainWP_Utility' ) ) {
			return (int) \MainWP\Dashboard\MainWP_Utility::get_timestamp();
		}
		return (int) ( time() + (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
	}

	/**
	 * Which update types are set to "Install Trusted Updates".
	 *
	 * @return array<string,bool>
	 */
	public static function enabled_types() {
		return array(
			'core'        => 1 === (int) get_option( 'mainwp_automaticDailyUpdate' ),
			'plugin'      => 1 === (int) get_option( 'mainwp_pluginAutomaticDailyUpdate' ),
			'theme'       => 1 === (int) get_option( 'mainwp_themeAutomaticDailyUpdate' ),
			'translation' => 1 === (int) get_option( 'mainwp_transAutomaticDailyUpdate' ),
		);
	}

	/**
	 * Mirrors MainWP's own check: auto updates run when core, plugins or themes are enabled.
	 *
	 * @return bool
	 */
	public static function any_type_enabled() {
		$types = self::enabled_types();
		return $types['core'] || $types['plugin'] || $types['theme'];
	}

	/**
	 * Human readable schedule.
	 *
	 * @return string
	 */
	public static function schedule_label() {
		$frequency = get_option( 'mainwp_frequency_AutoUpdate', 'daily' );
		$time      = (string) get_option( 'mainwp_time_AutoUpdate', '00:00' );
		if ( '' === $time ) {
			$time = '00:00';
		}

		$parts = array_map( 'intval', explode( ':', $time ) );
		$time  = date_i18n( get_option( 'time_format' ), gmmktime( $parts[0], isset( $parts[1] ) ? $parts[1] : 0, 0, 1, 1, 2000 ) );

		if ( 'weekly' === $frequency ) {
			$days = array(
				__( 'Monday', 'mainwp-auto-updates-status' ),
				__( 'Tuesday', 'mainwp-auto-updates-status' ),
				__( 'Wednesday', 'mainwp-auto-updates-status' ),
				__( 'Thursday', 'mainwp-auto-updates-status' ),
				__( 'Friday', 'mainwp-auto-updates-status' ),
				__( 'Saturday', 'mainwp-auto-updates-status' ),
				__( 'Sunday', 'mainwp-auto-updates-status' ),
			);
			$day  = (int) get_option( 'mainwp_dayinweek_AutoUpdate', 0 );
			/* translators: 1: weekday, 2: time */
			return sprintf( __( 'Weekly on %1$s at %2$s', 'mainwp-auto-updates-status' ), isset( $days[ $day ] ) ? $days[ $day ] : $days[0], $time );
		}

		if ( 'monthly' === $frequency ) {
			/* translators: 1: day of month, 2: time */
			return sprintf( __( 'Monthly on day %1$d at %2$s', 'mainwp-auto-updates-status' ), (int) get_option( 'mainwp_dayinmonth_AutoUpdate', 1 ), $time );
		}

		/* translators: %s: time */
		return sprintf( __( 'Daily at %s', 'mainwp-auto-updates-status' ), $time );
	}

	/**
	 * Number of trusted plugins and themes for the current user.
	 *
	 * @return array{plugins:int,themes:int}|null
	 */
	public static function trusted_counts() {
		if ( ! class_exists( '\MainWP\Dashboard\MainWP_DB_Common' ) ) {
			return null;
		}
		$ext = \MainWP\Dashboard\MainWP_DB_Common::instance()->get_user_extension_by_user_id();
		if ( empty( $ext ) ) {
			return null;
		}
		$plugins = ! empty( $ext->trusted_plugins ) ? json_decode( $ext->trusted_plugins, true ) : array();
		$themes  = ! empty( $ext->trusted_themes ) ? json_decode( $ext->trusted_themes, true ) : array();
		return array(
			'plugins' => is_array( $plugins ) ? count( $plugins ) : 0,
			'themes'  => is_array( $themes ) ? count( $themes ) : 0,
		);
	}

	/**
	 * Close recorded runs that MainWP is no longer running. A run whose finish
	 * was missed would otherwise show as running forever.
	 */
	public static function close_stale_runs() {
		$running = 1 === (int) get_option( 'mainwp_automatic_updates_is_running', 0 );
		DB::instance()->close_stale_runs( $running ? (int) get_option( 'mainwp_automatic_updates_start_lasttime', 0 ) : 0 );
	}

	/**
	 * Everything the widget needs in one array.
	 *
	 * @param array|null $runs Newest runs, if the caller already has them.
	 * @return array
	 */
	public static function get( $runs = null ) {
		$now       = self::now();
		$running   = 1 === (int) get_option( 'mainwp_automatic_updates_is_running', 0 );
		$run_start = (int) get_option( 'mainwp_automatic_updates_start_lasttime', 0 );
		$next_run  = (int) get_option( 'mainwp_automatic_update_next_run_timestamp', 0 );
		$enabled   = self::any_type_enabled();
		$progress  = $running && $run_start ? DB::instance()->get_run_progress( $run_start ) : null;

		if ( null === $runs ) {
			self::close_stale_runs();
			$runs = DB::instance()->get_runs( 10 );
		}

		$latest_run = null;
		foreach ( $runs as $run ) {
			// The card shows the last finished run; a run in progress is covered by the Status card.
			if ( DB::STATUS_RUNNING !== $run->status ) {
				$latest_run = $run;
				break;
			}
		}

		if ( $running ) {
			$state = 'running';
		} elseif ( ! $enabled ) {
			$state = 'disabled';
		} elseif ( $next_run && $next_run < $now - HOUR_IN_SECONDS ) {
			// The run time passed over an hour ago and nothing started: WP-Cron is probably not firing.
			$state = 'overdue';
		} else {
			$state = 'scheduled';
		}

		return array(
			'now'         => $now,
			'state'       => $state,
			'enabled'     => $enabled,
			'types'       => self::enabled_types(),
			'schedule'    => self::schedule_label(),
			'delay_days'  => (int) get_option( 'mainwp_delay_autoupdate', 1 ),
			'next_run'    => $enabled ? $next_run : 0,
			'run_start'   => $run_start,
			'progress'    => $progress,
			'latest_run'  => $latest_run,
			'trusted'     => self::trusted_counts(),
		);
	}

	/**
	 * Format a MainWP local timestamp.
	 *
	 * @param int $ts Local timestamp.
	 * @return string
	 */
	public static function format( $ts ) {
		if ( empty( $ts ) ) {
			return '—';
		}
		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $ts );
	}

	/**
	 * "3 hours ago" / "in 5 hours" for a MainWP local timestamp.
	 *
	 * @param int $ts Local timestamp.
	 * @return string
	 */
	public static function relative( $ts ) {
		if ( empty( $ts ) ) {
			return '';
		}
		$now  = self::now();
		$diff = human_time_diff( (int) $ts, $now );
		/* translators: %s: human readable time difference */
		return $ts > $now ? sprintf( __( 'in %s', 'mainwp-auto-updates-status' ), $diff ) : sprintf( __( '%s ago', 'mainwp-auto-updates-status' ), $diff );
	}

	/**
	 * Duration between two timestamps.
	 *
	 * @param int $start Start.
	 * @param int $end   End.
	 * @return string
	 */
	public static function duration( $start, $end ) {
		if ( empty( $start ) || empty( $end ) || $end < $start ) {
			return '—';
		}
		$secs = $end - $start;
		if ( $secs < MINUTE_IN_SECONDS ) {
			/* translators: %d: seconds */
			return sprintf( _n( '%d sec', '%d secs', $secs, 'mainwp-auto-updates-status' ), $secs );
		}
		return human_time_diff( $start, $end );
	}

	/**
	 * Label for an item type.
	 *
	 * @param string $type Item type.
	 * @return string
	 */
	public static function type_label( $type ) {
		$labels = array(
			'core'        => __( 'Core', 'mainwp-auto-updates-status' ),
			'plugin'      => __( 'Plugin', 'mainwp-auto-updates-status' ),
			'theme'       => __( 'Theme', 'mainwp-auto-updates-status' ),
			'translation' => __( 'Translation', 'mainwp-auto-updates-status' ),
		);
		return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
	}

	/**
	 * Label and colour for a run status.
	 *
	 * @param object $run Run row with counts.
	 * @return array{0:string,1:string}
	 */
	public static function run_badge( $run ) {
		switch ( $run->status ) {
			case DB::STATUS_RUNNING:
				return array( __( 'Running', 'mainwp-auto-updates-status' ), 'blue' );
			case DB::STATUS_TIMED_OUT:
				return array( __( 'Timed out', 'mainwp-auto-updates-status' ), 'orange' );
			case DB::STATUS_STOPPED:
				return array( __( 'Stopped', 'mainwp-auto-updates-status' ), 'grey' );
			case DB::STATUS_IMPORTED:
				if ( empty( $run->details_recorded ) ) {
					return array( __( 'No details', 'mainwp-auto-updates-status' ), 'grey' );
				}
				break;
		}
		if ( $run->failed > 0 ) {
			return array( __( 'Issues', 'mainwp-auto-updates-status' ), 'red' );
		}
		if ( 0 === (int) $run->updated ) {
			return array( __( 'Nothing to update', 'mainwp-auto-updates-status' ), 'grey' );
		}
		return array( __( 'Success', 'mainwp-auto-updates-status' ), 'green' );
	}
}
