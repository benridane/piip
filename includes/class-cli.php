<?php
/**
 * WP-CLI Commands
 *
 * Provides the `wp piip` command for masking text and scanning existing
 * content from the command line.
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
 * Detects and masks personally identifiable information (PII).
 *
 * @since 1.5.0
 */
class PIIP_CLI {

	/**
	 * Masks PII in the given text.
	 *
	 * Runs the text through the same masking pipeline used for real
	 * submissions and prints the masked result.
	 *
	 * ## OPTIONS
	 *
	 * <text>
	 * : The text to mask.
	 *
	 * [--field=<name>]
	 * : Treat the text as the value of a form field with this name and use
	 * field-based detection instead of free-text masking.
	 *
	 * ## EXAMPLES
	 *
	 *     wp piip mask "Call me at 090-1234-5678 or mail john@example.com"
	 *     wp piip mask --field=password "hunter2"
	 *
	 * @since 1.5.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function mask( $args, $assoc_args ) {
		$plugin = piip();
		$text   = $args[0];

		if ( ! isset( $plugin->masker ) ) {
			WP_CLI::error( 'PIIP is not initialized.' );
		}

		if ( isset( $assoc_args['field'] ) ) {
			WP_CLI::print_value( $plugin->masker->mask_value( (string) $assoc_args['field'], $text ) );
			return;
		}

		if ( $plugin->masker->has_consent_phrase( $text ) ) {
			WP_CLI::warning( 'A consent phrase was found: masking is bypassed for this text.' );
			WP_CLI::print_value( $text );
			return;
		}

		WP_CLI::print_value( $plugin->masker->mask_text_simple( $text ) );
	}

	/**
	 * Scans existing content for PII and optionally applies masking.
	 *
	 * Without --apply this is a dry run: nothing is changed and every item
	 * containing PII is listed. With --apply the listed items are rewritten
	 * with masked content. Comments are updated in place and cannot be
	 * restored; posts keep a revision where revisions are enabled.
	 *
	 * ## OPTIONS
	 *
	 * [--target=<targets>]
	 * : Comma-separated list of what to scan: "comments", any public post
	 * type name, or "all".
	 * ---
	 * default: comments
	 * ---
	 *
	 * [--apply]
	 * : Write masked values back to the database.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt when using --apply.
	 *
	 * [--format=<format>]
	 * : Output format for the item list.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Dry-run scan of comments.
	 *     wp piip scan
	 *
	 *     # Scan everything, then apply masking without prompting.
	 *     wp piip scan --target=all --apply --yes
	 *
	 *     # Scan posts and pages only.
	 *     wp piip scan --target=post,page
	 *
	 * @since 1.5.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function scan( $args, $assoc_args ) {
		unset( $args );

		$plugin = piip();
		if ( ! isset( $plugin->scanner ) ) {
			WP_CLI::error( 'PIIP is not initialized.' );
		}
		$scanner = $plugin->scanner;

		$apply  = ! empty( $assoc_args['apply'] );
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		$post_types = PIIP_Content_Scanner::get_scannable_post_types();
		$requested  = isset( $assoc_args['target'] ) ? (string) $assoc_args['target'] : 'comments';

		if ( 'all' === $requested ) {
			$targets = array_merge( array( 'comments' ), array_keys( $post_types ) );
		} else {
			$targets = array_filter( array_map( 'trim', explode( ',', $requested ) ) );
			foreach ( $targets as $target ) {
				if ( 'comments' !== $target && ! isset( $post_types[ $target ] ) ) {
					WP_CLI::error( sprintf( 'Unknown scan target: %s', $target ) );
				}
			}
		}

		if ( $apply ) {
			WP_CLI::confirm(
				'Apply masking to all items containing PII? Comments cannot be restored.',
				$assoc_args
			);
		}

		$items   = array();
		$scanned = 0;
		$applied = 0;

		foreach ( $targets as $target ) {
			$offset = 0;

			do {
				if ( 'comments' === $target ) {
					$batch = $scanner->scan_comments_batch( $offset, PIIP_Content_Scanner::BATCH_SIZE, $apply );
				} else {
					$batch = $scanner->scan_posts_batch( $target, $offset, PIIP_Content_Scanner::BATCH_SIZE, $apply );
				}

				$scanned += $batch['processed'];
				$offset  += $batch['processed'];

				foreach ( $batch['items'] as $item ) {
					if ( $item['applied'] ) {
						$applied++;
						$status = 'masked';
					} elseif ( $item['consent_bypassed'] ) {
						$status = 'consent-skipped';
					} elseif ( $item['would_change'] ) {
						$status = $apply ? 'update-failed' : 'would-mask';
					} else {
						$status = 'detected-only';
					}

					$items[] = array(
						'target'  => $item['target'],
						'id'      => $item['id'],
						'types'   => implode( ',', $item['detected_types'] ),
						'status'  => $status,
						'excerpt' => $item['label'],
					);
				}
			} while ( $batch['processed'] === PIIP_Content_Scanner::BATCH_SIZE );
		}

		if ( $items || 'count' === $format ) {
			WP_CLI\Utils\format_items( $format, $items, array( 'target', 'id', 'types', 'status', 'excerpt' ) );
		}

		if ( $apply ) {
			WP_CLI::success( sprintf( 'Scanned %d items; masking applied to %d.', $scanned, $applied ) );
		} else {
			WP_CLI::success( sprintf( 'Scanned %d items; %d contain PII. Re-run with --apply to mask them.', $scanned, count( $items ) ) );
		}
	}
}
