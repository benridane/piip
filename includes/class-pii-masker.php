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
	 * PII types that are opt-in: masked only when explicitly enabled.
	 *
	 * @since 1.6.0
	 * @var array
	 */
	private const DEFAULT_OFF_TYPES = array( 'name_text' );

	/**
	 * Consent phrases used while the consent setting has never been saved.
	 *
	 * Single source for the runtime check, the settings screen and the
	 * activation defaults, so they cannot drift apart.
	 *
	 * @since 1.7.0
	 * @var array
	 */
	public const DEFAULT_CONSENT_PHRASES = array(
		'マスクを外すことに同意',
		'個人情報の公開に同意します',
		'I consent to unmasking',
		'I consent to sharing my personal information',
	);

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

			case 'ip':
				return $this->mask_ip( $value );

			default:
				return $value;
		}
	}

	/**
	 * Check if value contains consent phrase.
	 *
	 * @since 1.2.2
	 * @since 1.4.1 Made public for the masking preview feature.
	 * @since 1.7.0 Falls back to the default phrases while the setting has
	 *              never been saved, and matches case-insensitively in
	 *              multibyte text.
	 *
	 * @param string $value The value to check.
	 * @return bool True if consent phrase found, false otherwise.
	 */
	public function has_consent_phrase( $value ) {
		$value = (string) $value;

		foreach ( $this->get_enabled_consent_phrases() as $phrase ) {
			if ( false !== mb_stripos( $value, $phrase ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the consent phrases that currently bypass masking.
	 *
	 * An unsaved setting means the defaults apply; a saved empty list means
	 * no phrase bypasses masking.
	 *
	 * @since 1.7.0
	 *
	 * @return array Enabled phrase strings.
	 */
	public function get_enabled_consent_phrases() {
		if ( ! isset( $this->settings['consent_phrases'] ) ) {
			return self::DEFAULT_CONSENT_PHRASES;
		}

		if ( ! is_array( $this->settings['consent_phrases'] ) ) {
			return array();
		}

		$enabled = array();
		foreach ( $this->settings['consent_phrases'] as $phrase_config ) {
			if ( ! empty( $phrase_config['enabled'] ) && ! empty( $phrase_config['phrase'] ) ) {
				$enabled[] = (string) $phrase_config['phrase'];
			}
		}

		return $enabled;
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
	 * Example: 4532-1234-5678-9014 -> ****-****-****-9014
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
	 * @since 1.6.0 Tokens shorter than 32 characters are no longer passed
	 *              through unmasked: 12-31 chars keep only the first 4
	 *              (provider prefix hint), anything shorter becomes ***.
	 *
	 * @param string $token Token to mask.
	 * @return string Masked token.
	 */
	public function mask_token( $token ) {
		$length = strlen( $token );

		if ( $length >= 32 ) {
			// Show first 4 and last 4 characters.
			return substr( $token, 0, 4 ) . str_repeat( '*', $length - 8 ) . substr( $token, -4 );
		}

		if ( $length >= 12 ) {
			return substr( $token, 0, 4 ) . '***';
		}

		return '***';
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
	 * @since 1.7.0 Falls back to per-segment masking when masking would
	 *              change the HTML markup.
	 *
	 * @param string $text Text content to scan and mask.
	 * @return string Text with PII masked.
	 */
	public function mask_text( $text ) {
		if ( ! is_string( $text ) || empty( $text ) ) {
			return $text;
		}

		$masked = $this->mask_plain_text( $text );

		// Masking can run after HTML sanitization (BuddyPress activity and
		// profile fields, user profiles, comment edits). A match that spans
		// markup would otherwise be able to rewrite tags or attributes, so
		// any change to the markup falls back to masking text and attribute
		// values one by one, which cannot alter the structure.
		if ( $masked !== $text && false !== strpos( $text, '<' )
			&& self::get_markup_signature( $text ) !== self::get_markup_signature( $masked ) ) {
			return $this->mask_html_segments( $text );
		}

		return $masked;
	}

	/**
	 * Mask HTML piece by piece: text runs and attribute values separately.
	 *
	 * @since 1.7.0
	 *
	 * @param string $html HTML fragment.
	 * @return string Masked HTML with the original markup structure.
	 */
	private function mask_html_segments( $html ) {
		$output = '';

		foreach ( wp_html_split( $html ) as $segment ) {
			if ( '' === $segment ) {
				continue;
			}

			// Text between tags.
			if ( '<' !== $segment[0] ) {
				$output .= $this->mask_plain_text( $segment );
				continue;
			}

			// Comments (including block delimiters) and CDATA stay as they are.
			if ( 0 === strpos( $segment, '<!' ) ) {
				$output .= $segment;
				continue;
			}

			$processor = new WP_HTML_Tag_Processor( $segment );
			if ( ! $processor->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
				// Not a parsable tag: treat it as text, escaped so it stays text.
				$output .= esc_html( $this->mask_plain_text( $segment ) );
				continue;
			}

			foreach ( (array) $processor->get_attribute_names_with_prefix( '' ) as $name ) {
				$value = $processor->get_attribute( $name );
				if ( ! is_string( $value ) || '' === $value ) {
					continue;
				}

				$masked_value = $this->mask_plain_text( $value );
				if ( $masked_value !== $value ) {
					// set_attribute() encodes the value for its context.
					$processor->set_attribute( $name, $masked_value );
				}
			}

			$output .= $processor->get_updated_html();
		}

		return $output;
	}

	/**
	 * Describe the markup of an HTML fragment: tags, closers, attribute names.
	 *
	 * Two fragments with the same signature have the same elements and
	 * attributes, only different attribute values and text.
	 *
	 * @since 1.7.0
	 *
	 * @param string $html HTML fragment.
	 * @return string Signature.
	 */
	private static function get_markup_signature( $html ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		$parts     = array();

		while ( $processor->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			// Line breaks inside a redacted block (e.g. a PEM key pasted into
			// a paragraph) may disappear with it; that cannot alter markup.
			if ( 'BR' === $processor->get_tag() ) {
				continue;
			}

			$names = (array) $processor->get_attribute_names_with_prefix( '' );
			sort( $names );
			$parts[] = ( $processor->is_tag_closer() ? '/' : '' ) . $processor->get_tag() . '[' . implode( ',', $names ) . ']';
		}

		if ( $processor->paused_at_incomplete_token() ) {
			$parts[] = '#incomplete';
		}

		return implode( ' ', $parts );
	}

	/**
	 * Mask plain text (no HTML awareness), full-width aware.
	 *
	 * Rules run on normalized text (full-width alphanumerics folded to
	 * ASCII); characters that masking left alone are then taken from the
	 * original, so full-width text stays full-width. Site-defined custom
	 * patterns run last, on the original characters.
	 *
	 * @since 1.7.0
	 *
	 * @param string $text Text content to scan and mask.
	 * @return string Text with PII masked.
	 */
	private function mask_plain_text( $text ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return $text;
		}

		$normalized = PIIP_PII_Patterns::normalize( $text );
		$masked     = $this->apply_text_rules( $normalized );

		if ( $normalized !== $text ) {
			$masked = $masked === $normalized
				? $text
				: PIIP_PII_Patterns::restore_original_chars( $text, $normalized, $masked );
		}

		return $this->mask_custom_patterns_in_text( $masked );
	}

	/**
	 * Apply every enabled text rule, in order.
	 *
	 * @since 1.7.0
	 *
	 * @param string $text Normalized text.
	 * @return string Masked text.
	 */
	private function apply_text_rules( $text ) {
		foreach ( PIIP_PII_Patterns::get_text_rules() as $rule ) {
			if ( ! $this->should_mask_type( $rule['type'] ) ) {
				continue;
			}

			$replaced = preg_replace_callback(
				$rule['regex'],
				function ( $m ) use ( $rule ) {
					return $this->mask_rule_match( $rule, $m );
				},
				$text,
				-1,
				$count,
				PREG_OFFSET_CAPTURE
			);

			if ( null !== $replaced ) {
				$text = $replaced;
			}
		}

		return $text;
	}

	/**
	 * Build the replacement for one rule match: only the value group changes.
	 *
	 * @since 1.7.0
	 *
	 * @param array $rule Rule.
	 * @param array $m    Match with offsets (PREG_OFFSET_CAPTURE).
	 * @return string Replacement for the whole match.
	 */
	private function mask_rule_match( array $rule, array $m ) {
		$whole = $m[0][0];
		$group = isset( $rule['group'] ) ? (int) $rule['group'] : 0;

		if ( ! isset( $m[ $group ] ) || -1 === $m[ $group ][1] || '' === $m[ $group ][0] ) {
			return $whole;
		}

		$value = $m[ $group ][0];
		if ( ! PIIP_PII_Patterns::validate( $rule, $value, $this->detector ) ) {
			return $whole;
		}

		if ( isset( $rule['replace'] ) ) {
			$masked = (string) $rule['replace'];
		} elseif ( isset( $rule['mask'] ) && method_exists( $this, $rule['mask'] ) ) {
			$masked = (string) $this->{$rule['mask']}( $value );
		} elseif ( isset( $rule['mask'] ) && is_callable( $rule['mask'] ) ) {
			$masked = (string) call_user_func( $rule['mask'], $value );
		} else {
			$masked = '***';
		}

		$offset = $m[ $group ][1] - $m[0][1];

		return substr( $whole, 0, $offset ) . $masked . substr( $whole, $offset + strlen( $value ) );
	}

	/**
	 * Keep the first four characters: Basic auth base64 and similar.
	 *
	 * @since 1.7.0
	 *
	 * @param string $value Value.
	 * @return string Masked value.
	 */
	public function mask_keep_prefix( $value ) {
		return substr( $value, 0, 4 ) . '***';
	}

	/**
	 * Mask an identity document number completely.
	 *
	 * @since 1.7.0
	 *
	 * @param string $value Document number.
	 * @return string Asterisks of the same length.
	 */
	public function mask_id_doc( $value ) {
		return str_repeat( '*', max( 4, mb_strlen( $value, 'UTF-8' ) ) );
	}

	/**
	 * Mask a labeled person name: keep the first character of each part.
	 *
	 * Unlike mask_name(), one-character parts are masked too, and
	 * honorifics (様, さん, 殿) are kept.
	 *
	 * @since 1.7.0
	 *
	 * @param string $name Name.
	 * @return string Masked name.
	 */
	public function mask_person_name( $name ) {
		$parts = preg_split( '/( +)/u', $name, -1, PREG_SPLIT_DELIM_CAPTURE );
		$out   = '';

		foreach ( $parts as $part ) {
			if ( '' === trim( $part ) || preg_match( '/^(?:様|さん|殿|御中)$/u', $part ) ) {
				$out .= $part;
				continue;
			}

			$length = mb_strlen( $part, 'UTF-8' );
			$out   .= 1 === $length ? '*' : mb_substr( $part, 0, 1, 'UTF-8' ) . str_repeat( '*', $length - 1 );
		}

		return $out;
	}

	/**
	 * Mask a labeled address, keeping only a leading prefecture or city.
	 *
	 * @since 1.7.0
	 *
	 * @param string $address Address.
	 * @return string Masked address.
	 */
	public function mask_labeled_address( $address ) {
		$lead = '/^(?:' . PIIP_PII_Patterns::PREFECTURES . '|' . PIIP_PII_Patterns::DESIGNATED_CITIES . '|' . PIIP_PII_Patterns::TOKYO_WARDS . ')/u';

		if ( preg_match( $lead, $address, $m ) ) {
			return $m[0] . '***';
		}

		return '***';
	}

	/**
	 * Mask an account, member or login ID: keep the first character.
	 *
	 * @since 1.7.0
	 *
	 * @param string $value ID.
	 * @return string Masked ID.
	 */
	public function mask_account_id( $value ) {
		return mb_substr( $value, 0, 1, 'UTF-8' ) . '***';
	}

	/**
	 * Mask a secret URL (webhook): keep scheme and host.
	 *
	 * @since 1.7.0
	 *
	 * @param string $url URL.
	 * @return string Masked URL.
	 */
	public function mask_url_secret( $url ) {
		if ( preg_match( '#^[a-z][a-z0-9+.-]*://[^/]+/#i', $url, $m ) ) {
			return $m[0] . '***';
		}

		return '***';
	}

	/**
	 * Mask site-defined custom patterns found in text.
	 *
	 * Patterns are managed on the settings page and validated on save;
	 * each match is replaced with the pattern's literal replacement string.
	 *
	 * @since 1.5.0
	 *
	 * @param string $text Text to process.
	 * @return string Text with custom patterns masked.
	 */
	private function mask_custom_patterns_in_text( $text ) {
		foreach ( PIIP_PII_Detector::get_custom_patterns() as $custom ) {
			// Escape backslashes and dollar signs so the replacement stays literal.
			$replaced = preg_replace( $custom['regex'], addcslashes( $custom['replacement'], '\\$' ), $text );

			if ( null !== $replaced ) {
				$text = $replaced;
			}
		}

		return $text;
	}

	/**
	 * Mask a date of birth value.
	 *
	 * @since 1.6.0
	 *
	 * @param string $date Date value to mask.
	 * @return string Masked date.
	 */
	public function mask_dob( $date ) {
		if ( false !== mb_strpos( $date, '年' ) ) {
			return '****年**月**日';
		}

		return '****-**-**';
	}

	/**
	 * Mask a bank account number, keeping the last 4 digits.
	 *
	 * @since 1.6.0
	 *
	 * @param string $account Account number to mask.
	 * @return string Masked account number.
	 */
	public function mask_bank( $account ) {
		return '***' . mb_substr( $account, -4 );
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
	 * Mask IP address.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ip IP address to mask.
	 * @return string Masked IP.
	 */
	public function mask_ip( $ip ) {
		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return $this->mask_ipv6( $ip );
		}

		$parts = explode( '.', $ip );
		if ( 4 === count( $parts ) ) {
			// Keep first octet, mask the rest.
			return $parts[0] . '.***.***.' . $parts[3];
		}
		return '***.***.***.***';
	}

	/**
	 * Mask an IPv6 address.
	 *
	 * Keeps the first two groups (the provider-level /32 prefix) and hides
	 * the rest: 2001:db8:85a3::8a2e:370:7334 -> 2001:db8:***.
	 *
	 * @since 1.7.0
	 *
	 * @param string $ip IPv6 address.
	 * @return string Masked IPv6 address.
	 */
	private function mask_ipv6( $ip ) {
		$packed = inet_pton( $ip );
		if ( false === $packed ) {
			return '***';
		}

		$groups = array_map(
			function ( $group ) {
				$group = ltrim( $group, '0' );
				return '' === $group ? '0' : $group;
			},
			str_split( bin2hex( $packed ), 4 )
		);

		return $groups[0] . ':' . $groups[1] . ':***';
	}

	/**
	 * Check if a PII type should be masked based on settings.
	 *
	 * An explicit mask_{type} setting always wins. When the setting is
	 * missing (fresh site, pre-upgrade option shape), the type's default
	 * applies: enabled for everything except the opt-in types listed in
	 * DEFAULT_OFF_TYPES.
	 *
	 * @since 1.0.0
	 * @since 1.6.0 Made public; missing settings now honor per-type defaults.
	 *
	 * @param string|null $pii_type The PII type to check.
	 * @return bool True if should mask, false otherwise.
	 */
	public function should_mask_type( $pii_type ) {
		if ( null === $pii_type ) {
			return false;
		}

		$setting_key = 'mask_' . $pii_type;
		if ( isset( $this->settings[ $setting_key ] ) ) {
			return ! empty( $this->settings[ $setting_key ] );
		}

		return ! in_array( $pii_type, self::DEFAULT_OFF_TYPES, true );
	}
}
