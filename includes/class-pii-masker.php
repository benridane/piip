<?php
/**
 * PII Masker Class
 *
 * Masks personally identifiable information (PII) in form data.
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
 * Class PIIP_PII_Masker
 *
 * Provides methods to mask different types of PII.
 *
 * @since 1.0.0
 */
class PIIP_PII_Masker {

	/**
	 * PII Detector instance.
	 *
	 * @since 1.0.0
	 * @var PIIP_PII_Detector
	 */
	private $detector;

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
	 * @param PIIP_PII_Detector $detector PII detector instance.
	 */
	public function __construct( $detector = null ) {
		$this->detector = $detector ? $detector : new PIIP_PII_Detector();
		$this->settings = get_option( 'piip_settings', array() );
	}

	/**
	 * Mask form data array.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Form data to mask.
	 * @return array Masked form data.
	 */
	public function mask_form_data( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		/**
		 * Filter form data before masking.
		 *
		 * @since 1.2.2
		 *
		 * @param array $data The form data array.
		 */
		$data = apply_filters( 'piip_before_mask_form_data', $data );

		// Allow complete custom override of form data masking.
		$custom_masked = apply_filters( 'piip_custom_mask_form_data', null, $data );
		if ( null !== $custom_masked ) {
			/**
			 * Action fired when custom form data masking is applied.
			 *
			 * @since 1.2.2
			 *
			 * @param array $original_data The original form data.
			 * @param array $masked_data   The masked form data.
			 */
			do_action( 'piip_form_data_masked', $data, $custom_masked );
			return $custom_masked;
		}

		$masked_data = array();

		foreach ( $data as $field_name => $value ) {
			// Skip system fields.
			if ( $this->detector->is_system_field( $field_name ) ) {
				$masked_data[ $field_name ] = $value;
				continue;
			}

			// Handle arrays recursively.
			if ( is_array( $value ) ) {
				$masked_data[ $field_name ] = $this->mask_form_data( $value );
				continue;
			}

			// Mask the value.
			$masked_data[ $field_name ] = $this->mask_value( $field_name, $value );
		}

		/**
		 * Filter form data after masking.
		 *
		 * @since 1.2.2
		 *
		 * @param array $masked_data   The masked form data.
		 * @param array $original_data The original form data.
		 */
		$masked_data = apply_filters( 'piip_after_mask_form_data', $masked_data, $data );

		/**
		 * Action fired after form data masking is complete.
		 *
		 * @since 1.2.2
		 *
		 * @param array $original_data The original form data.
		 * @param array $masked_data   The masked form data.
		 */
		do_action( 'piip_form_data_masked', $data, $masked_data );

		return $masked_data;
	}

	/**
	 * Mask text content without field name context.
	 *
	 * Simple method for masking any text content, useful for custom implementations.
	 *
	 * @since 1.2.2
	 *
	 * @param string $text The text content to mask.
	 * @return string Masked text content.
	 */
	public function mask_text_simple( $text ) {
		if ( ! is_string( $text ) || empty( $text ) ) {
			return $text;
		}

		/**
		 * Filter to allow complete custom override of simple text masking.
		 *
		 * @since 1.2.2
		 *
		 * @param string|null $custom_result Custom masking result, null to use default logic.
		 * @param string      $text          The original text.
		 */
		$custom_result = apply_filters( 'piip_custom_mask_text', null, $text );
		if ( null !== $custom_result ) {
			/**
			 * Action fired when custom text masking is applied.
			 *
			 * @since 1.2.2
			 *
			 * @param string $original_text The original text.
			 * @param string $masked_text   The masked text.
			 */
			do_action( 'piip_text_masked', $text, $custom_result );
			return $custom_result;
		}

		/**
		 * Filter text before PII detection and masking.
		 *
		 * @since 1.2.2
		 *
		 * @param string $text The text to be processed.
		 */
		$text = apply_filters( 'piip_before_mask_text', $text );

		// Use the existing mask_text method for content-based masking
		$masked_text = $this->mask_text( $text );

		/**
		 * Filter text after PII masking.
		 *
		 * @since 1.2.2
		 *
		 * @param string $masked_text   The masked text.
		 * @param string $original_text The original text.
		 */
		$masked_text = apply_filters( 'piip_after_mask_text', $masked_text, $text );

		/**
		 * Action fired after text masking is complete.
		 *
		 * @since 1.2.2
		 *
		 * @param string $original_text The original text.
		 * @param string $masked_text   The masked text.
		 */
		do_action( 'piip_text_masked', $text, $masked_text );

		return $masked_text;
	}

