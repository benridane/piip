=== PIIP - PII Protection ===
Contributors: benridane, presents111
Tags: privacy, pii, gdpr, security, data-protection
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.7.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically detects and masks PII in WordPress comments and forms to protect user privacy and support your GDPR compliance efforts.

== Description ==

PIIP (PII Protection) is a plugin that automatically detects and masks personally identifiable information (PII) in WordPress comments and community plugin content before the data is saved to your database. This helps protect user privacy and supports your compliance efforts under privacy regulations such as the GDPR. Note that PIIP is a technical tool and does not by itself make your site GDPR compliant.

= Key Features =

* **Automatic PII Detection**: Intelligently detects multiple types of PII including emails, phone numbers, addresses, credit cards, SSN/My Number, passwords, API tokens, IP addresses, and hosting account IDs
* **Server-Side Masking**: All masking happens on the server (PHP) for maximum security - cannot be bypassed by users
* **WordPress Core Support**: Native support for WordPress comments - from the comment form, the REST API (including block editor notes) and later edits - with per-type selection (comments, product reviews, notes, other types)
* **Commenter IP Anonymization**: Optionally store commenter IP addresses anonymized (192.0.2.123 → 192.0.2.0), the same way WordPress anonymizes them for personal data erasure
* **Inquiry-Style Text Protection**: Catches contact details posted to a forum by mistake instead of a contact form - labeled names, furigana, addresses, phone numbers, member/login IDs, card expiry dates and security codes (お名前：… / 住所：… / Name: … / Address: …)
* **Full-Width Aware**: Full-width digits and symbols typed with a Japanese IME (０９０－１２３４－５６７８, ｔａｒｏ＠ｅｘａｍｐｌｅ．ｊｐ) are detected; text outside masked parts keeps its original width
* **Abilities API**: `piip/mask-text` and `piip/scan-content` abilities let MCP clients and AI agents mask text and audit stored content without ever receiving raw PII
* **Community Plugin Support**: Works seamlessly with wpForo, BuddyPress, bbPress, and other popular community plugins
* **Configurable**: Choose which PII types to mask via easy-to-use settings page
* **Consent Opt-Out**: Users can include consent phrases to skip masking when sharing personal info publicly
* **Presidio-Level Detection**: High-accuracy detection with validation (Luhn for credit cards, check digits for My Number)
* **Retroactive Scan**: Scan content that existed before installing PIIP and apply masking after a dry-run review
* **Custom Patterns**: Mask site-specific identifiers (employee IDs, member numbers) with your own regular expressions
* **WP-CLI Support**: `wp piip mask` and `wp piip scan` commands for automation and large sites

= Supported PII Types =

* Email addresses (example@domain.com → e***@domain.com)
* Phone numbers (Japanese mobile/landline, parenthesized 03(1234)5678, toll-free/navi dial 0120/0570, international, US formats)
* Japanese street addresses in free text (東京都新宿区西新宿2-8-1 → 東京都***) and labeled postal codes (〒123-4567 → 〒***-****)
* Credit card numbers with Luhn validation (4532-1234-5678-9010 → ****-****-****-9010)
* Social Security Numbers / Japanese My Number with check digit validation
* Passwords, including labeled values in free text (password: xxx / パスワードは xxx / PW: xxx → [REDACTED]) and pasted configuration (define( 'DB_PASSWORD', '…' ), SMTP_PASSWORD=…, aws_secret_access_key = …)
* HTTP credentials: Basic auth (curl -u, Authorization: Basic, user:pass@host URLs) and Bearer tokens (including JWTs)
* Developer secrets: GitHub, GitLab, Slack (tokens and webhooks), Discord webhooks, AWS, Stripe, Google OAuth, SendGrid, npm, Twilio, Mailgun, Shopify, Telegram, DigitalOcean tokens and SSH/PEM private key blocks
* API Tokens/Keys (partial masking showing first and last 4 characters)
* AI API Keys (OpenAI sk-***, Anthropic sk-ant-***, Google AIza***, Hugging Face hf_***, Replicate r8_***, Cohere, Azure OpenAI)
* Labeled dates of birth (生年月日: 1990-01-15 → ****-**-**)
* Labeled bank account numbers (口座番号: 1234567 → ***4567)
* Labeled contact details in inquiry-style text: name, furigana, address, phone, member/login ID, card expiry and security code (お名前：山田 太郎 → お名前：山* 太*)
* Labeled ID document numbers: passport, driver's license, health insurance, basic pension, residence card
* Japanese addresses written without the prefecture, for designated cities and Tokyo's 23 wards (横浜市中区山下町1-2-3 → 横浜市***)
* Names in self-introduction phrases (山田太郎と申します → 山***と申します; opt-in, off by default)
* IP Addresses: IPv4 (192.168.1.1 → 192.***.***.1) and IPv6, including compressed forms (2001:db8::8a2e:370:7334 → 2001:db8:***)
* Hosting Account IDs (XServer, Sakura, AWS, Azure, GCP, ConoHa, Lolipop, mixhost)

