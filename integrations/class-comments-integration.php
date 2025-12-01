<?php
/**
 * WordPress Comments Integration
 *
 * Integrates PII masking with WordPress native comments.
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
 * Class PIIP_Comments_Integration
 *
 * Handles PII masking for WordPress native comments.
 *
 * @since 1.0.0
 */
class PIIP_Comments_Integration extends PIIP_Base_Integration {

	/**
	 * Integration slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $slug = 'comments';

	/**
	 * Integration name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $name = 'Comments';

	/**
	 * Initialize hooks for WordPress comments.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function init_hooks() {
		// Mask comment data before save.
		add_filter( 'preprocess_comment', array( $this, 'mask_comment_data' ), 10, 1 );
	}

	/**
	 * Check if WordPress comments are available (always true for core functionality).
	 *
	 * @since 1.0.0
	 *
	 * @return bool True always, as comments are a core WordPress feature.
	 */
	public static function is_plugin_active() {
		// WordPress comments are always available.
		return true;
	}

	/**
	 * Mask comment data before save.
	 *
	 * @since 1.0.0
	 *
	 * @param array $commentdata Comment data array.
	 * @return array Masked comment data.
	 */
	public function mask_comment_data( $commentdata ) {
		if ( ! is_array( $commentdata ) ) {
			return $commentdata;
		}

		$comment_id = isset( $commentdata['comment_ID'] ) ? (int) $commentdata['comment_ID'] : 0;

		// Mask comment content.
		if ( ! empty( $commentdata['comment_content'] ) ) {
			$commentdata['comment_content'] = $this->mask_content(
				$commentdata['comment_content'],
				'comment_content',
				$comment_id
			);
		}

		// Mask comment author name.
		if ( ! empty( $commentdata['comment_author'] ) ) {
			$commentdata['comment_author'] = $this->mask_content(
				$commentdata['comment_author'],
				'comment_author',
				$comment_id
			);
		}

		// Mask comment author URL.
		if ( ! empty( $commentdata['comment_author_url'] ) ) {
			$commentdata['comment_author_url'] = $this->mask_content(
				$commentdata['comment_author_url'],
				'comment_author_url',
				$comment_id
			);
		}

		return $commentdata;
	}
}
