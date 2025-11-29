<?php
/**
 * Logs Page Template
 *
 * @package    PIIP
 * @subpackage PIIP/admin/views
 * @since      1.0.0
 * @license    GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$piip_export_url = wp_nonce_url(
	admin_url( 'admin-post.php?action=piip_export_logs' ),
	'piip_export_logs'
);
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<p><?php esc_html_e( 'View all PII masking events logged by the plugin.', 'piip' ); ?></p>

	<p>
		<a href="<?php echo esc_url( $piip_export_url ); ?>" class="button">
			<?php esc_html_e( 'Export to CSV', 'piip' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=piip-settings' ) ); ?>" class="button">
			<?php esc_html_e( 'Settings', 'piip' ); ?>
		</a>
	</p>

	<form method="get">
		<input type="hidden" name="page" value="piip-logs">
		<?php
		$list_table->display();
		?>
	</form>
</div>