	/**
	 * Mask a single value based on detected PII type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $field_name The field name.
	 * @param mixed  $value The value to mask.
	 * @return mixed Masked value.
	 */
	public function mask_value( $field_name, $value ) {
		// Skip non-string values.
		if ( ! is_string( $value ) || empty( $value ) ) {
			return $value;
		}

		// Check if already masked.
		if ( $this->is_already_masked( $value ) ) {
			return $value;
		}

		/**
		 * Filter to allow custom pre-processing before PII detection.
		 *
		 * @since 1.2.2
		 *
		 * @param string $value      The field value.
		 * @param string $field_name The field name.
		 */
		$value = apply_filters( 'piip_before_mask_value', $value, $field_name );

		// Allow complete custom override of masking logic.
		$custom_masked = apply_filters( 'piip_custom_mask_value', null, $value, $field_name );
		if ( null !== $custom_masked ) {
			/**
			 * Action fired when custom masking is applied.
			 *
			 * @since 1.2.2
			 *
			 * @param string $original_value The original value.
			 * @param string $masked_value   The masked value.
			 * @param string $field_name     The field name.
			 * @param string $pii_type       The detected PII type.
			 */
			do_action( 'piip_value_masked', $value, $custom_masked, $field_name, 'custom' );
			return $custom_masked;
		}

		// Detect PII type.
		$pii_type = $this->detector->detect_pii_type( $field_name, $value );

		/**
		 * Filter the detected PII type.
		 *
		 * @since 1.2.2
		 *
		 * @param string|null $pii_type   The detected PII type or null.
		 * @param string      $value      The field value.
		 * @param string      $field_name The field name.
		 */
		$pii_type = apply_filters( 'piip_detected_pii_type', $pii_type, $value, $field_name );

		// Check if this PII type should be masked based on settings.
		if ( ! $this->should_mask_type( $pii_type ) ) {
			return $value;
		}

		// Check consent phrases (user opted out).
		if ( $this->has_consent_phrase( $value ) ) {
			/**
			 * Action fired when user consent bypasses masking.
			 *
			 * @since 1.2.2
			 *
			 * @param string $value      The field value.
			 * @param string $field_name The field name.
			 * @param string $pii_type   The detected PII type.
			 */
			do_action( 'piip_consent_bypass', $value, $field_name, $pii_type );
			return $value;
		}

		// Apply masking based on type.
		$masked_value = $this->apply_masking_by_type( $pii_type, $value );

		/**
		 * Filter the masked value before returning.
		 *
		 * @since 1.2.2
		 *
		 * @param string $masked_value   The masked value.
		 * @param string $original_value The original value.
		 * @param string $field_name     The field name.
		 * @param string $pii_type       The detected PII type.
		 */
		$masked_value = apply_filters( 'piip_after_mask_value', $masked_value, $value, $field_name, $pii_type );

		/**
		 * Action fired after successful masking.
		 *
		 * @since 1.2.2
		 *
		 * @param string $original_value The original value.
		 * @param string $masked_value   The masked value.
		 * @param string $field_name     The field name.
		 * @param string $pii_type       The detected PII type.
		 */
		do_action( 'piip_value_masked', $value, $masked_value, $field_name, $pii_type );

		return $masked_value;
	}

	/**
	 * Apply masking based on PII type.
	 *
	 * @since 1.2.2
	 *
	 * @param string $pii_type The detected PII type.
	 * @param string $value    The value to mask.
	 * @return string Masked value.
	 */
	private function apply_masking_by_type( $pii_type, $value ) {
		/**
		 * Filter to allow custom masking for specific PII types.
		 *
		 * @since 1.2.2
		 *
		 * @param string|null $custom_result Custom masking result, null to use default.
		 * @param string      $pii_type      The PII type.
		 * @param string      $value         The original value.
		 */
		$custom_result = apply_filters( 'piip_custom_mask_by_type', null, $pii_type, $value );
		if ( null !== $custom_result ) {
			return $custom_result;
		}

		// Default masking logic.
		switch ( $pii_type ) {
			case 'email':
				return $this->mask_email( $value );

			case 'phone':
				return $this->mask_phone( $value );

			case 'name':
				return $this->mask_name( $value );

			case 'address':
				return $this->mask_address( $value );

			case 'card':
				return $this->mask_credit_card( $value );

			case 'ssn':
				return $this->mask_ssn( $value );

			case 'password':
				return $this->mask_password( $value );

			case 'token':
				return $this->mask_token( $value );

			case 'hosting':
				return $this->mask_hosting_id( $value );

			default:
				return $value;
		}
	}

