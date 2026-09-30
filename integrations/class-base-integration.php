<?php
/**
 * Base Integration Class
 *
 * Abstract base class for all plugin integrations.
 *
 * @package    PIIP
 * @subpackage PIIP/integrations
 * @since      1.0.0
 * @license    GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Class PIIP_Base_Integration
 *
 * Base class for plugin integrations.
 *
 * @since 1.0.0
 */
abstract class PIIP_Base_Integration {

	/**
	 * Integration slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $slug;

	/**
	 * Integration name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $name;

	/**
	 * PII Masker instance.
	 *
	 * @since 1.0.0
	 * @var PIIP_PII_Masker
	 */
	protected $masker;

	/**
	 * PII Detector instance.
	 *
	 * @since 1.0.0
	 * @var PIIP_PII_Detector
	 */
	protected $detector;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param PIIP_PII_Masker   $masker   Masker instance.
	 * @param PIIP_PII_Detector $detector Detector instance.
	 */
	public function __construct( $masker, $detector ) {
		$this->masker   = $masker;
		$this->detector = $detector;

		$this->init_hooks();
	}

	/**
	 * Initialize hooks for this integration.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	abstract protected function init_hooks();

	/**
	 * Check if the target plugin is active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if active, false otherwise.
	 */
	abstract public static function is_plugin_active();

	/**
	 * Get integration slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string Integration slug.
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Get integration name.
	 *
	 * @since 1.0.0
	 *
	 * @return string Integration name.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Mask text content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Content to mask.
	 * @param string $context Context for logging.
	 * @param int    $item_id Item ID for logging.
	 * @return string Masked content.
	 */
	protected function mask_content( $content, $context = '', $item_id = 0 ) {
		if ( empty( $content ) ) {
			return $content;
		}

		// Check for consent phrase - skip masking if user consents.
		if ( $this->has_consent_phrase( $content ) ) {
			$this->log_consent_event( $context, $item_id );
			return $content;
		}

		$masked_content = $this->masker->mask_text( $content );

		// Log if content was changed.
		if ( $masked_content !== $content ) {
			$this->log_masking_event( $context, $item_id, $content, $masked_content );
		}

		return $masked_content;
	}

	/**
	 * Check if content contains an enabled consent phrase.
	 *
	 * @since 1.0.0
	 * @since 1.7.0 Delegates to PIIP_PII_Masker so submissions, the preview
	 *              and the scanner share one consent rule.
	 *
	 * @param string $content Content to check.
	 * @return bool True if consent phrase found.
	 */
	protected function has_consent_phrase( $content ) {
		return $this->masker->has_consent_phrase( $content );
	}

	/**
	 * Get enabled consent phrases from settings.
	 *
	 * @since 1.0.0
	 * @since 1.7.0 Delegates to PIIP_PII_Masker::get_enabled_consent_phrases().
	 *
	 * @return array Array of enabled phrase strings.
	 */
	protected function get_enabled_consent_phrases() {
		return $this->masker->get_enabled_consent_phrases();
	}

	/**
	 * Get default consent phrases.
	 *
	 * @since 1.0.0
	 * @since 1.7.0 Returns PIIP_PII_Masker::DEFAULT_CONSENT_PHRASES.
	 *
	 * @return array Default phrases.
	 */
	protected function get_default_consent_phrases() {
		return PIIP_PII_Masker::DEFAULT_CONSENT_PHRASES;
	}

	/**
	 * Log consent opt-out event.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context Context/field name.
	 * @param int    $item_id Item ID.
	 * @return void
	 */
	protected function log_consent_event( $context, $item_id ) {
		// No logging for privacy protection.
	}

	/**
	 * Log masking event.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context         Context/field name.
	 * @param int    $item_id         Item ID.
	 * @param string $original_value  Original value.
	 * @param string $masked_value    Masked value.
	 * @return void
	 */
	protected function log_masking_event( $context, $item_id, $original_value, $masked_value ) {
		// No logging for privacy protection.
	}
}
