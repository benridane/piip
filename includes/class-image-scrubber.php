<?php
/**
 * Image Metadata Scrubber
 *
 * Removes location and identifying metadata from image files in place.
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
 * Class PIIP_Image_Scrubber
 *
 * Every edit keeps the byte length: metadata values are overwritten with
 * zeros or spaces and the GPS IFD is emptied, so pixel data is never
 * re-encoded, container structures never move, and the EXIF orientation,
 * color profile and camera model survive. Only PNG needs a CRC update.
 *
 * Supported: JPEG (APP1 EXIF and XMP), WebP (EXIF and XMP chunks), PNG
 * (eXIf and uncompressed XMP iTXt) and HEIF/HEIC/AVIF (EXIF and XMP items,
 * located by signature and validated before any write). Edits are
 * collected first and applied only if the whole structure parsed, so a
 * malformed file is left untouched.
 *
 * @since 1.8.0
 */
final class PIIP_Image_Scrubber {

	/**
	 * Metadata was found and removed (or would be, in a dry run).
	 *
	 * @since 1.8.0
	 * @var string
	 */
	public const STATUS_SCRUBBED = 'scrubbed';

	/**
	 * Nothing to remove.
	 *
	 * @since 1.8.0
	 * @var string
	 */
	public const STATUS_CLEAN = 'clean';

	/**
	 * The file could not be parsed, written or verified; left unchanged.
	 *
	 * @since 1.8.0
	 * @var string
	 */
	public const STATUS_FAILED = 'failed';

	/**
	 * Not an image format this class handles.
	 *
	 * @since 1.8.0
	 * @var string
	 */
	public const STATUS_UNSUPPORTED = 'unsupported';

	/**
	 * EXIF tags pointing to sub-IFDs.
	 *
	 * @since 1.8.0
	 */
	private const TAG_EXIF_IFD = 0x8769;
	private const TAG_GPS_IFD  = 0x8825;

	/**
	 * Identifying EXIF tags: Artist, XPAuthor (IFD0); CameraOwnerName,
	 * BodySerialNumber, LensSerialNumber (Exif IFD).
	 *
	 * @since 1.8.0
	 * @var array
	 */
	private const IDENTITY_TAGS = array( 0x013B, 0x9C9D, 0xA430, 0xA431, 0xA435 );

	/**
	 * XMP properties holding a location.
	 *
	 * @since 1.8.0
	 * @var string
	 */
	private const XMP_LOCATION = 'exif:GPS[A-Za-z]*|photoshop:(?:City|State|Country)|Iptc4xmpCore:(?:Location|CountryCode)|Iptc4xmpExt:(?:LocationCreated|LocationShown)';

	/**
	 * XMP properties identifying a person or a device.
	 *
	 * @since 1.8.0
	 * @var string
	 */
	private const XMP_IDENTITY = 'aux:(?:SerialNumber|LensSerialNumber|OwnerName)|exifEX:(?:BodySerialNumber|LensSerialNumber|CameraOwnerName)|dc:creator';

	/**
	 * Byte sizes of TIFF field types.
	 *
	 * @since 1.8.0
	 * @var array
	 */
	private const TYPE_SIZES = array(
		1  => 1,
		2  => 1,
		3  => 2,
		4  => 4,
		5  => 8,
		6  => 1,
		7  => 1,
		8  => 2,
		9  => 4,
		10 => 8,
		11 => 4,
		12 => 8,
		13 => 4,
	);

	/**
	 * Largest IFD entry count accepted (guards against garbage).
	 *
	 * @since 1.8.0
	 * @var int
	 */
	private const MAX_IFD_ENTRIES = 1000;

	/**
	 * Scrub (or inspect) an image file.
	 *
	 * @since 1.8.0
	 *
	 * @param string $path    File path.
	 * @param array  $options {
	 *     Options.
	 *
	 *     @type bool $location Remove location (GPS, XMP location). Default true.
	 *     @type bool $identity Remove identifying fields (author, owner, serials). Default true.
	 *     @type bool $dry_run  Only report what would be removed. Default false.
	 * }
	 * @return array {
	 *     Result.
	 *
	 *     @type string $status   One of the STATUS_* constants.
	 *     @type string $format   Detected format (jpeg, webp, png, heif) or ''.
	 *     @type array  $found    Map with bool keys location and identity.
	 *     @type string $error    Error message for STATUS_FAILED.
	 * }
	 */
	public static function scrub_file( $path, array $options = array() ) {
		$options = self::normalize_options( $options );
		$result  = self::result( self::STATUS_UNSUPPORTED );

		if ( ! is_string( $path ) || ! is_file( $path ) || ! is_readable( $path ) ) {
			$result['status'] = self::STATUS_FAILED;
			$result['error']  = 'File is not readable.';
			return $result;
		}

		/**
		 * Filter the largest file size scrubbed, in bytes.
		 *
		 * @since 1.8.0
		 *
		 * @param int $max_size Default 100 MB.
		 */
		$max_size = (int) apply_filters( 'piip_image_scrub_max_size', 100 * MB_IN_BYTES );
		if ( filesize( $path ) > $max_size ) {
			$result['status'] = self::STATUS_FAILED;
			$result['error']  = 'File is larger than the scrub size limit.';
			return $result;
		}

		$data = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file, binary read.
		if ( false === $data ) {
			$result['status'] = self::STATUS_FAILED;
			$result['error']  = 'File could not be read.';
			return $result;
		}

		$result = self::scrub_buffer( $data, $options );

		if ( $options['dry_run'] || self::STATUS_SCRUBBED !== $result['status'] ) {
			return $result;
		}

		if ( ! self::write_atomically( $path, $data ) ) {
			$result['status'] = self::STATUS_FAILED;
			$result['error']  = 'File could not be written.';
		}

		return $result;
	}

