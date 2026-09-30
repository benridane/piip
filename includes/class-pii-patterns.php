<?php
/**
 * PII Text Patterns
 *
 * Single source of truth for PII found in free text: every rule here is
 * used both to mask text (PIIP_PII_Masker) and to report detections
 * (PIIP_PII_Detector::find_all_pii(), the preview, the scanner and the
 * Abilities API), so what is reported is exactly what gets masked.
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
 * Class PIIP_PII_Patterns
 *
 * Rules are applied in order to text normalized by normalize() (full-width
 * alphanumerics and symbols folded to ASCII, one character for one), so
 * patterns only need to handle half-width input.
 *
 * @since 1.7.0
 */
final class PIIP_PII_Patterns {

	/**
	 * The 47 prefectures.
	 *
	 * @since 1.7.0
	 * @var string
	 */
	public const PREFECTURES = '北海道|東京都|京都府|大阪府|(?:青森|岩手|宮城|秋田|山形|福島|茨城|栃木|群馬|埼玉|千葉|神奈川|新潟|富山|石川|福井|山梨|長野|岐阜|静岡|愛知|三重|滋賀|兵庫|奈良|和歌山|鳥取|島根|岡山|広島|山口|徳島|香川|愛媛|高知|福岡|佐賀|長崎|熊本|大分|宮崎|鹿児島|沖縄)県';

	/**
	 * The 20 designated cities, commonly written without the prefecture.
	 *
	 * @since 1.7.0
	 * @var string
	 */
	public const DESIGNATED_CITIES = '札幌市|仙台市|さいたま市|千葉市|横浜市|川崎市|相模原市|新潟市|静岡市|浜松市|名古屋市|京都市|大阪市|堺市|神戸市|岡山市|広島市|北九州市|福岡市|熊本市';

	/**
	 * Tokyo's 23 special wards, commonly written without 東京都.
	 *
	 * @since 1.7.0
	 * @var string
	 */
	public const TOKYO_WARDS = '千代田区|中央区|港区|新宿区|文京区|台東区|墨田区|江東区|品川区|目黒区|大田区|世田谷区|渋谷区|中野区|杉並区|豊島区|北区|荒川区|板橋区|練馬区|足立区|葛飾区|江戸川区';

	/**
	 * Required block/lot number at the end of a Japanese address.
	 *
	 * 丁目/番地/号 forms or a hyphenated digit run (2-8-1). This is what
	 * keeps mere place mentions (東京都に行きました) from matching.
	 *
	 * @since 1.7.0
	 * @var string
	 */
	public const ADDRESS_TAIL = '(?:[0-9]{1,4}丁目(?:[\s]*[0-9]{1,4}(?:(?:-[0-9]{1,4}){1,3}|番地?(?:[0-9]{1,4}号)?|号))?|[0-9]{1,4}(?:番地?|号)(?:[0-9]{1,4}号)?|[0-9]{1,4}(?:-[0-9]{1,4}){1,3})';

	/**
	 * Characters that may not directly precede a label.
	 *
	 * Keeps labels from matching inside words (Hotel:, 現住所 vs 住所).
	 * Hiragana is allowed so 私の名前： still matches.
	 *
	 * @since 1.7.0
	 * @var string
	 */
	private const LABEL_BOUNDARY = '(?<![A-Za-z0-9_' . self::KANJI . self::KATAKANA . '])';

	/**
	 * Kanji ranges for character classes.
	 *
	 * Explicit ranges instead of \p{Han}: PCRE2 10.40+ matches scripts by
	 * Script_Extensions, which puts 。、「」 and ー in Han/Katakana.
	 *
	 * @since 1.7.0
	 * @var string
	 */
	public const KANJI = '\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{F900}-\x{FAFF}々〆ヶ';

	/**
	 * Katakana ranges (with the long vowel mark) for character classes.
	 *
	 * @since 1.7.0
	 * @var string
	 */
	public const KATAKANA = '\x{30A1}-\x{30FA}\x{30FC}\x{31F0}-\x{31FF}';

	/**
	 * Hiragana range for character classes.
	 *
	 * @since 1.7.0
	 * @var string
	 */
	public const HIRAGANA = '\x{3041}-\x{3096}';

	/**
	 * Separator between a label and its value: colon, closing bracket, or tab.
	 *
	 * @since 1.7.0
	 * @var string
	 */
	private const LABEL_SEPARATOR = '(?:[】\]][\t ]*[:=]?|[\t ]*[:=]|\t)[\t ]*';

	/**
	 * Cached rules.
	 *
	 * @since 1.7.0
	 * @var array|null
	 */
	private static $rules = null;

	/**
	 * Fold full-width alphanumerics and symbols to ASCII, one char for one.
	 *
	 * HTML-significant characters (＜ ＞ ＂ ＇ ＆ ｀) are deliberately left
	 * alone so normalization can never create markup. Dash look-alikes
	 * between alphanumerics (090ー1234) become "-".
	 *
	 * @since 1.7.0
	 *
	 * @param string $text Text.
	 * @return string Normalized text with the same number of characters.
	 */
	public static function normalize( $text ) {
		static $map = null;

		if ( null === $map ) {
			$map = array( '　' => ' ' );
			// U+FF01..U+FF5E map to U+0021..U+007E.
			for ( $code = 0xFF01; $code <= 0xFF5E; $code++ ) {
				$ascii = chr( $code - 0xFEE0 );
				if ( false !== strpos( '<>"\'&`', $ascii ) ) {
					continue;
				}
				$map[ mb_chr( $code, 'UTF-8' ) ] = $ascii;
			}
		}

		$text = strtr( (string) $text, $map );

		$dashed = preg_replace( '/(?<=[0-9A-Za-z])[‐‑‒–—―−ーｰ](?=[0-9A-Za-z])/u', '-', $text );

		return null === $dashed ? $text : $dashed;
	}