= Supported Integrations =

* **WordPress Core**
  * Comments (comment form, REST API, edits; selectable types: comments, product reviews, block editor notes, other types)
  * Commenter IP addresses (optional anonymization)
  * User Profiles (display name, nickname, biographical info)
* **Form Plugins**
  * Contact Form 7 (free-text fields; protects sent mail and stored copies such as Flamingo)
* **Community Plugins**
  * wpForo Forum
  * BuddyPress
  * bbPress
* More integrations coming soon!

= How It Works =

1. User posts a comment or content in a community plugin
2. PIIP intercepts the submission before database save
3. Automatically detects PII using field names, regex patterns, and validation
4. Masks detected PII according to your settings
5. Content saves normally with masked data

= Privacy & Security =

* All processing happens on YOUR server (no external API calls)
* Original values are NEVER stored for maximum privacy protection
* Server-side processing prevents client-side bypass attempts
* Full control over your data

== Installation ==

= Automatic Installation =

1. Log in to your admin panel
2. Go to Plugins → Add New
3. Search for "PIIP" or "PII Protection"
4. Click "Install Now" and then "Activate"
5. Go to Settings → PII Protection to configure

= Manual Installation =

1. Download the plugin ZIP file
2. Upload to `/wp-content/plugins/piip` directory
3. Activate the plugin through the 'Plugins' menu
4. Go to Settings → PII Protection to configure

= After Activation =

1. Navigate to **Settings → PII Protection**
2. Enable/disable desired integrations (Comments, wpForo, BuddyPress, bbPress)
3. Select which PII types to mask
4. Configure consent phrases for opt-out feature
5. Save settings
6. Test with a post or comment to verify masking is working

== Frequently Asked Questions ==

= Does this work with WordPress comments? =

Yes! PIIP has native support for WordPress core comments. Simply enable the Comments integration in Settings → PII Protection.

Comments are masked whether they are submitted through the comment form, created through the REST API (for example by headless front ends or apps), or edited later. You can choose which comment types are masked: regular comments, product reviews (e.g. WooCommerce), block editor notes (off by default, as they are internal editorial comments), and other types such as pingbacks.

= Can PIIP anonymize commenter IP addresses? =

Yes. Set "Commenter IP addresses" to "Anonymize before saving" in Settings → PII Protection. The last part of the address is zeroed (192.0.2.123 becomes 192.0.2.0; IPv6 keeps the first 48 bits) for new and edited comments. Existing comments are not changed. Because the address is anonymized rather than removed, WordPress flood protection keeps working, per network block instead of per address.

= Someone posted their contact details to the forum instead of the contact form. Does PIIP catch that? =

Yes. With "Labeled contact details" enabled (the default), labeled fields typical of inquiry forms are masked: お名前/氏名, フリガナ, 住所/お届け先, 電話番号/TEL/携帯, 会員番号/ログインID, 有効期限, セキュリティコード and their English equivalents (Name, Address, Phone, Member ID, Expiry, CVV). Email addresses, postal codes, dates of birth, bank accounts and passwords in the same message are masked by their own types. Bare 名前/Name labels are only recognized at the start of a line, so technical text such as 変数の名前: foo is left alone.

= Can AI agents or MCP clients use PIIP? =

Yes. PIIP registers two abilities with the WordPress Abilities API: `piip/mask-text` masks a piece of text with your site's settings (users who can edit posts), and `piip/scan-content` runs a read-only scan of stored comments or posts (administrators). Results contain masked text and PII types only, never the raw values. They are available through the Abilities REST API and to MCP adapters that expose public abilities.

= Does this work with wpForo? =

Yes! PIIP has native integration with wpForo and will automatically mask PII in forum topics, posts, and private messages.

= Does this work with BuddyPress? =

Yes! PIIP supports BuddyPress activities, profile fields, private messages, group descriptions, and activity comments.

= Can users opt out of masking? =

Yes. If enabled, users can include consent phrases like "マスクを外すことに同意" or "I consent to unmasking" in their content to skip PII masking for that specific post.

= Will this slow down my website? =

No. PIIP adds minimal processing time (<20ms per submission) which is imperceptible to users. All processing happens server-side after submission.

= Can users bypass the masking? =

No. All masking happens on the server (PHP), so it cannot be bypassed by disabling JavaScript or using browser developer tools.

= Is the original data stored anywhere? =

No. The original data is never stored. We only store:
- The masked value

= Does this make my site GDPR compliant? =

No plugin can make a site GDPR compliant by itself. Compliance depends on how your site collects, processes, and stores personal data as a whole, and may require legal advice.

