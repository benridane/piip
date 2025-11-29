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
			__( 'PII Masking Logs', 'piip' ),
			__( 'PII Masking Logs', 'piip' ),
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
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'piip' ) );
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
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'piip' ) );
		}

		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'piip_export_logs' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'piip' ) );
		}

		$csv_content = $this->logger->export_to_csv();

		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="piip-logs-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo $csv_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		exit;
	}
}

/**
 * Class PIIP_Logs_List_Table
 *
 * Custom WP_List_Table for PII masking logs.
 *
 * @since 1.0.0
 *
 * @package    PIIP
 * @subpackage PIIP/admin
 */
// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
class PIIP_Logs_List_Table extends WP_List_Table {

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

		parent::__construct(
			array(
				'singular' => 'log',
				'plural'   => 'logs',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get columns.
	 *
	 * @since 1.0.0
	 *
	 * @return array Columns.
	 */
	public function get_columns() {
		return array(
			'id'           => __( 'ID', 'piip' ),
			'created_at'   => __( 'Date', 'piip' ),
			'form_type'    => __( 'Form Type', 'piip' ),
			'form_id'      => __( 'Form ID', 'piip' ),
			'field_name'   => __( 'Field Name', 'piip' ),
			'pii_type'     => __( 'PII Type', 'piip' ),
			'masked_value' => __( 'Masked Value', 'piip' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @since 1.0.0
	 *
	 * @return array Sortable columns.
	 */
	public function get_sortable_columns() {
		return array(
			'id'         => array( 'id', true ),
			'created_at' => array( 'created_at', true ),
			'form_type'  => array( 'form_type', false ),
			'pii_type'   => array( 'pii_type', false ),
		);
	}

	/**
	 * Prepare items.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function prepare_items() {
		$per_page = 20;
		$paged    = $this->get_pagenum();

		$args = array(
			'limit'  => $per_page,
			'offset' => ( $paged - 1 ) * $per_page,
		);

		// Get orderby and order.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading orderby/order is standard for WP_List_Table.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
		$order   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$args['orderby'] = $orderby;
		$args['order']   = $order;

		$this->items = $this->logger->get_logs( $args );
		$total_items = $this->logger->get_total_count();

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	/**
	 * Default column output.
	 *
	 * @since 1.0.0
	 *
	 * @param object $item        Log item.
	 * @param string $column_name Column name.
	 * @return string Column output.
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
			case 'form_id':
			case 'field_name':
			case 'masked_value':
				return esc_html( $item->$column_name );

			case 'created_at':
				return esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item->created_at ) );

			case 'form_type':
				return esc_html( ucwords( str_replace( '_', ' ', $item->form_type ) ) );

			case 'pii_type':
				return '<span class="piip-pii-type piip-pii-type-' . esc_attr( $item->pii_type ) . '">' . esc_html( ucfirst( $item->pii_type ) ) . '</span>';

			default:
				return esc_html( $item->$column_name );
		}
	}

	/**
	 * Display no items message.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No PII masking logs found.', 'piip' );
	}
}
