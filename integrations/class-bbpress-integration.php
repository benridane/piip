<?php
/**
 * BbPress Integration
 *
 * Integrates PII masking with bbPress forum plugin.
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
 * Class PIIP_bbPress_Integration
 *
 * Handles PII masking for bbPress forums, topics, and replies.
 *
 * @since 1.0.0
 */
class PIIP_BbPress_Integration extends PIIP_Base_Integration {

	/**
	 * Integration slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $slug = 'bbpress';

	/**
	 * Integration name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $name = 'bbPress';

	/**
	 * Initialize hooks for bbPress.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function init_hooks() {
		// Mask topic title and content before save.
		add_filter( 'bbp_new_topic_pre_title', array( $this, 'mask_topic_title' ), 10, 1 );
		add_filter( 'bbp_edit_topic_pre_title', array( $this, 'mask_topic_title' ), 10, 1 );
		add_filter( 'bbp_new_topic_pre_content', array( $this, 'mask_topic_content' ), 10, 1 );
		add_filter( 'bbp_edit_topic_pre_content', array( $this, 'mask_topic_content' ), 10, 1 );

		// Mask reply content before save.
		add_filter( 'bbp_new_reply_pre_content', array( $this, 'mask_reply_content' ), 10, 1 );
		add_filter( 'bbp_edit_reply_pre_content', array( $this, 'mask_reply_content' ), 10, 1 );

		// Mask forum descriptions.
		add_filter( 'bbp_new_forum_pre_content', array( $this, 'mask_forum_content' ), 10, 1 );
		add_filter( 'bbp_edit_forum_pre_content', array( $this, 'mask_forum_content' ), 10, 1 );
	}

	/**
	 * Check if bbPress is active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if active, false otherwise.
	 */
	public static function is_plugin_active() {
		return class_exists( 'bbPress' ) || function_exists( 'bbpress' );
	}

	/**
	 * Mask topic title before save.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title Topic title.
	 * @return string Masked title.
	 */
	public function mask_topic_title( $title ) {
		return $this->mask_content( $title, 'topic_title', 0 );
	}

	/**
	 * Mask topic content before save.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Topic content.
	 * @return string Masked content.
	 */
	public function mask_topic_content( $content ) {
		return $this->mask_content( $content, 'topic_content', 0 );
	}

	/**
	 * Mask reply content before save.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Reply content.
	 * @return string Masked content.
	 */
	public function mask_reply_content( $content ) {
		return $this->mask_content( $content, 'reply_content', 0 );
	}

	/**
	 * Mask forum content/description before save.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Forum content.
	 * @return string Masked content.
	 */
	public function mask_forum_content( $content ) {
		return $this->mask_content( $content, 'forum_content', 0 );
	}
}
