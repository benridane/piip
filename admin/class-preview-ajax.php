<?php
/**
 * Masking Preview AJAX Handler
 *
 * Handles AJAX requests for the masking preview tool on the settings page.
 *
 * @package    PIIP
 * @subpackage PIIP/admin
 * @since      1.4.1
 * @license    GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class PIIP_Preview_Ajax
 *
 * Runs user-supplied sample input through the real masking pipeline and
 * returns the masked result with a breakdown of detected PII.
 *
 * @since 1.4.1
 */
class PIIP_Preview_Ajax {

	/**
	 * Maximum input length accepted for preview.
	 *
	 * @since 1.4.1
	 * @var int
	 */
	const MAX_INPUT_LENGTH = 10000;

	/**
	 * PII Masker instance.
	 *
	 * @since 1.4.1
	 * @var PIIP_PII_Masker
	 */
	private $masker;

	/**
	 * PII Detector instance.
	 *
	 * @since 1.4.1
	 * @var PIIP_PII_Detector
	 */
	private $detector;

	/**
	 * Constructor.
	 *
	 * @since 1.4.1
	 *
	 * @param PIIP_PII_Masker   $masker   PII masker instance.
	 * @param PIIP_PII_Detector $detector PII detector instance.
	 */
	public function __construct( $masker, $detector ) {
		$this->masker   = $masker;
		$this->detector = $detector;

		// Logged-in users only; no wp_ajax_nopriv_ handler on purpose.
		add_action( 'wp_ajax_piip_preview_mask', array( $this, 'handle_preview' ) );
	}

	/**
	 * Handle the preview AJAX request.
	 *
	 * @since 1.4.1
	 *
	 * @return void
	 */
	public function handle_preview() {
		check_ajax_referer( 'piip_preview_mask', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You are not allowed to use the masking preview.', 'piip-pii-protection' ) ),
				403
			);
		}

		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'text';
		if ( ! in_array( $mode, array( 'text', 'field' ), true ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid preview mode.', 'piip-pii-protection' ) ),
				400
			);
		}

		// sanitize_textarea_field keeps line breaks, which mask_text() needs.
		$text = isset( $_POST['text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) : '';

		if ( mb_strlen( $text ) > self::MAX_INPUT_LENGTH ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: maximum number of characters. */
						__( 'Preview input is limited to %d characters.', 'piip-pii-protection' ),
						self::MAX_INPUT_LENGTH
					),
				),
				400
			);
		}

		if ( 'field' === $mode ) {
			$field_name = isset( $_POST['field_name'] ) ? sanitize_text_field( wp_unslash( $_POST['field_name'] ) ) : '';
			wp_send_json_success( $this->preview_field( $field_name, $text ) );
		}

		wp_send_json_success( $this->preview_text( $text ) );
	}

	/**
	 * Build the preview result for free-text mode.
	 *
	 * Mirrors the integration path (PIIP_Base_Integration::mask_content):
	 * consent phrase check first, then text masking through the full hook
	 * pipeline via mask_text_simple().
	 *
	 * @since 1.4.1
	 *
	 * @param string $text Sample text to mask.
	 * @return array Preview result data.
	 */
	private function preview_text( $text ) {
		$consent_bypassed = $this->masker->has_consent_phrase( $text );
		$masked           = $consent_bypassed ? $text : $this->masker->mask_text_simple( $text );

		$detected = array();
		$seen     = array();
		foreach ( $this->detector->find_all_pii( $text ) as $item ) {
			// Multiple detector patterns can match the same value; list it once.
			$key = $item['type'] . ':' . $item['value'];
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;

			$detected[] = array(
				'type'       => $item['type'],
				'value'      => $item['value'],
				'provider'   => isset( $item['provider'] ) ? $item['provider'] : '',
				'confidence' => $this->detector->get_confidence( $item['type'], $item['value'] ),
				'enabled'    => $this->is_type_enabled( $item['type'] ),
				// URLs are detected but mask_text() has no URL masking branch.
				'maskable'   => 'url' !== $item['type'],
				// Whether this value actually disappeared from the output.
				'was_masked' => false === strpos( $masked, $item['value'] ),
			);
		}

		return array(
			'masked'           => $masked,
			'consent_bypassed' => $consent_bypassed,
			'detected'         => $detected,
			'masking_enabled'  => $this->is_masking_enabled(),
		);
	}

	/**
	 * Build the preview result for field name + value mode.
	 *
	 * @since 1.4.1
	 *
	 * @param string $field_name Sample field name.
	 * @param string $value      Sample field value.
	 * @return array Preview result data.
	 */
	private function preview_field( $field_name, $value ) {
		$masked   = $this->masker->mask_value( $field_name, $value );
		$pii_type = $this->detector->detect_pii_type( $field_name, $value );

		$detected = array();
		if ( null !== $pii_type ) {
			$detected[] = array(
				'type'       => $pii_type,
				'value'      => $value,
				'provider'   => '',
				'confidence' => $this->detector->get_confidence( $pii_type, $value, $field_name ),
				'enabled'    => $this->is_type_enabled( $pii_type ),
				'maskable'   => true,
				'was_masked' => $masked !== $value,
			);
		}

		return array(
			'masked'           => $masked,
			'consent_bypassed' => ( null !== $pii_type ) && $this->masker->has_consent_phrase( $value ),
			'detected'         => $detected,
			'masking_enabled'  => $this->is_masking_enabled(),
		);
	}

	/**
	 * Check whether a PII type is enabled in the saved settings.
	 *
	 * @since 1.4.1
	 * @since 1.6.0 Delegates to PIIP_PII_Masker::should_mask_type() so the
	 *              per-type defaults (including opt-in types) cannot drift.
	 *
	 * @param string $pii_type The PII type.
	 * @return bool True if the type will be masked.
	 */
	private function is_type_enabled( $pii_type ) {
		return $this->masker->should_mask_type( $pii_type );
	}

	/**
	 * Check whether masking is enabled globally.
	 *
	 * @since 1.4.1
	 *
	 * @return bool True if global masking is on.
	 */
	private function is_masking_enabled() {
		$settings = get_option( 'piip_settings', array() );
		return ! empty( $settings['enable_masking'] );
	}
}
