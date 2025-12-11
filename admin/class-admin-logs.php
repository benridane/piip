<?php
/**
 * Admin Logs Page
 *
 * Handles plugin logs page in WordPress admin.
 *
 * @package    PIIP
 * @subpackage PIIP/admin
 * @since      1.0.0
 * @license    GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Load WP_List_Table if not loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class PIIP_Admin_Logs
 *
 * Manages plugin logs page and list table.
 *
 * @since 1.0.0
 */
class PIIP_Admin_Logs {

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var PIIP_PII_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param PIIP_PII_Logger $logger Logger instance.
	 */
	public function __construct( $logger ) {
		$this->logger = $logger;

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_post_piip_export_logs', array( $this, 'export_logs' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_management_page(
			__( 'PII Masking Logs', 'piip-pii-protection' ),
			__( 'PII Masking Logs', 'piip-pii-protection' ),
			'manage_options',
			'piip-logs',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Render logs page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'piip-pii-protection' ) );
		}

		$list_table = new PIIP_Logs_List_Table( $this->logger );
		$list_table->prepare_items();

		require_once plugin_dir_path( __FILE__ ) . 'views/logs-page.php';
	}

	/**
	 * Export logs to CSV.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function export_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'piip-pii-protection' ) );
		}

		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'piip_export_logs' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'piip-pii-protection' ) );
		}

		$csv_content = $this->logger->export_to_csv();

		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="piip-logs-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Output CSV content safely.
		wp_die( wp_kses( $csv_content, array() ), '', array( 'response' => 200 ) );
	}
}

// Load the list table class.
require_once PIIP_PLUGIN_DIR . 'admin/class-logs-list-table.php';
