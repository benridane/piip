<?php
/**
 * Content Scanner
 *
 * Scans existing content (comments and posts) for PII and optionally
 * applies masking retroactively. Pure logic class shared by the admin
 * scan page and the WP-CLI command.
 *
 * @package    PIIP
 * @subpackage PIIP/includes
 * @since      1.5.0
 * @license    GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class PIIP_Content_Scanner
 *
 * Batch-scans stored content through the same detection and masking
 * pipeline used for new submissions. Original values are never stored;
 * in apply mode the masked value simply replaces the stored one.
 *
 * @since 1.5.0
 */
class PIIP_Content_Scanner {

	/**
	 * Default batch size.
	 *
	 * @since 1.5.0
	 * @var int
	 */
	const BATCH_SIZE = 50;

	/**
	 * PII Masker instance.
	 *
	 * @since 1.5.0
	 * @var PIIP_PII_Masker
	 */
	private $masker;

	/**
	 * PII Detector instance.
	 *
	 * @since 1.5.0
	 * @var PIIP_PII_Detector
	 */
	private $detector;

	/**
	 * Constructor.
	 *
	 * @since 1.5.0
	 *
	 * @param PIIP_PII_Masker   $masker   PII masker instance.
	 * @param PIIP_PII_Detector $detector PII detector instance.
	 */
	public function __construct( $masker, $detector ) {
		$this->masker   = $masker;
		$this->detector = $detector;
	}

	/**
	 * Count scannable items for a target.
	 *
	 * @since 1.5.0
	 *
	 * @param string $target    Either 'comments' or 'posts'.
	 * @param string $post_type Post type when target is 'posts'.
	 * @return int Total number of items.
	 */
	public function count_items( $target, $post_type = '' ) {
		if ( 'comments' === $target ) {
			return (int) get_comments(
				array(
					'count'  => true,
					'status' => 'all',
				)
			);
		}

		$counts = wp_count_posts( $post_type );
		$total  = 0;
		foreach ( array( 'publish', 'pending', 'draft', 'future', 'private' ) as $status ) {
			if ( isset( $counts->$status ) ) {
				$total += (int) $counts->$status;
			}
		}

		return $total;
	}

	/**
	 * Scan (and optionally mask) one batch of comments.
	 *
	 * @since 1.5.0
	 *
	 * @param int  $offset Query offset.
	 * @param int  $limit  Batch size.
	 * @param bool $apply  Whether to write masked values back.
	 * @return array {
	 *     Batch result.
	 *
	 *     @type int   $processed Number of items examined in this batch.
	 *     @type array $items     Items that contain PII.
	 * }
	 */
	public function scan_comments_batch( $offset, $limit = self::BATCH_SIZE, $apply = false ) {
		$comments = get_comments(
			array(
				'number'  => $limit,
				'offset'  => $offset,
				'status'  => 'all',
				'orderby' => 'comment_ID',
				'order'   => 'ASC',
			)
		);

		$items = array();

		foreach ( $comments as $comment ) {
			$fields = array(
				'comment_content'    => (string) $comment->comment_content,
				'comment_author'     => (string) $comment->comment_author,
				'comment_author_url' => (string) $comment->comment_author_url,
			);

			$result = $this->examine_fields( $fields );

			if ( empty( $result['detected_types'] ) && ! $result['would_change'] ) {
				continue;
			}

			$item = array(
				'id'               => (int) $comment->comment_ID,
				'target'           => 'comments',
				'label'            => $this->excerpt( $comment->comment_content ),
				'edit_link'        => admin_url( 'comment.php?action=editcomment&c=' . (int) $comment->comment_ID ),
				'detected_types'   => $result['detected_types'],
				'would_change'     => $result['would_change'],
				'consent_bypassed' => $result['consent_bypassed'],
				'applied'          => false,
			);

			if ( $apply && $result['would_change'] ) {
				$update = array( 'comment_ID' => (int) $comment->comment_ID );
				foreach ( $result['masked'] as $field => $masked_value ) {
					if ( $masked_value !== $fields[ $field ] ) {
						$update[ $field ] = $masked_value;
					}
				}

				// wp_update_comment() expects slashed data, like $_POST.
				$item['applied'] = (bool) wp_update_comment( wp_slash( $update ) );
			}

			$items[] = $item;
		}

		return array(
			'processed' => count( $comments ),
			'items'     => $items,
		);
	}

