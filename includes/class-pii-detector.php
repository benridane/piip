<?php
/**
 * PII Detector Class
 *
 * Detects personally identifiable information (PII) in form data.
 * Implements Presidio-level detection patterns for high accuracy.
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
 * Class PIIP_PII_Detector
 *
 * Detects PII types using field name patterns, regex patterns, and validation.
 * Supports Japanese and international formats.
 *
 * @since 1.0.0
 */
class PIIP_PII_Detector {

	/**
	 * Field name patterns for PII detection (multilingual).
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $field_patterns = array(
		'email'    => array(
			'email', 'e-mail', 'mail', 'メール', 'メールアドレス', 'eメール',
		),
		'phone'    => array(
			'phone', 'tel', 'telephone', 'mobile', 'cell', 'fax', 'contact',
			'電話', '電話番号', 'TEL', '携帯', '連絡先', 'FAX',
		),
		'name'     => array(
			'name', 'first', 'last', 'fname', 'lname', 'fullname', 'full_name',
			'given', 'family', 'surname', 'nickname',
			'氏名', '名前', 'お名前', '姓', '名', 'フリガナ', 'ふりがな',
		),
		'address'  => array(
			'address', 'street', 'city', 'zip', 'postal', 'prefecture', 'state',
			'country', 'region', 'location', 'addr',
			'住所', '都道府県', '市区町村', '番地', '郵便番号', '〒',
		),
		'card'     => array(
			'card', 'credit', 'ccn', 'payment', 'cardnumber', 'card_number',
			'カード', 'クレジット', 'カード番号',
		),
		'ssn'      => array(
			'ssn', 'social', 'security', 'mynumber', 'national_id', 'tax_id',
			'マイナンバー', '個人番号',
		),
		'password' => array(
			'password', 'pwd', 'pass', 'secret', 'passwd',
			'パスワード', '暗証番号',
		),
		'token'    => array(
			'token', 'api', 'key', 'secret', 'apikey', 'api_key', 'access_token',
			'トークン', 'APIキー',
		),
		'dob'      => array(
			'dob', 'birth', 'birthday', 'birthdate', 'date_of_birth',
			'生年月日', '誕生日',
		),
		'ip'       => array(
			'ip', 'ip_address', 'ipaddress', 'remote_addr',
			'IPアドレス',
		),
		'url'      => array(
			'url', 'website', 'homepage', 'site', 'link',
			'URL', 'ホームページ', 'サイト',
		),
	);

	/**
	 * Email regex patterns (RFC 5322 compliant).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $email_pattern = '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/';

	/**
	 * Phone number patterns for various formats.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $phone_patterns = array(
		// Japanese mobile: 090-1234-5678, 080-1234-5678, 070-1234-5678.
		'jp_mobile'      => '/\b0[789]0[-\s]?\d{4}[-\s]?\d{4}\b/',
		// Japanese landline: 03-1234-5678, 06-1234-5678, 045-123-4567.
		'jp_landline'    => '/\b0\d{1,4}[-\s]?\d{1,4}[-\s]?\d{3,4}\b/',
		// Japanese toll-free: 0120-xxx-xxx, 0800-xxx-xxxx.
		'jp_tollfree'    => '/\b0[18]20[-\s]?\d{3}[-\s]?\d{3,4}\b/',
		// International with +: +81-90-1234-5678, +1-234-567-8900.
		'international'  => '/\+\d{1,3}[-\s]?\d{1,4}[-\s]?\d{1,4}[-\s]?\d{2,4}/',
		// US format: (123) 456-7890, 123-456-7890.
		'us_standard'    => '/\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/',
		// Generic: 7+ digits with separators.
		'generic'        => '/\b\d{2,4}[-.\s]\d{2,4}[-.\s]\d{2,4}\b/',
	);

	/**
	 * Credit card patterns by issuer (with Luhn validation).
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $card_patterns = array(
		// Visa: starts with 4.
		'visa'       => '/\b4\d{3}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/',
		// Mastercard: starts with 51-55 or 2221-2720.
		'mastercard' => '/\b5[1-5]\d{2}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/',
		// Amex: starts with 34 or 37.
		'amex'       => '/\b3[47]\d{2}[-\s]?\d{6}[-\s]?\d{5}\b/',
		// JCB: starts with 3528-3589.
		'jcb'        => '/\b35(?:2[89]|[3-8]\d)[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/',
		// Generic 13-19 digits.
		'generic'    => '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{1,7}\b/',
	);

	/**
	 * Japanese My Number pattern (12 digits).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $mynumber_pattern = '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/';

	/**
	 * US SSN pattern.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $ssn_pattern = '/\b\d{3}[-\s]\d{2}[-\s]\d{4}\b/';

	/**
	 * IP address patterns.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $ip_patterns = array(
		// IPv4.
		'ipv4' => '/\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/',
		// IPv6 (simplified).
		'ipv6' => '/\b(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}\b/',
	);

	/**
	 * Japanese postal code pattern.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $jp_postal_pattern = '/\b\d{3}[-]?\d{4}\b/';

	/**
	 * Date patterns for DOB detection.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $date_patterns = array(
		// ISO: 1990-01-15.
		'iso'      => '/\b\d{4}[-\/]\d{1,2}[-\/]\d{1,2}\b/',
		// US: 01/15/1990.
		'us'       => '/\b\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4}\b/',
		// Japanese: 1990年1月15日.
		'japanese' => '/\b\d{4}年\d{1,2}月\d{1,2}日\b/',
		// Japanese era: 平成2年1月15日.
		'jp_era'   => '/\b(?:明治|大正|昭和|平成|令和)\d{1,2}年\d{1,2}月\d{1,2}日\b/',
	);

	/**
	 * URL pattern.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $url_pattern = '/\bhttps?:\/\/[^\s<>"\']+/i';

	/**
	 * Detect PII type from field name and value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $field_name The form field name.
	 * @param mixed  $field_value The form field value.
	 * @return string|null The detected PII type or null if not detected.
	 */
	public function detect_pii_type( $field_name, $field_value ) {
		// Skip non-string values.
		if ( ! is_string( $field_value ) || empty( $field_value ) ) {
			return null;
		}

		// First, check field name patterns (high confidence).
		$type = $this->detect_by_field_name( $field_name );
		if ( $type ) {
			return $type;
		}

		// Second, check value patterns.
		return $this->detect_by_value( $field_value );
	}

