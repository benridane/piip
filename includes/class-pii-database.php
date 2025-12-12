<?php
/**
 * PII Database Class - Simplified Version
 *
 * Handles minimal database operations for PII protection.
 * Removed logging functionality to eliminate DirectQuery warnings.
 *
 * @package    PIIP
 * @subpackage Includes
 * @since      1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PII Database class.
 *
 * Simplified version focused only on essential operations.
 *
 * @since 1.0.0
 */
class PIIP_PII_Database {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// No database table needed - simplified approach.
	}

	/**
	 * Plugin activation - no database setup needed.
	 *
	 * @since 1.0.0
	 */
	public function create_table() {
		// Simplified: No custom table creation.
		return true;
	}

	/**
	 * Log masking activity (simplified - no database storage).
	 *
	 * @since 1.0.0
	 * @param array $data Log data.
	 * @return bool Always true for compatibility.
	 */
	public function insert_log( $data ) {
		// Simplified: Log to WordPress debug.log if WP_DEBUG is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$log_entry = sprintf(
				'[PIIP] PII Masked - Type: %s, Field: %s, Form: %s',
				$data['pii_type'] ?? 'unknown',
				$data['field_name'] ?? 'unknown',
				$data['form_type'] ?? 'unknown'
			);
			// Debug logging for development only.
			if ( function_exists( 'wp_debug_log' ) ) {
				wp_debug_log( $log_entry );
			}
		}

		return true;
	}

	/**
	 * Get logs (simplified - returns empty array).
	 *
	 * @since 1.0.0
	 * @param array $args Query arguments (unused).
	 * @return array Empty array.
	 */
	public function get_logs( $args = array() ) {
		// Simplified: No log storage, return empty array.
		// Note: $args parameter kept for compatibility.
		unset( $args ); // Prevent unused parameter warning.
		return array();
	}

	/**
	 * Get total log count (simplified).
	 *
	 * @since 1.0.0
	 * @return int Always returns 0.
	 */
	public function get_total_count() {
		// Simplified: No log storage, return 0.
		return 0;
	}

	/**
	 * Plugin uninstallation - no database cleanup needed.
	 *
	 * @since 1.0.0
	 */
	public function drop_table() {
		// Simplified: No custom table to drop.
		return true;
	}
}
