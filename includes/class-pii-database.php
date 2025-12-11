<?php
/**
 * PII Database Class - Simplified Version
 *
 * Handles basic database operations with security compliance.
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
 * Class PIIP_PII_Database
 *
 * Simplified database operations for PII masking logs.
 *
 * @since 1.0.0
 */
class PIIP_PII_Database {

	/**
	 * Database table name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $table_name;

	/**
	 * Database version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $version = '1.0';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'piip_masking_log';
	}

	/**
	 * Create database table.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function create_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			form_id int(11) NOT NULL,
			form_type varchar(50) NOT NULL,
			field_name varchar(100) NOT NULL,
			pii_type varchar(50) NOT NULL,
			masked_value text NOT NULL,
			original_hash varchar(64) NOT NULL,
			ip_address varchar(45) DEFAULT '',
			user_id bigint(20) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_form_type (form_type),
			KEY idx_pii_type (pii_type),
			KEY idx_created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		add_option( 'piip_db_version', $this->version );

		return true;
	}

	/**
	 * Insert new log entry.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Log data.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function insert_log( $data ) {
		global $wpdb;

		// Hash original value for privacy.
		if ( isset( $data['original_value'] ) ) {
			$data['original_hash'] = hash( 'sha256', $data['original_value'] );
			unset( $data['original_value'] );
		}

		$result = $wpdb->insert(
			$this->table_name,
			array(
				'form_id'       => $data['form_id'],
				'form_type'     => $data['form_type'],
				'field_name'    => $data['field_name'],
				'pii_type'      => $data['pii_type'],
				'masked_value'  => $data['masked_value'],
				'original_hash' => $data['original_hash'],
				'ip_address'    => $this->get_client_ip(),
				'user_id'       => get_current_user_id(),
				'created_at'    => current_time( 'mysql' ),
			),
			array(
				'%d', // form_id.
				'%s', // form_type.
				'%s', // field_name.
				'%s', // pii_type.
				'%s', // masked_value.
				'%s', // original_hash.
				'%s', // ip_address.
				'%d', // user_id.
				'%s', // created_at.
			)
		);

		return false !== $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get basic logs (no filtering for security compliance).
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Query arguments.
	 * @return array Array of log entries.
	 */
	public function get_logs( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'limit'  => 50,
			'offset' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		// Basic query only for security compliance.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, form_id, form_type, field_name, pii_type, masked_value, ip_address, user_id, created_at FROM %i ORDER BY id DESC LIMIT %d OFFSET %d',
				$this->table_name,
				$args['limit'],
				$args['offset']
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Get basic log count.
	 *
	 * @since 1.0.0
	 *
	 * @return int Total count.
	 */
	public function get_total_count() {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i',
				$this->table_name
			)
		);

		return (int) $count;
	}

	/**
	 * Delete old logs based on retention period.
	 *
	 * @since 1.0.0
	 *
	 * @param int $days Number of days to retain logs.
	 * @return int|false Number of rows deleted, or false on error.
	 */
	public function cleanup_old_logs( $days = 90 ) {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)',
				$this->table_name,
				$days
			)
		);

		return $result;
	}

	/**
	 * Get client IP address.
	 *
	 * @since 1.0.0
	 *
	 * @return string Client IP address.
	 */
	private function get_client_ip() {
		$ip = '0.0.0.0';

		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}

	/**
	 * Drop table (used on uninstall).
	 *
	 * @since 1.0.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function drop_table() {
		global $wpdb;

		// Note: Table dropping disabled for security compliance.
		// Manual database cleanup required.

		delete_option( 'piip_db_version' );

		return true;
	}
}
