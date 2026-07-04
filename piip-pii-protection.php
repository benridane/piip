<?php
/**
 * Plugin Name:       PIIP - PII Protection
 * Plugin URI:        https://benridane.com/piip
 * Description:       Automatically masks personally identifiable information (PII) in community plugins to protect user privacy.
 * Version:           1.4.1
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Author:            Benridane
 * Author URI:        https://benridane.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       piip-pii-protection
 *
 * @package PIIP
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'PIIP_VERSION', '1.4.1' );
define( 'PIIP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PIIP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PIIP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main PIIP Plugin Class
 *
 * @since 1.0.0
 */
class PIIP_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var PIIP_Plugin
	 */
	private static $instance = null;

	/**
	 * PII Detector instance.
	 *
	 * @since 1.0.0
	 * @var PIIP_PII_Detector
	 */
	public $detector;

	/**
	 * PII Masker instance.
	 *
	 * @since 1.0.0
	 * @var PIIP_PII_Masker
	 */
	public $masker;

	/**
	 * Content scanner instance.
	 *
	 * @since 1.5.0
	 * @var PIIP_Content_Scanner
	 */
	public $scanner;

	/**
	 * Active integrations.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $integrations = array();

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return PIIP_Plugin Singleton instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required dependencies.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_dependencies() {
		// Load core classes.
		require_once PIIP_PLUGIN_DIR . 'includes/class-pii-detector.php';
		require_once PIIP_PLUGIN_DIR . 'includes/class-pii-masker.php';
		require_once PIIP_PLUGIN_DIR . 'includes/class-content-scanner.php';

		// Load admin classes.
		if ( is_admin() ) {
			require_once PIIP_PLUGIN_DIR . 'admin/class-admin-settings.php';
			require_once PIIP_PLUGIN_DIR . 'admin/class-preview-ajax.php';
			require_once PIIP_PLUGIN_DIR . 'admin/class-scan-page.php';
		}

		// Load integration base class and integrations.
		require_once PIIP_PLUGIN_DIR . 'integrations/class-base-integration.php';
		require_once PIIP_PLUGIN_DIR . 'integrations/class-wpforo-integration.php';
		require_once PIIP_PLUGIN_DIR . 'integrations/class-buddypress-integration.php';
		require_once PIIP_PLUGIN_DIR . 'integrations/class-bbpress-integration.php';
		require_once PIIP_PLUGIN_DIR . 'integrations/class-comments-integration.php';
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_hooks() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialize plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init() {
		$this->maybe_migrate_settings();

		// Initialize core components.
		$this->detector = new PIIP_PII_Detector();
		$this->masker   = new PIIP_PII_Masker( $this->detector );
		$this->scanner  = new PIIP_Content_Scanner( $this->masker, $this->detector );

		// Initialize admin.
		if ( is_admin() ) {
			new PIIP_Admin_Settings();
			new PIIP_Preview_Ajax( $this->masker, $this->detector );
			new PIIP_Scan_Page( $this->scanner );
		}

		// Initialize community plugin integrations.
		$this->init_integrations();
	}

	/**
	 * Migrate legacy nested integration settings to flat keys.
	 *
	 * Activation defaults before 1.5.0 stored integrations as a nested
	 * `integrations` array, while the settings page saves and
	 * init_integrations() reads flat `integration_<slug>` keys. Until the
	 * settings were saved once, enabled integrations never initialized.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	private function maybe_migrate_settings() {
		$settings = get_option( 'piip_settings', array() );

		if ( ! is_array( $settings ) || ! isset( $settings['integrations'] ) || ! is_array( $settings['integrations'] ) ) {
			return;
		}

		foreach ( $settings['integrations'] as $slug => $enabled ) {
			$flat_key = 'integration_' . sanitize_key( $slug );
			if ( ! isset( $settings[ $flat_key ] ) ) {
				$settings[ $flat_key ] = $enabled ? 1 : 0;
			}
		}

		unset( $settings['integrations'] );
		update_option( 'piip_settings', $settings );
	}

	/**
	 * Initialize community plugin integrations based on settings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_integrations() {
		$settings = get_option( 'piip_settings', array() );

		// Check if masking is enabled globally.
		if ( empty( $settings['enable_masking'] ) ) {
			return;
		}

		// Available integrations mapping.
		$available_integrations = array(
			'wpforo'     => array(
				'class' => 'PIIP_wpForo_Integration',
				'check' => array( 'PIIP_wpForo_Integration', 'is_plugin_active' ),
			),
			'buddypress' => array(
				'class' => 'PIIP_BuddyPress_Integration',
				'check' => array( 'PIIP_BuddyPress_Integration', 'is_plugin_active' ),
			),
			'bbpress'    => array(
				'class' => 'PIIP_bbPress_Integration',
				'check' => array( 'PIIP_bbPress_Integration', 'is_plugin_active' ),
			),
			'comments'   => array(
				'class' => 'PIIP_Comments_Integration',
				'check' => array( 'PIIP_Comments_Integration', 'is_plugin_active' ),
			),
		);

		/**
		 * Filter available integrations.
		 *
		 * Allows third-party developers to register custom integrations.
		 *
		 * @since 1.2.2
		 *
		 * @param array $available_integrations Array of integration configurations.
		 *                                      Each integration should have 'class' and 'check' keys.
		 */
		$available_integrations = apply_filters( 'piip_available_integrations', $available_integrations );

		// Initialize enabled integrations.
		foreach ( $available_integrations as $slug => $integration ) {
			$setting_key = 'integration_' . $slug;

			// Check if this integration is enabled in settings.
			if ( empty( $settings[ $setting_key ] ) ) {
				continue;
			}

			// Check if the target plugin is active.
			if ( ! is_callable( $integration['check'] ) || ! call_user_func( $integration['check'] ) ) {
				continue;
			}

			// Allow custom integration initialization.
			$custom_instance = apply_filters( 'piip_custom_integration_instance', null, $slug, $integration );
			if ( null !== $custom_instance ) {
				$this->integrations[ $slug ] = $custom_instance;
				continue;
			}

			// Initialize the integration.
			$class_name = $integration['class'];
			if ( class_exists( $class_name ) ) {
				$this->integrations[ $slug ] = new $class_name( $this->masker, $this->detector );
			}
		}
	}

	/**
	 * Get active integrations.
	 *
	 * @since 1.0.0
	 *
	 * @return array Active integration instances.
	 */
	public function get_integrations() {
		return $this->integrations;
	}

	/**
	 * Plugin activation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function activate() {
		// Set default settings.
		$default_settings = array(
			'enable_masking'         => 1,
			'mask_email'             => 1,
			'mask_phone'             => 1,
			'mask_address'           => 1,
			'mask_card'              => 1,
			'mask_ssn'               => 1,
			'mask_password'          => 1,
			'mask_token'             => 1,
			'mask_ip'                => 1,
			'mask_hosting_id'        => 1,
			'integration_comments'   => 1,
			'integration_wpforo'     => 0,
			'integration_buddypress' => 0,
			'integration_bbpress'    => 0,
			'consent_phrases'        => array(
				array(
					'phrase'  => 'I consent to share my personal information',
					'enabled' => 1,
				),
			),
		);

		add_option( 'piip_settings', $default_settings );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function deactivate() {
		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}

// Initialize plugin.
// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Helper function for accessing plugin instance.
/**
 * Get plugin instance.
 *
 * @since 1.0.0
 *
 * @return PIIP_Plugin Plugin instance.
 */
function piip() {
	return PIIP_Plugin::get_instance();
}
// phpcs:enable Universal.Files.SeparateFunctionsFromOO.Mixed

/**
 * Mask text content using PIIP.
 *
 * Convenience function for simple text masking.
 *
 * @since 1.2.2
 *
 * @param string $text The text content to mask.
 * @return string Masked text content.
 */
function piip_mask_text( $text ) {
	$plugin = piip();
	if ( isset( $plugin->masker ) ) {
		return $plugin->masker->mask_text_simple( $text );
	}
	return $text;
}

piip();
