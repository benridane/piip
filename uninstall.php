<?php
/**
 * Uninstall Script
 *
 * Handles cleanup when the plugin is uninstalled.
 *
 * @package    PIIP
 * @since      1.0.0
 * @license    GPL-2.0-or-later
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // Exit if accessed directly.
}

// Load database class.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pii-database.php';

// Delete options.
delete_option( 'piip_settings' );
delete_option( 'piip_db_version' );

// Drop database table.
$piip_database = new PIIP_PII_Database();
$piip_database->drop_table();

// Clear scheduled cron jobs.
$piip_timestamp = wp_next_scheduled( 'piip_cleanup_logs' );
if ( $piip_timestamp ) {
	wp_unschedule_event( $piip_timestamp, 'piip_cleanup_logs' );
}

// Clear any cached data.
wp_cache_flush();
