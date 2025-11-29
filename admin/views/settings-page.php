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
		submit_button( __( 'Save Settings', 'piip' ) );
		?>
	</form>

	<hr>

	<h2><?php esc_html_e( 'Information', 'piip' ); ?></h2>
	<p><?php esc_html_e( 'This plugin automatically masks personally identifiable information (PII) in form submissions before saving to the database.', 'piip' ); ?></p>
	<p><?php esc_html_e( 'Supported form plugins: Contact Form 7, Snow Monkey Forms', 'piip' ); ?></p>
	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=piip-logs' ) ); ?>" class="button">
			<?php esc_html_e( 'View Masking Logs', 'piip' ); ?>
		</a>
	</p>
</div>