	/**
	 * Scrub (or inspect) image data in memory.
	 *
	 * @since 1.8.0
	 *
	 * @param string $data    Image bytes; modified in place unless dry_run.
	 * @param array  $options See scrub_file().
	 * @return array Result, see scrub_file().
	 */
	public static function scrub_buffer( &$data, array $options = array() ) {
		$options = self::normalize_options( $options );
		$format  = self::detect_format( $data );
		$result  = self::result( self::STATUS_UNSUPPORTED, $format );

		if ( '' === $format ) {
			return $result;
		}

		$found = array(
			'location' => false,
			'identity' => false,
		);
		$edits = array();

		switch ( $format ) {
			case 'jpeg':
				$ok = self::collect_jpeg( $data, $options, $found, $edits );
				break;
			case 'webp':
				$ok = self::collect_webp( $data, $options, $found, $edits );
				break;
			case 'png':
				$ok = self::collect_png( $data, $options, $found, $edits );
				break;
			default:
				// HEIF: the item table is best effort; the signature sweep
				// below still runs if it cannot be read.
				self::collect_heif( $data, $options, $found, $edits );
				$ok = true;
		}

		// Signature sweep: HEIF items, JPEG multi-picture (MPF) extras such
		// as gain maps, and anything the structured pass did not reach.
		if ( $ok && 'png' !== $format ) {
			self::collect_signatures( $data, $options, $found, $edits );
		}

		$result['found'] = $found;

		if ( ! $ok ) {
			$result['status'] = self::STATUS_FAILED;
			$result['error']  = 'Malformed image structure; left unchanged.';
			return $result;
		}

		$wanted = ( $options['location'] && $found['location'] ) || ( $options['identity'] && $found['identity'] );
		if ( ! $wanted ) {
			$result['status'] = self::STATUS_CLEAN;
			return $result;
		}

		$result['status'] = self::STATUS_SCRUBBED;
		if ( $options['dry_run'] ) {
			return $result;
		}

		$scrubbed = self::apply_edits( $data, $edits );
		if ( 'png' === $format ) {
			$scrubbed = self::fix_png_crcs( $scrubbed );
		}

		// Verify: nothing selected may remain, and the length must not change.
		$check = $scrubbed;
		$after = self::scrub_buffer(
			$check,
			array(
				'location' => $options['location'],
				'identity' => $options['identity'],
				'dry_run'  => true,
			)
		);
		if ( strlen( $scrubbed ) !== strlen( $data ) || self::STATUS_CLEAN !== $after['status'] ) {
			$result['status'] = self::STATUS_FAILED;
			$result['error']  = 'Verification after scrubbing failed; left unchanged.';
			return $result;
		}

		$data = $scrubbed;

		return $result;
	}

	/**
	 * Fill in option defaults.
	 *
	 * @since 1.8.0
	 *
	 * @param array $options Options.
	 * @return array Options.
	 */
	private static function normalize_options( array $options ) {
		return array(
			'location' => ! isset( $options['location'] ) || ! empty( $options['location'] ),
			'identity' => ! isset( $options['identity'] ) || ! empty( $options['identity'] ),
			'dry_run'  => ! empty( $options['dry_run'] ),
		);
	}

	/**
	 * Build an empty result.
	 *
	 * @since 1.8.0
	 *
	 * @param string $status Status.
	 * @param string $format Format.
	 * @return array Result.
	 */
	private static function result( $status, $format = '' ) {
		return array(
			'status' => $status,
			'format' => $format,
			'found'  => array(
				'location' => false,
				'identity' => false,
			),
			'error'  => '',
		);
	}

