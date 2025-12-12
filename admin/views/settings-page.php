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

	<h2><?php esc_html_e( 'Information', 'piip-pii-protection' ); ?></h2>
	<p><?php esc_html_e( 'This plugin automatically masks personally identifiable information (PII) in WordPress comments and community plugin content before saving to the database.', 'piip-pii-protection' ); ?></p>
	<p><?php esc_html_e( 'Supported integrations: WordPress Comments, wpForo, BuddyPress, bbPress', 'piip-pii-protection' ); ?></p>
</div>
