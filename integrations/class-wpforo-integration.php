<?php
/**
 * wpForo Integration
 *
 * Integrates PII masking with wpForo forum plugin.
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
 * Class PIIP_wpForo_Integration
 *
 * Handles PII masking for wpForo forum posts and profiles.
 *
 * @since 1.0.0
 */
class PIIP_wpForo_Integration extends PIIP_Base_Integration {

	/**
	 * Integration slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $slug = 'wpforo';

	/**
	 * Integration name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $name = 'wpForo';

	/**
	 * Initialize hooks for wpForo.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function init_hooks() {
		// Mask topic content before save (correct wpForo filter names).
		add_filter( 'wpforo_add_topic_data_filter', array( $this, 'mask_topic_data' ), 10, 1 );
		add_filter( 'wpforo_edit_topic_data_filter', array( $this, 'mask_topic_data' ), 10, 1 );

		// Mask post/reply content before save.
		add_filter( 'wpforo_add_post_data_filter', array( $this, 'mask_post_data' ), 10, 1 );
		add_filter( 'wpforo_edit_post_data_filter', array( $this, 'mask_post_data' ), 10, 1 );

		// Mask profile fields before save.
		add_filter( 'wpforo_member_before_update', array( $this, 'mask_profile_data' ), 10, 1 );

		// Mask private messages.
		add_filter( 'wpforo_add_pm_data_filter', array( $this, 'mask_private_message' ), 10, 1 );
	}

	/**
	 * Check if wpForo is active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if active, false otherwise.
	 */
	public static function is_plugin_active() {
		return class_exists( 'wpForo' ) || defined( 'WPFORO_VERSION' );
	}

	/**
	 * Mask topic data before save.
	 *
	 * @since 1.0.0
	 *
	 * @param array $topic Topic data.
	 * @return array Masked topic data.
	 */
	public function mask_topic_data( $topic ) {
		if ( ! is_array( $topic ) ) {
			return $topic;
		}

		$topic_id = isset( $topic['topicid'] ) ? (int) $topic['topicid'] : 0;

		// Mask topic title.
		if ( ! empty( $topic['title'] ) ) {
			$topic['title'] = $this->mask_content( $topic['title'], 'topic_title', $topic_id );
		}

		// Mask topic body if present.
		if ( ! empty( $topic['body'] ) ) {
			$topic['body'] = $this->mask_content( $topic['body'], 'topic_body', $topic_id );
		}

		return $topic;
	}

	/**
	 * Mask post/reply data before save.
	 *
	 * @since 1.0.0
	 *
	 * @param array $post Post data.
	 * @return array Masked post data.
	 */
	public function mask_post_data( $post ) {
		if ( ! is_array( $post ) ) {
			return $post;
		}

		$post_id = isset( $post['postid'] ) ? (int) $post['postid'] : 0;

		// Mask post body.
		if ( ! empty( $post['body'] ) ) {
			$post['body'] = $this->mask_content( $post['body'], 'post_body', $post_id );
		}

		return $post;
	}

	/**
	 * Mask profile data before save.
	 *
	 * @since 1.0.0
	 *
	 * @param array $profile Profile data.
	 * @return array Masked profile data.
	 */
	public function mask_profile_data( $profile ) {
		if ( ! is_array( $profile ) ) {
			return $profile;
		}

		$user_id = isset( $profile['userid'] ) ? (int) $profile['userid'] : 0;

		// Fields to potentially mask.
		$fields_to_check = array(
			'about'     => 'profile_about',
			'signature' => 'profile_signature',
			'site'      => 'profile_website',
			'location'  => 'profile_location',
		);

		foreach ( $fields_to_check as $field => $context ) {
			if ( ! empty( $profile[ $field ] ) ) {
				$profile[ $field ] = $this->mask_content( $profile[ $field ], $context, $user_id );
			}
		}

		return $profile;
	}

	/**
	 * Mask private message before send.
	 *
	 * @since 1.0.0
	 *
	 * @param array $message Message data.
	 * @return array Masked message data.
	 */
	public function mask_private_message( $message ) {
		if ( ! is_array( $message ) ) {
			return $message;
		}

		// Mask message body.
		if ( ! empty( $message['body'] ) ) {
			$message['body'] = $this->mask_content( $message['body'], 'private_message', 0 );
		}

		// Mask message title/subject.
		if ( ! empty( $message['title'] ) ) {
			$message['title'] = $this->mask_content( $message['title'], 'pm_title', 0 );
		}

		return $message;
	}
}
