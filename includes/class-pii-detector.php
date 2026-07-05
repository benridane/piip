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
			'email',
			'e-mail',
			'mail',
			'メール',
			'メールアドレス',
			'eメール',
		),
		'phone'    => array(
			'phone',
			'tel',
			'telephone',
			'mobile',
			'cell',
			'fax',
			'contact',
			'電話',
			'電話番号',
			'TEL',
			'携帯',
			'連絡先',
			'FAX',
		),
		'name'     => array(
			'name',
			'first',
			'last',
			'fname',
			'lname',
			'fullname',
			'full_name',
			'given',
			'family',
			'surname',
			'nickname',
			'氏名',
			'名前',
			'お名前',
			'姓',
			'名',
			'フリガナ',
			'ふりがな',
		),
		'address'  => array(
			'address',
			'street',
			'city',
			'zip',
			'postal',
			'prefecture',
			'state',
			'country',
			'region',
			'location',
			'addr',
			'住所',
			'都道府県',
			'市区町村',
			'番地',
			'郵便番号',
			'〒',
		),
		'card'     => array(
			'card',
			'credit',
			'ccn',
			'payment',
			'cardnumber',
			'card_number',
			'カード',
			'クレジット',
			'カード番号',
		),
		'ssn'      => array(
			'ssn',
			'social',
			'security',
			'mynumber',
			'national_id',
			'tax_id',
			'マイナンバー',
			'個人番号',
		),
		'password' => array(
			'password',
			'pwd',
			'pass',
			'secret',
			'passwd',
			'パスワード',
			'暗証番号',
		),
		'token'    => array(
			'token',
			'api',
			'key',
			'secret',
			'apikey',
			'api_key',
			'access_token',
			'openai',
			'claude',
			'anthropic',
			'gemini',
			'cohere',
			'huggingface',
			'replicate',
			'ai_key',
			'ai_token',
			'トークン',
			'APIキー',
			'AIキー',
		),
		'dob'      => array(
			'dob',
			'birth',
			'birthday',
			'birthdate',
			'date_of_birth',
			'生年月日',
			'誕生日',
		),
		'ip'       => array(
			'ip',
			'ip_address',
			'ipaddress',
			'remote_addr',
			'IPアドレス',
		),
		'url'      => array(
			'url',
			'website',
			'homepage',
			'site',
			'link',
			'URL',
			'ホームページ',
			'サイト',
		),
		'hosting'  => array(
			'server',
			'server_id',
			'account_id',
			'hosting',
			'subscription',
			'project_id',
			'xserver',
			'sakura',
			'aws',
			'azure',
			'gcp',
			'サーバー',
			'サーバーID',
			'アカウントID',
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
		// Japanese mobile patterns: 090, 080, 070 numbers.
		'jp_mobile'     => '/\b0[789]0[-\s]?\d{4}[-\s]?\d{4}\b/',
		// Japanese landline patterns: area code + number.
		'jp_landline'   => '/\b0\d{1,4}[-\s]?\d{1,4}[-\s]?\d{3,4}\b/',
		// Japanese toll-free: 0120-xxx-xxx, 0800-xxx-xxxx.
		'jp_tollfree'   => '/\b0[18]20[-\s]?\d{3}[-\s]?\d{3,4}\b/',
		// International with +: +81-90-1234-5678, +1-234-567-8900.
		'international' => '/\+\d{1,3}[-\s]?\d{1,4}[-\s]?\d{1,4}[-\s]?\d{2,4}/',
		// US format: (123) 456-7890, 123-456-7890.
		'us_standard'   => '/\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/',
		// Generic: 7+ digits with separators.
		'generic'       => '/\b\d{2,4}[-.\s]\d{2,4}[-.\s]\d{2,4}\b/',
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
	 * Hosting account/server ID patterns.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $hosting_patterns = array(
		// Xserver: xs123456, sv1234.
		'xserver_account' => '/\bxs\d{5,8}\b/i',
		'xserver_server'  => '/\bsv\d{3,5}\b/i',
		// Sakura Internet: abc12345 (3 letters + 5 digits).
		'sakura_account'  => '/\b[a-z]{3}\d{5}\b/i',
		'sakura_domain'   => '/\b[\w-]+\.sakura\.ne\.jp\b/i',
		// AWS: 12-digit account ID (with context).
		'aws_account'     => '/\b\d{12}\b/',
		// Azure: GUID subscription/resource ID.
		'azure_guid'      => '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i',
		// GCP: project ID (lowercase, hyphens, 6-30 chars).
		'gcp_project'     => '/\b[a-z][a-z0-9-]{4,28}[a-z0-9]\b/',
		// ConoHa: account format.
		'conoha_account'  => '/\bgnc[a-z0-9]{8,12}\b/i',
		// Lolipop: subdomain format.
		'lolipop_domain'  => '/\b[\w-]+\.lolipop\.jp\b/i',
		// mixhost: account format.
		'mixhost_domain'  => '/\b[\w-]+\.mixh\.jp\b/i',
	);

	/**
	 * AI API key patterns for various providers.
	 *
	 * @since 1.2.2
	 * @var array
	 */
	private $ai_key_patterns = array(
		// OpenAI API keys: sk-... (various lengths)
		'openai_legacy'   => '/\bsk-[A-Za-z0-9]{20,100}\b/',
		// OpenAI API keys: new format with project prefix
		'openai_project'  => '/\bsk-proj-[A-Za-z0-9]{20,100}\b/',
		// Anthropic Claude API keys: sk-ant-...
		'anthropic'       => '/\bsk-ant-[A-Za-z0-9_-]{30,100}\b/',
		// Google AI Studio API keys: AIza...
		'google_ai'       => '/\bAIza[A-Za-z0-9_-]{30,40}\b/',
		// Cohere API keys
		'cohere'          => '/\b[A-Za-z0-9]{30,50}-co\b/',
		// Hugging Face tokens: hf_...
		'huggingface'     => '/\bhf_[A-Za-z0-9]{20,40}\b/',
		// Replicate tokens: r8_...
		'replicate'       => '/\br8_[A-Za-z0-9]{20,50}\b/',
		// Azure OpenAI keys (32 hex chars)
		'azure_openai'    => '/\b[a-f0-9]{32}\b/',
		// Generic AI API key patterns (fallback)
		'generic_ai_key'  => '/\b(?:sk-|ai-|api-)[A-Za-z0-9_-]{20,100}\b/',
	);

	/**
	 * Labeled password pattern for free text ("password: xxx").
	 *
	 * Shared with the masker. The value class is printable ASCII only,
	 * which structurally rejects Japanese prose following the label
	 * (e.g. パスワードは忘れました), and the lookbehind keeps the label
	 * from matching inside identifiers.
	 *
	 * @since 1.6.0
	 * @var string
	 */
	public const LABELED_PASSWORD_PATTERN = '/((?<![A-Za-z0-9_])(?:password|passwd|pwd|パスワード|暗証番号)[\s　]*[::=は][\s　]*)([\x21-\x7E]{4,64})/iu';

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

		/**
		 * Filter to allow custom pre-processing before PII detection.
		 *
		 * @since 1.2.2
		 *
		 * @param string $field_value The field value.
		 * @param string $field_name  The field name.
		 */
		$field_value = apply_filters( 'piip_before_detect_pii', $field_value, $field_name );

		// Allow complete custom override of PII detection.
		$custom_type = apply_filters( 'piip_custom_detect_pii_type', null, $field_name, $field_value );
		if ( null !== $custom_type ) {
			return $custom_type;
		}

		// First, check field name patterns (high confidence).
		$type = $this->detect_by_field_name( $field_name );
		if ( $type ) {
			/**
			 * Filter the PII type detected by field name.
			 *
			 * @since 1.2.2
			 *
			 * @param string $type        The detected PII type.
			 * @param string $field_name  The field name.
			 * @param string $field_value The field value.
			 */
			return apply_filters( 'piip_detected_pii_by_field_name', $type, $field_name, $field_value );
		}

		// Second, check value patterns.
		$type = $this->detect_by_value( $field_value );
		if ( $type ) {
			/**
			 * Filter the PII type detected by field value.
			 *
			 * @since 1.2.2
			 *
			 * @param string $type        The detected PII type.
			 * @param string $field_name  The field name.
			 * @param string $field_value The field value.
			 */
			return apply_filters( 'piip_detected_pii_by_value', $type, $field_name, $field_value );
		}

		return null;
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

		// Check hosting account/server ID.
		if ( $this->is_hosting_id( $value ) ) {
			return 'hosting';
		}

		// Check AI API key.
		if ( $this->is_ai_key( $value ) ) {
			return 'token';
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
	 * Check if value is an AI API key.
	 *
	 * Supports various AI service providers:
	 * - OpenAI (sk-...)
	 * - Anthropic Claude (sk-ant-...)
	 * - Google AI Studio (AIza...)
	 * - Cohere
	 * - Hugging Face (hf_...)
	 * - Replicate (r8_...)
	 * - Azure OpenAI
	 *
	 * @since 1.2.2
	 *
	 * @param string $value The value to check.
	 * @return bool True if AI API key, false otherwise.
	 */
	public function is_ai_key( $value ) {
		// Check against known AI API key patterns.
		foreach ( $this->ai_key_patterns as $provider => $pattern ) {
			if ( 1 === preg_match( $pattern, $value ) ) {
				// Additional validation for specific providers.
				if ( 'azure_openai' === $provider ) {
					// Azure keys are 32-char hex, but we need to avoid false positives.
					if ( 32 === strlen( $value ) && ctype_xdigit( $value ) ) {
						// Check if it looks like a hash (high entropy).
						$unique_chars = count( array_unique( str_split( strtolower( $value ) ) ) );
						return $unique_chars >= 8; // Should have diverse hex chars.
					}
					continue;
				}

				if ( 'generic_ai_key' === $provider ) {
					// Only match generic pattern if no specific pattern matched.
					// This is a fallback for new AI services.
					$length = strlen( $value );
					if ( $length >= 20 && $length <= 100 ) {
						// Should start with known AI prefixes.
						if ( preg_match( '/^(sk-|ai-|api-)/i', $value ) ) {
							return true;
						}
					}
					continue;
				}

				return true;
			}
		}

		return false;
	}

	/**
	 * Get the AI service provider from an API key.
	 *
	 * @since 1.2.2
	 *
	 * @param string $value The API key value.
	 * @return string|null Provider name or null.
	 */
	public function get_ai_provider( $value ) {
		$providers = array(
			'openai_legacy'   => 'OpenAI',
			'openai_project'  => 'OpenAI',
			'anthropic'       => 'Anthropic',
			'google_ai'       => 'Google AI',
			'cohere'          => 'Cohere',
			'huggingface'     => 'Hugging Face',
			'replicate'       => 'Replicate',
			'azure_openai'    => 'Azure OpenAI',
			'generic_ai_key'  => 'AI Service',
		);

		foreach ( $this->ai_key_patterns as $name => $pattern ) {
			if ( 1 === preg_match( $pattern, $value ) ) {
				// Additional validation for azure_openai.
				if ( 'azure_openai' === $name ) {
					if ( 32 === strlen( $value ) && ctype_xdigit( $value ) ) {
						$unique_chars = count( array_unique( str_split( strtolower( $value ) ) ) );
						if ( $unique_chars >= 8 ) {
							return $providers[ $name ];
						}
					}
					continue;
				}

				// Additional validation for generic pattern.
				if ( 'generic_ai_key' === $name ) {
					if ( preg_match( '/^(sk-|ai-|api-)/i', $value ) ) {
						return $providers[ $name ];
					}
					continue;
				}

				return isset( $providers[ $name ] ) ? $providers[ $name ] : 'Unknown AI Service';
			}
		}

		return null;
	}

	/**
	 * Check if value is a hosting account/server ID.
	 *
	 * Supports Japanese and international hosting providers:
	 * - Xserver (xs123456, sv1234)
	 * - Sakura Internet (abc12345, *.sakura.ne.jp)
	 * - AWS (12-digit account ID)
	 * - Azure (GUID subscription ID)
	 * - GCP (project ID)
	 * - ConoHa, Lolipop, mixhost
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The value to check.
	 * @return bool True if hosting ID, false otherwise.
	 */
	public function is_hosting_id( $value ) {
		// Skip very short or very long values.
		$length = strlen( $value );
		if ( $length < 5 || $length > 50 ) {
			return false;
		}

		// Check against known hosting patterns.
		foreach ( $this->hosting_patterns as $name => $pattern ) {
			if ( 1 === preg_match( $pattern, $value ) ) {
				// For AWS account (12 digits), require additional context to avoid false positives.
				if ( 'aws_account' === $name ) {
					// Only match if it looks like an AWS account ID (not a phone or other number).
					$digits = preg_replace( '/\D/', '', $value );
					if ( 12 === strlen( $digits ) && 12 === strlen( $value ) ) {
						return true;
					}
					continue;
				}

				// For GCP project, avoid common words.
				if ( 'gcp_project' === $name ) {
					$common_words = array( 'example', 'default', 'project', 'website', 'content', 'message', 'activity' );
					if ( in_array( strtolower( $value ), $common_words, true ) ) {
						continue;
					}
				}

				return true;
			}
		}

		return false;
	}

	/**
	 * Get the hosting provider name from a hosting ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The hosting ID value.
	 * @return string|null Provider name or null.
	 */
	public function get_hosting_provider( $value ) {
		$providers = array(
			'xserver_account' => 'Xserver',
			'xserver_server'  => 'Xserver',
			'sakura_account'  => 'Sakura',
			'sakura_domain'   => 'Sakura',
			'aws_account'     => 'AWS',
			'azure_guid'      => 'Azure',
			'gcp_project'     => 'GCP',
			'conoha_account'  => 'ConoHa',
			'lolipop_domain'  => 'Lolipop',
			'mixhost_domain'  => 'mixhost',
		);

		foreach ( $this->hosting_patterns as $name => $pattern ) {
			if ( 1 === preg_match( $pattern, $value ) ) {
				return isset( $providers[ $name ] ) ? $providers[ $name ] : 'Unknown';
			}
		}

		return null;
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

		// Find hosting account/server IDs.
		foreach ( $this->hosting_patterns as $name => $pattern ) {
			// GCP project IDs are indistinguishable from ordinary lowercase
			// words in free text and mask_text() does not mask them; the
			// pattern only makes sense for whole field values (is_hosting_id).
			if ( 'gcp_project' === $name ) {
				continue;
			}

			if ( preg_match_all( $pattern, $text, $matches ) ) {
				foreach ( $matches[0] as $match ) {
					// Skip false positives.
					if ( 'aws_account' === $name ) {
						$digits = preg_replace( '/\D/', '', $match );
						if ( 12 !== strlen( $digits ) || 12 !== strlen( $match ) ) {
							continue;
						}
					}
					if ( 'gcp_project' === $name ) {
						$common_words = array( 'example', 'default', 'project', 'website', 'content', 'message', 'activity' );
						if ( in_array( strtolower( $match ), $common_words, true ) ) {
							continue;
						}
					}
					$found[] = array(
						'type'     => 'hosting',
						'value'    => $match,
						'provider' => $this->get_hosting_provider( $match ),
					);
				}
			}
		}

		// Find AI API keys.
		foreach ( $this->ai_key_patterns as $name => $pattern ) {
			if ( preg_match_all( $pattern, $text, $matches ) ) {
				foreach ( $matches[0] as $match ) {
					// Additional validation for specific providers.
					if ( 'azure_openai' === $name ) {
						if ( 32 === strlen( $match ) && ctype_xdigit( $match ) ) {
							$unique_chars = count( array_unique( str_split( strtolower( $match ) ) ) );
							if ( $unique_chars >= 8 ) {
								$found[] = array(
									'type'     => 'token',
									'value'    => $match,
									'provider' => $this->get_ai_provider( $match ),
								);
							}
						}
						continue;
					}

					if ( 'generic_ai_key' === $name ) {
						if ( preg_match( '/^(sk-|ai-|api-)/i', $match ) ) {
							$found[] = array(
								'type'     => 'token',
								'value'    => $match,
								'provider' => $this->get_ai_provider( $match ),
							);
						}
						continue;
					}

					$found[] = array(
						'type'     => 'token',
						'value'    => $match,
						'provider' => $this->get_ai_provider( $match ),
					);
				}
			}
		}

		// Find labeled passwords ("password: xxx").
		if ( preg_match_all( self::LABELED_PASSWORD_PATTERN, $text, $matches ) ) {
			foreach ( $matches[2] as $match ) {
				$found[] = array(
					'type'  => 'password',
					'value' => $match,
				);
			}
		}

		// Find site-defined custom patterns.
		foreach ( self::get_custom_patterns() as $custom ) {
			if ( preg_match_all( $custom['regex'], $text, $matches ) ) {
				foreach ( $matches[0] as $match ) {
					$found[] = array(
						'type'     => 'custom',
						'value'    => $match,
						'provider' => $custom['label'],
					);
				}
			}
		}

		return $found;
	}

	/**
	 * Get enabled, valid site-defined custom patterns from settings.
	 *
	 * @since 1.5.0
	 *
	 * @return array {
	 *     List of custom patterns.
	 *
	 *     @type string $label       Human-readable pattern name.
	 *     @type string $regex       Delimited, validated regex.
	 *     @type string $replacement Literal replacement string.
	 * }
	 */
	public static function get_custom_patterns() {
		$settings = get_option( 'piip_settings', array() );
		$rows     = isset( $settings['custom_patterns'] ) && is_array( $settings['custom_patterns'] )
			? $settings['custom_patterns']
			: array();

		$patterns = array();

		foreach ( $rows as $row ) {
			if ( empty( $row['enabled'] ) || empty( $row['pattern'] ) ) {
				continue;
			}

			$regex = '/' . str_replace( '/', '\/', $row['pattern'] ) . '/u';

			// Rows are validated on save, but guard against manual edits.
			if ( false === @preg_match( $regex, '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Probing regex validity.
				continue;
			}

			$patterns[] = array(
				'label'       => isset( $row['label'] ) && '' !== $row['label'] ? $row['label'] : __( 'Custom pattern', 'piip-pii-protection' ),
				'regex'       => $regex,
				'replacement' => isset( $row['replacement'] ) && '' !== $row['replacement'] ? $row['replacement'] : '***',
			);
		}

		return $patterns;
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
			'email'    => 0.95, // Very reliable pattern.
			'card'     => 0.95, // Luhn validation.
			'ssn'      => 0.90, // Format + validation.
			'ip'       => 0.95, // Very reliable pattern.
			'phone'    => 0.70, // Can have false positives.
			'url'      => 0.90, // Reliable pattern.
			'hosting'  => 0.85, // Provider-specific patterns.
			'token'    => 0.60, // Heuristic-based.
			'password' => 0.85, // Labeled matches only.
			'name'     => 0.50, // Context-dependent.
			'dob'      => 0.60, // Date could be anything.
			'custom'   => 1.00, // Exact match of a site-defined pattern.
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
