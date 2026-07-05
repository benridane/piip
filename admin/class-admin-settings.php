<?php
/**
 * Admin Settings Page
 *
 * Handles plugin settings page in WordPress admin.
 *
 * @package    PIIP
 * @subpackage PIIP/admin
 * @since      1.0.0
 * @license    GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class PIIP_Admin_Settings
 *
 * Manages plugin settings using WordPress Settings API.
 *
 * @since 1.0.0
 */
class PIIP_Admin_Settings {

	/**
	 * Available integrations.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $available_integrations = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		$this->setup_available_integrations();
	}

	/**
	 * Setup available integrations list.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function setup_available_integrations() {
		$this->available_integrations = array(
			'comments'   => array(
				'name'        => 'Comments',
				'description' => __( 'Native WordPress comment system', 'piip-pii-protection' ),
				'class'       => 'PIIP_Comments_Integration',
				'check'       => array( 'PIIP_Comments_Integration', 'is_plugin_active' ),
			),
			'users'      => array(
				'name'        => 'User Profiles',
				'description' => __( 'Masks PII written into publicly visible profile fields (display name, nickname, biographical info). Account email and website are not touched.', 'piip-pii-protection' ),
				'class'       => 'PIIP_Users_Integration',
				'check'       => array( 'PIIP_Users_Integration', 'is_plugin_active' ),
			),
			'wpforo'     => array(
				'name'        => 'wpForo',
				'description' => __( 'Forum discussions and private messages', 'piip-pii-protection' ),
				'class'       => 'PIIP_wpForo_Integration',
				'check'       => array( 'PIIP_wpForo_Integration', 'is_plugin_active' ),
			),
			'buddypress' => array(
				'name'        => 'BuddyPress',
				'description' => __( 'Activities, profiles, and messages', 'piip-pii-protection' ),
				'class'       => 'PIIP_BuddyPress_Integration',
				'check'       => array( 'PIIP_BuddyPress_Integration', 'is_plugin_active' ),
			),
			'bbpress'    => array(
				'name'        => 'bbPress',
				'description' => __( 'Forum topics and replies', 'piip-pii-protection' ),
				'class'       => 'PIIP_bbPress_Integration',
				'check'       => array( 'PIIP_bbPress_Integration', 'is_plugin_active' ),
			),
			'cf7'        => array(
				'name'        => 'Contact Form 7',
				'description' => __( 'Masks free-text (message) fields in form submissions; dedicated fields such as name and email are kept for replies. Affects both the sent mail and stored copies (e.g. Flamingo).', 'piip-pii-protection' ),
				'class'       => 'PIIP_CF7_Integration',
				'check'       => array( 'PIIP_CF7_Integration', 'is_plugin_active' ),
			),
		);

		/**
		 * Filter available integrations for admin settings.
		 *
		 * Allows third-party developers to add custom integrations to the settings page.
		 *
		 * @since 1.2.2
		 *
		 * @param array $available_integrations Array of integration configurations.
		 *                                      Each integration should have 'name', 'description', 'class', and 'check' keys.
		 */
		$this->available_integrations = apply_filters( 'piip_admin_available_integrations', $this->available_integrations );
	}

	/**
	 * Get available integrations.
	 *
	 * @since 1.0.0
	 *
	 * @return array Available integrations.
	 */
	public function get_available_integrations() {
		return $this->available_integrations;
	}

	/**
	 * Add admin menu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'PIIP Settings', 'piip-pii-protection' ),
			__( 'PII Protection', 'piip-pii-protection' ),
			'manage_options',
			'piip-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		if ( 'settings_page_piip-settings' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script( 'jquery' );

		$phrases = get_option( 'piip_settings', array() );
		$phrases = isset( $phrases['consent_phrases'] ) ? $phrases['consent_phrases'] : array();

		$script = '
		jQuery(document).ready(function($) {
			var phraseIndex = ' . count( $phrases ) . ";

			$('#piip-add-phrase').on('click', function() {
				var newRow = '<div class=\"piip-phrase-row\" style=\"margin-bottom: 8px; display: flex; align-items: center; gap: 8px;\">' +
					'<input type=\"checkbox\" name=\"piip_settings[consent_phrases][' + phraseIndex + '][enabled]\" value=\"1\" checked>' +
					'<input type=\"text\" name=\"piip_settings[consent_phrases][' + phraseIndex + '][phrase]\" value=\"\" class=\"regular-text\" style=\"flex: 1;\" placeholder=\"" . esc_js( __( 'Enter consent phrase...', 'piip-pii-protection' ) ) . "\">' +
					'<button type=\"button\" class=\"button piip-remove-phrase\" style=\"color: #a00;\">" . esc_js( __( 'Remove', 'piip-pii-protection' ) ) . "</button>' +
					'</div>';
				$('#piip-consent-phrases').append(newRow);
				phraseIndex++;
			});

			$(document).on('click', '.piip-remove-phrase', function() {
				$(this).closest('.piip-phrase-row').remove();
			});

			var patternIndex = $('#piip-custom-patterns .piip-pattern-row').length;

			$('#piip-add-pattern').on('click', function() {
				var newRow = '<div class=\"piip-pattern-row\" style=\"margin-bottom: 8px; display: flex; align-items: center; gap: 8px;\">' +
					'<input type=\"checkbox\" name=\"piip_settings[custom_patterns][' + patternIndex + '][enabled]\" value=\"1\" checked>' +
					'<input type=\"text\" name=\"piip_settings[custom_patterns][' + patternIndex + '][label]\" value=\"\" placeholder=\"" . esc_js( __( 'Name (e.g. Employee ID)', 'piip-pii-protection' ) ) . "\" style=\"width: 160px;\">' +
					'<input type=\"text\" name=\"piip_settings[custom_patterns][' + patternIndex + '][pattern]\" value=\"\" class=\"code\" placeholder=\"" . esc_js( __( 'Regex, e.g. EMP-\\d{6}', 'piip-pii-protection' ) ) . "\" style=\"flex: 1;\">' +
					'<input type=\"text\" name=\"piip_settings[custom_patterns][' + patternIndex + '][replacement]\" value=\"\" class=\"code\" placeholder=\"***\" style=\"width: 140px;\">' +
					'<button type=\"button\" class=\"button piip-remove-pattern\" style=\"color: #a00;\">" . esc_js( __( 'Remove', 'piip-pii-protection' ) ) . "</button>' +
					'</div>';
				$('#piip-custom-patterns').append(newRow);
				patternIndex++;
			});

			$(document).on('click', '.piip-remove-pattern', function() {
				$(this).closest('.piip-pattern-row').remove();
			});
		});
		";

		wp_add_inline_script( 'jquery', $script );

		wp_enqueue_script(
			'piip-admin-preview',
			PIIP_PLUGIN_URL . 'admin/js/preview.js',
			array( 'jquery' ),
			PIIP_VERSION,
			true
		);
		wp_localize_script(
			'piip-admin-preview',
			'piipPreview',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'piip_preview_mask' ),
				'i18n'    => array(
					'checking'    => __( 'Checking…', 'piip-pii-protection' ),
					'error'       => __( 'Preview request failed. Please try again.', 'piip-pii-protection' ),
					'masked'      => __( 'Masked', 'piip-pii-protection' ),
					'notMasked'   => __( 'Not masked', 'piip-pii-protection' ),
					'disabled'    => __( 'Disabled in settings', 'piip-pii-protection' ),
					'notMaskable' => __( 'Detected only (not masked)', 'piip-pii-protection' ),
				),
			)
		);
	}

	/**
	 * Register settings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_settings() {
		// Register setting.
		register_setting(
			'piip_settings_group',
			'piip_settings',
			array( $this, 'sanitize_settings' )
		);

		// General settings section.
		add_settings_section(
			'piip_general_section',
			__( 'General Settings', 'piip-pii-protection' ),
			array( $this, 'general_section_callback' ),
			'piip-settings'
		);

		// WordPress Core section.
		add_settings_section(
			'piip_wordpress_core_section',
			__( 'Core', 'piip-pii-protection' ),
			array( $this, 'wordpress_core_section_callback' ),
			'piip-settings'
		);

		// Integrations section.
		add_settings_section(
			'piip_integrations_section',
			__( 'Plugin Integrations', 'piip-pii-protection' ),
			array( $this, 'integrations_section_callback' ),
			'piip-settings'
		);

		// PII types section.
		add_settings_section(
			'piip_pii_types_section',
			__( 'PII Types to Mask', 'piip-pii-protection' ),
			array( $this, 'pii_types_section_callback' ),
			'piip-settings'
		);

		// Consent phrases section.
		add_settings_section(
			'piip_consent_section',
			__( 'Consent Opt-Out Phrases', 'piip-pii-protection' ),
			array( $this, 'consent_section_callback' ),
			'piip-settings'
		);

		// Custom patterns section.
		add_settings_section(
			'piip_custom_patterns_section',
			__( 'Custom Patterns', 'piip-pii-protection' ),
			array( $this, 'custom_patterns_section_callback' ),
			'piip-settings'
		);

		// Add fields.
		$this->add_general_fields();
		$this->add_wordpress_core_fields();
		$this->add_integration_fields();
		$this->add_pii_type_fields();
		$this->add_consent_fields();
		$this->add_custom_pattern_fields();
	}

	/**
	 * Add general fields.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function add_general_fields() {
		add_settings_field(
			'enable_masking',
			__( 'Enable PII Masking', 'piip-pii-protection' ),
			array( $this, 'checkbox_field_callback' ),
			'piip-settings',
			'piip_general_section',
			array(
				'label_for'   => 'enable_masking',
				'description' => __( 'Enable automatic PII masking globally', 'piip-pii-protection' ),
			)
		);
	}

	/**
	 * Add WordPress Core fields.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function add_wordpress_core_fields() {
		// Core features live in their own section, not under Plugin Integrations.
		foreach ( array( 'comments', 'users' ) as $slug ) {
			if ( ! isset( $this->available_integrations[ $slug ] ) ) {
				continue;
			}

			$integration = $this->available_integrations[ $slug ];
			add_settings_field(
				'integration_' . $slug,
				$integration['name'],
				array( $this, 'integration_field_callback' ),
				'piip-settings',
				'piip_wordpress_core_section',
				array(
					'label_for'   => 'integration_' . $slug,
					'slug'        => $slug,
					'integration' => $integration,
				)
			);
		}
	}

	/**
	 * Add integration fields.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function add_integration_fields() {
		foreach ( $this->available_integrations as $slug => $integration ) {
			// Skip core features; they are in the WordPress Core section.
			if ( in_array( $slug, array( 'comments', 'users' ), true ) ) {
				continue;
			}

			add_settings_field(
				'integration_' . $slug,
				$integration['name'],
				array( $this, 'integration_field_callback' ),
				'piip-settings',
				'piip_integrations_section',
				array(
					'label_for'   => 'integration_' . $slug,
					'slug'        => $slug,
					'integration' => $integration,
				)
			);
		}
	}

	/**
	 * Add PII type fields.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function add_pii_type_fields() {
		$pii_types = array(
			'email'    => __( 'Email Addresses', 'piip-pii-protection' ),
			'phone'    => __( 'Phone Numbers', 'piip-pii-protection' ),
			'address'  => __( 'Addresses', 'piip-pii-protection' ),
			'card'     => __( 'Credit Card Numbers', 'piip-pii-protection' ),
			'ssn'      => __( 'Social Security Numbers', 'piip-pii-protection' ),
			'password' => __( 'Passwords', 'piip-pii-protection' ),
			'token'    => __( 'AI API Keys/Tokens', 'piip-pii-protection' ),
			'ip'       => __( 'IP Addresses', 'piip-pii-protection' ),
			'hosting'  => __( 'Hosting Account IDs', 'piip-pii-protection' ),
			'dob'      => __( 'Dates of Birth', 'piip-pii-protection' ),
			'bank'     => __( 'Bank Account Numbers', 'piip-pii-protection' ),
		);

		// Opt-in types: unchecked when the setting has never been saved.
		$default_off = array( 'name_text' );

		foreach ( $pii_types as $type => $label ) {
			add_settings_field(
				'mask_' . $type,
				$label,
				array( $this, 'checkbox_field_callback' ),
				'piip-settings',
				'piip_pii_types_section',
				array(
					'label_for' => 'mask_' . $type,
					'default'   => in_array( $type, $default_off, true ) ? 0 : 1,
				)
			);
		}
	}

	/**
	 * Add logging fields.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	/**
	 * Add consent phrase fields.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function add_consent_fields() {
		add_settings_field(
			'consent_phrases',
			__( 'Consent Phrases', 'piip-pii-protection' ),
			array( $this, 'consent_phrases_field_callback' ),
			'piip-settings',
			'piip_consent_section',
			array(
				'label_for' => 'consent_phrases',
			)
		);
	}

	/**
	 * Add custom pattern fields.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	private function add_custom_pattern_fields() {
		add_settings_field(
			'custom_patterns',
			__( 'Patterns', 'piip-pii-protection' ),
			array( $this, 'custom_patterns_field_callback' ),
			'piip-settings',
			'piip_custom_patterns_section',
			array(
				'label_for' => 'custom_patterns',
			)
		);
	}

	/**
	 * General section callback.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function general_section_callback() {
		echo '<p>' . esc_html__( 'Configure general PII masking settings.', 'piip-pii-protection' ) . '</p>';
	}

	/**
	 * WordPress Core section callback.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function wordpress_core_section_callback() {
		echo '<p>' . esc_html__( 'Enable PII masking for WordPress core features.', 'piip-pii-protection' ) . '</p>';
	}

	/**
	 * Integrations section callback.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function integrations_section_callback() {
		echo '<p>' . esc_html__( 'Select which community plugins should have PII masking enabled. Only installed and active plugins can be enabled.', 'piip-pii-protection' ) . '</p>';
	}

	/**
	 * PII types section callback.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function pii_types_section_callback() {
		echo '<p>' . esc_html__( 'Select which types of PII should be automatically masked.', 'piip-pii-protection' ) . '</p>';
	}

	/**
	 * Logging section callback.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	/**
	 * Consent section callback.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function consent_section_callback() {
		echo '<p>' . esc_html__( 'When content contains one of these phrases, PII masking will be skipped. Users can include these phrases to share personal information publicly.', 'piip-pii-protection' ) . '</p>';
	}

	/**
	 * Custom patterns section callback.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public function custom_patterns_section_callback() {
		echo '<p>' . esc_html__( 'Mask site-specific identifiers (e.g. employee or member IDs) with your own regular expressions. Each match is replaced with the replacement text.', 'piip-pii-protection' ) . '</p>';
	}

	/**
	 * Integration field callback.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function integration_field_callback( $args ) {
		$options     = get_option( 'piip_settings', array() );
		$slug        = $args['slug'];
		$integration = $args['integration'];
		$field_id    = 'integration_' . $slug;

		// Check if the target plugin is active.
		$is_plugin_active = is_callable( $integration['check'] ) && call_user_func( $integration['check'] );
		$is_enabled       = isset( $options[ $field_id ] ) && $options[ $field_id ];
		$disabled         = $is_plugin_active ? '' : 'disabled';
		$checked_attr     = ( $is_enabled && $is_plugin_active ) ? 'checked="checked"' : '';

		printf(
			'<input type="checkbox" id="%s" name="piip_settings[%s]" value="1" %s %s>',
			esc_attr( $field_id ),
			esc_attr( $field_id ),
			esc_attr( $checked_attr ),
			esc_attr( $disabled )
		);

		// Show status.
		if ( $is_plugin_active ) {
			// Different status text for core features vs plugins.
			if ( in_array( $slug, array( 'comments', 'users' ), true ) ) {
				printf(
					'<span class="piip-status piip-status-active" style="color: green; margin-left: 10px;">%s</span>',
					esc_html__( 'Available', 'piip-pii-protection' )
				);
			} else {
				printf(
					'<span class="piip-status piip-status-active" style="color: green; margin-left: 10px;">%s</span>',
					esc_html__( 'Plugin Active', 'piip-pii-protection' )
				);
			}
		} else {
			printf(
				'<span class="piip-status piip-status-inactive" style="color: #999; margin-left: 10px;">%s</span>',
				esc_html__( 'Plugin Not Installed', 'piip-pii-protection' )
			);
		}

		// Show description.
		printf(
			'<p class="description">%s</p>',
			esc_html( $integration['description'] )
		);
	}

	/**
	 * Checkbox field callback.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function checkbox_field_callback( $args ) {
		$options = get_option( 'piip_settings', array() );
		$default = isset( $args['default'] ) ? (int) $args['default'] : 1;

		if ( ! isset( $options[ $args['label_for'] ] ) ) {
			$checked_attr = $default ? 'checked="checked"' : '';
		} else {
			$checked_attr = $options[ $args['label_for'] ] ? 'checked="checked"' : '';
		}

		printf(
			'<input type="checkbox" id="%s" name="piip_settings[%s]" value="1" %s>',
			esc_attr( $args['label_for'] ),
			esc_attr( $args['label_for'] ),
			esc_attr( $checked_attr )
		);

		if ( isset( $args['description'] ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html( $args['description'] )
			);
		}
	}

	/**
	 * Select field callback.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function select_field_callback( $args ) {
		$options = get_option( 'piip_settings', array() );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '90';

		printf(
			'<select id="%s" name="piip_settings[%s]">',
			esc_attr( $args['label_for'] ),
			esc_attr( $args['label_for'] )
		);

		foreach ( $args['options'] as $option_value => $option_label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $option_value ),
				selected( $value, $option_value, false ),
				esc_html( $option_label )
			);
		}

		echo '</select>';

		if ( isset( $args['description'] ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html( $args['description'] )
			);
		}
	}

	/**
	 * Consent phrases field callback.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Field arguments.
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function consent_phrases_field_callback( $args ) {
		// Parameter preserved for interface compatibility.
		unset( $args ); // Explicitly unset unused parameter.
		$options = get_option( 'piip_settings', array() );
		$phrases = isset( $options['consent_phrases'] ) ? $options['consent_phrases'] : array();

		// Default phrases if none set.
		if ( empty( $phrases ) ) {
			$phrases = array(
				array(
					'phrase'  => 'マスクを外すことに同意',
					'enabled' => true,
				),
				array(
					'phrase'  => '個人情報の公開に同意します',
					'enabled' => true,
				),
				array(
					'phrase'  => 'I consent to unmasking',
					'enabled' => true,
				),
				array(
					'phrase'  => 'I consent to sharing my personal information',
					'enabled' => true,
				),
			);
		}

		echo '<div id="piip-consent-phrases">';

		foreach ( $phrases as $index => $phrase_data ) {
			$phrase  = isset( $phrase_data['phrase'] ) ? $phrase_data['phrase'] : '';
			$enabled = ! empty( $phrase_data['enabled'] );

			printf(
				'<div class="piip-phrase-row" style="margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
					<input type="checkbox" name="piip_settings[consent_phrases][%d][enabled]" value="1" %s>
					<input type="text" name="piip_settings[consent_phrases][%d][phrase]" value="%s" class="regular-text" style="flex: 1;">
					<button type="button" class="button piip-remove-phrase" style="color: #a00;">%s</button>
				</div>',
				absint( $index ),
				checked( $enabled, true, false ),
				absint( $index ),
				esc_attr( $phrase ),
				esc_html__( 'Remove', 'piip-pii-protection' )
			);
		}

		echo '</div>';

		printf(
			'<button type="button" id="piip-add-phrase" class="button" style="margin-top: 8px;">%s</button>',
			esc_html__( '+ Add Phrase', 'piip-pii-protection' )
		);

		echo '<p class="description">' . esc_html__( 'Check to enable a phrase. Uncheck to disable without removing.', 'piip-pii-protection' ) . '</p>';
	}

	/**
	 * Custom patterns field callback.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Field arguments.
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function custom_patterns_field_callback( $args ) {
		unset( $args ); // Explicitly unset unused parameter.
		$options  = get_option( 'piip_settings', array() );
		$patterns = isset( $options['custom_patterns'] ) && is_array( $options['custom_patterns'] ) ? $options['custom_patterns'] : array();

		echo '<div id="piip-custom-patterns">';

		foreach ( $patterns as $index => $pattern_data ) {
			$label       = isset( $pattern_data['label'] ) ? $pattern_data['label'] : '';
			$pattern     = isset( $pattern_data['pattern'] ) ? $pattern_data['pattern'] : '';
			$replacement = isset( $pattern_data['replacement'] ) ? $pattern_data['replacement'] : '';
			$enabled     = ! empty( $pattern_data['enabled'] );

			printf(
				'<div class="piip-pattern-row" style="margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
					<input type="checkbox" name="piip_settings[custom_patterns][%1$d][enabled]" value="1" %2$s>
					<input type="text" name="piip_settings[custom_patterns][%1$d][label]" value="%3$s" placeholder="%6$s" style="width: 160px;">
					<input type="text" name="piip_settings[custom_patterns][%1$d][pattern]" value="%4$s" class="code" placeholder="%7$s" style="flex: 1;">
					<input type="text" name="piip_settings[custom_patterns][%1$d][replacement]" value="%5$s" class="code" placeholder="***" style="width: 140px;">
					<button type="button" class="button piip-remove-pattern" style="color: #a00;">%8$s</button>
				</div>',
				absint( $index ),
				checked( $enabled, true, false ),
				esc_attr( $label ),
				esc_attr( $pattern ),
				esc_attr( $replacement ),
				esc_attr__( 'Name (e.g. Employee ID)', 'piip-pii-protection' ),
				esc_attr__( 'Regex, e.g. EMP-\d{6}', 'piip-pii-protection' ),
				esc_html__( 'Remove', 'piip-pii-protection' )
			);
		}

		echo '</div>';

		printf(
			'<button type="button" id="piip-add-pattern" class="button" style="margin-top: 8px;">%s</button>',
			esc_html__( '+ Add Pattern', 'piip-pii-protection' )
		);

		echo '<p class="description">' . esc_html__( 'Regular expression without delimiters, applied case-sensitively to text content (PCRE, Unicode mode). Invalid expressions are rejected on save. The replacement is inserted literally.', 'piip-pii-protection' ) . '</p>';
	}

	/**
	 * Sanitize settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input Input settings.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// Sanitize checkboxes.
		$checkboxes = array(
			'enable_masking',
			'mask_email',
			'mask_phone',
			'mask_address',
			'mask_card',
			'mask_ssn',
			'mask_password',
			'mask_token',
			'mask_ip',
			'mask_hosting',
			'mask_dob',
			'mask_bank',
		);

		// Add integration checkboxes.
		foreach ( array_keys( $this->available_integrations ) as $slug ) {
			$checkboxes[] = 'integration_' . $slug;
		}

		foreach ( $checkboxes as $checkbox ) {
			$sanitized[ $checkbox ] = isset( $input[ $checkbox ] ) ? 1 : 0;
		}

		// Saving replaces the whole option; keep the schema version.
		$sanitized['settings_version'] = PIIP_SETTINGS_VERSION;

		// Sanitize consent phrases.
		if ( isset( $input['consent_phrases'] ) && is_array( $input['consent_phrases'] ) ) {
			$sanitized['consent_phrases'] = array();
			foreach ( $input['consent_phrases'] as $phrase_data ) {
				if ( ! empty( $phrase_data['phrase'] ) ) {
					$sanitized['consent_phrases'][] = array(
						'phrase'  => sanitize_text_field( $phrase_data['phrase'] ),
						'enabled' => ! empty( $phrase_data['enabled'] ) ? 1 : 0,
					);
				}
			}
			// Re-index array.
			$sanitized['consent_phrases'] = array_values( $sanitized['consent_phrases'] );
		}

		// Sanitize custom patterns.
		if ( isset( $input['custom_patterns'] ) && is_array( $input['custom_patterns'] ) ) {
			$sanitized['custom_patterns'] = array();
			foreach ( $input['custom_patterns'] as $pattern_data ) {
				$pattern = isset( $pattern_data['pattern'] ) ? trim( (string) $pattern_data['pattern'] ) : '';

				if ( '' === $pattern ) {
					continue;
				}

				if ( strlen( $pattern ) > 500 ) {
					add_settings_error(
						'piip_settings',
						'piip_custom_pattern_too_long',
						__( 'A custom pattern was discarded: patterns are limited to 500 characters.', 'piip-pii-protection' )
					);
					continue;
				}

				$regex = '/' . str_replace( '/', '\/', $pattern ) . '/u';
				if ( false === @preg_match( $regex, '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Probing regex validity.
					add_settings_error(
						'piip_settings',
						'piip_custom_pattern_invalid',
						sprintf(
							/* translators: %s: the rejected regular expression. */
							__( 'A custom pattern was discarded because it is not a valid regular expression: %s', 'piip-pii-protection' ),
							$pattern
						)
					);
					continue;
				}

				$sanitized['custom_patterns'][] = array(
					'label'       => isset( $pattern_data['label'] ) ? sanitize_text_field( $pattern_data['label'] ) : '',
					'pattern'     => $pattern,
					'replacement' => isset( $pattern_data['replacement'] ) ? sanitize_text_field( $pattern_data['replacement'] ) : '',
					'enabled'     => ! empty( $pattern_data['enabled'] ) ? 1 : 0,
				);
			}
			$sanitized['custom_patterns'] = array_values( $sanitized['custom_patterns'] );
		}

		return $sanitized;
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'piip-pii-protection' ) );
		}

		require_once plugin_dir_path( __FILE__ ) . 'views/settings-page.php';
	}
}