	/**
	 * Detect the container format from magic bytes.
	 *
	 * @since 1.8.0
	 *
	 * @param string $data Image bytes.
	 * @return string jpeg, webp, png, heif or '' when unsupported.
	 */
	public static function detect_format( $data ) {
		if ( 0 === strncmp( $data, "\xFF\xD8\xFF", 3 ) ) {
			return 'jpeg';
		}
		if ( 0 === strncmp( $data, "\x89PNG\r\n\x1A\n", 8 ) ) {
			return 'png';
		}
		if ( 0 === strncmp( $data, 'RIFF', 4 ) && 'WEBP' === substr( $data, 8, 4 ) ) {
			return 'webp';
		}
		if ( 'ftyp' === substr( $data, 4, 4 ) && preg_match( '/^(?:heic|heix|hevc|hevx|heim|heis|hevm|hevs|mif1|msf1|avif|avis)$/', substr( $data, 8, 4 ) ) ) {
			return 'heif';
		}

		return '';
	}

	/**
	 * Collect edits for a JPEG: walk segments up to the first scan.
	 *
	 * @since 1.8.0
	 *
	 * @param string $d       Data.
	 * @param array  $options Options.
	 * @param array  $found   Found flags (by reference).
	 * @param array  $edits   Edits (by reference).
	 * @return bool False if the structure is malformed.
	 */
	private static function collect_jpeg( $d, array $options, array &$found, array &$edits ) {
		$n = strlen( $d );
		$p = 2;

		while ( $p + 4 <= $n ) {
			if ( "\xFF" !== $d[ $p ] ) {
				return false;
			}
			$marker = ord( $d[ $p + 1 ] );

			if ( 0xFF === $marker ) {
				++$p; // Fill byte.
				continue;
			}
			if ( 0xD8 === $marker || 0x01 === $marker || ( $marker >= 0xD0 && $marker <= 0xD7 ) ) {
				$p += 2;
				continue;
			}
			if ( 0xDA === $marker || 0xD9 === $marker ) {
				return true; // Start of scan / end of image: metadata is before this.
			}

			$length = self::u16( $d, $p + 2, false );
			if ( $length < 2 || $p + 2 + $length > $n ) {
				return false;
			}
			$start = $p + 4;
			$end   = $p + 2 + $length;

			if ( 0xE1 === $marker ) {
				if ( self::starts_at( $d, $start, "Exif\0\0" ) ) {
					self::collect_exif_block( $d, $start + 6, $end, $options, $found, $edits );
				} elseif ( self::starts_at( $d, $start, "http://ns.adobe.com/xap/1.0/\0" ) ) {
					self::collect_xmp( $d, $start + 29, $end, $options, $found, $edits );
				} elseif ( self::starts_at( $d, $start, "http://ns.adobe.com/xmp/extension/\0" ) ) {
					// GUID (32) + full length (4) + offset (4) precede the chunk.
					self::collect_xmp( $d, min( $end, $start + 75 ), $end, $options, $found, $edits );
				}
			}

			$p = $end;
		}

		return true;
	}

	/**
	 * Collect edits for a WebP: EXIF and XMP chunks.
	 *
	 * @since 1.8.0
	 *
	 * @param string $d       Data.
	 * @param array  $options Options.
	 * @param array  $found   Found flags (by reference).
	 * @param array  $edits   Edits (by reference).
	 * @return bool False if the structure is malformed.
	 */
	private static function collect_webp( $d, array $options, array &$found, array &$edits ) {
		$n = strlen( $d );
		$p = 12;

		while ( $p + 8 <= $n ) {
			$fourcc = substr( $d, $p, 4 );
			$size   = self::u32( $d, $p + 4, true );
			$start  = $p + 8;
			$end    = $start + $size;
			if ( $end > $n ) {
				return false;
			}

			if ( 'EXIF' === $fourcc ) {
				$tiff = self::starts_at( $d, $start, "Exif\0\0" ) ? $start + 6 : $start;
				self::collect_exif_block( $d, $tiff, $end, $options, $found, $edits );
			} elseif ( 'XMP ' === $fourcc ) {
				self::collect_xmp( $d, $start, $end, $options, $found, $edits );
			}

			$p = $end + ( $size & 1 );
		}

		return true;
	}

