<?php
/**
 * Abilities API Registration
 *
 * Exposes read-only PIIP features to the WordPress Abilities API, and through
 * it to the REST API, MCP clients and AI agents.
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
 * Class PIIP_Abilities
 *
 * Registers:
 * - piip/mask-text:    mask a piece of text with the site's settings.
 * - piip/scan-content: dry-run scan of stored comments or posts for PII.
 *
 * Neither ability writes to the database. Results never echo detected PII
 * values back: callers receive masked text and PII types only.
 *
 * @since 1.7.0
 */
class PIIP_Abilities {

	/**
	 * Ability category slug.
	 *
	 * @since 1.7.0
	 * @var string
	 */
	public const CATEGORY = 'piip';

	/**
	 * Maximum text length accepted by piip/mask-text.
	 *
	 * @since 1.7.0
	 * @var int
	 */
	public const MAX_TEXT_LENGTH = 10000;

	/**
	 * Maximum batch size accepted by piip/scan-content.
	 *
	 * @since 1.7.0
	 * @var int
	 */
	public const MAX_SCAN_LIMIT = 100;

	/**
	 * Constructor.
	 *
	 * @since 1.7.0
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register the PIIP ability category.
	 *
	 * @since 1.7.0
	 *
	 * @return void
	 */
	public function register_category() {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'PII Protection', 'piip-pii-protection' ),
				'description' => __( 'Detect and mask personally identifiable information.', 'piip-pii-protection' ),
			)
		);
	}

	/**
	 * Register PIIP abilities.
	 *
	 * @since 1.7.0
	 *
	 * @return void
	 */
	public function register_abilities() {
		$scan_meta = array(
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
			'public'       => true,
			'show_in_rest' => true,
		);

		// mask-text changes nothing either, but the REST API only accepts
		// GET for read-only abilities, which would put the PII being masked
		// into URLs and server access logs. Not flagging it read-only keeps
		// the input in a POST body.
		$mask_meta                            = $scan_meta;
		$mask_meta['annotations']['readonly'] = false;

		wp_register_ability(
			'piip/mask-text',
			array(
				'label'               => __( 'Mask PII in text', 'piip-pii-protection' ),
				'description'         => __( 'Masks personally identifiable information (emails, phone numbers, card numbers, API keys, etc.) in a piece of text using this site\'s PIIP settings, and lists the PII types found. Consent phrases configured on the site bypass masking. Nothing is saved.', 'piip-pii-protection' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'text' => array(
							'type'        => 'string',
							'description' => __( 'Text to mask.', 'piip-pii-protection' ),
							'maxLength'   => self::MAX_TEXT_LENGTH,
						),
					),
					'required'             => array( 'text' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'masked'           => array(
							'type'        => 'string',
							'description' => __( 'The text with PII masked.', 'piip-pii-protection' ),
						),
						'changed'          => array(
							'type'        => 'boolean',
							'description' => __( 'Whether masking changed the text.', 'piip-pii-protection' ),
						),
						'detected_types'   => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'PII types detected in the text.', 'piip-pii-protection' ),
						),
						'consent_bypassed' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether a consent phrase skipped masking.', 'piip-pii-protection' ),
						),
						'masking_enabled'  => array(
							'type'        => 'boolean',
							'description' => __( 'Whether masking of new submissions is enabled on this site.', 'piip-pii-protection' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'execute_mask_text' ),
				'permission_callback' => array( $this, 'can_mask_text' ),
				'meta'                => $mask_meta,
			)
		);

		wp_register_ability(
			'piip/scan-content',
			array(
				'label'               => __( 'Scan stored content for PII', 'piip-pii-protection' ),
				'description'         => __( 'Dry-run scan of one batch of stored comments or posts for PII. Reports which items contain PII, the PII types, and whether masking would change them. Labels are returned masked. Nothing is modified; page through results with offset and next_offset.', 'piip-pii-protection' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'target' => array(
							'type'        => 'string',
							'description' => __( '"comments", or a public post type such as "post" or "page".', 'piip-pii-protection' ),
							'default'     => 'comments',
						),
						'offset' => array(
							'type'        => 'integer',
							'description' => __( 'Number of items to skip.', 'piip-pii-protection' ),
							'minimum'     => 0,
							'default'     => 0,
						),
						'limit'  => array(
							'type'        => 'integer',
							'description' => __( 'Number of items to examine.', 'piip-pii-protection' ),
							'minimum'     => 1,
							'maximum'     => self::MAX_SCAN_LIMIT,
							'default'     => PIIP_Content_Scanner::BATCH_SIZE,
						),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'target'      => array( 'type' => 'string' ),
						'total'       => array(
							'type'        => 'integer',
							'description' => __( 'Total items for the target.', 'piip-pii-protection' ),
						),
						'processed'   => array(
							'type'        => 'integer',
							'description' => __( 'Items examined in this batch.', 'piip-pii-protection' ),
						),
						'next_offset' => array(
							'type'        => array( 'integer', 'null' ),
							'description' => __( 'Offset for the next batch, or null when done.', 'piip-pii-protection' ),
						),
						'items'       => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'               => array( 'type' => 'integer' ),
									'label'            => array( 'type' => 'string' ),
									'edit_link'        => array( 'type' => 'string' ),
									'detected_types'   => array(
										'type'  => 'array',
										'items' => array( 'type' => 'string' ),
									),
									'would_change'     => array( 'type' => 'boolean' ),
									'consent_bypassed' => array( 'type' => 'boolean' ),
								),
							),
						),
					),
				),
				'execute_callback'    => array( $this, 'execute_scan_content' ),
				'permission_callback' => array( $this, 'can_scan_content' ),
				'meta'                => $scan_meta,
			)
		);
	}

	/**
	 * Permission check for piip/mask-text.
	 *
	 * @since 1.7.0
	 *
	 * @return bool True if the current user may use the ability.
	 */
	public function can_mask_text() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Permission check for piip/scan-content.
	 *
	 * Same capability as the Tools > PII Scan screen.
	 *
	 * @since 1.7.0
	 *
	 * @return bool True if the current user may use the ability.
	 */
	public function can_scan_content() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Execute piip/mask-text.
	 *
	 * Mirrors the submission pipeline: consent phrase check first, then the
	 * full mask_text_simple() hook pipeline.
	 *
	 * @since 1.7.0
	 *
	 * @param array $input Ability input.
	 * @return array|WP_Error Result.
	 */
	public function execute_mask_text( $input ) {
		$plugin = piip();
		if ( ! isset( $plugin->masker, $plugin->detector ) ) {
			return new WP_Error( 'piip_not_ready', __( 'PIIP is not initialized yet.', 'piip-pii-protection' ) );
		}

		$text = is_array( $input ) && isset( $input['text'] ) ? (string) $input['text'] : '';
		if ( mb_strlen( $text ) > self::MAX_TEXT_LENGTH ) {
			return new WP_Error(
				'piip_text_too_long',
				/* translators: %d: maximum number of characters. */
				sprintf( __( 'Text is limited to %d characters.', 'piip-pii-protection' ), self::MAX_TEXT_LENGTH )
			);
		}

		$consent_bypassed = $plugin->masker->has_consent_phrase( $text );
		$masked           = $consent_bypassed ? $text : $plugin->masker->mask_text_simple( $text );

		$detected_types = array();
		foreach ( $plugin->detector->find_all_pii( $text ) as $pii ) {
			// URLs are detected but never masked; match the scanner's report.
			if ( 'url' === $pii['type'] ) {
				continue;
			}
			$detected_types[ $pii['type'] ] = true;
		}

		$settings = get_option( 'piip_settings', array() );

		return array(
			'masked'           => $masked,
			'changed'          => $masked !== $text,
			'detected_types'   => array_keys( $detected_types ),
			'consent_bypassed' => $consent_bypassed,
			'masking_enabled'  => ! empty( $settings['enable_masking'] ),
		);
	}

	/**
	 * Execute piip/scan-content.
	 *
	 * @since 1.7.0
	 *
	 * @param array|null $input Ability input.
	 * @return array|WP_Error Result.
	 */
	public function execute_scan_content( $input ) {
		$plugin = piip();
		if ( ! isset( $plugin->scanner, $plugin->masker ) ) {
			return new WP_Error( 'piip_not_ready', __( 'PIIP is not initialized yet.', 'piip-pii-protection' ) );
		}

		$input  = is_array( $input ) ? $input : array();
		$target = isset( $input['target'] ) ? sanitize_key( $input['target'] ) : 'comments';
		$offset = isset( $input['offset'] ) ? max( 0, (int) $input['offset'] ) : 0;
		$limit  = isset( $input['limit'] ) ? (int) $input['limit'] : PIIP_Content_Scanner::BATCH_SIZE;
		$limit  = min( max( 1, $limit ), self::MAX_SCAN_LIMIT );

		if ( 'comments' === $target ) {
			$total = $plugin->scanner->count_items( 'comments' );
			$batch = $plugin->scanner->scan_comments_batch( $offset, $limit, false );
		} elseif ( array_key_exists( $target, PIIP_Content_Scanner::get_scannable_post_types() ) ) {
			$total = $plugin->scanner->count_items( 'posts', $target );
			$batch = $plugin->scanner->scan_posts_batch( $target, $offset, $limit, false );
		} else {
			return new WP_Error(
				'piip_invalid_target',
				/* translators: %s: comma-separated list of valid targets. */
				sprintf( __( 'Invalid target. Use one of: %s', 'piip-pii-protection' ), implode( ', ', array_merge( array( 'comments' ), array_keys( PIIP_Content_Scanner::get_scannable_post_types() ) ) ) )
			);
		}

		$items = array();
		foreach ( $batch['items'] as $item ) {
			$items[] = array(
				'id'               => (int) $item['id'],
				// Excerpts are raw stored text; never hand PII to the caller.
				'label'            => $plugin->masker->mask_text_simple( (string) $item['label'] ),
				'edit_link'        => (string) $item['edit_link'],
				'detected_types'   => array_values( $item['detected_types'] ),
				'would_change'     => (bool) $item['would_change'],
				'consent_bypassed' => (bool) $item['consent_bypassed'],
			);
		}

		return array(
			'target'      => $target,
			'total'       => (int) $total,
			'processed'   => (int) $batch['processed'],
			'next_offset' => $batch['processed'] < $limit ? null : $offset + $batch['processed'],
			'items'       => $items,
		);
	}
}
