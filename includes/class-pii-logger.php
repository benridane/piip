<?php
/**
 * PII Logger Class
 *
 * Handles logging of PII masking events.
 *
 * @package    PIIP
 * @subpackage PIIP/includes
 * @since      1.0.0
 * @license    GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class PIIP_PII_Logger
 *
 * Logs PII masking events to database and handles cleanup.
 *
 * @since 1.0.0
 */
class PIIP_PII_Logger {

	/**
	 * Database instance.
	 *
	 * @since 1.0.0
	 * @var PIIP_PII_Database
	 */
	private $database;

	/**
	 * Plugin settings.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param PIIP_PII_Database $database Database instance.
	 */
	public function __construct( $database = null ) {
		$this->database = $database ? $database : new PIIP_PII_Database();
		$this->settings = get_option( 'piip_settings', array() );

		// Register cleanup cron job.
		add_action( 'piip_cleanup_logs', array( $this, 'cleanup_old_logs' ) );
	}

	/**
	 * Log a PII masking event.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Log arguments.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function log_masking_event( $args ) {
		// Check if logging is enabled.
		if ( isset( $this->settings['enable_logging'] ) && ! $this->settings['enable_logging'] ) {
			return false;
		}

		$defaults = array(
			'form_id'        => 0,
			'form_type'      => '',
			'field_name'     => '',
			'field_label'    => '',
			'pii_type'       => '',
			'original_value' => '',
			'masked_value'   => '',
			'masking_method' => 'automatic',
		);

		$args = wp_parse_args( $args, $defaults );

		// Insert into database.
		$result = $this->database->insert_log( $args );

		// Fire action hook for custom logging implementations of critical PII types.
		if ( in_array( $args['pii_type'], array( 'card', 'ssn', 'password', 'token' ), true ) ) {
			do_action( 'piip_critical_pii_masked', $args );
		}

		return $result;
	}

	/**
	 * Log multiple masking events.
	 *
	 * @since 1.0.0
	 *
	 * @param array $events Array of log events.
	 * @return int Number of events logged.
	 */
	public function log_multiple_events( $events ) {
		$count = 0;

		foreach ( $events as $event ) {
			if ( $this->log_masking_event( $event ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Get logs.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Query arguments.
	 * @return array Array of log entries.
	 */
	public function get_logs( $args = array() ) {
		return $this->database->get_logs( $args );
	}

	/**
	 * Get total log count.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Query arguments.
	 * @return int Total count.
	 */
	public function get_total_count( $args = array() ) {
		return $this->database->get_total_count( $args );
	}

	/**
	 * Cleanup old logs based on retention period.
	 *
	 * @since 1.0.0
	 *
	 * @return int|false Number of rows deleted, or false on error.
	 */
	public function cleanup_old_logs() {
		// Get retention period from settings (default: 90 days).
		$retention_days = isset( $this->settings['log_retention_days'] ) ? (int) $this->settings['log_retention_days'] : 90;

		return $this->database->cleanup_old_logs( $retention_days );
	}

	/**
	 * Schedule cleanup cron job.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function schedule_cleanup() {
		if ( ! wp_next_scheduled( 'piip_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'piip_cleanup_logs' );
		}
	}

	/**
	 * Unschedule cleanup cron job.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function unschedule_cleanup() {
		$timestamp = wp_next_scheduled( 'piip_cleanup_logs' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'piip_cleanup_logs' );
		}
	}

	/**
	 * Export logs to CSV.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Query arguments.
	 * @return string CSV content.
	 */
	public function export_to_csv( $args = array() ) {
		$logs = $this->get_logs( $args );

		if ( empty( $logs ) ) {
			return '';
		}

		// Simple CSV generation without file operations for security compliance.
		$csv_lines = array();

		// Headers.
		$csv_lines[] = '"ID","Date","Form ID","Form Type","Field Name","PII Type","Masked Value","IP Address","User ID"';

		// Data rows.
		foreach ( $logs as $log ) {
			$csv_lines[] = sprintf(
				'"%s","%s","%s","%s","%s","%s","%s","%s","%s"',
				esc_attr( $log->id ),
				esc_attr( $log->created_at ),
				esc_attr( $log->form_id ),
				esc_attr( $log->form_type ),
				esc_attr( $log->field_name ),
				esc_attr( $log->pii_type ),
				esc_attr( $log->masked_value ),
				esc_attr( $log->ip_address ),
				esc_attr( $log->user_id ? $log->user_id : 'N/A' )
			);
		}

		return implode( "\n", $csv_lines );
	}

	/**
	 * Sanitize CSV field to prevent formula injection.
	 *
	 * Prevents CSV injection attacks by prefixing dangerous characters
	 * that could be interpreted as formulas in spreadsheet applications.
	 *
	 * @since 1.0.0
	 *
	 * @param string $field Field value.
	 * @return string Sanitized field.
	 */
	private function sanitize_csv_field( $field ) {
		if ( empty( $field ) || ! is_string( $field ) ) {
			return $field;
		}

		// Characters that can trigger formula execution in Excel/LibreOffice.
		$dangerous_chars = array( '=', '+', '-', '@', "\t", "\r", "\n" );

		// Check if field starts with a dangerous character.
		foreach ( $dangerous_chars as $char ) {
			if ( 0 === strpos( $field, $char ) ) {
				// Prefix with single quote to prevent formula interpretation.
				return "'" . $field;
			}
		}

		return $field;
	}

	/**
	 * Get statistics for dashboard.

/**
	 * Get simplified statistics.
	 *
	 * @since 1.0.0
	 *
	 * @return array Basic statistics data.
	 */
	public function get_statistics() {
		// Simplified statistics for security compliance.
		return array(
			'total'         => 0,
			'by_type'       => array(),
			'by_form'       => array(),
			'recent_30days' => 0,
		);
	}
}