	/**
	 * Collect edits for a PNG: eXIf and uncompressed XMP iTXt chunks.
	 *
	 * @since 1.8.0
	 *
	 * @param string $d       Data.
	 * @param array  $options Options.
	 * @param array  $found   Found flags (by reference).
	 * @param array  $edits   Edits (by reference).
	 * @return bool False if the structure is malformed.
	 */
	private static function collect_png( $d, array $options, array &$found, array &$edits ) {
		$n = strlen( $d );
		$p = 8;

		while ( $p + 12 <= $n ) {
			$length = self::u32( $d, $p, false );
			$type   = substr( $d, $p + 4, 4 );
			$start  = $p + 8;
			$end    = $start + $length;
			if ( $end + 4 > $n ) {
				return false;
			}

			if ( 'eXIf' === $type ) {
				$tiff = self::starts_at( $d, $start, "Exif\0\0" ) ? $start + 6 : $start;
				self::collect_exif_block( $d, $tiff, $end, $options, $found, $edits );
			} elseif ( 'iTXt' === $type && self::starts_at( $d, $start, "XML:com.adobe.xmp\0" ) ) {
				$q = $start + 18;
				if ( $q + 2 <= $end && "\0" === $d[ $q ] ) { // Uncompressed only.
					$q += 2;
					// Skip language tag and translated keyword (both NUL-terminated).
					for ( $i = 0; $i < 2; $i++ ) {
						$nul = strpos( $d, "\0", $q );
						if ( false === $nul || $nul >= $end ) {
							return false;
						}
						$q = $nul + 1;
					}
					self::collect_xmp( $d, $q, $end, $options, $found, $edits );
				}
			} elseif ( 'IEND' === $type ) {
				return true;
			}

			$p = $end + 4;
		}

		return true;
	}

	/**
	 * Collect edits for an EXIF block found in a known container slot.
	 *
	 * A block that does not parse may still hold a location that cannot be
	 * read, so with location removal on it is zero-filled (the container
	 * stays valid: the slot keeps its size, only its contents go). An
	 * all-zero block counts as empty, which keeps the result stable.
	 *
	 * @since 1.8.0
	 *
	 * @param string $d       Data.
	 * @param int    $t       TIFF header offset.
	 * @param int    $end     Block end.
	 * @param array  $options Options.
	 * @param array  $found   Found flags (by reference).
	 * @param array  $edits   Edits (by reference).
	 * @return void
	 */
	private static function collect_exif_block( $d, $t, $end, array $options, array &$found, array &$edits ) {
		if ( $end <= $t || '' === trim( substr( $d, $t, min( 64, $end - $t ) ), "\0" ) ) {
			return;
		}

		$local_found = array(
			'location' => false,
			'identity' => false,
		);
		$local_edits = array();

		if ( self::collect_tiff( $d, $t, $end, $options, $local_found, $local_edits ) ) {
			$found['location'] = $found['location'] || $local_found['location'];
			$found['identity'] = $found['identity'] || $local_found['identity'];
			foreach ( $local_edits as $edit ) {
				$edits[ $edit[0] ] = $edit;
			}
			return;
		}

		$found['location'] = true;
		if ( $options['location'] ) {
			$edits[ $t ] = array( $t, str_repeat( "\0", $end - $t ) );
		}
	}

