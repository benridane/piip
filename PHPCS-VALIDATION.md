# PHPCS Ignore Directives Validation Report

## 概要
このドキュメントでは、PIIPプラグインで使用されているすべての`phpcs:ignore`ディレクティブの妥当性を検証します。

---

## 1. データベースクエリ関連 (WordPress.DB.PreparedSQL)

### 箇所: `includes/class-pii-database.php` (6件)

#### 1.1 get_logs() メソッド (Line 202, 207)
```php
$query = $wpdb->prepare(
    "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d",
    $args['limit'],
    $args['offset']
);
$results = $wpdb->get_results( $query );
```

**警告内容:** `InterpolatedNotPrepared`, `NotPrepared`

**現状の実装:**
- `$this->table_name` はコンストラクタで `$wpdb->prefix . 'piip_masking_log'` として設定
- `$where_clause` は `$wpdb->prepare()` で事前に準備済み
- `$orderby` は `sanitize_sql_orderby()` で検証済み

**問題点:** ❌ **修正が必要**
- `$where_clause` が複数のprepare文を連結しているため、プレースホルダーが正しく動作しない可能性
- `$orderby` はサニタイズされているが、SQLインジェクションのリスクが完全には排除されていない

**推奨修正:**
```php
// WHERE句の構築を改善
$where = array();
$prepare_args = array();

if ( ! is_null( $args['form_id'] ) ) {
    $where[] = 'form_id = %d';
    $prepare_args[] = $args['form_id'];
}
// ... 他の条件も同様に

$where_clause = ! empty( $where ) ? implode( ' AND ', $where ) : '1=1';

// 許可されたカラムのホワイトリスト
$allowed_orderby = array( 'id', 'created_at', 'form_type', 'pii_type' );
$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
$order = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

$query = $wpdb->prepare(
    "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
    array_merge( $prepare_args, array( $args['limit'], $args['offset'] ) )
);
```

**妥当性評価:** 🔴 **不適切** - セキュリティリスクあり

---

#### 1.2 get_total_count() メソッド (Line 239, 241)
```php
$query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
$count = $wpdb->get_var( $query );
```

**問題点:** 同上

**妥当性評価:** 🔴 **不適切** - get_logs()と同じ問題

---

#### 1.3 cleanup_old_logs() メソッド (Line 259)
```php
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
        $days
    )
);
```

**警告内容:** `InterpolatedNotPrepared`

**現状:**
- `$this->table_name` は動的だがプレフィックス付きで安全
- `$days` は `%d` でプリペアされている

**妥当性評価:** ✅ **適切** - テーブル名は内部的に管理されており、外部入力ではない

---

#### 1.4 drop_table() メソッド (Line 298)
```php
$wpdb->query( "DROP TABLE IF EXISTS {$this->table_name}" );
```

**警告内容:** `InterpolatedNotPrepared`

**問題点:** ❌ **修正推奨**
- DROP文には`$wpdb->prepare()`が使えない（テーブル名はプレースホルダー不可）
- しかし、`$this->table_name`の検証が不十分

**推奨修正:**
```php
// テーブル名の検証を追加
if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', str_replace( $wpdb->prefix, '', $this->table_name ) ) ) {
    return false;
}
$wpdb->query( "DROP TABLE IF EXISTS {$this->table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated.
```

**妥当性評価:** 🟡 **条件付きで適切** - 検証を追加すればOK

---

### 箇所: `includes/class-pii-logger.php` (4件)

#### 1.5 get_statistics() メソッド (Line 238, 242, 248, 254)
```php
$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
$by_type = $wpdb->get_results(
    "SELECT pii_type, COUNT(*) as count FROM {$table_name} GROUP BY pii_type ORDER BY count DESC",
    ARRAY_A
);
```

**問題点:** 同様にテーブル名が動的

**妥当性評価:** ✅ **適切** - 統計クエリで外部入力なし、テーブル名は内部管理

---

## 2. 未使用パラメータ (Generic.CodeAnalysis.UnusedFunctionParameter)

### 箇所: `includes/class-pii-masker.php` (2件)

#### 2.1 mask_address() メソッド (Line 244)
```php
public function mask_address( $address ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
    return '*** *** ***';
}
```

**理由:** 将来の拡張のためのインターフェース維持

**問題点:** ❌ **設計上の問題**
- 他のマスキングメソッドはすべてパラメータを使用している
- インターフェースの一貫性のためだけに無視するのは不適切

**推奨修正:**
```php
public function mask_address( $address ) {
    // Simple pattern-based masking
    $parts = preg_split( '/[\s,]+/', $address );
    if ( count( $parts ) > 2 ) {
        // Keep first part, mask the rest
        return $parts[0] . ' *** ***';
    }
    return '*** *** ***';
}
```

**妥当性評価:** 🔴 **不適切** - パラメータを実際に使用すべき

---

#### 2.2 mask_password() メソッド (Line 307)
```php
public function mask_password( $password ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
    return '[REDACTED]';
}
```

**理由:** パスワードは常に完全に隠蔽するため、値を使用しない

**妥当性評価:** ✅ **適切** - セキュリティ上、パスワードは長さに関わらず完全マスクが正しい

---

## 3. エラーログ使用 (WordPress.PHP.DevelopmentFunctions)

### 箇所: `includes/class-pii-logger.php` (Line 89)

```php
error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    sprintf(
        'PIIP: Masked %s in form %d (type: %s, field: %s)',
        $args['pii_type'],
        $args['form_id'],
        $args['form_type'],
        $args['field_name']
    )
);
```

**理由:** 重要なPII（カード、SSN等）のマスキングイベントを記録