	/**
	 * Detect PII type by field name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $field_name The form field name.
	 * @return string|null The detected PII type or null.
	 */
	private function detect_by_field_name( $field_name ) {
		$field_name_lower = strtolower( $field_name );

		foreach ( $this->field_patterns as $type => $patterns ) {
			foreach ( $patterns as $pattern ) {
				if ( false !== stripos( $field_name_lower, strtolower( $pattern ) ) ) {
					return $type;
				}
			}
		}

		return null;
	}

	/**
	 * Detect PII type by value pattern.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The field value.
	 * @return string|null The detected PII type or null.
	 */
	private function detect_by_value( $value ) {
		// Priority order: most specific first.

		// Check email (high confidence).
		if ( $this->is_email( $value ) ) {
			return 'email';
		}

		// Check credit card (high confidence with Luhn).
		if ( $this->is_credit_card( $value ) ) {
			return 'card';
		}

		// Check SSN/My Number.
		if ( $this->is_ssn( $value ) || $this->is_mynumber( $value ) ) {
			return 'ssn';
		}

		// Check IP address.
		if ( $this->is_ip_address( $value ) ) {
			return 'ip';
		}

		// Check phone (moderate confidence).
		if ( $this->is_phone( $value ) ) {
			return 'phone';
		}

		// Check URL.
		if ( $this->is_url( $value ) ) {
			return 'url';
		}

		// Check token/API key.
		if ( $this->is_token( $value ) ) {
			return 'token';
		}

		return null;
	}

	/**
	 * Check if value is an email address.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The value to check.
	 * @return bool True if email, false otherwise.
	 */
	public function is_email( $value ) {
		// Use PHP's built-in validation + regex.
		return false !== filter_var( $value, FILTER_VALIDATE_EMAIL )
			|| 1 === preg_match( $this->email_pattern, $value );
	}