	/**
	 * Carry masking done on normalized text back onto the original text.
	 *
	 * Characters that masking left alone are taken from the original, so
	 * full-width text stays full-width outside the masked spans. $original
	 * and $normalized have the same characters, one for one.
	 *
	 * @since 1.7.0
	 *
	 * @param string $original   Original text.
	 * @param string $normalized normalize( $original ).
	 * @param string $masked     Masked version of $normalized.
	 * @return string Masked text in the original's characters.
	 */
	public static function restore_original_chars( $original, $normalized, $masked ) {
		$split = function ( $text ) {
			return preg_split( '/(?<=\n)/', $text, -1, PREG_SPLIT_NO_EMPTY );
		};

		$orig_lines = $split( $original );
		$norm_lines = $split( $normalized );
		$mask_lines = $split( $masked );

		if ( count( $orig_lines ) !== count( $norm_lines ) ) {
			return $masked; // Should not happen; stay safe (masked) if it does.
		}

		$output = '';
		$hunk_o = '';
		$hunk_n = '';
		$hunk_m = '';

		$flush = function () use ( &$output, &$hunk_o, &$hunk_n, &$hunk_m ) {
			if ( '' !== $hunk_n || '' !== $hunk_m ) {
				$output .= self::restore_chars( $hunk_o, $hunk_n, $hunk_m );
			}
			$hunk_o = '';
			$hunk_n = '';
			$hunk_m = '';
		};

		foreach ( self::diff( $norm_lines, $mask_lines ) as $op ) {
			if ( '=' === $op[0] ) {
				$flush();
				$output .= $orig_lines[ $op[1] ];
			} elseif ( '-' === $op[0] ) {
				$hunk_o .= $orig_lines[ $op[1] ];
				$hunk_n .= $norm_lines[ $op[1] ];
			} else {
				$hunk_m .= $mask_lines[ $op[2] ];
			}
		}
		$flush();

		return $output;
	}

	/**
	 * Character-level version of restore_original_chars() for one hunk.
	 *
	 * @since 1.7.0
	 *
	 * @param string $original   Original hunk.
	 * @param string $normalized Normalized hunk.
	 * @param string $masked     Masked hunk.
	 * @return string Restored hunk.
	 */
	private static function restore_chars( $original, $normalized, $masked ) {
		$orig_chars = mb_str_split( $original, 1, 'UTF-8' );
		$norm_chars = mb_str_split( $normalized, 1, 'UTF-8' );
		$mask_chars = mb_str_split( $masked, 1, 'UTF-8' );

		if ( count( $orig_chars ) !== count( $norm_chars ) ) {
			return $masked;
		}

		$output = '';
		foreach ( self::diff( $norm_chars, $mask_chars ) as $op ) {
			if ( '=' === $op[0] ) {
				$output .= $orig_chars[ $op[1] ];
			} elseif ( '+' === $op[0] ) {
				$output .= $mask_chars[ $op[2] ];
			}
		}

		return $output;
	}

	/**
	 * Shortest edit script between two sequences (Myers' algorithm).
	 *
	 * @since 1.7.0
	 *
	 * @param array $a Old sequence.
	 * @param array $b New sequence.
	 * @return array List of [op, index in $a, index in $b]; op is '=', '-' or '+'.
	 */
	private static function diff( array $a, array $b ) {
		$n = count( $a );
		$m = count( $b );

		// Common prefix and suffix are the bulk of the text: skip them.
		$start = 0;
		while ( $start < $n && $start < $m && $a[ $start ] === $b[ $start ] ) {
			++$start;
		}
		$end_a = $n;
		$end_b = $m;
		while ( $end_a > $start && $end_b > $start && $a[ $end_a - 1 ] === $b[ $end_b - 1 ] ) {
			--$end_a;
			--$end_b;
		}

		$ops = array();
		for ( $i = 0; $i < $start; $i++ ) {
			$ops[] = array( '=', $i, $i );
		}

		$mid_a = array_slice( $a, $start, $end_a - $start );
		$mid_b = array_slice( $b, $start, $end_b - $start );
		foreach ( self::myers( $mid_a, $mid_b ) as $op ) {
			$ops[] = array( $op[0], $op[1] + $start, $op[2] + $start );
		}

		for ( $i = 0; $i < $n - $end_a; $i++ ) {
			$ops[] = array( '=', $end_a + $i, $end_b + $i );
		}

		return $ops;
	}

