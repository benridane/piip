<?php
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

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class PIIP_Logs_List_Table
 *
 * Extends WP_List_Table to display PII masking logs.
 *
 * @since 1.0.0
 */
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
			'id'           => __( 'ID', 'piip-pii-protection' ),
			'created_at'   => __( 'Date', 'piip-pii-protection' ),
			'form_type'    => __( 'Form Type', 'piip-pii-protection' ),
			'form_id'      => __( 'Form ID', 'piip-pii-protection' ),
			'field_name'   => __( 'Field Name', 'piip-pii-protection' ),
			'pii_type'     => __( 'PII Type', 'piip-pii-protection' ),
			'masked_value' => __( 'Masked Value', 'piip-pii-protection' ),
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
		esc_html_e( 'No PII masking logs found.', 'piip-pii-protection' );
	}
}