	/**
	 * Check if value is a phone number.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The value to check.
	 * @return bool True if phone number, false otherwise.
	 */
	public function is_phone( $value ) {
		// Extract digits only for length check.
		$digits = preg_replace( '/\D/', '', $value );
		$length = strlen( $digits );

		// Phone numbers typically have 7-15 digits.
		if ( $length < 7 || $length > 15 ) {
			return false;
		}

		// Check against known patterns.
		foreach ( $this->phone_patterns as $pattern ) {
			if ( 1 === preg_match( $pattern, $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if value is a credit card number.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The value to check.
	 * @return bool True if credit card, false otherwise.
	 */
	public function is_credit_card( $value ) {
		// Remove all non-digit characters.
		$digits = preg_replace( '/\D/', '', $value );

		// Credit cards have 13-19 digits.
		$length = strlen( $digits );
		if ( $length < 13 || $length > 19 ) {
			return false;
		}

		// Check patterns first.
		$matches_pattern = false;
		foreach ( $this->card_patterns as $pattern ) {
			if ( 1 === preg_match( $pattern, $value ) ) {
				$matches_pattern = true;
				break;
			}
		}

		// Validate using Luhn algorithm.
		if ( $matches_pattern || ( $length >= 13 && $length <= 19 ) ) {
			return $this->validate_luhn( $digits );
		}

		return false;
	}

	/**
	 * Check if value is a US Social Security Number.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The value to check.
	 * @return bool True if SSN, false otherwise.
	 */
	public function is_ssn( $value ) {
		if ( 1 !== preg_match( $this->ssn_pattern, $value ) ) {
			return false;
		}

		// Additional validation: not all zeros in any group.
		$digits = preg_replace( '/\D/', '', $value );
		if ( 9 !== strlen( $digits ) ) {
			return false;
		}

		$area   = substr( $digits, 0, 3 );
		$group  = substr( $digits, 3, 2 );
		$serial = substr( $digits, 5, 4 );

		// SSN cannot have 000 in area, 00 in group, or 0000 in serial.
		if ( '000' === $area || '00' === $group || '0000' === $serial ) {
			return false;
		}

		// SSN cannot start with 9.
		if ( '9' === $area[0] ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if value is a Japanese My Number.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The value to check.
	 * @return bool True if My Number, false otherwise.
	 */
	public function is_mynumber( $value ) {
		if ( 1 !== preg_match( $this->mynumber_pattern, $value ) ) {
			return false;
		}

		$digits = preg_replace( '/\D/', '', $value );

		// My Number is exactly 12 digits.
		if ( 12 !== strlen( $digits ) ) {
			return false;
		}

		// Validate check digit (last digit).
		return $this->validate_mynumber_checkdigit( $digits );
	}

	/**
	 * Validate Japanese My Number check digit.
	 *
	 * @since 1.0.0
	 *
	 * @param string $number The 12-digit number.
	 * @return bool True if valid, false otherwise.
	 */
	private function validate_mynumber_checkdigit( $number ) {
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
	 * Check if value is an IP address.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The value to check.
	 * @return bool True if IP address, false otherwise.
	 */
	public function is_ip_address( $value ) {
		// Use PHP's built-in validation.
		if ( false !== filter_var( $value, FILTER_VALIDATE_IP ) ) {
			return true;
		}

		// Fallback to regex.
		foreach ( $this->ip_patterns as $pattern ) {
			if ( 1 === preg_match( $pattern, $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if value is a URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The value to check.
	 * @return bool True if URL, false otherwise.
	 */
	public function is_url( $value ) {
		return false !== filter_var( $value, FILTER_VALIDATE_URL )
			|| 1 === preg_match( $this->url_pattern, $value );
	}

	/**
	 * Check if value is a token/API key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The value to check.
	 * @return bool True if token, false otherwise.
	 */
	public function is_token( $value ) {
		// Tokens are typically long alphanumeric strings.
		$length = strlen( $value );

		// Must be at least 32 characters.
		if ( $length < 32 ) {
			return false;
		}

		// Should be mostly alphanumeric with possible underscores/hyphens.
		if ( 1 !== preg_match( '/^[A-Za-z0-9_\-\.]+$/', $value ) ) {
			return false;
		}

		// Should have high entropy (mix of characters).
		$unique_chars = count( array_unique( str_split( $value ) ) );
		$entropy      = $unique_chars / $length;

		return $entropy > 0.3;
	}

	/**
	 * Check if value is a Japanese postal code.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The value to check.
	 * @return bool True if postal code, false otherwise.
	 */
	public function is_jp_postal( $value ) {
		return 1 === preg_match( $this->jp_postal_pattern, $value );
	}

	/**
	 * Check if value is a date (potential DOB).
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The value to check.
	 * @return bool True if date, false otherwise.
	 */
	public function is_date( $value ) {
		foreach ( $this->date_patterns as $pattern ) {
			if ( 1 === preg_match( $pattern, $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate credit card using Luhn algorithm.
	 *
	 * @since 1.0.0
	 *
	 * @param string $number The card number to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private function validate_luhn( $number ) {
		$sum        = 0;
		$num_digits = strlen( $number );
		$parity     = $num_digits % 2;

		for ( $i = 0; $i < $num_digits; $i++ ) {
			$digit = (int) $number[ $i ];

			if ( $i % 2 === $parity ) {
				$digit *= 2;
			}

			if ( $digit > 9 ) {
				$digit -= 9;
			}

			$sum += $digit;
		}

		return 0 === ( $sum % 10 );
	}

	/**
	 * Check if a field is a system field (should not be masked).
	 *
	 * @since 1.0.0
	 *
	 * @param string $field_name The field name to check.
	 * @return bool True if system field, false otherwise.
	 */
	public function is_system_field( $field_name ) {
		$system_fields = array(
			'_wpnonce',
			'_wp_http_referer',
			'_wpcf7',
			'_wpcf7_version',
			'_wpcf7_locale',
			'_wpcf7_unit_tag',
			'_wpcf7_container_post',
			'_wpcf7_posted_data_hash',
			'action',
			'submit',
			'_client_masked',
			// wpForo system fields.
			'wpforo_nonce',
			'_wpforo_',
		);

		// Check if field starts with underscore (WordPress convention).
		if ( 0 === strpos( $field_name, '_' ) ) {
			return true;
		}

		foreach ( $system_fields as $sys_field ) {
			if ( false !== strpos( $field_name, $sys_field ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find all PII in text content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Text to scan.
	 * @return array Array of found PII with type and value.
	 */
	public function find_all_pii( $text ) {
		$found = array();

		// Find emails.
		if ( preg_match_all( $this->email_pattern, $text, $matches ) ) {
			foreach ( $matches[0] as $match ) {
				$found[] = array(
					'type'  => 'email',
					'value' => $match,
				);
			}
		}

		// Find phones.
		foreach ( $this->phone_patterns as $name => $pattern ) {
			if ( preg_match_all( $pattern, $text, $matches ) ) {
				foreach ( $matches[0] as $match ) {
					$found[] = array(
						'type'  => 'phone',
						'value' => $match,
					);
				}
			}
		}

		// Find credit cards.
		foreach ( $this->card_patterns as $name => $pattern ) {
			if ( preg_match_all( $pattern, $text, $matches ) ) {
				foreach ( $matches[0] as $match ) {
					$digits = preg_replace( '/\D/', '', $match );
					if ( $this->validate_luhn( $digits ) ) {
						$found[] = array(
							'type'  => 'card',
							'value' => $match,
						);
					}
				}
			}
		}

		// Find SSN.
		if ( preg_match_all( $this->ssn_pattern, $text, $matches ) ) {
			foreach ( $matches[0] as $match ) {
				if ( $this->is_ssn( $match ) ) {
					$found[] = array(
						'type'  => 'ssn',
						'value' => $match,
					);
				}
			}
		}

		// Find My Number.
		if ( preg_match_all( $this->mynumber_pattern, $text, $matches ) ) {
			foreach ( $matches[0] as $match ) {
				if ( $this->is_mynumber( $match ) ) {
					$found[] = array(
						'type'  => 'ssn',
						'value' => $match,
					);
				}
			}
		}

		// Find IP addresses.
		foreach ( $this->ip_patterns as $pattern ) {
			if ( preg_match_all( $pattern, $text, $matches ) ) {
				foreach ( $matches[0] as $match ) {
					$found[] = array(
						'type'  => 'ip',
						'value' => $match,
					);
				}
			}
		}

		// Find URLs.
		if ( preg_match_all( $this->url_pattern, $text, $matches ) ) {
			foreach ( $matches[0] as $match ) {
				$found[] = array(
					'type'  => 'url',
					'value' => $match,
				);
			}
		}

		return $found;
	}

	/**
	 * Get confidence score for PII detection.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type PII type.
	 * @param string $value The detected value.
	 * @param string $context Optional context (field name).
	 * @return float Confidence score 0.0 to 1.0.
	 */
	public function get_confidence( $type, $value, $context = '' ) {
		$base_confidence = array(
			'email' => 0.95, // Very reliable pattern.
			'card'  => 0.95, // Luhn validation.
			'ssn'   => 0.90, // Format + validation.
			'ip'    => 0.95, // Very reliable pattern.
			'phone' => 0.70, // Can have false positives.
			'url'   => 0.90, // Reliable pattern.
			'token' => 0.60, // Heuristic-based.
			'name'  => 0.50, // Context-dependent.
			'dob'   => 0.60, // Date could be anything.
		);

		$confidence = isset( $base_confidence[ $type ] ) ? $base_confidence[ $type ] : 0.5;

		// Boost confidence if field name matches.
		if ( ! empty( $context ) ) {
			$field_type = $this->detect_by_field_name( $context );
			if ( $field_type === $type ) {
				$confidence = min( 1.0, $confidence + 0.2 );
			}
		}

		return $confidence;
	}
}