	/**
	 * Myers' O(ND) diff core.
	 *
	 * @since 1.7.0
	 *
	 * @param array $a Old sequence.
	 * @param array $b New sequence.
	 * @return array Edit script, see diff().
	 */
	private static function myers( array $a, array $b ) {
		$n = count( $a );
		$m = count( $b );

		if ( 0 === $n ) {
			return array_map(
				function ( $j ) {
					return array( '+', 0, $j );
				},
				range( 0, $m - 1 )
			);
		}
		if ( 0 === $m ) {
			return array_map(
				function ( $i ) {
					return array( '-', $i, 0 );
				},
				range( 0, $n - 1 )
			);
		}

		$max   = $n + $m;
		$v     = array( 1 => 0 );
		$trace = array();

		for ( $d = 0; $d <= $max; $d++ ) {
			$trace[] = $v;
			for ( $k = -$d; $k <= $d; $k += 2 ) {
				if ( -$d === $k || ( $d !== $k && ( isset( $v[ $k - 1 ] ) ? $v[ $k - 1 ] : -1 ) < ( isset( $v[ $k + 1 ] ) ? $v[ $k + 1 ] : -1 ) ) ) {
					$x = isset( $v[ $k + 1 ] ) ? $v[ $k + 1 ] : 0;
				} else {
					$x = ( isset( $v[ $k - 1 ] ) ? $v[ $k - 1 ] : 0 ) + 1;
				}
				$y = $x - $k;
				while ( $x < $n && $y < $m && $a[ $x ] === $b[ $y ] ) {
					++$x;
					++$y;
				}
				$v[ $k ] = $x;
				if ( $x >= $n && $y >= $m ) {
					break 2;
				}
			}
		}

		// Walk the trace backwards.
		$ops = array();
		$x   = $n;
		$y   = $m;
		for ( $d = count( $trace ) - 1; $d >= 0; $d-- ) {
			$v = $trace[ $d ];
			$k = $x - $y;
			if ( -$d === $k || ( $d !== $k && ( isset( $v[ $k - 1 ] ) ? $v[ $k - 1 ] : -1 ) < ( isset( $v[ $k + 1 ] ) ? $v[ $k + 1 ] : -1 ) ) ) {
				$prev_k = $k + 1;
			} else {
				$prev_k = $k - 1;
			}
			$prev_x = isset( $v[ $prev_k ] ) ? $v[ $prev_k ] : 0;
			$prev_y = $prev_x - $prev_k;

			while ( $x > $prev_x && $y > $prev_y ) {
				--$x;
				--$y;
				$ops[] = array( '=', $x, $y );
			}
			if ( $d > 0 ) {
				if ( $x === $prev_x ) {
					--$y;
					$ops[] = array( '+', $x, $y );
				} else {
					--$x;
					$ops[] = array( '-', $x, $y );
				}
			}
		}

		return array_reverse( $ops );
	}

	/**
	 * Get the ordered text rules.
	 *
	 * Each rule:
	 * - id:       Unique key.
	 * - type:     PII type (settings key mask_<type>).
	 * - regex:    Pattern applied to normalized text.
	 * - group:    Capture group holding the sensitive value (0 = whole match).
	 * - validate: Optional validator name, see validate().
	 * - mask:     PIIP_PII_Masker method applied to the value, or
	 * - replace:  Literal replacement for the value.
	 * - provider: Optional provider label, or provider_cb: detector method.
	 *
	 * Order matters: earlier rules consume text before later, more generic
	 * ones see it (a private key before token patterns, labeled values
	 * before bare numbers).
	 *
	 * @since 1.7.0
	 *
	 * @return array Rules.
	 */
	public static function get_text_rules() {
		if ( null === self::$rules ) {
			self::$rules = self::build_rules();
		}

		/**
		 * Filter the free-text PII rules used for masking and detection.
		 *
		 * @since 1.7.0
		 *
		 * @param array $rules Ordered rules, see PIIP_PII_Patterns::get_text_rules().
		 */
		return apply_filters( 'piip_text_rules', self::$rules );
	}

