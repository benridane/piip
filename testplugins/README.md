# PIIP Test Plugins

このディレクトリには、PIIP（PII Protection）プラグインのカスタムフック機能をテストするためのテストプラグインが含まれています。

## ⚠️ 重要な注意事項

**これらのテストプラグインは本番環境では使用しないでください。** 
開発・テスト環境でのみ使用し、フック機能の動作確認用です。

## 🔒 安全性について

両方のテストプラグインに以下の安全機能を追加済み：
- **依存関係チェック**: PIIPプラグインが無効な場合、自動的に無効化
- **エラーハンドリング**: 適切なエラーメッセージとフォールバック
- **遅延初期化**: `plugins_loaded`フックでPIIP読み込み後に実行

## エラー対処法

### 「Class "PIIP_Base_Integration" not found」エラーの場合

1. **PIIPプラグインが有効化されているか確認**
   - WordPress管理画面 → プラグイン → PIIP - PII Protectionが「有効」になっているか

2. **プラグインの読み込み順序を確認**
   - PIIPプラグインが先に読み込まれる必要があります
   - 必要に応じてテストプラグインを一度無効化 → 再有効化

3. **WordPressエラーログの確認**
   - wp-content/debug.log でより詳細なエラー情報を確認

4. **テストプラグインの再インストール**
   - 古いバージョンのテストプラグインの場合、安全性機能がない可能性があります

### 安全な使用手順

```bash
# 1. 開発環境でのみ使用
# 2. PIIPプラグインを先にインストール・有効化
# 3. テストプラグインをインストール・有効化
# 4. テスト完了後は必ずテストプラグインを削除
```

## 含まれるテストプラグイン

### 1. PIIP Hook Tester (`piip-hook-tester.php`)

PIIPの全てのカスタムフック機能をテストするための包括的なテストプラグイン。

#### 機能
- 全18個のフィルターフックのテスト
- 4個のアクションフックのテスト
- 管理画面でのリアルタイムテスト
- 自動テストスイート
- テスト結果の詳細表示

#### 使用方法
1. PIIPプラグインを有効化
2. `piip-hook-tester.php`を`/wp-content/plugins/`にアップロード
3. プラグインを有効化
4. 管理画面の「ツール」→「PIIP Hook Tester」にアクセス
5. 「Run All Hook Tests」ボタンでテスト実行

#### テスト項目
- カスタムPII検出（銀行口座番号など）
- カスタム値マスキング
- テキストマスキング
- 前後処理フック
- ログ用アクションフック
- 統合登録フック

### 2. PIIP Integration Demo (`piip-integration-demo.php`)

新しいコミュニティプラグインがPIIPと統合する方法を実演するデモプラグイン。

#### 機能
- カスタム統合の登録例
- フォーム送信でのPIIマスキング実演
- カスタムPII型の検出・マスキング
- 設定ページでの統合表示
- 実際のコード例の提示

#### 使用方法
1. PIIPプラグインを有効化
2. `piip-integration-demo.php`を`/wp-content/plugins/`にアップロード  
3. プラグインを有効化
4. 管理画面の「ツール」→「PIIP Integration Demo」にアクセス
5. フォームにPIIデータを入力してテスト

#### デモ内容
- メンバーID（MEM12345形式）のカスタム検出
- フォームデータの自動マスキング
- 同意フレーズによるマスキング回避
- マスキング前後の比較表示

## インストール手順

### WordPress環境での使用
```bash
# 1. テストプラグインをWordPressプラグインディレクトリにコピー
cp testplugins/*.php /path/to/wordpress/wp-content/plugins/

# 2. WordPressにログインしてプラグインを有効化
# 管理画面 → プラグイン → PIIP Hook Tester → 有効化
# 管理画面 → プラグイン → PIIP Integration Demo → 有効化
```

### wp-env環境での使用
```bash
# 1. PIIPプラグインのディレクトリ内で
cp testplugins/*.php .wp-env/plugins/

# 2. wp-envを再起動
npx wp-env start
```

## テスト手順

### 基本テスト
1. **PIIP Hook Tester**で全体的なフック動作を確認
2. **PIIP Integration Demo**で実際の統合例を確認
3. PIIPの設定画面で「Demo Community Plugin」が表示されることを確認

### 詳細テスト

#### 1. カスタムPII検出テスト
```
フィールド名: member_id
値: MEM12345
期待結果: demo_member_id として検出され、MEM***45 にマスキング
```

#### 2. テキストマスキングテスト
```
テキスト: "Contact john@example.com and TEST_SECRET:abc123"
期待結果: "Contact j***@example.com and TEST_SECRET:***"
```

#### 3. 同意フレーズテスト
```
テキスト: "My email is john@example.com. I consent to share my personal information"
期待結果: マスキングされずに元のまま
```

## 開発者向け情報

### カスタムフック一覧
テストプラグインで確認できるフック：

**フィルター（18個）:**
- `piip_before_mask_value` - 値マスキング前処理
- `piip_custom_mask_value` - 値マスキング完全オーバーライド
- `piip_detected_pii_type` - PII型検出結果変更
- `piip_after_mask_value` - 値マスキング後処理
- `piip_custom_mask_by_type` - 型別カスタムマスキング
- `piip_before_mask_form_data` - フォームデータ前処理
- `piip_custom_mask_form_data` - フォームデータ完全オーバーライド
- `piip_after_mask_form_data` - フォームデータ後処理
- `piip_before_detect_pii` - PII検出前処理
- `piip_custom_detect_pii_type` - PII検出完全オーバーライド
- `piip_detected_pii_by_field_name` - フィールド名検出結果変更
- `piip_detected_pii_by_value` - 値検出結果変更
- `piip_available_integrations` - 統合登録
- `piip_admin_available_integrations` - 管理画面統合登録
- `piip_custom_integration_instance` - カスタム統合インスタンス
- `piip_custom_mask_text` - テキストマスキング完全オーバーライド
- `piip_before_mask_text` - テキストマスキング前処理
- `piip_after_mask_text` - テキストマスキング後処理

**アクション（4個）:**
- `piip_value_masked` - 値マスキング完了
- `piip_consent_bypass` - 同意による回避
- `piip_form_data_masked` - フォームデータマスキング完了
- `piip_text_masked` - テキストマスキング完了

### 統合例コード
詳細な統合方法は `piip-integration-demo.php` のソースコードと `CUSTOM-HOOKS.md` を参照してください。

## トラブルシューティング

### よくある問題

1. **「PIIP plugin is not active」エラー**
   - PIIPプラグインが有効化されているか確認
   - PIIPのバージョンが1.2.2以降か確認

2. **フックが動作しない**
   - WordPressの`init`フックより後で登録されているか確認
   - フック名のスペルミスがないか確認

3. **テスト結果が表示されない**
   - ブラウザのJavaScriptコンソールでエラーを確認
   - WordPressのデバッグログを確認

## ライセンス

これらのテストプラグインは、PIIPプラグインと同じGPL-2.0-or-laterライセンスです。