	/**
	 * Scan (and optionally mask) one batch of posts.
	 *
	 * @since 1.5.0
	 *
	 * @param string $post_type Post type to scan.
	 * @param int    $offset    Query offset.
	 * @param int    $limit     Batch size.
	 * @param bool   $apply     Whether to write masked values back.
	 * @return array Batch result, same shape as scan_comments_batch().
	 */
	public function scan_posts_batch( $post_type, $offset, $limit = self::BATCH_SIZE, $apply = false ) {
		$posts = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => array( 'publish', 'pending', 'draft', 'future', 'private' ),
				'numberposts'      => $limit,
				'offset'           => $offset,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		$items = array();

		foreach ( $posts as $post ) {
			$fields = array(
				'post_title'   => (string) $post->post_title,
				'post_content' => (string) $post->post_content,
				'post_excerpt' => (string) $post->post_excerpt,
			);

			$result = $this->examine_fields( $fields );

			if ( empty( $result['detected_types'] ) && ! $result['would_change'] ) {
				continue;
			}

			$item = array(
				'id'               => (int) $post->ID,
				'target'           => $post_type,
				'label'            => '' !== $post->post_title ? $this->excerpt( $post->post_title ) : $this->excerpt( $post->post_content ),
				'edit_link'        => get_edit_post_link( $post->ID, 'raw' ),
				'detected_types'   => $result['detected_types'],
				'would_change'     => $result['would_change'],
				'consent_bypassed' => $result['consent_bypassed'],
				'applied'          => false,
			);

			if ( $apply && $result['would_change'] ) {
				$update = array( 'ID' => (int) $post->ID );
				foreach ( $result['masked'] as $field => $masked_value ) {
					if ( $masked_value !== $fields[ $field ] ) {
						$update[ $field ] = $masked_value;
					}
				}

				// wp_update_post() expects slashed data and creates a revision
				// for revision-enabled post types.
				$updated         = wp_update_post( wp_slash( $update ), true );
				$item['applied'] = ! is_wp_error( $updated ) && 0 !== $updated;
			}

			$items[] = $item;
		}

		return array(
			'processed' => count( $posts ),
			'items'     => $items,
		);
	}

	/**
	 * Run detection and masking over a set of text fields.
	 *
	 * Mirrors the submission-time pipeline (PIIP_Base_Integration::mask_content):
	 * a consent phrase skips masking, otherwise the text goes through the
	 * full mask_text_simple() hook pipeline.
	 *
	 * @since 1.5.0
	 *
	 * @param array $fields Map of field name => stored text.
	 * @return array {
	 *     Examination result.
	 *
	 *     @type array $masked           Map of field name => masked text.
	 *     @type array $detected_types   Unique detected PII types across fields.
	 *     @type bool  $would_change     Whether any field would change.
	 *     @type bool  $consent_bypassed Whether a consent phrase skipped masking.
	 * }
	 */
	public function examine_fields( $fields ) {
		$masked           = array();
		$detected_types   = array();
		$would_change     = false;
		$consent_bypassed = false;

		foreach ( $fields as $name => $text ) {
			if ( '' === $text ) {
				$masked[ $name ] = $text;
				continue;
			}

			foreach ( $this->detector->find_all_pii( $text ) as $pii ) {
				// URLs are detected but mask_text() has no URL masking branch.
				if ( 'url' === $pii['type'] ) {
					continue;
				}
				$detected_types[ $pii['type'] ] = true;
			}

			if ( $this->masker->has_consent_phrase( $text ) ) {
				$masked[ $name ]  = $text;
				$consent_bypassed = true;
				continue;
			}

			$masked[ $name ] = $this->masker->mask_text_simple( $text );

			if ( $masked[ $name ] !== $text ) {
				$would_change = true;
			}
		}

		return array(
			'masked'           => $masked,
			'detected_types'   => array_keys( $detected_types ),
			'would_change'     => $would_change,
			'consent_bypassed' => $consent_bypassed,
		);
	}

	/**
	 * Get the post types offered for scanning.
	 *
	 * @since 1.5.0
	 *
	 * @return array Map of post type name => label.
	 */
	public static function get_scannable_post_types() {
		$post_types = array();

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $post_type ) {
			if ( 'attachment' === $post_type->name ) {
				continue;
			}
			$post_types[ $post_type->name ] = $post_type->labels->name;
		}

		/**
		 * Filter the post types offered on the PII scan page.
		 *
		 * @since 1.5.0
		 *
		 * @param array $post_types Map of post type name => label.
		 */
		return apply_filters( 'piip_scannable_post_types', $post_types );
	}

	/**
	 * Build a short plain-text excerpt for report rows.
	 *
	 * @since 1.5.0
	 *
	 * @param string $text Source text.
	 * @return string Excerpt (40 chars max).
	 */
	private function excerpt( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		$text = preg_replace( '/\s+/u', ' ', $text );

		if ( mb_strlen( $text ) > 40 ) {
			return mb_substr( $text, 0, 40 ) . '…';
		}

		return $text;
	}
}