**問題点:** 🟡 **条件付きで適切**
- 本番環境でのerror_log使用は推奨されない
- しかし、セキュリティ監査のためのログは重要

**推奨修正:**
```php
// WordPressのdo_actionを使用
do_action( 'piip_critical_pii_masked', $args );

// または、WP_DEBUG時のみログ
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( sprintf( ... ) );
}
```

**妥当性評価:** 🟡 **条件付きで適切** - WP_DEBUGチェックを追加すべき

---

## 4. 出力エスケープ (WordPress.Security.EscapeOutput)

### 箇所: `admin/class-admin-settings.php` (Line 245)

```php
printf(
    '<input type="checkbox" id="%s" name="piip_settings[%s]" value="1" %s>',
    esc_attr( $args['label_for'] ),
    esc_attr( $args['label_for'] ),
    $checked // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);
```

**現状:** `$checked` は `checked()` 関数の戻り値（既にエスケープ済み）

**問題点:** ❌ **修正が必要**
- `checked()`の戻り値は文字列 `'checked="checked"'` または空文字列
- これは既にエスケープされているが、PHPCSはそれを認識できない

**推奨修正:**
```php
$checked_attr = isset( $options[ $args['label_for'] ] ) && $options[ $args['label_for'] ] ? 'checked="checked"' : '';

printf(
    '<input type="checkbox" id="%s" name="piip_settings[%s]" value="1" %s>',
    esc_attr( $args['label_for'] ),
    esc_attr( $args['label_for'] ),
    $checked_attr // Safe: controlled string
);
```

**妥当性評価:** 🟡 **条件付きで適切** - コメントで理由を明記すべき

---

### 箇所: `admin/class-admin-logs.php` (Line 112)

```php
echo $csv_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
```

**コンテキスト:** CSVエクスポート機能

**理由:**
- CSVファイルのダウンロードであり、HTMLではない
- Content-Typeが `text/csv` に設定されている
- エスケープするとCSV形式が壊れる

**妥当性評価:** ✅ **適切** - CSVダウンロードではエスケープ不要

---

## 5. ファイル構造 (Generic.Files.OneObjectStructurePerFile)

### 箇所: `admin/class-admin-logs.php` (Line 128)

```php
// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
class PIIP_Logs_List_Table extends WP_List_Table {
```

**理由:** WordPressの`WP_List_Table`パターンでは、メインクラスとリストテーブルクラスを同じファイルに配置するのが慣例

**WordPress公式例:**
- `wp-admin/includes/class-wp-posts-list-table.php`
- `wp-admin/includes/class-wp-users-list-table.php`

**妥当性評価:** ✅ **適切** - WordPress標準パターンに従っている

---

## 6. Nonce検証 (WordPress.Security.NonceVerification)

### 箇所: `admin/class-admin-logs.php` (Line 210-213)

```php
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
$order   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';
// phpcs:enable WordPress.Security.NonceVerification.Recommended
```

**理由:** `WP_List_Table`のソート機能では、nonceなしで`$_GET`を読み取るのが標準

**WordPress公式:** WordPressコアのすべてのList Tableでも同様の実装

**セキュリティ:**
- データは`sanitize_text_field()`でサニタイズされている
- 読み取り専用の操作（ソート）のため、CSRFリスクは低い
- さらに`sanitize_sql_orderby()`で検証されている

**妥当性評価:** ✅ **適切** - WordPress標準パターン

---

## 7. 関数とクラスの混在 (Universal.Files.SeparateFunctionsFromOO)

### 箇所: `piip.php` (Line 256-267)

```php
// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed
function piip() {
    return PIIP_Plugin::get_instance();
}
// phpcs:enable Universal.Files.SeparateFunctionsFromOO.Mixed
```

**理由:** プラグインのヘルパー関数（ファサードパターン）

**一般的なパターン:**
- `woocommerce()->cart` (WooCommerce)
- `WC()` (WooCommerce)
- `acf()` (Advanced Custom Fields)

**妥当性評価:** ✅ **適切** - WordPressプラグインの標準パターン

---

## 総合評価とアクションアイテム

### ✅ 適切 (7件)
- データベース: cleanup_old_logs(), get_statistics() の4件
- mask_password() の未使用パラメータ
- CSVエクスポートの出力
- WP_List_Table パターン
- Nonce検証スキップ
- ヘルパー関数の混在

### 🟡 要改善 (2件)
- `error_log()` 使用 → WP_DEBUGチェックを追加
- `$checked` 変数 → コメントで理由を明記

### 🔴 修正必須 (3件)
1. **get_logs() と get_total_count()** - WHERE句とORDER BY句の構築方法を改善
2. **mask_address()** - パラメータを実際に使用する
3. **drop_table()** - テーブル名の検証を追加

---

## 推奨される次のステップ

1. 🔴 **優先度: 高** - データベースクエリのセキュリティ強化
2. 🟡 **優先度: 中** - error_log()の条件付き使用
3. 🔴 **優先度: 中** - mask_address()の実装改善
4. 📝 すべてのignoreに詳細なコメントを追加

---

## 結論

**全体的な評価:** 🟡 **条件付きで許容可能**

- 17個のphpcs:ignore使用のうち、7個は完全に適切
- 2個は改善の余地あり
- 3個は修正が必要（セキュリティまたは設計上の問題）

**WordPress.org公開前に修正が必要な項目:**
- データベースクエリのセキュリティ強化（特にget_logs関連）
- mask_address()の実装改善

**その他は WordPress Coding Standards および WordPress プラグイン開発のベストプラクティスに準拠しています。**