PIIP supports your compliance efforts by:
- Minimizing stored personal data (masking PII before it is saved)
- No third-party data sharing (everything stays on your server)
- No detailed logging to protect user privacy

Also note that detection is pattern-based and may not catch every piece of personal information, so do not rely on it as your only safeguard.

== Screenshots ==

1. Settings page - Configure integrations and PII types to mask
2. Consent phrases configuration
3. Example of masked content in forum post

== Changelog ==

= 1.7.0 - 2026-09-28 =
* **New**: Choose which comment types are masked - comments, product reviews, block editor notes, and other types (pingbacks, trackbacks, custom types). Notes are off by default
* **New**: Optional anonymization of commenter IP addresses (Settings → PII Protection → Commenter IP addresses)
* **New**: Abilities API support - `piip/mask-text` and `piip/scan-content` abilities for MCP clients and AI agents; results never include raw PII
* **New**: `piip_mask_comment_type` filter to decide per comment type whether a comment is masked
* **New**: Inquiry-style text protection (new PII type "Labeled contact details", on by default) - labeled names, furigana, addresses, phones, member/login IDs, card expiry and security codes, as posted when a forum is mistaken for a contact form
* **New**: Labeled ID document numbers (new PII type, on by default) - passport, driver's license, health insurance, basic pension and residence card numbers
* **New**: Full-width digits and symbols are detected (０９０－１２３４－５６７８, ｔａｒｏ＠ｅｘａｍｐｌｅ．ｊｐ); text outside masked parts keeps its original width
* **New**: More phone formats - 03(1234)5678, 090(1234)5678, 0570/0990 numbers, US 1-800-555-0199
* **New**: Amex (4-6-5) and Diners (4-6-4) card groupings
* **New**: More password labels (PW, P/W, PIN, passcode, ログインパス) and pasted configuration secrets (define( 'DB_PASSWORD', … ), *_PASSWORD=, *_SECRET=, *_TOKEN=, api_key=)
* **New**: More service tokens - Google OAuth, Stripe test/restricted/webhook secrets, SendGrid, npm, GitLab, Twilio, Mailgun, Shopify, Telegram, DigitalOcean, Slack and Discord webhook URLs
* **New**: Japanese addresses without the prefecture for designated cities and Tokyo's 23 wards
* **New**: `piip_text_rules` filter to add or change free-text detection rules
* **Changed**: Detection and masking now share one set of rules, so the preview, the PII scan and the Abilities API report exactly what masking handles
* **New**: IPv6 addresses in text are masked, including compressed forms (2001:db8::1 → 2001:db8:***)
* **Security**: Fixed a stored XSS where a private key block spanning HTML markup could rewrite tag attributes in integrations that mask after HTML sanitization (e.g. BuddyPress activity). Private key matching is now limited to the PEM alphabet, and any masking result that would change the HTML markup falls back to masking text and attribute values separately
* **Security**: Custom pattern replacements can no longer contain < > " ' ` (removed on save and at runtime for existing patterns)
* **Fixed**: Credit card numbers in text are masked only when they pass the Luhn checksum, so order numbers, JAN codes and other long IDs are no longer masked; fields named as card fields are still masked regardless
* **Fixed**: BuddyPress activity links were broken for user names that look like hosting IDs (e.g. abc12345); the generated activity action is no longer masked, and activity content is masked once instead of twice
* **Fixed**: Exact field names now take precedence over partial matches (e.g. remote_addr is treated as an IP address, not a postal address)
* **Fixed**: Comments created through the REST API (headless front ends, apps, block editor notes) were saved without masking
* **Fixed**: Edited comments were saved without masking; only changed fields are masked, so moderation never rewrites older text
* **Fixed**: Removing every consent phrase and saving silently re-enabled the default phrases for real submissions, while the preview showed masking
* **Fixed**: Consent phrase defaults were different between the settings screen, activation and runtime; they now share one list, and matching is case-insensitive for multibyte text
* **Fixed**: Adding a consent phrase or custom pattern after removing a row could overwrite another row on save
* **Fixed**: The error shown for a rejected custom pattern now escapes the pattern
* Tested up to WordPress 7.1

