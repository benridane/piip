<?php
/**
 * PII Database Class
 *
 * Handles database table creation and management.
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
 * Manages custom database table for PII masking logs.
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
	 * @return void
	 */
	public function create_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL DEFAULT 0,
			form_type varchar(50) NOT NULL DEFAULT '',
			field_name varchar(255) NOT NULL DEFAULT '',
			field_label varchar(255) NOT NULL DEFAULT '',
			pii_type varchar(50) NOT NULL DEFAULT '',
			original_hash varchar(64) NOT NULL DEFAULT '',
			masked_value text NOT NULL,
			masking_method varchar(50) NOT NULL DEFAULT '',
			ip_address varchar(45) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned DEFAULT NULL,
			user_agent varchar(255) NOT NULL DEFAULT '',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY pii_type (pii_type),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Store database version.
		update_option( 'piip_db_version', $this->version );
	}

	/**
	 * Insert log entry.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Log data.
	 * @return int|false The number of rows inserted, or false on error.
	 */
	public function insert_log( $data ) {
		global $wpdb;

		$defaults = array(
			'form_id'        => 0,
			'form_type'      => '',
			'field_name'     => '',
			'field_label'    => '',
			'pii_type'       => '',
			'original_hash'  => '',
			'masked_value'   => '',
			'masking_method' => 'automatic',
			'ip_address'     => $this->get_client_ip(),
			'user_id'        => get_current_user_id(),
			'user_agent'     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
		);

		$data = wp_parse_args( $data, $defaults );

		// Hash the original value if provided.
		if ( isset( $data['original_value'] ) && ! empty( $data['original_value'] ) ) {
			$data['original_hash'] = hash( 'sha256', $data['original_value'] );
			unset( $data['original_value'] );
		}

		$result = $wpdb->insert(
			$this->table_name,
			array(
				'form_id'        => $data['form_id'],
				'form_type'      => $data['form_type'],
				'field_name'     => $data['field_name'],
				'field_label'    => $data['field_label'],
				'pii_type'       => $data['pii_type'],
				'original_hash'  => $data['original_hash'],
				'masked_value'   => $data['masked_value'],
				'masking_method' => $data['masking_method'],
				'ip_address'     => $data['ip_address'],
				'user_id'        => $data['user_id'],
				'user_agent'     => $data['user_agent'],
			),
			array(
				'%d', // form_id.
				'%s', // form_type.
				'%s', // field_name.
				'%s', // field_label.
				'%s', // pii_type.
				'%s', // original_hash.
				'%s', // masked_value.
				'%s', // masking_method.
				'%s', // ip_address.
				'%d', // user_id.
				'%s', // user_agent.
			)
		);

		return $result;
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
		global $wpdb;

		$defaults = array(
			'limit'     => 100,
			'offset'    => 0,
			'orderby'   => 'created_at',
			'order'     => 'DESC',
			'form_id'   => null,
			'form_type' => null,
			'pii_type'  => null,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );

		if ( ! is_null( $args['form_id'] ) ) {
			$where[] = $wpdb->prepare( 'form_id = %d', $args['form_id'] );
		}

		if ( ! is_null( $args['form_type'] ) ) {
			$where[] = $wpdb->prepare( 'form_type = %s', $args['form_type'] );
		}

		if ( ! is_null( $args['pii_type'] ) ) {
			$where[] = $wpdb->prepare( 'pii_type = %s', $args['pii_type'] );
		}

		$where_clause = implode( ' AND ', $where );

		// Whitelist allowed ORDER BY columns for security.
		$allowed_orderby = array( 'id', 'created_at', 'form_id', 'form_type', 'pii_type' );
		$orderby_column  = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order_direction = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$orderby         = "{$orderby_column} {$order_direction}";

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is from constructor, WHERE clause uses prepared statements, ORDER BY is whitelisted.
			$args['limit'],
			$args['offset']
		);

		$results = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare().

		return $results;
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
		global $wpdb;

		$where = array( '1=1' );

		if ( isset( $args['form_id'] ) && ! is_null( $args['form_id'] ) ) {
			$where[] = $wpdb->prepare( 'form_id = %d', $args['form_id'] );
		}

		if ( isset( $args['form_type'] ) && ! is_null( $args['form_type'] ) ) {
			$where[] = $wpdb->prepare( 'form_type = %s', $args['form_type'] );
		}

		if ( isset( $args['pii_type'] ) && ! is_null( $args['pii_type'] ) ) {
			$where[] = $wpdb->prepare( 'pii_type = %s', $args['pii_type'] );
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is from constructor, WHERE clause uses prepared statements.

		$count = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query uses prepared WHERE clause components.

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
				"DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is from constructor, days parameter is prepared.
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
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
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

		// Validate table name format for security.
		$table_suffix = str_replace( $wpdb->prefix, '', $this->table_name );
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table_suffix ) ) {
			return false;
		}

		$result = $wpdb->query( "DROP TABLE IF EXISTS {$this->table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated above.

		delete_option( 'piip_db_version' );

		return false !== $result;
	}
}
