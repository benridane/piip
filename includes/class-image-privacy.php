<?php
/**
 * Image Privacy
 *
 * Removes location and identifying metadata from uploaded images.
 *
 * @package    PIIP
 * @subpackage PIIP/includes
 * @since      1.8.0
 * @license    GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class PIIP_Image_Privacy
 *
 * - wp_handle_upload (uploads and sideloads): scrubs the original file
 *   before WordPress generates sub-sizes or converts HEIC, so every derived
 *   file starts clean whichever image editor is used.
 * - wp_generate_attachment_metadata: scrubs every file of the attachment
 *   (also covers attachments created without wp_handle_upload) and records
 *   the outcome in the _piip_image_scrub post meta.
 * - wp_read_image_metadata: drops the EXIF author ("credit"), which core
 *   stores and exposes through the REST API.
 *
 * @since 1.8.0
 */
class PIIP_Image_Privacy {

	/**
	 * Post meta key holding the last scrub outcome.
	 *
	 * @since 1.8.0
	 * @var string
	 */
	public const META_KEY = '_piip_image_scrub';

	/**
	 * Upload-time results by file path, so the attachment record reflects
	 * a scrub that happened before the attachment existed.
	 *
	 * @since 1.8.0
	 * @var array
	 */
	private static $upload_results = array();

