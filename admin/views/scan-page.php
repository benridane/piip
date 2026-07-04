<?php
/**
 * PII Scan Page Template
 *
 * @package    PIIP
 * @subpackage PIIP/admin/views
 * @since      1.5.0
 * @license    GPL-2.0-or-later
 *
 * @var array $post_types Map of post type name => label, set by PIIP_Scan_Page::render_page().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$piip_settings        = get_option( 'piip_settings', array() );
$piip_masking_enabled = ! empty( $piip_settings['enable_masking'] );
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<p>
		<?php esc_html_e( 'Scan content that already exists in the database for PII. The scan itself changes nothing; after reviewing the report you can apply masking to the listed items.', 'piip-pii-protection' ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'The scan uses the saved plugin settings (enabled PII types and consent phrases). Applying masking rewrites the stored content: comments cannot be restored, posts keep a revision where revisions are enabled.', 'piip-pii-protection' ); ?>
	</p>

	<?php if ( ! $piip_masking_enabled ) : ?>
		<div class="notice notice-warning inline" style="margin: 10px 0;">
			<p><?php esc_html_e( 'PII masking is globally disabled in the settings, so new submissions are currently not masked. This scan tool still works on existing content.', 'piip-pii-protection' ); ?></p>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Content to scan', 'piip-pii-protection' ); ?></h2>
	<fieldset id="piip-scan-targets" style="margin-bottom: 12px;">
		<label style="display: block; margin-bottom: 4px;">
			<input type="checkbox" name="piip-scan-target" value="comments" checked>
			<?php esc_html_e( 'Comments', 'piip-pii-protection' ); ?>
		</label>
		<?php foreach ( $post_types as $piip_post_type => $piip_label ) : ?>
			<label style="display: block; margin-bottom: 4px;">
				<input type="checkbox" name="piip-scan-target" value="<?php echo esc_attr( $piip_post_type ); ?>">
				<?php echo esc_html( $piip_label ); ?>
				<code><?php echo esc_html( $piip_post_type ); ?></code>
			</label>
		<?php endforeach; ?>
	</fieldset>

	<p>
		<button type="button" id="piip-scan-start" class="button button-primary"><?php esc_html_e( 'Run Scan (no changes)', 'piip-pii-protection' ); ?></button>
		<button type="button" id="piip-scan-apply" class="button" style="display: none;"><?php esc_html_e( 'Apply Masking to Listed Items', 'piip-pii-protection' ); ?></button>
	</p>

	<div id="piip-scan-progress-wrap" style="display: none; max-width: 480px; margin-bottom: 8px;">
		<div style="background: #dcdcde; border-radius: 3px; overflow: hidden;">
			<div id="piip-scan-progress-bar" style="background: #2271b1; height: 16px; width: 0;"></div>
		</div>
		<p id="piip-scan-progress-text" style="margin: 4px 0 0; color: #646970;"></p>
	</div>

	<div id="piip-scan-summary" class="notice notice-info inline" style="display: none; margin: 10px 0;">
		<p></p>
	</div>

	<div id="piip-scan-results-wrap" style="display: none;">
		<h2><?php esc_html_e( 'Items containing PII', 'piip-pii-protection' ); ?></h2>
		<table class="widefat striped" id="piip-scan-results" style="max-width: 1000px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Content', 'piip-pii-protection' ); ?></th>
					<th><?php esc_html_e( 'ID', 'piip-pii-protection' ); ?></th>
					<th><?php esc_html_e( 'Excerpt', 'piip-pii-protection' ); ?></th>
					<th><?php esc_html_e( 'Detected types', 'piip-pii-protection' ); ?></th>
					<th><?php esc_html_e( 'Status', 'piip-pii-protection' ); ?></th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
	</div>
</div>
