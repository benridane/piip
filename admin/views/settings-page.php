<?php
/**
 * Settings Page Template
 *
 * @package    PIIP
 * @subpackage PIIP/admin/views
 * @since      1.0.0
 * @license    GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors( 'piip_settings' ); ?>

	<form action="options.php" method="post">
		<?php
		settings_fields( 'piip_settings_group' );
		do_settings_sections( 'piip-settings' );
		submit_button( __( 'Save Settings', 'piip-pii-protection' ) );
		?>
	</form>

	<hr>

	<h2><?php esc_html_e( 'Masking Preview', 'piip-pii-protection' ); ?></h2>
	<p>
		<?php esc_html_e( 'Type sample content below to see how it will be masked. The preview runs through the same masking pipeline as real submissions.', 'piip-pii-protection' ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'The preview uses the saved settings above. HTML tags are stripped from the input before masking.', 'piip-pii-protection' ); ?>
	</p>

	<div id="piip-preview-unsaved-notice" class="notice notice-warning inline" style="display: none; margin: 10px 0;">
		<p><?php esc_html_e( 'You have unsaved settings changes. The preview reflects the saved settings only.', 'piip-pii-protection' ); ?></p>
	</div>

	<fieldset style="margin-bottom: 12px;">
		<label style="margin-right: 16px;">
			<input type="radio" name="piip-preview-mode" value="text" checked>
			<?php esc_html_e( 'Free text', 'piip-pii-protection' ); ?>
		</label>
		<label>
			<input type="radio" name="piip-preview-mode" value="field">
			<?php esc_html_e( 'Field name + value', 'piip-pii-protection' ); ?>
		</label>
	</fieldset>

	<p id="piip-preview-field-name-row" style="display: none;">
		<label for="piip-preview-field-name"><strong><?php esc_html_e( 'Field name', 'piip-pii-protection' ); ?></strong></label><br>
		<input type="text" id="piip-preview-field-name" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. email, phone, password', 'piip-pii-protection' ); ?>">
	</p>

	<p>
		<label for="piip-preview-input"><strong><?php esc_html_e( 'Sample input', 'piip-pii-protection' ); ?></strong></label><br>
		<textarea id="piip-preview-input" class="large-text" rows="5" placeholder="<?php esc_attr_e( 'e.g. Contact me at john@example.com or 090-1234-5678', 'piip-pii-protection' ); ?>"></textarea>
	</p>

	<p>
		<strong><?php esc_html_e( 'Masked result', 'piip-pii-protection' ); ?></strong>
		<span id="piip-preview-status" style="margin-left: 8px; color: #646970;"></span>
	</p>
	<div id="piip-preview-consent-notice" class="notice notice-info inline" style="display: none; margin: 0 0 10px;">
		<p><?php esc_html_e( 'A consent phrase was found: masking is bypassed for this input.', 'piip-pii-protection' ); ?></p>
	</div>
	<div id="piip-preview-disabled-notice" class="notice notice-warning inline" style="display: none; margin: 0 0 10px;">
		<p><?php esc_html_e( 'Masking is globally disabled in the settings above. Real submissions are not masked, but the preview still shows what masking would produce.', 'piip-pii-protection' ); ?></p>
	</div>
	<pre id="piip-preview-result" style="background: #fff; border: 1px solid #c3c4c7; padding: 12px; min-height: 3em; white-space: pre-wrap; word-break: break-word; margin-top: 0;"></pre>

	<div id="piip-preview-detected-wrap" style="display: none;">
		<p><strong><?php esc_html_e( 'Detected PII', 'piip-pii-protection' ); ?></strong></p>
		<table class="widefat striped" id="piip-preview-detected" style="max-width: 800px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Type', 'piip-pii-protection' ); ?></th>
					<th><?php esc_html_e( 'Detected value', 'piip-pii-protection' ); ?></th>
					<th><?php esc_html_e( 'Confidence', 'piip-pii-protection' ); ?></th>
					<th><?php esc_html_e( 'Status', 'piip-pii-protection' ); ?></th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
	</div>

	<hr>

	<h2><?php esc_html_e( 'Information', 'piip-pii-protection' ); ?></h2>
	<p><?php esc_html_e( 'This plugin automatically masks personally identifiable information (PII) in WordPress comments and community plugin content before saving to the database.', 'piip-pii-protection' ); ?></p>
	<p><?php esc_html_e( 'Supported integrations: WordPress Comments, User Profiles, Contact Form 7, wpForo, BuddyPress, bbPress', 'piip-pii-protection' ); ?></p>
</div>
