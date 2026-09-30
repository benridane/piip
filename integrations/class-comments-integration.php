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
	 * Comment type groups that can be selected for masking, with defaults.
	 *
	 * Keys map to the `comment_type_<group>` settings. Notes default to off:
	 * they only became reachable in 1.7.0 (they are created through the REST
	 * API) and are internal editorial comments.
	 *
	 * @since 1.7.0
	 * @var array
	 */
	public const COMMENT_TYPE_GROUPS = array(
		'comment' => 1,
		'review'  => 1,
		'note'    => 0,
		'other'   => 1,
	);

	/**
	 * Fields masked on every comment.
	 *
	 * @since 1.7.0
	 * @var array
	 */
	private const MASKED_FIELDS = array( 'comment_content', 'comment_author', 'comment_author_url' );

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
	 * @since 1.7.0 Also masks comments created through the REST API (which
	 *              skips preprocess_comment) and comment edits.
	 *
	 * @return void
	 */
	protected function init_hooks() {
		// Comment form, wp_new_comment() callers.
		add_filter( 'preprocess_comment', array( $this, 'mask_comment_data' ), 10, 1 );

		// REST API creation (e.g. block editor notes, headless front ends).
		add_filter( 'rest_preprocess_comment', array( $this, 'mask_rest_comment_data' ), 10, 2 );

		// Edits: admin screen, REST updates, wp_update_comment() callers.
		add_filter( 'wp_update_comment_data', array( $this, 'mask_updated_comment_data' ), 10, 3 );
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
	 * @since 1.7.0 Honors the comment type selection.
	 *
	 * @param array $commentdata Comment data array.
	 * @return array Masked comment data.
	 */
	public function mask_comment_data( $commentdata ) {
		if ( ! is_array( $commentdata ) ) {
			return $commentdata;
		}

		$comment_type = isset( $commentdata['comment_type'] ) ? (string) $commentdata['comment_type'] : '';
		if ( ! $this->should_mask_comment_type( $comment_type, $commentdata ) ) {
			return $commentdata;
		}

		$comment_id = isset( $commentdata['comment_ID'] ) ? (int) $commentdata['comment_ID'] : 0;

		foreach ( self::MASKED_FIELDS as $field ) {
			if ( ! empty( $commentdata[ $field ] ) ) {
				$commentdata[ $field ] = $this->mask_content( $commentdata[ $field ], $field, $comment_id );
			}
		}

		return $commentdata;
	}

	/**
	 * Mask a comment being created through the REST API.
	 *
	 * The REST controller builds the comment itself and never runs
	 * preprocess_comment. Updates are skipped here because they reach
	 * wp_update_comment(), where mask_updated_comment_data() handles them.
	 *
	 * @since 1.7.0
	 *
	 * @param array|WP_Error  $prepared_comment Prepared comment data.
	 * @param WP_REST_Request $request          Request object.
	 * @return array|WP_Error Masked comment data.
	 */
	public function mask_rest_comment_data( $prepared_comment, $request ) {
		if ( ! is_array( $prepared_comment ) || ! $request instanceof WP_REST_Request ) {
			return $prepared_comment;
		}

		if ( ! empty( $request['id'] ) ) {
			return $prepared_comment;
		}

		// The controller assigns comment_type from the request after this filter.
		$prepared_comment['comment_type'] = isset( $prepared_comment['comment_type'] )
			? $prepared_comment['comment_type']
			: (string) $request['type'];

		return $this->mask_comment_data( $prepared_comment );
	}

	/**
	 * Mask the fields an edit changes.
	 *
	 * Only fields that differ from the stored comment are masked, so status
	 * changes and moderation never rewrite text that was saved earlier.
	 *
	 * @since 1.7.0
	 *
	 * @param array|WP_Error $data       Unslashed comment data about to be saved.
	 * @param array          $comment    The stored comment, as an array.
	 * @param array          $commentarr The update arguments.
	 * @return array|WP_Error Masked comment data.
	 */
	public function mask_updated_comment_data( $data, $comment, $commentarr ) {
		unset( $commentarr );

		if ( ! is_array( $data ) || ! is_array( $comment ) ) {
			return $data;
		}

		$comment_type = isset( $data['comment_type'] ) ? (string) $data['comment_type'] : '';
		if ( ! $this->should_mask_comment_type( $comment_type, $data ) ) {
			return $data;
		}

		$comment_id = isset( $comment['comment_ID'] ) ? (int) $comment['comment_ID'] : 0;

		foreach ( self::MASKED_FIELDS as $field ) {
			if ( empty( $data[ $field ] ) ) {
				continue;
			}

			$stored = isset( $comment[ $field ] ) ? (string) $comment[ $field ] : '';
			if ( (string) $data[ $field ] === $stored ) {
				continue;
			}

			$data[ $field ] = $this->mask_content( $data[ $field ], $field, $comment_id );
		}

		return $data;
	}

	/**
	 * Map a comment type to its settings group.
	 *
	 * @since 1.7.0
	 *
	 * @param string $comment_type Comment type ('' is a legacy plain comment).
	 * @return string Group key from COMMENT_TYPE_GROUPS.
	 */
	public static function get_comment_type_group( $comment_type ) {
		$comment_type = '' === $comment_type ? 'comment' : $comment_type;

		return isset( self::COMMENT_TYPE_GROUPS[ $comment_type ] ) && 'other' !== $comment_type
			? $comment_type
			: 'other';
	}

	/**
	 * Check whether comments of a type are masked.
	 *
	 * @since 1.7.0
	 *
	 * @param string $comment_type Comment type.
	 * @param array  $commentdata  Comment data, passed to the filter.
	 * @return bool True if the comment should be masked.
	 */
	private function should_mask_comment_type( $comment_type, $commentdata ) {
		$settings = get_option( 'piip_settings', array() );
		$group    = self::get_comment_type_group( $comment_type );
		$key      = 'comment_type_' . $group;
		$enabled  = isset( $settings[ $key ] ) ? ! empty( $settings[ $key ] ) : (bool) self::COMMENT_TYPE_GROUPS[ $group ];

		/**
		 * Filter whether a comment of a given type is masked.
		 *
		 * @since 1.7.0
		 *
		 * @param bool   $enabled      Whether the comment is masked.
		 * @param string $comment_type Comment type ('' for legacy plain comments).
		 * @param array  $commentdata  Comment data being saved.
		 */
		return (bool) apply_filters( 'piip_mask_comment_type', $enabled, $comment_type, $commentdata );
	}
}
