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
	 * PII Logger instance.
	 *
	 * @since 1.0.0
	 * @var PIIP_PII_Logger
	 */
	protected $logger;

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
	 * @param PIIP_PII_Logger   $logger   Logger instance.
	 * @param PIIP_PII_Detector $detector Detector instance.
	 */
	public function __construct( $masker, $logger, $detector ) {
		$this->masker   = $masker;
		$this->logger   = $logger;
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

		$masked_content = $this->masker->mask_text( $content );

		// Log if content was changed.
		if ( $masked_content !== $content ) {
			$this->log_masking_event( $context, $item_id, $content, $masked_content );
		}

		return $masked_content;
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
		$this->logger->log_masking_event(
			array(
				'form_id'        => $item_id,
				'form_type'      => $this->slug,
				'field_name'     => $context,
				'field_label'    => $context,
				'pii_type'       => 'mixed',
				'original_value' => $original_value,
				'masked_value'   => $masked_value,
				'masking_method' => 'server_side',
			)
		);
	}
}