	/**
	 * Build the rule list.
	 *
	 * @since 1.7.0
	 *
	 * @return array Rules.
	 */
	private static function build_rules() {
		$lb  = self::LABEL_BOUNDARY;
		$sep = self::LABEL_SEPARATOR;

		// Labels used as value terminators (a value ends where the next field starts).
		$name_labels    = 'お名前|ご氏名|登録氏名|登録名|氏名|フルネーム|姓名|ご担当者様?名|担当者名|受取人(?:氏名|名|様)?|お届け先(?:氏名|名|様)|宛名|口座名義人?|カード名義人?|名義人|契約者(?:氏名|名|様)?|申込者(?:氏名|名)?|Full\s?name|Your\s+name|Customer\s+name|Contact\s+name|Cardholder(?:\s+name)?|Account\s+holder(?:\s+name)?';
		$kana_labels    = 'フリガナ|ふりがな|カナ氏名|セイメイ|(?:お名前|氏名)[(](?:カナ|フリガナ|ふりがな)[)]';
		$address_labels = 'ご住所|現住所|住所|所在地|お届け先(?:ご)?住所|お届け先|配送先(?:住所)?|送付先(?:住所)?|発送先(?:住所)?|請求先住所|Shipping\s+address|Billing\s+address|Mailing\s+address|Home\s+address|Address';
		$phone_labels   = 'お電話番号|ご連絡先電話番号|連絡先電話番号|電話番号|携帯電話番号|携帯番号|携帯電話|携帯|連絡先|電話|TEL|FAX|ファックス|Phone(?:\s+number)?|Telephone|Mobile(?:\s+number)?|Cell(?:\s+phone)?';
		$account_labels = '会員番号|会員ID|会員コード|お客様番号|お客様ID|顧客番号|顧客ID|ユーザーID|ユーザー名|ユーザネーム|ログインID|ログイン名|アカウントID|アカウント名|契約番号|加入者番号|利用者ID|利用者番号|Customer\s+(?:ID|number|no\.?)|Member(?:ship)?\s+(?:ID|number|no\.?)|Account\s+(?:ID|number|no\.?)|User\s?name|User\s+ID|Login\s+(?:ID|name)';
		$expiry_labels  = 'カード有効期限|有効期限|Expiry(?:\s+date)?|Expiration(?:\s+date)?|Exp\.?\s*date|Valid\s+thru|EXP';
		$cvv_labels     = 'セキュリティーコード|セキュリティコード|CVV2?|CVC2?|CID|Security\s+code';
		$other_labels   = 'メールアドレス|メール|E-?mail|パスワード|Password|生年月日|郵便番号|〒|口座番号|注文番号|Order\s*(?:number|no\.?|#)';
		$all_labels     = implode( '|', array( $name_labels, $kana_labels, $address_labels, $phone_labels, $account_labels, $expiry_labels, $cvv_labels, $other_labels ) );

		// Space-separated name parts stop at the next label (お名前：山田 太郎 電話：…).
		$kanji_kana = self::KANJI . self::KATAKANA;
		$name_value = '[' . $kanji_kana . 'A-Za-z][' . $kanji_kana . '・A-Za-z.\'-]{0,15}(?:[ ](?!(?:' . $all_labels . '))[' . $kanji_kana . '・A-Za-z.\'-]{1,15}){0,2}';
		$kana_value = '[' . self::KATAKANA . self::HIRAGANA . ']{1,15}(?:[ ][' . self::KATAKANA . self::HIRAGANA . ']{1,15})?';

		$pass_kw   = 'PASSWORD|PASSWD|PASS|PWD';
		$secret_kw = 'SECRET|TOKEN|API_?KEY|ACCESS_?KEY|PRIVATE_?KEY|AUTH_?KEY|SALT|CREDENTIALS?';
		$env_name  = function ( $kw ) {
			$lower = strtolower( $kw );
			return '(?<![A-Za-z0-9_])(?:(?-i:[A-Z0-9_]*(?:' . $kw . ')[A-Z0-9_]*)|(?-i:(?=[a-z0-9]*_)[a-z0-9_]*(?:' . $lower . ')[a-z0-9_]*))';
		};
		$env_value = '([^\s"\'<>,;]{4,200})';

		$rules = array(
			// Private key blocks: whole block, before token patterns shred it.
			array(
				'id'       => 'private_key',
				'type'     => 'token',
				'regex'    => PIIP_PII_Detector::PRIVATE_KEY_PATTERN,
				'group'    => 0,
				'replace'  => '[REDACTED]',
				'provider' => 'Private key',
			),

			// Passwords and config secrets.
			array(
				'id'      => 'labeled_password',
				'type'    => 'password',
				'regex'   => '/((?<![A-Za-z0-9_])(?:password|passwd|passcode|pwd|P\/W|PW|PIN|パスワード|パスコード|ログインパス|暗証番号)[\s]*[:=は][\s]*)([\x21-\x7E]{4,64})/iu',
				'group'   => 2,
				'replace' => '[REDACTED]',
			),
			array(
				'id'       => 'define_secret',
				'type'     => 'token',
				'regex'    => '/(define\(\s*["\'](?-i:[A-Z0-9_]*(?:' . $pass_kw . '|' . $secret_kw . ')[A-Z0-9_]*)["\']\s*,\s*["\'])([^"\'\r\n<>]{1,200})/u',
				'group'    => 2,
				'validate' => 'is_secret_value',
				'replace'  => '[REDACTED]',
				'provider' => 'Config secret',
			),
			array(
				'id'       => 'env_password',
				'type'     => 'password',
				'regex'    => '/(' . $env_name( $pass_kw ) . '[\t ]*(?:=>|=|:)[\t ]*["\']?)' . $env_value . '/u',
				'group'    => 2,
				'validate' => 'is_secret_value',
				'replace'  => '[REDACTED]',
			),
			array(
				'id'       => 'env_secret',
				'type'     => 'token',
				'regex'    => '/(' . $env_name( $secret_kw ) . '[\t ]*(?:=>|=|:)[\t ]*["\']?)' . $env_value . '/u',
				'group'    => 2,
				'validate' => 'is_secret_value',
				'replace'  => '[REDACTED]',
				'provider' => 'Config secret',
			),

			// HTTP credentials.
			array(
				'id'       => 'curl_user',
				'type'     => 'token',
				'regex'    => PIIP_PII_Detector::BASIC_AUTH_PATTERNS['curl_user'],
				'group'    => 3,
				'replace'  => '[REDACTED]',
				'provider' => 'Basic auth',
			),
			array(
				'id'       => 'auth_basic',
				'type'     => 'token',
				'regex'    => PIIP_PII_Detector::BASIC_AUTH_PATTERNS['auth_basic'],
				'group'    => 2,
				'mask'     => 'mask_keep_prefix',
				'provider' => 'Basic auth',
			),
			array(
				'id'       => 'url_userinfo',
				'type'     => 'token',
				'regex'    => PIIP_PII_Detector::BASIC_AUTH_PATTERNS['url_userinfo'],
				'group'    => 3,
				'replace'  => '***',
				'provider' => 'Basic auth',
			),
			array(
				'id'       => 'bearer',
				'type'     => 'token',
				'regex'    => PIIP_PII_Detector::BEARER_PATTERN,
				'group'    => 2,
				'validate' => 'has_token_chars',
				'mask'     => 'mask_token',
				'provider' => 'Bearer token',
			),

			// Labeled personal data.
			array(
				'id'    => 'dob',
				'type'  => 'dob',
				'regex' => PIIP_PII_Detector::LABELED_DOB_PATTERN,
				'group' => 2,
				'mask'  => 'mask_dob',
			),
			array(
				'id'    => 'bank_labeled',
				'type'  => 'bank',
				'regex' => PIIP_PII_Detector::BANK_PATTERNS['labeled_account'],
				'group' => 2,
				'mask'  => 'mask_bank',
			),
			array(
				'id'    => 'bank_passbook',
				'type'  => 'bank',
				'regex' => PIIP_PII_Detector::BANK_PATTERNS['passbook_style'],
				'group' => 2,
				'mask'  => 'mask_bank',
			),

			// Identity document numbers (labeled).
			array(
				'id'       => 'passport',
				'type'     => 'id_doc',
				'regex'    => '/(' . $lb . '(?:旅券番号|パスポート番号|パスポートNo\.?|Passport\s*(?:No\.?|number|#))[\t ]*[:]?[\t ]*)([A-Z]{2}[0-9]{7})(?![A-Za-z0-9])/iu',
				'group'    => 2,
				'mask'     => 'mask_id_doc',
				'provider' => 'Passport',
			),
			array(
				'id'       => 'drivers_license_jp',
				'type'     => 'id_doc',
				'regex'    => '/(' . $lb . '(?:運転免許証番号|運転免許番号|免許証番号|免許番号)[\t ]*[:]?[\t ]*(?:第[\t ]*)?)([0-9]{12})(?![0-9])/u',
				'group'    => 2,
				'mask'     => 'mask_id_doc',
				'provider' => 'Driver\'s license',
			),
			array(
				'id'       => 'drivers_license',
				'type'     => 'id_doc',
				'regex'    => '/((?<![A-Za-z])Driver\'?s?\s+licen[cs]e\s*(?:No\.?|number|#)?[\t ]*[:]?[\t ]*)([A-Z0-9][A-Z0-9-]{4,19})(?![A-Za-z0-9-])/iu',
				'group'    => 2,
				'validate' => 'has_digit',
				'mask'     => 'mask_id_doc',
				'provider' => 'Driver\'s license',
			),
			array(
				'id'       => 'health_insurance',
				'type'     => 'id_doc',
				'regex'    => '/(' . $lb . '(?:被保険者証?番号|保険者番号|保険証番号|保険証の番号)[\t ]*[:]?[\t ]*)([0-9A-Za-z-]{4,20})(?![0-9A-Za-z-])/u',
				'group'    => 2,
				'validate' => 'has_digit',
				'mask'     => 'mask_id_doc',
				'provider' => 'Health insurance',
			),
			array(
				'id'       => 'pension',
				'type'     => 'id_doc',
				'regex'    => '/(' . $lb . '(?:基礎年金番号|年金番号)[\t ]*[:]?[\t ]*)([0-9]{4}[-\s]?[0-9]{6})(?![0-9])/u',
				'group'    => 2,
				'mask'     => 'mask_id_doc',
				'provider' => 'Pension',
			),
			array(
				'id'       => 'residence_card',
				'type'     => 'id_doc',
				'regex'    => '/(' . $lb . '(?:在留カード番号|在留カードの番号)[\t ]*[:]?[\t ]*)([A-Z]{2}[0-9]{8}[A-Z]{2})(?![A-Za-z0-9])/iu',
				'group'    => 2,
				'mask'     => 'mask_id_doc',
				'provider' => 'Residence card',
			),

			// Inquiry-style labeled contact details ("お名前：山田太郎").
			array(
				'id'    => 'contact_kana',
				'type'  => 'contact',
				'regex' => '/(' . $lb . '[【\[]?(?:' . $kana_labels . ')' . $sep . ')(' . $kana_value . ')/iu',
				'group' => 2,
				'mask'  => 'mask_person_name',
			),
			array(
				'id'       => 'contact_name',
				'type'     => 'contact',
				'regex'    => '/(' . $lb . '[【\[]?(?:' . $name_labels . ')' . $sep . ')(' . $name_value . ')/iu',
				'group'    => 2,
				'validate' => 'is_person_name',
				'mask'     => 'mask_person_name',
			),
			array(
				// Bare 名前/Name/名義 only at the start of a line: mid-sentence
				// they are too common in technical text (変数の名前: foo).
				'id'       => 'contact_name_line',
				'type'     => 'contact',
				'regex'    => '/((?:^|(?<=[\n>]))[\t ]*(?:[■□●○◆◇・*※-]|[0-9]{1,2}[.)])?[\t ]*[【\[]?(?:名前|Name|名義)' . $sep . ')(' . $name_value . ')/imu',
				'group'    => 2,
				'validate' => 'is_person_name',
				'mask'     => 'mask_person_name',
			),
			array(
				'id'       => 'contact_address',
				'type'     => 'contact',
				'regex'    => '/(' . $lb . '[【\[]?(?:' . $address_labels . ')' . $sep . ')([^\r\n<]{4,120}?)(?=[\t ]*(?:$|[\r\n<]|(?:[,、，;|][\t ]*)?' . $lb . '[【\[]?(?:' . $all_labels . ')' . $sep . '))/imu',
				'group'    => 2,
				'validate' => 'has_digit',
				'mask'     => 'mask_labeled_address',
			),
			array(
				'id'       => 'contact_phone',
				'type'     => 'contact',
				'regex'    => '/(' . $lb . '[【\[]?(?:' . $phone_labels . ')(?:\.)?' . $sep . ')(\+?\(?[0-9][0-9 ().-]{6,22}[0-9](?:[\t ]*[(]?(?:内線|ext\.?|x)[\t ]*[0-9]{1,6}[)]?)?)/iu',
				'group'    => 2,
				'validate' => 'is_phone_value',
				'mask'     => 'mask_phone',
			),
			array(
				'id'       => 'contact_account',
				'type'     => 'contact',
				'regex'    => '/(' . $lb . '[【\[]?(?:' . $account_labels . ')' . $sep . ')([^\s,、，;|<>"\'。]{2,64})/iu',
				'group'    => 2,
				'validate' => 'is_account_value',
				'mask'     => 'mask_account_id',
			),
			array(
				'id'      => 'contact_card_expiry',
				'type'    => 'contact',
				'regex'   => '/(' . $lb . '[【\[]?(?:' . $expiry_labels . ')' . $sep . ')((?:0?[1-9]|1[0-2])[\t ]*[\/-][\t ]*(?:20)?[0-9]{2}(?![0-9])|(?:20)?[0-9]{2}年[\t ]*(?:0?[1-9]|1[0-2])月(?![\t ]*[0-9]{1,2}日))/iu',
				'group'   => 2,
				'replace' => '**/**',
			),
			array(
				'id'      => 'contact_card_cvv',
				'type'    => 'contact',
				'regex'   => '/(' . $lb . '[【\[]?(?:' . $cvv_labels . ')' . $sep . ')([0-9]{3,4})(?![0-9])/iu',
				'group'   => 2,
				'replace' => '***',
			),

			// Japanese addresses and labeled postal codes.
			array(
				'id'      => 'jp_address',
				'type'    => 'address',
				'regex'   => '/(' . self::PREFECTURES . ')((?:[一-龠々ぁ-んァ-ヶー]{1,10}?[市区町村郡]){1,3}[一-龠々ぁ-んァ-ヶー0-9]{0,20}?' . self::ADDRESS_TAIL . ')/u',
				'group'   => 2,
				'replace' => '***',
			),
			array(
				'id'      => 'jp_city_address',
				'type'    => 'address',
				'regex'   => '/(?<![一-龠々])((?:' . self::DESIGNATED_CITIES . ')(?:[一-龠々ぁ-んァ-ヶー]{1,5}区)?|(?:' . self::TOKYO_WARDS . '))((?:[一-龠々ァ-ヶ][一-龠々ぁ-んァ-ヶー0-9]{0,19}?)' . self::ADDRESS_TAIL . ')/u',
				'group'   => 2,
				'replace' => '***',
			),
			array(
				'id'      => 'jp_postal',
				'type'    => 'address',
				'regex'   => PIIP_PII_Detector::JP_POSTAL_LABELED_PATTERN,
				'group'   => 2,
				'replace' => '***-****',
			),

			// Email.
			array(
				'id'    => 'email',
				'type'  => 'email',
				'regex' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
				'group' => 0,
				'mask'  => 'mask_email',
			),

			// Payment cards (Luhn-validated).
			array(
				'id'       => 'card',
				'type'     => 'card',
				'regex'    => '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{1,7}\b/',
				'group'    => 0,
				'validate' => 'is_card_number',
				'mask'     => 'mask_credit_card',
			),
			array(
				'id'       => 'card_amex_diners',
				'type'     => 'card',
				'regex'    => '/\b\d{4}[-\s]?\d{6}[-\s]?\d{4,5}\b/',
				'group'    => 0,
				'validate' => 'is_card_number',
				'mask'     => 'mask_credit_card',
			),

			// National IDs.
			array(
				'id'       => 'ssn',
				'type'     => 'ssn',
				'regex'    => '/\b\d{3}[-\s]\d{2}[-\s]\d{4}\b/',
				'group'    => 0,
				'validate' => 'is_ssn_number',
				'mask'     => 'mask_ssn',
			),
			array(
				'id'       => 'mynumber',
				'type'     => 'ssn',
				'regex'    => '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/',
				'group'    => 0,
				'validate' => 'is_mynumber_number',
				'mask'     => 'mask_mynumber',
			),

			// Phone numbers.
			array(
				'id'    => 'phone_jp_mobile',
				'type'  => 'phone',
				'regex' => '/\b0[5789]0[-\s]?\d{4}[-\s]?\d{4}\b/',
				'group' => 0,
				'mask'  => 'mask_phone',
			),
			array(
				'id'    => 'phone_jp_paren',
				'type'  => 'phone',
				'regex' => '/\b0\d{1,4}\(\d{1,4}\)\d{4}\b|\(0\d{1,4}\)\s?\d{1,4}[-\s]?\d{4}\b/',
				'group' => 0,
				'mask'  => 'mask_phone',
			),
			array(
				'id'    => 'phone_jp_special',
				'type'  => 'phone',
				'regex' => '/\b0(?:120|800|570|990)[-\s]?\d{3}[-\s]?\d{3,4}\b/',
				'group' => 0,
				'mask'  => 'mask_phone',
			),
			array(
				'id'    => 'phone_jp_landline',
				'type'  => 'phone',
				'regex' => '/\b0\d{1,4}[-\s]?\d{1,4}[-\s]?\d{4}\b/',
				'group' => 0,
				'mask'  => 'mask_phone',
			),
			array(
				'id'    => 'phone_international',
				'type'  => 'phone',
				'regex' => '/\+\d{1,3}[-\s]?\d{1,4}[-\s]?\d{1,4}[-\s]?\d{2,4}\b/',
				'group' => 0,
				'mask'  => 'mask_phone',
			),
			array(
				'id'    => 'phone_us_paren',
				'type'  => 'phone',
				'regex' => '/\(\d{3}\)\s?\d{3}[-\s]?\d{4}/',
				'group' => 0,
				'mask'  => 'mask_phone',
			),
			array(
				'id'    => 'phone_us_dashed',
				'type'  => 'phone',
				'regex' => '/(?<![\d.-])(?:1[-.])?\d{3}([-.])\d{3}\1\d{4}(?![\d.-])/',
				'group' => 0,
				'mask'  => 'mask_phone',
			),

			// IP addresses.
			array(
				'id'    => 'ipv4',
				'type'  => 'ip',
				'regex' => '/\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/',
				'group' => 0,
				'mask'  => 'mask_ip',
			),
			array(
				'id'       => 'ipv6',
				'type'     => 'ip',
				'regex'    => PIIP_PII_Detector::IPV6_CANDIDATE_PATTERN,
				'group'    => 0,
				'validate' => 'is_maskable_ipv6',
				'mask'     => 'mask_ip',
			),
		);

		// Hosting account/server IDs.
		$hosting = array(
			'xserver_account' => '/\bxs\d{5,8}\b/i',
			'xserver_server'  => '/\bsv\d{3,5}\b/i',
			'sakura_account'  => '/\b[a-z]{3}\d{5}\b/i',
			'sakura_domain'   => '/\b[\w-]+\.sakura\.ne\.jp\b/i',
			'conoha_account'  => '/\bgnc[a-z0-9]{8,12}\b/i',
			'lolipop_domain'  => '/\b[\w-]+\.lolipop\.jp\b/i',
			'mixhost_domain'  => '/\b[\w-]+\.mixh\.jp\b/i',
			'azure_guid'      => '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i',
		);
		foreach ( $hosting as $id => $regex ) {
			$rules[] = array(
				'id'          => 'hosting_' . $id,
				'type'        => 'hosting',
				'regex'       => $regex,
				'group'       => 0,
				'mask'        => 'mask_hosting_id',
				'provider_cb' => 'get_hosting_provider',
			);
		}

		// Developer secrets with fixed prefixes. Run before the generic AI
		// key patterns so those cannot pre-chew their substrings.
		foreach ( PIIP_PII_Detector::DEV_SECRET_PATTERNS as $id => $regex ) {
			$is_url  = in_array( $id, array( 'slack_webhook', 'discord_webhook' ), true );
			$rules[] = array(
				'id'       => 'secret_' . $id,
				'type'     => 'token',
				'regex'    => $regex,
				'group'    => 0,
				'mask'     => $is_url ? 'mask_url_secret' : 'mask_token',
				'provider' => PIIP_PII_Detector::DEV_SECRET_PROVIDERS[ $id ],
			);
		}

		// AI API keys.
		$ai_keys = array(
			'openai_project' => '/\bsk-proj-[A-Za-z0-9_-]{40,}\b/',
			'openai_legacy'  => '/\bsk-[A-Za-z0-9]{20}T3BlbkFJ[A-Za-z0-9]{20}\b/',
			'openai_48'      => '/\bsk-[A-Za-z0-9]{48}\b/',
			'anthropic'      => '/\bsk-ant-[A-Za-z0-9_-]{95,100}\b/',
			'google_ai'      => '/\bAIza[A-Za-z0-9_-]{30,}\b/',
			'huggingface'    => '/\bhf_[A-Za-z0-9]{30,}\b/',
			'replicate'      => '/\br8_[A-Za-z0-9]{30,}\b/',
			'cohere'         => '/\b[A-Za-z0-9]{30,}-co\b/',
			'azure_openai'   => '/\b[a-fA-F0-9]{32}\b/',
			'generic'        => '/\b(?:sk|ai|api)-[A-Za-z0-9_-]{20,}\b/',
		);
		foreach ( $ai_keys as $id => $regex ) {
			$rules[] = array(
				'id'          => 'ai_' . $id,
				'type'        => 'token',
				'regex'       => $regex,
				'group'       => 0,
				'validate'    => 'is_ai_key',
				'mask'        => 'mask_token',
				'provider_cb' => 'get_ai_provider',
			);
		}

		// Name self-introductions (opt-in type name_text).
		foreach ( PIIP_PII_Detector::NAME_PATTERNS as $id => $regex ) {
			$rules[] = array(
				'id'       => 'name_' . $id,
				'type'     => 'name_text',
				'regex'    => $regex,
				'group'    => 2,
				'validate' => 'is_person_name',
				'mask'     => 'mask_name',
			);
		}

		return $rules;
	}