	/**
	 * Constructor.
	 *
	 * @since 1.8.0
	 */
	public function __construct() {
		add_filter( 'wp_handle_upload', array( $this, 'scrub_upload' ), 10, 2 );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'scrub_attachment_files' ), 10, 2 );
		add_filter( 'wp_read_image_metadata', array( $this, 'filter_image_meta' ) );
	}

	/**
	 * Get the scrub options from the settings.
	 *
	 * @since 1.8.0
	 *
	 * @param array|null $settings Settings (null = read the option).
	 * @return array {location, identity} booleans.
	 */
	public static function get_options( $settings = null ) {
		$settings = is_array( $settings ) ? $settings : get_option( 'piip_settings', array() );

		return array(
			'location' => ! isset( $settings['image_strip_location'] ) || ! empty( $settings['image_strip_location'] ),
			'identity' => ! isset( $settings['image_strip_identity'] ) || ! empty( $settings['image_strip_identity'] ),
		);
	}

	/**
	 * Whether any image scrubbing is enabled.
	 *
	 * @since 1.8.0
	 *
	 * @param array|null $settings Settings (null = read the option).
	 * @return bool
	 */
	public static function is_enabled( $settings = null ) {
		$options = self::get_options( $settings );

		return $options['location'] || $options['identity'];
	}

	/**
	 * Scrub an uploaded or sideloaded image before attachment processing.
	 *
	 * @since 1.8.0
	 *
	 * @param array  $upload  {file, url, type}.
	 * @param string $context 'upload' or 'sideload'.
	 * @return array Unchanged upload data.
	 */
	public function scrub_upload( $upload, $context = 'upload' ) {
		if ( ! is_array( $upload ) || empty( $upload['file'] ) || ! empty( $upload['error'] ) ) {
			return $upload;
		}

		$type = isset( $upload['type'] ) ? (string) $upload['type'] : '';
		if ( 0 !== strpos( $type, 'image/' ) ) {
			return $upload;
		}

		$result = self::scrub( $upload['file'], $context );

		self::$upload_results[ $upload['file'] ] = $result['status'];

		return $upload;
	}

	/**
	 * Scrub every file of an image attachment and record the outcome.
	 *
	 * @since 1.8.0
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Metadata (credit cleared when identity scrubbing is on).
	 */
	public function scrub_attachment_files( $metadata, $attachment_id ) {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return $metadata;
		}

		$status = self::scrub_attachment( $attachment_id, $metadata, 'metadata' );

		// The original may have been scrubbed at upload, before this pass.
		$original = wp_get_original_image_path( $attachment_id );
		$uploaded = $original && isset( self::$upload_results[ $original ] ) ? self::$upload_results[ $original ] : '';
		if ( PIIP_Image_Scrubber::STATUS_CLEAN === $status && '' !== $uploaded && PIIP_Image_Scrubber::STATUS_CLEAN !== $uploaded ) {
			$status = $uploaded;
		}

		update_post_meta(
			$attachment_id,
			self::META_KEY,
			array(
				'status' => $status,
				'time'   => time(),
			)
		);

		$options = self::get_options();
		if ( $options['identity'] && is_array( $metadata ) && ! empty( $metadata['image_meta']['credit'] ) ) {
			$metadata['image_meta']['credit'] = '';
		}

		return $metadata;
	}

	/**
	 * Drop the EXIF author from metadata core reads.
	 *
	 * @since 1.8.0
	 *
	 * @param array $meta Image meta.
	 * @return array Image meta.
	 */
	public function filter_image_meta( $meta ) {
		$options = self::get_options();
		if ( $options['identity'] && is_array( $meta ) && isset( $meta['credit'] ) ) {
			$meta['credit'] = '';
		}

		return $meta;
	}

	/**
	 * Scrub one file with the configured options.
	 *
	 * @since 1.8.0
	 *
	 * @param string $file    Path.
	 * @param string $context Where the file comes from (upload, sideload, metadata, scan).
	 * @param bool   $dry_run Only report.
	 * @return array Scrubber result.
	 */
	public static function scrub( $file, $context = 'upload', $dry_run = false ) {
		$options = self::get_options();

		/**
		 * Filter whether an image file is scrubbed.
		 *
		 * @since 1.8.0
		 *
		 * @param bool   $scrub   Whether to scrub. Default true.
		 * @param string $file    File path.
		 * @param string $context upload, sideload, metadata or scan.
		 */
		if ( ! apply_filters( 'piip_scrub_image', true, $file, $context ) ) {
			return array(
				'status' => PIIP_Image_Scrubber::STATUS_CLEAN,
				'format' => '',
				'found'  => array(
					'location' => false,
					'identity' => false,
				),
				'error'  => '',
			);
		}

		$result = PIIP_Image_Scrubber::scrub_file(
			$file,
			array(
				'location' => $options['location'],
				'identity' => $options['identity'],
				'dry_run'  => $dry_run,
			)
		);

		if ( ! $dry_run ) {
			/**
			 * Fires after an image file was processed.
			 *
			 * @since 1.8.0
			 *
			 * @param array  $result  Scrubber result (status, format, found, error).
			 * @param string $file    File path.
			 * @param string $context upload, sideload, metadata or scan.
			 */
			do_action( 'piip_image_scrubbed', $result, $file, $context );
		}

		return $result;
	}

	/**
	 * Scrub (or inspect) all files of an attachment.
	 *
	 * @since 1.8.0
	 *
	 * @param int        $attachment_id Attachment ID.
	 * @param array|null $metadata      Metadata (null = load it).
	 * @param string     $context       Context passed to the filters.
	 * @param bool       $dry_run       Only report.
	 * @return string Overall status: failed if any file failed, else scrubbed if any changed, else clean.
	 */
	public static function scrub_attachment( $attachment_id, $metadata = null, $context = 'scan', $dry_run = false ) {
		$overall = PIIP_Image_Scrubber::STATUS_CLEAN;

		foreach ( self::get_attachment_files( $attachment_id, $metadata ) as $file ) {
			$result = self::scrub( $file, $context, $dry_run );

			if ( PIIP_Image_Scrubber::STATUS_FAILED === $result['status'] ) {
				$overall = PIIP_Image_Scrubber::STATUS_FAILED;
			} elseif ( PIIP_Image_Scrubber::STATUS_SCRUBBED === $result['status'] && PIIP_Image_Scrubber::STATUS_FAILED !== $overall ) {
				$overall = PIIP_Image_Scrubber::STATUS_SCRUBBED;
			}
		}

		return $overall;
	}

	/**
	 * Inspect an attachment: which kinds of metadata its files carry.
	 *
	 * @since 1.8.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array {location: bool, identity: bool, failed: bool}.
	 */
	public static function inspect_attachment( $attachment_id ) {
		$report = array(
			'location' => false,
			'identity' => false,
			'failed'   => false,
		);

		foreach ( self::get_attachment_files( $attachment_id ) as $file ) {
			$result = PIIP_Image_Scrubber::scrub_file( $file, array( 'dry_run' => true ) );

			$report['location'] = $report['location'] || $result['found']['location'];
			$report['identity'] = $report['identity'] || $result['found']['identity'];
			$report['failed']   = $report['failed'] || PIIP_Image_Scrubber::STATUS_FAILED === $result['status'];
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $metadata ) && ! empty( $metadata['image_meta']['credit'] ) ) {
			$report['identity'] = true;
		}

		return $report;
	}

	/**
	 * Get every file of an image attachment: original, attached file, sizes.
	 *
	 * @since 1.8.0
	 *
	 * @param int        $attachment_id Attachment ID.
	 * @param array|null $metadata      Metadata (null = load it).
	 * @return array Unique existing file paths.
	 */
	public static function get_attachment_files( $attachment_id, $metadata = null ) {
		$metadata = is_array( $metadata ) ? $metadata : wp_get_attachment_metadata( $attachment_id );
		$attached = get_attached_file( $attachment_id );
		$files    = array();

		if ( $attached ) {
			$files[] = $attached;
			$dir     = dirname( $attached );

			if ( is_array( $metadata ) ) {
				if ( ! empty( $metadata['original_image'] ) ) {
					$files[] = $dir . '/' . wp_basename( $metadata['original_image'] );
				}
				foreach ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ? $metadata['sizes'] : array() as $size ) {
					if ( ! empty( $size['file'] ) ) {
						$files[] = $dir . '/' . wp_basename( $size['file'] );
					}
				}
			}
		}

		return array_values( array_unique( array_filter( $files, 'is_file' ) ) );
	}

	/**
	 * Clear the stored EXIF author of an attachment.
	 *
	 * @since 1.8.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function clear_stored_credit( $attachment_id ) {
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $metadata ) && ! empty( $metadata['image_meta']['credit'] ) ) {
			$metadata['image_meta']['credit'] = '';
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}
	}
}
