<?php
/**
 * Commenter IP Anonymizer
 *
 * Anonymizes the IP address stored with each comment.
 *
 * @package    PIIP
 * @subpackage PIIP/includes
 * @since      1.7.0
 * @license    GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class PIIP_Comment_IP_Anonymizer
 *
 * Hooks pre_comment_user_ip, which wp_filter_comment() applies to comment
 * form submissions, REST API comments and comment edits alike. Addresses
 * are anonymized with wp_privacy_anonymize_ip(), the same routine core uses
 * when erasing personal data. The address is never removed outright: core
 * flood protection matches on the stored IP, and an empty value shared by
 * every comment would throttle all guests at once.
 *
 * @since 1.7.0
 */
class PIIP_Comment_IP_Anonymizer {

	/**
	 * Store IP addresses unchanged.
	 *
	 * @since 1.7.0
	 * @var string
	 */
	public const MODE_KEEP = 'keep';

	/**
	 * Store anonymized IP addresses.
	 *
	 * @since 1.7.0
	 * @var string
	 */
	public const MODE_ANONYMIZE = 'anonymize';

	/**
	 * Valid modes.
	 *
	 * @since 1.7.0
	 * @var array
	 */
	public const MODES = array( self::MODE_KEEP, self::MODE_ANONYMIZE );

	/**
	 * Constructor.
	 *
	 * @since 1.7.0
	 */
	public function __construct() {
		add_filter( 'pre_comment_user_ip', array( $this, 'anonymize_ip' ) );
	}

	/**
	 * Get the configured mode.
	 *
	 * @since 1.7.0
	 *
	 * @param array $settings Plugin settings.
	 * @return string One of MODES.
	 */
	public static function get_mode( $settings ) {
		$mode = isset( $settings['comment_ip'] ) ? (string) $settings['comment_ip'] : self::MODE_KEEP;

		return in_array( $mode, self::MODES, true ) ? $mode : self::MODE_KEEP;
	}

	/**
	 * Anonymize a commenter IP address.
	 *
	 * @since 1.7.0
	 *
	 * @param string $ip IP address about to be stored.
	 * @return string Anonymized IP address, or the input when it is empty.
	 */
	public function anonymize_ip( $ip ) {
		if ( ! is_string( $ip ) || '' === $ip ) {
			return $ip;
		}

		return wp_privacy_anonymize_ip( $ip );
	}
}