	/**
	 * Run a rule's validator.
	 *
	 * Validators are static methods of this class, or public methods of
	 * the detector (is_ai_key) when a detector is passed.
	 *
	 * @since 1.7.0
	 *
	 * @param array                  $rule     Rule.
	 * @param string                 $value    Captured value.
	 * @param PIIP_PII_Detector|null $detector Detector instance.
	 * @return bool True if the value is valid (or the rule has no validator).
	 */
	public static function validate( array $rule, $value, $detector = null ) {
		if ( empty( $rule['validate'] ) ) {
			return true;
		}

		$method = $rule['validate'];
		if ( method_exists( __CLASS__, $method ) ) {
			return (bool) self::$method( $value );
		}
		if ( $detector && method_exists( $detector, $method ) ) {
			return (bool) $detector->$method( $value );
		}
		if ( is_callable( $method ) ) {
			return (bool) call_user_func( $method, $value );
		}

		return true;
	}

	/**
	 * Card number check: 13-19 digits passing Luhn.
	 *
	 * @since 1.7.0
	 *
	 * @param string $value Candidate.
	 * @return bool
	 */
	public static function is_card_number( $value ) {
		$digits = preg_replace( '/\D/', '', $value );
		$length = strlen( $digits );

		return $length >= 13 && $length <= 19 && PIIP_PII_Detector::is_luhn_valid( $digits );
	}

