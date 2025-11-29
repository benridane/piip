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

		// Also log critical PII types to error log (only in debug mode).
		if ( in_array( $args['pii_type'], array( 'card', 'ssn', 'password', 'token' ), true ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional logging for critical PII events in debug mode only.
					sprintf(
						'PIIP: Masked %s in form %d (type: %s, field: %s)',
						$args['pii_type'],
						$args['form_id'],
						$args['form_type'],
						$args['field_name']
					)
				);
			}
			// Fire action hook for custom logging implementations.
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

		// CSV headers.
		$csv_output = "ID,Date,Form ID,Form Type,Field Name,PII Type,Masked Value,IP Address,User ID\n";

		// CSV rows.
		foreach ( $logs as $log ) {
			$csv_output .= sprintf(
				"%d,%s,%d,%s,%s,%s,%s,%s,%s\n",
				$log->id,
				$log->created_at,
				$log->form_id,
				$log->form_type,
				$log->field_name,
				$log->pii_type,
				str_replace( ',', ';', $log->masked_value ),
				$log->ip_address,
				$log->user_id ? $log->user_id : 'N/A'
			);
		}

		return $csv_output;
	}

	/**
	 * Get statistics for dashboard.
	 *
	 * @since 1.0.0
	 *
	 * @return array Statistics data.
	 */
	public function get_statistics() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'piip_masking_log';

		// Total masked entries.
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is constructed from prefix, no user input.

		// Count by PII type.
		$by_type = $wpdb->get_results(
			"SELECT pii_type, COUNT(*) as count FROM {$table_name} GROUP BY pii_type ORDER BY count DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is constructed from prefix, no user input.
			ARRAY_A
		);

		// Count by form type.
		$by_form = $wpdb->get_results(
			"SELECT form_type, COUNT(*) as count FROM {$table_name} GROUP BY form_type ORDER BY count DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is constructed from prefix, no user input.
			ARRAY_A
		);

		// Recent activity (last 30 days).
		$recent = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_name} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is constructed from prefix, no user input.
		);

		return array(
			'total'         => (int) $total,
			'by_type'       => $by_type,
			'by_form'       => $by_form,
			'recent_30days' => (int) $recent,
		);
	}
}
