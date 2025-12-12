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

// Delete options.
delete_option( 'piip_settings' );
delete_option( 'piip_db_version' );

// Clear any cached data.
wp_cache_flush();