	/**
	 * US SSN check: area not 000/666/9xx, group not 00, serial not 0000.
	 *
	 * @since 1.7.0
	 *
	 * @param string $value Candidate.
	 * @return bool
	 */
	public static function is_ssn_number( $value ) {
		$digits = preg_replace( '/\D/', '', $value );
		if ( 9 !== strlen( $digits ) ) {
			return false;
		}

		$area = substr( $digits, 0, 3 );

		return '000' !== $area && '666' !== $area && '9' !== $area[0]
			&& '00' !== substr( $digits, 3, 2 ) && '0000' !== substr( $digits, 5, 4 );
	}

	/**
	 * Japanese My Number check: 12 digits with a valid check digit.
	 *
	 * @since 1.7.0
	 *
	 * @param string $value Candidate.
	 * @return bool
	 */
	public static function is_mynumber_number( $value ) {
		$digits = preg_replace( '/\D/', '', $value );
		if ( 12 !== strlen( $digits ) ) {
			return false;
		}

		$weights = array( 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2 );
		$sum     = 0;
		for ( $i = 0; $i < 11; $i++ ) {
			$sum += (int) $digits[ $i ] * $weights[ $i ];
		}
		$remainder = $sum % 11;

		return ( $remainder <= 1 ? 0 : 11 - $remainder ) === (int) $digits[11];
	}

