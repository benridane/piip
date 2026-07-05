<?php
/**
 * PII Scan Admin Page
 *
 * Tools page that scans existing content for PII and applies masking
 * retroactively in AJAX-driven batches.
 *
 * @package    PIIP
 * @subpackage PIIP/admin
 * @since      1.5.0
 * @license    GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class PIIP_Scan_Page
 *
 * @since 1.5.0
 */
class PIIP_Scan_Page {

	/**
	 * Content scanner instance.
	 *
	 * @since 1.5.0
	 * @var PIIP_Content_Scanner
	 */
	private $scanner;

	/**
	 * Constructor.
	 *
	 * @since 1.5.0
	 *
	 * @param PIIP_Content_Scanner $scanner Content scanner instance.
	 */
	public function __construct( $scanner ) {
		$this->scanner = $scanner;

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Logged-in users only; no wp_ajax_nopriv_ handler on purpose.
		add_action( 'wp_ajax_piip_scan_batch', array( $this, 'handle_scan_batch' ) );
	}

	/**
	 * Register the Tools submenu page.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_management_page(
			__( 'PII Scan', 'piip-pii-protection' ),
			__( 'PII Scan', 'piip-pii-protection' ),
			'manage_options',
			'piip-scan',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue scripts for the scan page.
	 *
	 * @since 1.5.0
	 *
	 * @param string $hook_suffix The current admin page.
	 * @return void
	 */
	public function enqueue_scripts( $hook_suffix ) {
		if ( 'tools_page_piip-scan' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'piip-admin-scan',
			PIIP_PLUGIN_URL . 'admin/js/scan.js',
			array( 'jquery' ),
			PIIP_VERSION,
			true
		);
		wp_localize_script(
			'piip-admin-scan',
			'piipScan',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'piip_scan_batch' ),
				'i18n'    => array(
					'scanning'       => __( 'Scanning…', 'piip-pii-protection' ),
					'applying'       => __( 'Applying masking…', 'piip-pii-protection' ),
					'error'          => __( 'Request failed. Please try again.', 'piip-pii-protection' ),
					'noTargets'      => __( 'Select at least one content type to scan.', 'piip-pii-protection' ),
					'noResults'      => __( 'No PII found in the scanned content.', 'piip-pii-protection' ),
					/* translators: 1: number of items with PII, 2: number of scanned items. */
					'summary'        => __( '%1$s of %2$s scanned items contain PII.', 'piip-pii-protection' ),
					/* translators: %s: number of masked items. */
					'applied'        => __( 'Masking applied to %s items.', 'piip-pii-protection' ),
					'confirmApply'   => __( 'Apply masking to all listed items? Comments are updated in place and cannot be restored. Posts keep a revision where revisions are enabled.', 'piip-pii-protection' ),
					'statusChange'   => __( 'Will be masked', 'piip-pii-protection' ),
					'statusNoChange' => __( 'Detected only', 'piip-pii-protection' ),
					'statusConsent'  => __( 'Consent phrase (skipped)', 'piip-pii-protection' ),
					'statusApplied'  => __( 'Masked', 'piip-pii-protection' ),
					'statusFailed'   => __( 'Update failed', 'piip-pii-protection' ),
					'edit'           => __( 'Edit', 'piip-pii-protection' ),
				),
			)
		);
	}

	/**
	 * Render the scan page.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'piip-pii-protection' ) );
		}

		$post_types = PIIP_Content_Scanner::get_scannable_post_types();

		require plugin_dir_path( __FILE__ ) . 'views/scan-page.php';
	}

	/**
	 * Handle one scan/apply batch via AJAX.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public function handle_scan_batch() {
		check_ajax_referer( 'piip_scan_batch', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You are not allowed to run the PII scan.', 'piip-pii-protection' ) ),
				403
			);
		}

		$target = isset( $_POST['target'] ) ? sanitize_key( wp_unslash( $_POST['target'] ) ) : '';
		$offset = isset( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0;
		$apply  = ! empty( $_POST['apply'] );

		$valid_targets = array_merge(
			array( 'comments' ),
			array_keys( PIIP_Content_Scanner::get_scannable_post_types() )
		);
		if ( ! in_array( $target, $valid_targets, true ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid scan target.', 'piip-pii-protection' ) ),
				400
			);
		}

		$limit = PIIP_Content_Scanner::BATCH_SIZE;

		if ( 'comments' === $target ) {
			$total = $this->scanner->count_items( 'comments' );
			$batch = $this->scanner->scan_comments_batch( $offset, $limit, $apply );
		} else {
			$total = $this->scanner->count_items( 'posts', $target );
			$batch = $this->scanner->scan_posts_batch( $target, $offset, $limit, $apply );
		}

		wp_send_json_success(
			array(
				'target'    => $target,
				'total'     => $total,
				'processed' => $batch['processed'],
				'items'     => $batch['items'],
				'done'      => $batch['processed'] < $limit,
			)
		);
	}
}