	/**
	 * Check if value contains consent phrase.
	 *
	 * @since 1.2.2
	 * @since 1.4.1 Made public for the masking preview feature.
	 *
	 * @param string $value The value to check.
	 * @return bool True if consent phrase found, false otherwise.
	 */
	public function has_consent_phrase( $value ) {
		$consent_phrases = $this->settings['consent_phrases'] ?? array();
		
		if ( empty( $consent_phrases ) || ! is_array( $consent_phrases ) ) {
			return false;
		}

		$value_lower = strtolower( $value );

		foreach ( $consent_phrases as $phrase_config ) {
			if ( empty( $phrase_config['enabled'] ) || empty( $phrase_config['phrase'] ) ) {
				continue;
			}

			$phrase_lower = strtolower( $phrase_config['phrase'] );
			if ( false !== strpos( $value_lower, $phrase_lower ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Mask email address.
	 *
	 * Example: john.doe@example.com -> j***@example.com
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Email address to mask.
	 * @return string Masked email.
	 */
	public function mask_email( $email ) {
		if ( ! $this->detector->is_email( $email ) ) {
			return $email;
		}

		$parts = explode( '@', $email );
		if ( 2 !== count( $parts ) ) {
			return '***@***';
		}

		$local_part = $parts[0];
		$domain     = $parts[1];

		// Keep first character, mask the rest.
		$masked_local = substr( $local_part, 0, 1 ) . '***';

		return $masked_local . '@' . $domain;
	}

	/**
	 * Mask phone number.
	 *
	 * Example: +1-234-567-8900 -> ***-***-8900
	 *
	 * @since 1.0.0
	 *
	 * @param string $phone Phone number to mask.
	 * @return string Masked phone number.
	 */
	public function mask_phone( $phone ) {
		// Extract digits only.
		$digits = preg_replace( '/\D/', '', $phone );

		if ( strlen( $digits ) < 7 ) {
			return $phone;
		}

		// Keep last 4 digits.
		$last_four = substr( $digits, -4 );

		return '***-***-' . $last_four;
	}

	/**
	 * Mask name.
	 *
	 * Example: 山田太郎 -> 山田**, Taro Yamada -> T*** Y****
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Name to mask.
	 * @return string Masked name.
	 */
	public function mask_name( $name ) {
		// Split by spaces.
		$parts = preg_split( '/\s+/', trim( $name ) );

		$masked_parts = array();
		foreach ( $parts as $part ) {
			if ( empty( $part ) ) {
				continue;
			}

			// Check if Japanese (multibyte).
			if ( mb_strlen( $part, 'UTF-8' ) !== strlen( $part ) ) {
				// Japanese name - keep first character.
				$masked_parts[] = mb_substr( $part, 0, 1, 'UTF-8' ) . str_repeat( '*', mb_strlen( $part, 'UTF-8' ) - 1 );
			} else {
				// English name - keep first character.
				$masked_parts[] = substr( $part, 0, 1 ) . str_repeat( '*', strlen( $part ) - 1 );
			}
		}

		return implode( ' ', $masked_parts );
	}

	/**
	 * Mask address.
	 *
	 * Keeps first part of address visible, masks the rest for partial context.
	 *
	 * @since 1.0.0
	 *
	 * @param string $address Address to mask.
	 * @return string Masked address.
	 */
	public function mask_address( $address ) {
		// Split by common delimiters (space, comma, newline).
		$parts = preg_split( '/[\s,\n\r]+/', trim( $address ), -1, PREG_SPLIT_NO_EMPTY );

		if ( empty( $parts ) ) {
			return '***';
		}

		// Keep first part (typically street number or prefecture), mask rest.
		if ( count( $parts ) > 2 ) {
			return $parts[0] . ' *** ***';
		} elseif ( count( $parts ) === 2 ) {
			return $parts[0] . ' ***';
		}

		// Single part - partially mask.
		$length = mb_strlen( $address, 'UTF-8' );
		if ( $length > 4 ) {
			return mb_substr( $address, 0, 2, 'UTF-8' ) . str_repeat( '*', min( 3, $length - 2 ) );
		}

		return '***';
	}

	/**
	 * Mask credit card number.
	 *
	 * Example: 4532-1234-5678-9010 -> ****-****-****-9010
	 *
	 * @since 1.0.0
	 *
	 * @param string $card Credit card number to mask.
	 * @return string Masked credit card.
	 */
	public function mask_credit_card( $card ) {
		// Extract digits only.
		$digits = preg_replace( '/\D/', '', $card );

		if ( strlen( $digits ) < 13 || strlen( $digits ) > 19 ) {
			return $card;
		}

		// Keep last 4 digits only.
		$last_four = substr( $digits, -4 );

		return '****-****-****-' . $last_four;
	}

	/**
	 * Mask Social Security Number.
	 *
	 * Example: 123-45-6789 -> ***-**-6789
	 *
	 * @since 1.0.0
	 *
	 * @param string $ssn SSN to mask.
	 * @return string Masked SSN.
	 */
	public function mask_ssn( $ssn ) {
		// Extract digits only.
		$digits = preg_replace( '/\D/', '', $ssn );

		if ( 9 !== strlen( $digits ) ) {
			return '***-**-****';
		}

		// Keep last 4 digits.
		$last_four = substr( $digits, -4 );

		return '***-**-' . $last_four;
	}

	/**
	 * Mask password.
	 *
	 * @since 1.0.0
	 *
	 * @param string $password Password to mask.
	 * @return string Masked password.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function mask_password( $password ) {
		// Parameter preserved for interface compatibility.
		unset( $password ); // Explicitly unset for security.
		return '[REDACTED]';
	}

	/**
	 * Mask token/API key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token Token to mask.
	 * @return string Masked token.
	 */
	public function mask_token( $token ) {
		if ( strlen( $token ) < 32 ) {
			return $token;
		}

		// Show first 4 and last 4 characters.
		return substr( $token, 0, 4 ) . str_repeat( '*', strlen( $token ) - 8 ) . substr( $token, -4 );
	}

	/**
	 * Mask hosting account/server ID.
	 *
	 * Supports various hosting providers:
	 * - Xserver: xs123456 -> xs***456, sv1234 -> sv***4
	 * - Sakura: abc12345 -> abc***45, *.sakura.ne.jp -> ***.sakura.ne.jp
	 * - AWS: 123456789012 -> ****-****-9012
	 * - Azure GUID: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx -> ****-****-****-****-xxxx
	 * - GCP: my-project-123 -> my-***-123
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Hosting ID to mask.
	 * @return string Masked hosting ID.
	 */
	public function mask_hosting_id( $value ) {
		$length = strlen( $value );

		// Xserver account: xs123456.
		if ( preg_match( '/^xs\d{5,8}$/i', $value ) ) {
			return substr( $value, 0, 2 ) . '***' . substr( $value, -3 );
		}

		// Xserver server: sv1234.
		if ( preg_match( '/^sv\d{3,5}$/i', $value ) ) {
			return substr( $value, 0, 2 ) . '***' . substr( $value, -1 );
		}

		// Sakura account: abc12345.
		if ( preg_match( '/^[a-z]{3}\d{5}$/i', $value ) ) {
			return substr( $value, 0, 3 ) . '***' . substr( $value, -2 );
		}

		// Sakura domain: example.sakura.ne.jp.
		if ( preg_match( '/\.sakura\.ne\.jp$/i', $value ) ) {
			return '***.sakura.ne.jp';
		}

		// Lolipop domain: example.lolipop.jp.
		if ( preg_match( '/\.lolipop\.jp$/i', $value ) ) {
			return '***.lolipop.jp';
		}

		// mixhost domain: example.mixh.jp.
		if ( preg_match( '/\.mixh\.jp$/i', $value ) ) {
			return '***.mixh.jp';
		}

		// ConoHa account: gnc123456789.
		if ( preg_match( '/^gnc[a-z0-9]{8,12}$/i', $value ) ) {
			return 'gnc***' . substr( $value, -4 );
		}

		// AWS 12-digit account.
		if ( preg_match( '/^\d{12}$/', $value ) ) {
			return '****-****-' . substr( $value, -4 );
		}

		// Azure GUID.
		if ( preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value ) ) {
			$parts = explode( '-', $value );
			return '****-****-****-****-' . $parts[4];
		}

		// GCP project ID: keep first and last parts.
		if ( preg_match( '/^[a-z][a-z0-9-]{4,28}[a-z0-9]$/', $value ) ) {
			if ( $length > 10 ) {
				return substr( $value, 0, 3 ) . '***' . substr( $value, -3 );
			}
			return substr( $value, 0, 2 ) . '***' . substr( $value, -2 );
		}

		// Generic fallback: show first 2 and last 2.
		if ( $length > 6 ) {
			return substr( $value, 0, 2 ) . '***' . substr( $value, -2 );
		}

		return '***';
	}

	/**
	 * Check if value is already masked.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Value to check.
	 * @return bool True if already masked.
	 */
	private function is_already_masked( $value ) {
		return false !== strpos( $value, '***' ) || false !== strpos( $value, '****' ) || '[REDACTED]' === $value;
	}

	/**
	 * Mask PII in free-form text content.
	 *
	 * Scans text for embedded PII patterns (emails, phones, etc.) and masks them inline.
	 * Presidio-level detection with validation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Text content to scan and mask.
	 * @return string Text with PII masked.
	 */
	public function mask_text( $text ) {
		if ( ! is_string( $text ) || empty( $text ) ) {
			return $text;
		}

		// Mask emails (high confidence).
		if ( $this->should_mask_type( 'email' ) ) {
			$text = $this->mask_emails_in_text( $text );
		}

		// Mask credit cards (with Luhn validation).
		if ( $this->should_mask_type( 'card' ) ) {
			$text = $this->mask_credit_cards_in_text( $text );
		}

		// Mask SSNs and My Number.
		if ( $this->should_mask_type( 'ssn' ) ) {
			$text = $this->mask_ssns_in_text( $text );
			$text = $this->mask_mynumber_in_text( $text );
		}

		// Mask phone numbers (Japanese and international).
		if ( $this->should_mask_type( 'phone' ) ) {
			$text = $this->mask_phones_in_text( $text );
		}

		// Mask IP addresses.
		$text = $this->mask_ip_addresses_in_text( $text );

		// Mask hosting account/server IDs.
		if ( $this->should_mask_type( 'hosting' ) ) {
			$text = $this->mask_hosting_ids_in_text( $text );
		}

		// Mask AI API keys and tokens.
		if ( $this->should_mask_type( 'token' ) ) {
			$text = $this->mask_ai_keys_in_text( $text );
		}

		return $text;
	}

	/**
	 * Mask email addresses found in text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Text to process.
	 * @return string Text with emails masked.
	 */
	private function mask_emails_in_text( $text ) {
		$pattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) {
				return $this->mask_email( $matches[0] );
			},
			$text
		);
	}

	/**
	 * Mask phone numbers found in text.
	 *
	 * Supports various formats:
	 * - Japanese: 090-1234-5678, 03-1234-5678, +81-90-1234-5678
	 * - US/International: +1-234-567-8900, (123) 456-7890
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Text to process.
	 * @return string Text with phones masked.
	 */
	private function mask_phones_in_text( $text ) {
		// Japanese phone patterns.
		$patterns = array(
			// Japanese mobile patterns: 090, 080, 070 numbers.
			'/\b0[789]0[-\s]?\d{4}[-\s]?\d{4}\b/',
			// Japanese toll-free: 0120-xxx-xxx, 0800-xxx-xxxx (same as detector).
			'/\b0[18]20[-\s]?\d{3}[-\s]?\d{3,4}\b/',
			// Japanese landline patterns: area code + number.
			'/\b0\d{1,4}[-\s]?\d{1,4}[-\s]?\d{4}\b/',
			// International format with +.
			'/\+\d{1,3}[-\s]?\d{1,4}[-\s]?\d{1,4}[-\s]?\d{2,4}\b/',
			// US format: (123) 456-7890.
			'/\(\d{3}\)\s?\d{3}[-\s]?\d{4}/',
		);

		foreach ( $patterns as $pattern ) {
			$text = preg_replace_callback(
				$pattern,
				function ( $matches ) {
					return $this->mask_phone( $matches[0] );
				},
				$text
			);
		}

		return $text;
	}

	/**
	 * Mask credit card numbers found in text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Text to process.
	 * @return string Text with credit cards masked.
	 */
	private function mask_credit_cards_in_text( $text ) {
		// Pattern for 13-19 digit numbers with optional separators.
		$pattern = '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{1,7}\b/';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) {
				$digits = preg_replace( '/\D/', '', $matches[0] );
				// Only mask if it looks like a credit card (13-19 digits).
				if ( strlen( $digits ) >= 13 && strlen( $digits ) <= 19 ) {
					return $this->mask_credit_card( $matches[0] );
				}
				return $matches[0];
			},
			$text
		);
	}

	/**
	 * Mask SSNs found in text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Text to process.
	 * @return string Text with SSNs masked.
	 */
	private function mask_ssns_in_text( $text ) {
		// US SSN pattern: 123-45-6789 or 123 45 6789.
		$pattern = '/\b\d{3}[-\s]\d{2}[-\s]\d{4}\b/';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) {
				// Validate SSN format.
				$digits = preg_replace( '/\D/', '', $matches[0] );
				if ( 9 === strlen( $digits ) ) {
					$area = substr( $digits, 0, 3 );
					// SSN cannot start with 000, 666, or 9xx.
					if ( '000' !== $area && '666' !== $area && '9' !== $area[0] ) {
						return $this->mask_ssn( $matches[0] );
					}
				}
				return $matches[0];
			},
			$text
		);
	}

	/**
	 * Mask Japanese My Number (12 digits) found in text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Text to process.
	 * @return string Text with My Numbers masked.
	 */
	private function mask_mynumber_in_text( $text ) {
		// My Number pattern: 1234-5678-9012 or 123456789012.
		$pattern = '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) {
				$digits = preg_replace( '/\D/', '', $matches[0] );
				// My Number is exactly 12 digits.
				if ( 12 === strlen( $digits ) && $this->validate_mynumber( $digits ) ) {
					return $this->mask_mynumber( $matches[0] );
				}
				return $matches[0];
			},
			$text
		);
	}

	/**
	 * Validate Japanese My Number check digit.
	 *
	 * @since 1.0.0
	 *
	 * @param string $number The 12-digit number.
	 * @return bool True if valid, false otherwise.
	 */
	private function validate_mynumber( $number ) {
		$weights = array( 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2 );
		$sum     = 0;

		for ( $i = 0; $i < 11; $i++ ) {
			$sum += (int) $number[ $i ] * $weights[ $i ];
		}

		$remainder   = $sum % 11;
		$check_digit = ( $remainder <= 1 ) ? 0 : ( 11 - $remainder );

		return (int) $number[11] === $check_digit;
	}

	/**
	 * Mask My Number.
	 *
	 * @since 1.0.0
	 *
	 * @param string $mynumber My Number to mask.
	 * @return string Masked My Number.
	 */
	public function mask_mynumber( $mynumber ) {
		$digits = preg_replace( '/\D/', '', $mynumber );
		if ( 12 !== strlen( $digits ) ) {
			return '****-****-****';
		}

		// Keep last 4 digits.
		$last_four = substr( $digits, -4 );
		return '****-****-' . $last_four;
	}

	/**
	 * Mask hosting account/server IDs found in text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Text to process.
	 * @return string Text with hosting IDs masked.
	 */
	private function mask_hosting_ids_in_text( $text ) {
		// Hosting ID patterns (same as detector).
		$patterns = array(
			// Xserver: xs123456, sv1234.
			'xserver_account' => '/\bxs\d{5,8}\b/i',
			'xserver_server'  => '/\bsv\d{3,5}\b/i',
			// Sakura: abc12345, *.sakura.ne.jp.
			'sakura_account'  => '/\b[a-z]{3}\d{5}\b/i',
			'sakura_domain'   => '/\b[\w-]+\.sakura\.ne\.jp\b/i',
			// ConoHa: gnc*.
			'conoha_account'  => '/\bgnc[a-z0-9]{8,12}\b/i',
			// Lolipop: *.lolipop.jp.
			'lolipop_domain'  => '/\b[\w-]+\.lolipop\.jp\b/i',
			// mixhost: *.mixh.jp.
			'mixhost_domain'  => '/\b[\w-]+\.mixh\.jp\b/i',
			// Azure GUID.
			'azure_guid'      => '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i',
		);

		foreach ( $patterns as $name => $pattern ) {
			$text = preg_replace_callback(
				$pattern,
				function ( $matches ) {
					return $this->mask_hosting_id( $matches[0] );
				},
				$text
			);
		}

		return $text;
	}

	/**
	 * Mask IP addresses found in text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Text to process.
	 * @return string Text with IP addresses masked.
	 */
	private function mask_ip_addresses_in_text( $text ) {
		// IPv4 pattern.
		$ipv4_pattern = '/\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/';

		$text = preg_replace_callback(
			$ipv4_pattern,
			function ( $matches ) {
				return $this->mask_ip( $matches[0] );
			},
			$text
		);

		return $text;
	}

	/**
	 * Mask IP address.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ip IP address to mask.
	 * @return string Masked IP.
	 */
	public function mask_ip( $ip ) {
		$parts = explode( '.', $ip );
		if ( 4 === count( $parts ) ) {
			// Keep first octet, mask the rest.
			return $parts[0] . '.***.***.' . $parts[3];
		}
		return '***.***.***.***';
	}

	/**
	 * Mask AI API keys found in text.
	 *
	 * Detects and masks various AI service API keys:
	 * - OpenAI: sk-, sk-proj-
	 * - Anthropic Claude: sk-ant-
	 * - Google AI: AIza
	 * - Hugging Face: hf_
	 * - Replicate: r8_
	 * - Cohere: -*co
	 * - Azure OpenAI: 32-char hex
	 * - Generic: sk-, ai-, api-
	 *
	 * @since 1.2.1
	 *
	 * @param string $text Text to process.
	 * @return string Text with AI API keys masked.
	 */
	private function mask_ai_keys_in_text( $text ) {
		// AI API key patterns (same as in detector class).
		$patterns = array(
			// OpenAI: sk- or sk-proj- followed by alphanumeric.
			'/\bsk-proj-[A-Za-z0-9]{40,}\b/',
			'/\bsk-[A-Za-z0-9]{20}T3BlbkFJ[A-Za-z0-9]{20}\b/',
			'/\bsk-[A-Za-z0-9]{48}\b/',
			// Anthropic Claude: sk-ant- followed by alphanumeric/dashes.
			'/\bsk-ant-[A-Za-z0-9_-]{95,100}\b/',
			// Google AI Studio: AIza followed by alphanumeric/dashes (at least 30 chars total).
			'/\bAIza[A-Za-z0-9_-]{30,}\b/',
			// Hugging Face: hf_ followed by alphanumeric.
			'/\bhf_[A-Za-z0-9]{30,}\b/',
			// Replicate: r8_ followed by alphanumeric.
			'/\br8_[A-Za-z0-9]{30,}\b/',
			// Cohere: ends with -co (at least 30 chars total).
			'/\b[A-Za-z0-9]{30,}-co\b/',
			// Azure OpenAI: 32-character hex string.
			'/\b[a-fA-F0-9]{32}\b/',
			// Generic AI API keys: sk-, ai-, api- followed by alphanumeric.
			'/\b(?:sk|ai|api)-[A-Za-z0-9_-]{20,}\b/',
		);

		foreach ( $patterns as $pattern ) {
			$text = preg_replace_callback(
				$pattern,
				function ( $matches ) {
					// Verify it's actually an AI key using the detector.
					if ( $this->detector->is_ai_key( $matches[0] ) ) {
						return $this->mask_token( $matches[0] );
					}
					return $matches[0];
				},
				$text
			);
		}

		return $text;
	}

	/**
	 * Check if a PII type should be masked based on settings.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $pii_type The PII type to check.
	 * @return bool True if should mask, false otherwise.
	 */
	private function should_mask_type( $pii_type ) {
		if ( null === $pii_type ) {
			return false;
		}

		// If no settings, mask all types by default.
		if ( empty( $this->settings ) ) {
			return true;
		}

		// Check if this specific type is enabled.
		$setting_key = 'mask_' . $pii_type;
		return ! isset( $this->settings[ $setting_key ] ) || ! empty( $this->settings[ $setting_key ] );
	}
}