	/**
	 * IPv6 check, see PIIP_PII_Detector::is_maskable_ipv6().
	 *
	 * @since 1.7.0
	 *
	 * @param string $value Candidate.
	 * @return bool
	 */
	public static function is_maskable_ipv6( $value ) {
		return PIIP_PII_Detector::is_maskable_ipv6( $value );
	}

	/**
	 * Bearer value check: rejects plain words (Bearer authentication).
	 *
	 * @since 1.7.0
	 *
	 * @param string $value Candidate.
	 * @return bool
	 */
	public static function has_token_chars( $value ) {
		return 1 === preg_match( '/[0-9\-_+\/=.]/', $value );
	}

	/**
	 * Person name check: rejects companies and placeholder words.
	 *
	 * @since 1.7.0
	 *
	 * @param string $value Candidate.
	 * @return bool
	 */
	public static function is_person_name( $value ) {
		if ( preg_match( PIIP_PII_Detector::NAME_EXCLUSION_PATTERN, $value ) ) {
			return false;
		}

		return 1 !== preg_match( '/^(?:未定|なし|無し|不明|匿名|非公開|未記入|省略|同上|テスト|test|none|n\/a|anonymous|unknown|same)$/iu', trim( $value ) );
	}

	/**
	 * At least one digit.
	 *
	 * @since 1.7.0
	 *
	 * @param string $value Candidate.
	 * @return bool
	 */
	public static function has_digit( $value ) {
		return 1 === preg_match( '/[0-9]/', $value );
	}

