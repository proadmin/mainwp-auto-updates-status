<?php
/**
 * Remove the extension's tables and options.
 *
 * @package MainWP_Auto_Updates_Status
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mainwp_aus_items, {$wpdb->prefix}mainwp_aus_runs" );

foreach ( array( 'mainwp_aus_db_version', 'mainwp_aus_imported', 'mainwp_aus_retention_days' ) as $option ) {
	delete_option( $option );
}
