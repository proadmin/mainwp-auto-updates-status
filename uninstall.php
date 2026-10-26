<?php
/**
 * Remove the extension's tables and options.
 *
 * @package   MainWP_Auto_Updates_Status
 * @author    ProAdmin <https://proadminwp.net/>
 * @copyright Copyright (c) 2026 ProAdmin
 * @license   GPL-2.0-or-later
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i, %i', $wpdb->prefix . 'mainwp_aus_items', $wpdb->prefix . 'mainwp_aus_runs' ) );

foreach ( array( 'mainwp_aus_db_version', 'mainwp_aus_imported', 'mainwp_aus_retention_days' ) as $option ) {
	delete_option( $option );
}
