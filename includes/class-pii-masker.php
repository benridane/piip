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

		return $masked_data;
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

		// Detect PII type.
		$pii_type = $this->detector->detect_pii_type( $field_name, $value );

		// Check if this PII type should be masked based on settings.
		if ( ! $this->should_mask_type( $pii_type ) ) {
			return $value;
		}

		// Mask based on type.
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

			default:
				return $value;
		}
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
	public function mask_password( $password ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
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
			// Japanese mobile: 090-1234-5678, 080-1234-5678, 070-1234-5678.
			'/\b0[789]0[-\s]?\d{4}[-\s]?\d{4}\b/',
			// Japanese landline: 03-1234-5678, 06-1234-5678.
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
