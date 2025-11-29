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
			'wpforo'     => array(
				'name'        => 'wpForo',
				'description' => __( 'Forum discussions and private messages', 'piip' ),
				'class'       => 'PIIP_wpForo_Integration',
				'check'       => array( 'PIIP_wpForo_Integration', 'is_plugin_active' ),
			),
			'buddypress' => array(
				'name'        => 'BuddyPress',
				'description' => __( 'Activities, profiles, and messages', 'piip' ),
				'class'       => 'PIIP_BuddyPress_Integration',
				'check'       => array( 'PIIP_BuddyPress_Integration', 'is_plugin_active' ),
			),
			'bbpress'    => array(
				'name'        => 'bbPress',
				'description' => __( 'Forum topics and replies', 'piip' ),
				'class'       => 'PIIP_bbPress_Integration',
				'check'       => array( 'PIIP_bbPress_Integration', 'is_plugin_active' ),
			),
		);
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
			__( 'PIIP Settings', 'piip' ),
			__( 'PII Protection', 'piip' ),
			'manage_options',
			'piip-settings',
			array( $this, 'render_settings_page' )
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
			__( 'General Settings', 'piip' ),
			array( $this, 'general_section_callback' ),
			'piip-settings'
		);

		// Integrations section.
		add_settings_section(
			'piip_integrations_section',
			__( 'Plugin Integrations', 'piip' ),
			array( $this, 'integrations_section_callback' ),
			'piip-settings'
		);

		// PII types section.
		add_settings_section(
			'piip_pii_types_section',
			__( 'PII Types to Mask', 'piip' ),
			array( $this, 'pii_types_section_callback' ),
			'piip-settings'
		);

		// Logging section.
		add_settings_section(
			'piip_logging_section',
			__( 'Logging Settings', 'piip' ),
			array( $this, 'logging_section_callback' ),
			'piip-settings'
		);

		// Consent phrases section.
		add_settings_section(
			'piip_consent_section',
			__( 'Consent Opt-Out Phrases', 'piip' ),
			array( $this, 'consent_section_callback' ),
			'piip-settings'
		);

		// Add fields.
		$this->add_general_fields();
		$this->add_integration_fields();
		$this->add_pii_type_fields();
		$this->add_logging_fields();
		$this->add_consent_fields();
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
			__( 'Enable PII Masking', 'piip' ),
			array( $this, 'checkbox_field_callback' ),
			'piip-settings',
			'piip_general_section',
			array(
				'label_for'   => 'enable_masking',
				'description' => __( 'Enable automatic PII masking globally', 'piip' ),
			)
		);
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
			'email'    => __( 'Email Addresses', 'piip' ),
			'phone'    => __( 'Phone Numbers', 'piip' ),
			'name'     => __( 'Names', 'piip' ),
			'address'  => __( 'Addresses', 'piip' ),
			'card'     => __( 'Credit Card Numbers', 'piip' ),
			'ssn'      => __( 'Social Security Numbers', 'piip' ),
			'password' => __( 'Passwords', 'piip' ),
			'token'    => __( 'Tokens/API Keys', 'piip' ),
		);

		foreach ( $pii_types as $type => $label ) {
			add_settings_field(
				'mask_' . $type,
				$label,
				array( $this, 'checkbox_field_callback' ),
				'piip-settings',
				'piip_pii_types_section',
				array(
					'label_for' => 'mask_' . $type,
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
	private function add_logging_fields() {
		add_settings_field(
			'enable_logging',
			__( 'Enable Logging', 'piip' ),
			array( $this, 'checkbox_field_callback' ),
			'piip-settings',
			'piip_logging_section',
			array(
				'label_for'   => 'enable_logging',
				'description' => __( 'Log all PII masking events to database', 'piip' ),
			)
		);

		add_settings_field(
			'log_retention_days',
			__( 'Log Retention Period', 'piip' ),
			array( $this, 'select_field_callback' ),
			'piip-settings',
			'piip_logging_section',
			array(
				'label_for'   => 'log_retention_days',
				'options'     => array(
					'30'  => __( '30 days', 'piip' ),
					'60'  => __( '60 days', 'piip' ),
					'90'  => __( '90 days (recommended)', 'piip' ),
					'180' => __( '180 days', 'piip' ),
					'365' => __( '1 year', 'piip' ),
				),
				'description' => __( 'Automatically delete logs older than this period (GDPR compliance)', 'piip' ),
			)
		);
	}

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
			__( 'Consent Phrases', 'piip' ),
			array( $this, 'consent_phrases_field_callback' ),
			'piip-settings',
			'piip_consent_section',
			array(
				'label_for' => 'consent_phrases',
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
		echo '<p>' . esc_html__( 'Configure general PII masking settings.', 'piip' ) . '</p>';
	}

	/**
	 * Integrations section callback.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function integrations_section_callback() {
		echo '<p>' . esc_html__( 'Select which community plugins should have PII masking enabled. Only installed and active plugins can be enabled.', 'piip' ) . '</p>';
	}

	/**
	 * PII types section callback.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function pii_types_section_callback() {
		echo '<p>' . esc_html__( 'Select which types of PII should be automatically masked.', 'piip' ) . '</p>';
	}

	/**
	 * Logging section callback.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function logging_section_callback() {
		echo '<p>' . esc_html__( 'Configure logging and data retention settings.', 'piip' ) . '</p>';
	}

	/**
	 * Consent section callback.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function consent_section_callback() {
		echo '<p>' . esc_html__( 'When content contains one of these phrases, PII masking will be skipped. Users can include these phrases to share personal information publicly.', 'piip' ) . '</p>';
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
		$checked          = $is_enabled && $is_plugin_active ? 'checked="checked"' : '';

		printf(
			'<input type="checkbox" id="%s" name="piip_settings[%s]" value="1" %s %s>',
			esc_attr( $field_id ),
			esc_attr( $field_id ),
			$checked, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe HTML attribute.
			esc_attr( $disabled )
		);

		// Show status.
		if ( $is_plugin_active ) {
			printf(
				'<span class="piip-status piip-status-active" style="color: green; margin-left: 10px;">%s</span>',
				esc_html__( 'Plugin Active', 'piip' )
			);
		} else {
			printf(
				'<span class="piip-status piip-status-inactive" style="color: #999; margin-left: 10px;">%s</span>',
				esc_html__( 'Plugin Not Installed', 'piip' )
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
		$checked = isset( $options[ $args['label_for'] ] ) ? checked( $options[ $args['label_for'] ], 1, false ) : '';

		if ( ! isset( $options[ $args['label_for'] ] ) ) {
			$checked = 'checked="checked"'; // Default to enabled.
		}

		printf(
			'<input type="checkbox" id="%s" name="piip_settings[%s]" value="1" %s>',
			esc_attr( $args['label_for'] ),
			esc_attr( $args['label_for'] ),
			$checked // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $checked is from checked() function which returns safe HTML attribute string.
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
	public function consent_phrases_field_callback( $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
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
				esc_html__( 'Remove', 'piip' )
			);
		}

		echo '</div>';

		printf(
			'<button type="button" id="piip-add-phrase" class="button" style="margin-top: 8px;">%s</button>',
			esc_html__( '+ Add Phrase', 'piip' )
		);

		echo '<p class="description">' . esc_html__( 'Check to enable a phrase. Uncheck to disable without removing.', 'piip' ) . '</p>';

		// JavaScript for add/remove functionality.
		?>
		<script>
		jQuery(document).ready(function($) {
			var phraseIndex = <?php echo count( $phrases ); ?>;

			$('#piip-add-phrase').on('click', function() {
				var newRow = '<div class="piip-phrase-row" style="margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">' +
					'<input type="checkbox" name="piip_settings[consent_phrases][' + phraseIndex + '][enabled]" value="1" checked>' +
					'<input type="text" name="piip_settings[consent_phrases][' + phraseIndex + '][phrase]" value="" class="regular-text" style="flex: 1;" placeholder="<?php echo esc_js( __( 'Enter consent phrase...', 'piip' ) ); ?>">' +
					'<button type="button" class="button piip-remove-phrase" style="color: #a00;"><?php echo esc_js( __( 'Remove', 'piip' ) ); ?></button>' +
					'</div>';
				$('#piip-consent-phrases').append(newRow);
				phraseIndex++;
			});

			$(document).on('click', '.piip-remove-phrase', function() {
				$(this).closest('.piip-phrase-row').remove();
			});
		});
		</script>
		<?php
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
			'mask_name',
			'mask_address',
			'mask_card',
			'mask_ssn',
			'mask_password',
			'mask_token',
			'enable_logging',
		);

		// Add integration checkboxes.
		foreach ( array_keys( $this->available_integrations ) as $slug ) {
			$checkboxes[] = 'integration_' . $slug;
		}

		foreach ( $checkboxes as $checkbox ) {
			$sanitized[ $checkbox ] = isset( $input[ $checkbox ] ) ? 1 : 0;
		}

		// Sanitize log retention days.
		if ( isset( $input['log_retention_days'] ) ) {
			$sanitized['log_retention_days'] = absint( $input['log_retention_days'] );
		}

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
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'piip' ) );
		}

		require_once plugin_dir_path( __FILE__ ) . 'views/settings-page.php';
	}
}