= 1.6.0 - 2026-07-05 =
* **New**: Japanese street addresses are now detected and masked in free text (prefecture + municipality + block number required, so mere place mentions are untouched); labeled postal codes (〒 / 郵便番号) are masked too
* **New**: Labeled passwords in free text (password: xxx / パスワードは xxx) are masked to [REDACTED]
* **New**: HTTP Basic auth credentials are masked - curl -u user:pass, Authorization: Basic headers, and user:pass@host URLs (password only)
* **New**: Bearer tokens (including JWTs) are masked
* **New**: Developer secrets are masked - GitHub, Slack, AWS, Stripe tokens, bare JWTs, and whole SSH/PEM private key blocks
* **New**: Labeled dates of birth (生年月日/誕生日/birth date) and labeled bank account numbers (口座番号, 普通/当座) are masked; both can be disabled in settings
* **New**: Opt-in masking of names in self-introduction phrases (〜と申します, 名前: 〜); off by default, enable "Names (self-introduction phrases)" in settings
* **Changed**: Tokens shorter than 32 characters are now partially masked instead of passing through unmasked
* **Changed**: IP address and hosting ID masking can now be toggled in settings (previously always on); the internal mask_hosting_id setting key was migrated to mask_hosting
* **Fixed**: The masking preview now uses the exact same per-type enablement rules as real submissions

= 1.5.0 - 2026-07-05 =
* **New**: PII Scan tool (Tools → PII Scan) - scan existing comments and posts for PII with a dry-run report, then apply masking retroactively
* **New**: Contact Form 7 integration - masks free-text (message) fields in submissions, protecting both the sent mail and stored copies (e.g. Flamingo); name/email fields are kept for replies
* **New**: User Profiles integration - masks PII written into publicly visible profile fields (display name, nickname, biographical info)
* **New**: Custom patterns - define your own regex patterns and replacements in the settings for site-specific identifiers (employee IDs, member numbers, etc.)
* **New**: WP-CLI commands - `wp piip mask` and `wp piip scan --apply` for large sites and CI
* **Fixed**: Enabled integrations (e.g. Comments) were inactive until the settings were saved once, due to a settings key mismatch in the activation defaults
* **Fixed**: Ordinary words were reported as GCP hosting IDs in the preview/scan detection breakdown, flagging almost every text as containing PII

= 1.4.1 - 2026-07-02 =
* **New**: Masking Preview tool on the settings page - type sample text and see the masked result in real time
* **New**: Field name + value preview mode for testing field-based detection (e.g. password fields)
* **New**: Detected PII breakdown with type, confidence score, and actual masking status
* **New**: Consent phrase bypass indication in the preview
* **Fixed**: Japanese toll-free numbers (0120-xxx-xxx, 0800-xxx-xxxx) were detected but not masked in free text content

= 1.4.0 - 2026-02-07 =
* **New Feature**: AI API Key Detection and Masking
* **Added**: Support for 10 AI service providers (OpenAI, Anthropic Claude, Google AI, Hugging Face, Replicate, Cohere, Azure OpenAI, and more)
* **Added**: Automatic detection of AI API keys in comments and form submissions
* **Added**: Pattern-based detection for sk-, sk-proj-, sk-ant-, AIza, hf_, r8_ prefixed keys
* **Enhanced**: Token masking now includes AI-specific key patterns
* **Enhanced**: Comment integration now masks AI API keys in text content
* **Security**: Prevents accidental exposure of AI API credentials in public content
* **Tested**: 100% test coverage with 35 test cases across 3 test suites

= 1.3.0 - 2025-12-27 =
* **Major Feature**: Added comprehensive custom hook system for developers
* **New**: 18 filter hooks for custom PII detection and masking
* **New**: 4 action hooks for logging and compliance tracking
* **New**: `piip_mask_text()` global function for simple text masking
* **New**: Dynamic integration registration for community plugins
* **New**: Custom PII type detection and masking capabilities
* **Enhanced**: Form data processing with before/after hooks
* **Enhanced**: Integration system now supports third-party extensions
* **Developer**: Complete hook documentation and examples included
* **Tested**: Comprehensive test plugins for hook validation

= 0.2.0 - 2025-12-01 =
* Initial release
* Support for multiple PII types with validation (email, phone, address, credit card, SSN/My Number, password, token, IP, hosting IDs)
* WordPress Comments integration
* wpForo, BuddyPress, bbPress integrations
* Consent-based opt-out feature
* Admin settings page
* Hosting account ID detection (Japanese and international providers)
* Privacy-focused design with no detailed logging
* Note: Name masking excluded due to accuracy limitations

== Upgrade Notice ==

= 1.7.0 =
Security release: fixes a stored XSS in integrations that mask after HTML sanitization (e.g. BuddyPress). REST API comments and comment edits are now masked; block editor notes stay unmasked unless enabled.

= 0.2.0 =
Initial release of PIIP - PII Protection plugin.

== Privacy Policy ==

PIIP - PII Protection does NOT:
* Send any data to external servers
* Track users
* Use cookies
* Share data with third parties

PIIP DOES:
* Process content locally on your server
* Automatically mask PII without storing sensitive data
* Optionally anonymize the IP addresses stored with comments

== Support ==

For support, bug reports, or feature requests:
* Website: https://github.com/benridane/piip

== Development ==

Development happens on GitHub. Pull requests welcome!
* Follow coding standards
* All code must pass `composer run phpcs`
