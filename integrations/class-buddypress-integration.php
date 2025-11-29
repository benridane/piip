<?php
/**
 * BuddyPress Integration
 *
 * Integrates PII masking with BuddyPress.
 *
 * @package    PIIP
 * @subpackage PIIP/integrations
 * @since      1.0.0
 * @license    GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PIIP_BuddyPress_Integration
 *
 * Handles PII masking for BuddyPress activities, profiles, and messages.
 *
 * @since 1.0.0
 */
class PIIP_BuddyPress_Integration extends PIIP_Base_Integration {

	/**
	 * Integration slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $slug = 'buddypress';

	/**
	 * Integration name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $name = 'BuddyPress';

	/**
	 * Initialize hooks for BuddyPress.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function init_hooks() {
		// Mask activity content.
		add_filter( 'bp_activity_content_before_save', array( $this, 'mask_activity_content' ), 10, 1 );
		add_filter( 'bp_activity_action_before_save', array( $this, 'mask_activity_action' ), 10, 1 );

		// Mask profile fields.
		add_filter( 'xprofile_data_value_before_save', array( $this, 'mask_profile_field' ), 10, 3 );

		// Mask private messages.
		add_filter( 'messages_message_content_before_save', array( $this, 'mask_message_content' ), 10, 1 );
		add_filter( 'messages_message_subject_before_save', array( $this, 'mask_message_subject' ), 10, 1 );

		// Mask group descriptions.
		add_filter( 'groups_group_description_before_save', array( $this, 'mask_group_description' ), 10, 2 );

		// Mask comments on activities.
		add_filter( 'bp_activity_comment_content', array( $this, 'mask_activity_comment' ), 10, 1 );
	}

	/**
	 * Check if BuddyPress is active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if active, false otherwise.
	 */
	public static function is_plugin_active() {
		return class_exists( 'BuddyPress' ) || defined( 'BP_VERSION' );
	}

	/**
	 * Mask activity content before save.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Activity content.
	 * @return string Masked content.
	 */
	public function mask_activity_content( $content ) {
		return $this->mask_content( $content, 'activity_content', 0 );
	}

	/**
	 * Mask activity action before save.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action Activity action string.
	 * @return string Masked action.
	 */
	public function mask_activity_action( $action ) {
		return $this->mask_content( $action, 'activity_action', 0 );
	}

	/**
	 * Mask profile field value before save.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value    Field value.
	 * @param int    $field_id Field ID.
	 * @param int    $user_id  User ID (BuddyPress may pass this as third param in some versions).
	 * @return string Masked value.
	 */
	public function mask_profile_field( $value, $field_id, $user_id = 0 ) {
		if ( empty( $value ) || ! is_string( $value ) ) {
			return $value;
		}

		return $this->mask_content( $value, 'profile_field_' . $field_id, $user_id );
	}

	/**
	 * Mask message content before save.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Message content.
	 * @return string Masked content.
	 */
	public function mask_message_content( $content ) {
		return $this->mask_content( $content, 'message_content', 0 );
	}

	/**
	 * Mask message subject before save.
	 *
	 * @since 1.0.0
	 *
	 * @param string $subject Message subject.
	 * @return string Masked subject.
	 */
	public function mask_message_subject( $subject ) {
		return $this->mask_content( $subject, 'message_subject', 0 );
	}

	/**
	 * Mask group description before save.
	 *
	 * @since 1.0.0
	 *
	 * @param string $description Group description.
	 * @param int    $group_id    Group ID.
	 * @return string Masked description.
	 */
	public function mask_group_description( $description, $group_id = 0 ) {
		return $this->mask_content( $description, 'group_description', $group_id );
	}

	/**
	 * Mask activity comment content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Comment content.
	 * @return string Masked content.
	 */
	public function mask_activity_comment( $content ) {
		return $this->mask_content( $content, 'activity_comment', 0 );
	}
}