	/**
	 * Phone value check: 9-15 digits before any extension.
	 *
	 * @since 1.7.0
	 *
	 * @param string $value Candidate.
	 * @return bool
	 */
	public static function is_phone_value( $value ) {
		$main   = preg_replace( '/[\t ]*[(]?(?:内線|ext\.?|x)[\t ]*[0-9]{1,6}[)]?$/iu', '', $value );
		$digits = strlen( preg_replace( '/\D/', '', (string) $main ) );

		return $digits >= 9 && $digits <= 15;
	}

	/**
	 * Account/member ID check: rejects placeholders.
	 *
	 * @since 1.7.0
	 *
	 * @param string $value Candidate.
	 * @return bool
	 */
	public static function is_account_value( $value ) {
		return 1 !== preg_match( '/^(?:未定|なし|無し|不明|未登録|忘れました|わかりません|none|n\/a|unknown|\*+|x+)$/iu', $value );
	}

	/**
	 * Config value check: rejects placeholders and environment references.
	 *
	 * @since 1.7.0
	 *
	 * @param string $value Candidate.
	 * @return bool
	 */
	public static function is_secret_value( $value ) {
		return 1 !== preg_match( '/^(?:\$|%|\{|getenv|env\(|process\.env|null$|true$|false$|\*+$|x{3,}$|\[REDACTED\]$)/i', $value );
	}
}
