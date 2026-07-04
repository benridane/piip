<?php
/**
 * User Profiles Integration
 *
 * Integrates PII masking with WordPress user profile fields.
 *
 * @package    PIIP
 * @subpackage PIIP/integrations
 * @since      1.5.0
 * @license    GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PIIP_Users_Integration
 *
 * Masks PII that users write into publicly visible profile fields:
 * display name, nickname, and biographical info. Users sometimes put a
 * phone number or email address there without realizing those fields
 * are shown publicly (author pages, comment bylines, theme author boxes).
 *
 * The account email and website fields are intentionally left alone:
 * they are collected on purpose and needed for login and notifications.
 *
 * @since 1.5.0
 */
class PIIP_Users_Integration extends PIIP_Base_Integration {

	/**
	 * Integration slug.
	 *
	 * @since 1.5.0
	 * @var string
	 */
	protected $slug = 'users';

	/**
	 * Integration name.
	 *
	 * @since 1.5.0
	 * @var string
	 */
	protected $name = 'User Profiles';

	/**
	 * Initialize hooks for user profile fields.
	 *
	 * The pre_user_* filters run inside wp_insert_user()/wp_update_user()
	 * on both registration and profile updates, before the value is saved.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	protected function init_hooks() {
		add_filter( 'pre_user_display_name', array( $this, 'mask_display_name' ) );
		add_filter( 'pre_user_nickname', array( $this, 'mask_nickname' ) );
		add_filter( 'pre_user_description', array( $this, 'mask_description' ) );
	}

	/**
	 * Check if user profiles are available (always true for core functionality).
	 *
	 * @since 1.5.0
	 *
	 * @return bool True always, as user profiles are a core WordPress feature.
	 */
	public static function is_plugin_active() {
		return true;
	}

	/**
	 * Mask PII in the display name before save.
	 *
	 * @since 1.5.0
	 *
	 * @param string $display_name The user's display name.
	 * @return string Masked display name.
	 */
	public function mask_display_name( $display_name ) {
		return $this->mask_profile_field( $display_name, 'user_display_name' );
	}

	/**
	 * Mask PII in the nickname before save.
	 *
	 * @since 1.5.0
	 *
	 * @param string $nickname The user's nickname.
	 * @return string Masked nickname.
	 */
	public function mask_nickname( $nickname ) {
		return $this->mask_profile_field( $nickname, 'user_nickname' );
	}

	/**
	 * Mask PII in the biographical info before save.
	 *
	 * @since 1.5.0
	 *
	 * @param string $description The user's biographical info.
	 * @return string Masked biographical info.
	 */
	public function mask_description( $description ) {
		return $this->mask_profile_field( $description, 'user_description' );
	}

	/**
	 * Mask a single profile field value.
	 *
	 * @since 1.5.0
	 *
	 * @param mixed  $value   The field value.
	 * @param string $context Context for the masking pipeline.
	 * @return mixed Masked value.
	 */
	private function mask_profile_field( $value, $context ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}

		return $this->mask_content( $value, $context );
	}
}