	/**
	 * Collect edits for the Exif items of a HEIF/HEIC/AVIF file.
	 *
	 * Reads meta > iinf (item types) and meta > iloc (item locations).
	 * Needed for writers that store the TIFF header without the
	 * "Exif\0\0" prefix the signature sweep looks for.
	 *
	 * @since 1.8.0
	 *
	 * @param string $d       Data.
	 * @param array  $options Options.
	 * @param array  $found   Found flags (by reference).
	 * @param array  $edits   Edits (by reference).
	 * @return void
	 */
	private static function collect_heif( $d, array $options, array &$found, array &$edits ) {
		$n    = strlen( $d );
		$meta = self::find_box( $d, 0, $n, 'meta' );
		if ( null === $meta ) {
			return;
		}

		// meta is a full box: skip version and flags.
		$iinf = self::find_box( $d, $meta[0] + 4, $meta[1], 'iinf' );
		$iloc = self::find_box( $d, $meta[0] + 4, $meta[1], 'iloc' );
		if ( null === $iinf || null === $iloc ) {
			return;
		}

		// Item IDs of type Exif.
		$exif_ids = array();
		$version  = ord( $d[ $iinf[0] ] );
		$p        = $iinf[0] + 4 + ( 0 === $version ? 2 : 4 );
		while ( null !== ( $infe = self::next_box( $d, $p, $iinf[1] ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			list( $type, $start, $end ) = $infe;
			$p                          = $end;
			if ( 'infe' !== $type || $start + 4 > $end ) {
				continue;
			}
			$infe_version = ord( $d[ $start ] );
			if ( $infe_version < 2 ) {
				continue;
			}
			$id_size = 2 === $infe_version ? 2 : 4;
			$item_id = 2 === $id_size ? self::u16( $d, $start + 4, false ) : self::u32( $d, $start + 4, false );
			if ( 'Exif' === substr( $d, $start + 4 + $id_size + 2, 4 ) ) {
				$exif_ids[ $item_id ] = true;
			}
		}
		if ( ! $exif_ids ) {
			return;
		}

		// Locations from iloc.
		$q           = $iloc[0];
		$version     = ord( $d[ $q ] );
		$sizes       = ord( $d[ $q + 4 ] );
		$sizes2      = ord( $d[ $q + 5 ] );
		$offset_size = $sizes >> 4;
		$length_size = $sizes & 0x0F;
		$base_size   = $sizes2 >> 4;
		$index_size  = $version > 0 ? $sizes2 & 0x0F : 0;
		$q          += 6;
		$count       = $version < 2 ? self::u16( $d, $q, false ) : self::u32( $d, $q, false );
		$q          += $version < 2 ? 2 : 4;

		for ( $i = 0; $i < $count && $q < $iloc[1]; $i++ ) {
			$item_id = $version < 2 ? self::u16( $d, $q, false ) : self::u32( $d, $q, false );
			$q      += $version < 2 ? 2 : 4;
			$method  = 0;
			if ( $version > 0 ) {
				$method = self::u16( $d, $q, false ) & 0x0F;
				$q     += 2;
			}
			$q          += 2; // data_reference_index.
			$base        = self::uint( $d, $q, $base_size );
			$q          += $base_size;
			$extents     = self::u16( $d, $q, false );
			$q          += 2;
			$first_start = null;
			$first_len   = 0;
			for ( $e = 0; $e < $extents; $e++ ) {
				$q      += $index_size;
				$ext_off = self::uint( $d, $q, $offset_size );
				$q      += $offset_size;
				$ext_len = self::uint( $d, $q, $length_size );
				$q      += $length_size;
				if ( null === $first_start ) {
					$first_start = $base + $ext_off;
					$first_len   = $ext_len;
				}
			}

			if ( ! isset( $exif_ids[ $item_id ] ) || 0 !== $method || null === $first_start || $first_start + 4 > $n ) {
				continue;
			}

			$item_end = $first_len > 0 ? min( $n, $first_start + $first_len ) : $n;
			$tiff     = $first_start + 4 + self::u32( $d, $first_start, false );
			if ( self::starts_at( $d, $tiff, "Exif\0\0" ) ) {
				$tiff += 6;
			}
			if ( $tiff < $item_end ) {
				self::collect_exif_block( $d, $tiff, $item_end, $options, $found, $edits );
			}
		}
	}

	/**
	 * Find the first box of a type among the boxes in [start, end).
	 *
	 * @since 1.8.0
	 *
	 * @param string $d     Data.
	 * @param int    $start Start.
	 * @param int    $end   End.
	 * @param string $type  Box type.
	 * @return array|null [payload start, box end] or null.
	 */
	private static function find_box( $d, $start, $end, $type ) {
		$p = $start;
		while ( null !== ( $box = self::next_box( $d, $p, $end ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( $type === $box[0] ) {
				return array( $box[1], $box[2] );
			}
			$p = $box[2];
		}

		return null;
	}

	/**
	 * Read the ISOBMFF box at $p.
	 *
	 * @since 1.8.0
	 *
	 * @param string $d   Data.
	 * @param int    $p   Box start.
	 * @param int    $end Parent end.
	 * @return array|null [type, payload start, box end] or null.
	 */
	private static function next_box( $d, $p, $end ) {
		if ( $p + 8 > $end ) {
			return null;
		}

		$size   = self::u32( $d, $p, false );
		$type   = substr( $d, $p + 4, 4 );
		$header = 8;
		if ( 1 === $size ) {
			$size   = self::uint( $d, $p + 8, 8 );
			$header = 16;
		} elseif ( 0 === $size ) {
			$size = $end - $p;
		}
		if ( $size < $header || $p + $size > $end ) {
			return null;
		}

		return array( $type, $p + $header, $p + $size );
	}

	/**
	 * Read a big-endian unsigned integer of 0, 4 or 8 bytes.
	 *
	 * @since 1.8.0
	 *
	 * @param string $d    Data.
	 * @param int    $pos  Offset.
	 * @param int    $size Byte size.
	 * @return int Value.
	 */
	private static function uint( $d, $pos, $size ) {
		if ( 4 === $size ) {
			return self::u32( $d, $pos, false );
		}
		if ( 8 === $size ) {
			return ( self::u32( $d, $pos, false ) << 32 ) | self::u32( $d, $pos + 4, false );
		}

		return 0;
	}

	/**
	 * Find EXIF ("Exif\0\0" + TIFF header) and XMP packets by signature.
	 *
	 * Each EXIF candidate is validated as a complete TIFF structure before
	 * any edit is recorded, so a coincidental byte match in pixel data is
	 * ignored. Edits overlapping ones already collected are skipped.
	 *
	 * @since 1.8.0
	 *
	 * @param string $d       Data.
	 * @param array  $options Options.
	 * @param array  $found   Found flags (by reference).
	 * @param array  $edits   Edits (by reference).
	 * @return void
	 */
	private static function collect_signatures( $d, array $options, array &$found, array &$edits ) {
		$n      = strlen( $d );
		$offset = 0;

		while ( false !== ( $pos = strpos( $d, "Exif\0\0", $offset ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$offset = $pos + 6;
			$tiff   = $pos + 6;
			$head   = substr( $d, $tiff, 4 );
			if ( "II*\0" !== $head && "MM\0*" !== $head ) {
				continue;
			}

			$local_found = array(
				'location' => false,
				'identity' => false,
			);
			$local_edits = array();
			if ( self::collect_tiff( $d, $tiff, $n, $options, $local_found, $local_edits ) ) {
				$found['location'] = $found['location'] || $local_found['location'];
				$found['identity'] = $found['identity'] || $local_found['identity'];
				foreach ( $local_edits as $edit ) {
					$edits[ $edit[0] ] = $edit;
				}
			}
		}

		$offset = 0;
		while ( false !== ( $pos = strpos( $d, '<x:xmpmeta', $offset ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$close = strpos( $d, '</x:xmpmeta>', $pos );
			if ( false === $close ) {
				break;
			}
			$end = $close + 12;
			self::collect_xmp( $d, $pos, $end, $options, $found, $edits );
			$offset = $end;
		}
	}

	/**
	 * Validate a TIFF/EXIF structure and collect its edits.
	 *
	 * @since 1.8.0
	 *
	 * @param string $d       Data.
	 * @param int    $t       Offset of the TIFF header.
	 * @param int    $end     End of the region holding the TIFF data.
	 * @param array  $options Options.
	 * @param array  $found   Found flags (by reference).
	 * @param array  $edits   Edits (by reference).
	 * @return bool False if the structure is malformed.
	 */
	private static function collect_tiff( $d, $t, $end, array $options, array &$found, array &$edits ) {
		if ( $t + 8 > $end ) {
			return false;
		}

		$order = substr( $d, $t, 2 );
		if ( 'II' === $order ) {
			$le = true;
		} elseif ( 'MM' === $order ) {
			$le = false;
		} else {
			return false;
		}
		if ( 42 !== self::u16( $d, $t + 2, $le ) ) {
			return false;
		}

		$ifd0 = self::read_ifd( $d, $t, $end, self::u32( $d, $t + 4, $le ), $le );
		if ( false === $ifd0 ) {
			return false;
		}

		$local    = array();
		$gps      = null;
		$exif     = null;
		$exif_ifd = false;

		foreach ( $ifd0['entries'] as $entry ) {
			if ( self::TAG_GPS_IFD === $entry['tag'] ) {
				$gps = $entry['value'];
			} elseif ( self::TAG_EXIF_IFD === $entry['tag'] ) {
				$exif = $entry['value'];
			} elseif ( in_array( $entry['tag'], self::IDENTITY_TAGS, true ) ) {
				self::collect_identity( $d, $entry, $found, $local );
			}
		}

		if ( null !== $exif ) {
			$exif_ifd = self::read_ifd( $d, $t, $end, $exif, $le );
			if ( false === $exif_ifd ) {
				return false;
			}
			foreach ( $exif_ifd['entries'] as $entry ) {
				if ( in_array( $entry['tag'], self::IDENTITY_TAGS, true ) ) {
					self::collect_identity( $d, $entry, $found, $local );
				}
			}
		}

		$gps_edits = array();
		if ( null !== $gps ) {
			$gps_ifd = self::read_ifd( $d, $t, $end, $gps, $le );
			if ( false === $gps_ifd ) {
				return false;
			}

			// Bytes other structures use. Malformed files can point the GPS
			// IFD into the Exif IFD (or share value data); never zero those.
			$protected = self::structure_ranges( $ifd0 );
			if ( false !== $exif_ifd ) {
				$protected = array_merge( $protected, self::structure_ranges( $exif_ifd ) );
				foreach ( $exif_ifd['entries'] as $entry ) {
					if ( 0xA005 === $entry['tag'] ) { // Interoperability IFD.
						$interop = self::read_ifd( $d, $t, $end, $entry['value'], $le );
						if ( false !== $interop ) {
							$protected = array_merge( $protected, self::structure_ranges( $interop ) );
						}
					}
				}
			}

			$gps_found = false;
			foreach ( $gps_ifd['entries'] as $entry ) {
				// GPS tags are 0x00-0x1F; anything else is not a location.
				if ( $entry['tag'] > 0x1F || '' === trim( substr( $d, $entry['data_pos'], $entry['size'] ), "\0" ) ) {
					continue;
				}
				$gps_found = true;
				$range     = array( $entry['data_pos'], $entry['data_pos'] + $entry['size'] );
				if ( ! self::overlaps( $range, $protected ) ) {
					$gps_edits[] = array( $entry['data_pos'], str_repeat( "\0", $entry['size'] ) );
				}
			}

			if ( $gps_found ) {
				$found['location'] = true;
				// Also empty the IFD itself when it is not shared: an IFD with
				// zero entries and no next IFD is a valid, empty GPS IFD.
				$span = array( $gps_ifd['pos'], $gps_ifd['pos'] + 2 + 12 * $gps_ifd['count'] + 4 );
				if ( ! self::overlaps( $span, $protected ) ) {
					$gps_edits[] = array( $gps_ifd['pos'], str_repeat( "\0", $span[1] - $span[0] ) );
				}
			}
		}

		if ( $options['identity'] ) {
			foreach ( $local as $edit ) {
				$edits[ $edit[0] ] = $edit;
			}
		}
		if ( $options['location'] ) {
			foreach ( $gps_edits as $edit ) {
				$edits[ $edit[0] ] = $edit;
			}
		}

		return true;
	}

	/**
	 * Byte ranges an IFD occupies: its entry table and out-of-line values.
	 *
	 * @since 1.8.0
	 *
	 * @param array $ifd IFD from read_ifd().
	 * @return array List of [start, end) ranges.
	 */
	private static function structure_ranges( array $ifd ) {
		$ranges = array( array( $ifd['pos'], $ifd['pos'] + 2 + 12 * $ifd['count'] + 4 ) );
		foreach ( $ifd['entries'] as $entry ) {
			if ( $entry['size'] > 4 ) {
				$ranges[] = array( $entry['data_pos'], $entry['data_pos'] + $entry['size'] );
			}
		}

		return $ranges;
	}

	/**
	 * Whether a [start, end) range overlaps any of the given ranges.
	 *
	 * @since 1.8.0
	 *
	 * @param array $range  Range.
	 * @param array $ranges Ranges.
	 * @return bool
	 */
	private static function overlaps( array $range, array $ranges ) {
		foreach ( $ranges as $other ) {
			if ( $range[0] < $other[1] && $other[0] < $range[1] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Record an identifying field: blank its value, keep its length.
	 *
	 * @since 1.8.0
	 *
	 * @param string $d     Data.
	 * @param array  $entry IFD entry.
	 * @param array  $found Found flags (by reference).
	 * @param array  $local Edits (by reference).
	 * @return void
	 */
	private static function collect_identity( $d, array $entry, array &$found, array &$local ) {
		$value = substr( $d, $entry['data_pos'], $entry['size'] );
		if ( '' === trim( $value, " \0" ) ) {
			return;
		}

		$found['identity'] = true;

		// ASCII: spaces before the terminating NUL; other types: zeros.
		if ( 2 === $entry['type'] ) {
			$blank = str_repeat( ' ', max( 0, $entry['size'] - 1 ) ) . ( $entry['size'] > 0 ? "\0" : '' );
		} else {
			$blank = str_repeat( "\0", $entry['size'] );
		}
		$local[] = array( $entry['data_pos'], $blank );
	}

	/**
	 * Read and bounds-check an IFD.
	 *
	 * @since 1.8.0
	 *
	 * @param string $d      Data.
	 * @param int    $t      TIFF header offset.
	 * @param int    $end    Region end.
	 * @param int    $offset IFD offset relative to $t.
	 * @param bool   $le     Little endian.
	 * @return array|false {pos, count, entries[{tag, type, size, value, data_pos}]} or false.
	 */
	private static function read_ifd( $d, $t, $end, $offset, $le ) {
		$pos = $t + $offset;
		if ( $offset < 8 || $pos + 2 > $end ) {
			return false;
		}

		$count = self::u16( $d, $pos, $le );
		if ( $count > self::MAX_IFD_ENTRIES || $pos + 2 + 12 * $count + 4 > $end ) {
			return false;
		}

		$entries = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$e    = $pos + 2 + 12 * $i;
			$type = self::u16( $d, $e + 2, $le );
			$num  = self::u32( $d, $e + 4, $le );
			$unit = isset( self::TYPE_SIZES[ $type ] ) ? self::TYPE_SIZES[ $type ] : 0;
			if ( 0 === $unit ) {
				continue; // Unknown type: leave it alone.
			}
			$size  = $unit * $num;
			$value = self::u32( $d, $e + 8, $le );

			if ( $size <= 4 ) {
				$data_pos = $e + 8;
			} else {
				$data_pos = $t + $value;
				if ( $data_pos < $t || $data_pos + $size > $end ) {
					return false;
				}
			}

			$entries[] = array(
				'tag'      => self::u16( $d, $e, $le ),
				'type'     => $type,
				'size'     => $size,
				'value'    => $value,
				'data_pos' => $data_pos,
			);
		}

		return array(
			'pos'     => $pos,
			'count'   => $count,
			'entries' => $entries,
		);
	}

	/**
	 * Collect XMP edits: blank selected properties, keep the packet length.
	 *
	 * @since 1.8.0
	 *
	 * @param string $d       Data.
	 * @param int    $start   XMP start.
	 * @param int    $end     XMP end.
	 * @param array  $options Options.
	 * @param array  $found   Found flags (by reference).
	 * @param array  $edits   Edits (by reference).
	 * @return void
	 */
	private static function collect_xmp( $d, $start, $end, array $options, array &$found, array &$edits ) {
		if ( $end <= $start ) {
			return;
		}

		$xmp = substr( $d, $start, $end - $start );

		foreach ( array(
			'location' => self::XMP_LOCATION,
			'identity' => self::XMP_IDENTITY,
		) as $kind => $names ) {
			$patterns = array(
				// Attribute form: exif:GPSLatitude="35,39.6N".
				'/(?<=[\s"\'])(?:' . $names . ')\s*=\s*(?:"[^"]*"|\'[^\']*\')/',
				// Element form, including nested rdf:Seq/rdf:Alt.
				'/<(' . $names . ')(?:\s[^>]*)?>.*?<\/\1>/s',
				// Empty element form.
				'/<(?:' . $names . ')(?:\s[^>]*)?\/>/',
			);

			foreach ( $patterns as $pattern ) {
				if ( ! preg_match_all( $pattern, $xmp, $matches, PREG_OFFSET_CAPTURE ) ) {
					continue;
				}
				foreach ( $matches[0] as $match ) {
					$found[ $kind ] = true;
					if ( $options[ $kind ] ) {
						$pos           = $start + $match[1];
						$edits[ $pos ] = array( $pos, str_repeat( ' ', strlen( $match[0] ) ) );
					}
				}
			}
		}
	}

	/**
	 * Apply same-length edits.
	 *
	 * @since 1.8.0
	 *
	 * @param string $data  Data.
	 * @param array  $edits List of [position, bytes].
	 * @return string Edited data.
	 */
	private static function apply_edits( $data, array $edits ) {
		foreach ( $edits as $edit ) {
			$data = substr_replace( $data, $edit[1], $edit[0], strlen( $edit[1] ) );
		}

		return $data;
	}

	/**
	 * Recompute every PNG chunk CRC (only edited chunks change).
	 *
	 * @since 1.8.0
	 *
	 * @param string $data PNG bytes.
	 * @return string PNG bytes.
	 */
	private static function fix_png_crcs( $data ) {
		$n = strlen( $data );
		$p = 8;

		while ( $p + 12 <= $n ) {
			$length = self::u32( $data, $p, false );
			$end    = $p + 8 + $length;
			if ( $end + 4 > $n ) {
				break;
			}
			$crc  = pack( 'N', crc32( substr( $data, $p + 4, 4 + $length ) ) );
			$data = substr_replace( $data, $crc, $end, 4 );
			$p    = $end + 4;
		}

		return $data;
	}

	/**
	 * Write data via a temporary file in the same directory, then rename.
	 *
	 * @since 1.8.0
	 *
	 * @param string $path Target path.
	 * @param string $data Data.
	 * @return bool Success.
	 */
	private static function write_atomically( $path, $data ) {
		$temp = $path . '.piip-' . wp_generate_password( 8, false ) . '.tmp';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local file in the uploads directory.
		if ( strlen( $data ) !== file_put_contents( $temp, $data ) ) {
			wp_delete_file( $temp );
			return false;
		}

		$perms = fileperms( $path );
		if ( false !== $perms ) {
			chmod( $temp, $perms & 0777 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic replace.
		if ( ! rename( $temp, $path ) ) {
			wp_delete_file( $temp );
			return false;
		}

		clearstatcache( true, $path );

		return true;
	}

	/**
	 * Whether $needle occurs in $d at $pos (safe at any offset).
	 *
	 * @since 1.8.0
	 *
	 * @param string $d      Data.
	 * @param int    $pos    Offset.
	 * @param string $needle Bytes.
	 * @return bool
	 */
	private static function starts_at( $d, $pos, $needle ) {
		return $pos >= 0 && substr( $d, $pos, strlen( $needle ) ) === $needle;
	}

	/**
	 * Read an unsigned 16-bit integer.
	 *
	 * @since 1.8.0
	 *
	 * @param string $d   Data.
	 * @param int    $pos Offset.
	 * @param bool   $le  Little endian.
	 * @return int Value (0 when out of range).
	 */
	private static function u16( $d, $pos, $le ) {
		if ( $pos < 0 || $pos + 2 > strlen( $d ) ) {
			return 0;
		}

		return unpack( $le ? 'v' : 'n', substr( $d, $pos, 2 ) )[1];
	}

	/**
	 * Read an unsigned 32-bit integer.
	 *
	 * @since 1.8.0
	 *
	 * @param string $d   Data.
	 * @param int    $pos Offset.
	 * @param bool   $le  Little endian.
	 * @return int Value (0 when out of range).
	 */
	private static function u32( $d, $pos, $le ) {
		if ( $pos < 0 || $pos + 4 > strlen( $d ) ) {
			return 0;
		}

		return unpack( $le ? 'V' : 'N', substr( $d, $pos, 4 ) )[1];
	}
}
