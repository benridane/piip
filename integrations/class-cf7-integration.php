<?php
/**
 * Contact Form 7 Integration
 *
 * Integrates PII masking with Contact Form 7 submissions.
 *
 * @package    PIIP
 * @subpackage PIIP/integrations
 * @since      1.5.0
 * @license    GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PIIP_CF7_Integration
 *
 * Masks PII in Contact Form 7 submissions via the wpcf7_posted_data
 * filter, which runs before mail composition and before storage add-ons
 * such as Flamingo read the data. Both the sent mail and the stored
 * copy therefore receive the masked values.
 *
 * Contact forms intentionally collect PII in dedicated fields (name,
 * email), so by default only free-text fields (textarea) are masked:
 * that is where accidental PII such as card numbers, credentials, or
 * third-party contact details ends up.
 *
 * @since 1.5.0
 */
class PIIP_CF7_Integration extends PIIP_Base_Integration {

	/**
	 * Integration slug.
	 *
	 * @since 1.5.0
	 * @var string
	 */
	protected $slug = 'cf7';

	/**
	 * Integration name.
	 *
	 * @since 1.5.0
	 * @var string
	 */
	protected $name = 'Contact Form 7';

	/**
	 * Initialize hooks for Contact Form 7.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	protected function init_hooks() {
		add_filter( 'wpcf7_posted_data', array( $this, 'mask_posted_data' ), 10, 1 );
	}

	/**
	 * Check if Contact Form 7 is active.
	 *
	 * @since 1.5.0
	 *
	 * @return bool True if Contact Form 7 is active.
	 */
	public static function is_plugin_active() {
		return class_exists( 'WPCF7' );
	}

	/**
	 * Mask PII in submitted form data.
	 *
	 * @since 1.5.0
	 *
	 * @param array $posted_data Submitted form data.
	 * @return array Masked form data.
	 */
	public function mask_posted_data( $posted_data ) {
		if ( ! is_array( $posted_data ) ) {
			return $posted_data;
		}

		$basetypes = $this->get_field_basetypes();

		foreach ( $posted_data as $field_name => $value ) {
			// Keys starting with an underscore are CF7 internals.
			if ( 0 === strpos( $field_name, '_' ) ) {
				continue;
			}

			$basetype = isset( $basetypes[ $field_name ] ) ? $basetypes[ $field_name ] : '';

			if ( ! $this->should_mask_field( $field_name, $basetype ) ) {
				continue;
			}

			if ( is_string( $value ) ) {
				$posted_data[ $field_name ] = $this->mask_content( $value, 'cf7_' . $field_name );
			} elseif ( is_array( $value ) ) {
				foreach ( $value as $index => $single ) {
					if ( is_string( $single ) ) {
						$value[ $index ] = $this->mask_content( $single, 'cf7_' . $field_name );
					}
				}
				$posted_data[ $field_name ] = $value;
			}
		}

		return $posted_data;
	}

	/**
	 * Decide whether a submitted field should be masked.
	 *
	 * @since 1.5.0
	 *
	 * @param string $field_name Field name from the form.
	 * @param string $basetype   CF7 form-tag basetype (e.g. 'text', 'textarea').
	 * @return bool True if the field value should be masked.
	 */
	private function should_mask_field( $field_name, $basetype ) {
		/**
		 * Filter the CF7 form-tag basetypes whose values are masked.
		 *
		 * Defaults to free-text fields only: dedicated fields such as the
		 * sender's name and email are collected on purpose and needed for
		 * replies, while accidental PII lands in the message body.
		 *
		 * @since 1.5.0
		 *
		 * @param array $maskable_basetypes Basetypes to mask.
		 */
		$maskable_basetypes = apply_filters( 'piip_cf7_maskable_basetypes', array( 'textarea' ) );

		$mask = in_array( $basetype, $maskable_basetypes, true );

		/**
		 * Filter whether a specific CF7 field should be masked.
		 *
		 * Allows per-field overrides, e.g. masking an additional text field
		 * or excluding one textarea.
		 *
		 * @since 1.5.0
		 *
		 * @param bool   $mask       Whether the field will be masked.
		 * @param string $field_name Field name from the form.
		 * @param string $basetype   CF7 form-tag basetype.
		 */
		return (bool) apply_filters( 'piip_cf7_mask_field', $mask, $field_name, $basetype );
	}

	/**
	 * Map submitted field names to their CF7 form-tag basetypes.
	 *
	 * @since 1.5.0
	 *
	 * @return array Map of field name => basetype.
	 */
	private function get_field_basetypes() {
		$basetypes = array();

		if ( ! class_exists( 'WPCF7_Submission' ) ) {
			return $basetypes;
		}

		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return $basetypes;
		}

		$contact_form = $submission->get_contact_form();
		if ( ! $contact_form || ! method_exists( $contact_form, 'scan_form_tags' ) ) {
			return $basetypes;
		}

		foreach ( $contact_form->scan_form_tags() as $tag ) {
			if ( ! empty( $tag->name ) ) {
				$basetypes[ $tag->name ] = $tag->basetype;
			}
		}

		return $basetypes;
	}
}
