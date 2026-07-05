=== PIIP - PII Protection ===
Contributors: benridane, presents111
Tags: privacy, pii, gdpr, security, data-protection
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 1.6.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically detects and masks PII in WordPress comments and forms to protect user privacy and support your GDPR compliance efforts.

== Description ==

PIIP (PII Protection) is a plugin that automatically detects and masks personally identifiable information (PII) in WordPress comments and community plugin content before the data is saved to your database. This helps protect user privacy and supports your compliance efforts under privacy regulations such as the GDPR. Note that PIIP is a technical tool and does not by itself make your site GDPR compliant.

= Key Features =

* **Automatic PII Detection**: Intelligently detects multiple types of PII including emails, phone numbers, addresses, credit cards, SSN/My Number, passwords, API tokens, IP addresses, and hosting account IDs
* **Server-Side Masking**: All masking happens on the server (PHP) for maximum security - cannot be bypassed by users
* **WordPress Core Support**: Native support for WordPress comments
* **Community Plugin Support**: Works seamlessly with wpForo, BuddyPress, bbPress, and other popular community plugins
* **Configurable**: Choose which PII types to mask via easy-to-use settings page
* **Consent Opt-Out**: Users can include consent phrases to skip masking when sharing personal info publicly
* **Presidio-Level Detection**: High-accuracy detection with validation (Luhn for credit cards, check digits for My Number)
* **Retroactive Scan**: Scan content that existed before installing PIIP and apply masking after a dry-run review
* **Custom Patterns**: Mask site-specific identifiers (employee IDs, member numbers) with your own regular expressions
* **WP-CLI Support**: `wp piip mask` and `wp piip scan` commands for automation and large sites

= Supported PII Types =

* Email addresses (example@domain.com → e***@domain.com)
* Phone numbers (Japanese mobile/landline, international formats)
* Japanese street addresses in free text (東京都新宿区西新宿2-8-1 → 東京都***) and labeled postal codes (〒123-4567 → 〒***-****)
* Credit card numbers with Luhn validation (4532-1234-5678-9010 → ****-****-****-9010)
* Social Security Numbers / Japanese My Number with check digit validation
* Passwords, including labeled values in free text (password: xxx / パスワードは xxx → [REDACTED])
* HTTP credentials: Basic auth (curl -u, Authorization: Basic, user:pass@host URLs) and Bearer tokens (including JWTs)
* Developer secrets: GitHub, Slack, AWS, Stripe tokens and SSH/PEM private key blocks
* API Tokens/Keys (partial masking showing first and last 4 characters)
* AI API Keys (OpenAI sk-***, Anthropic sk-ant-***, Google AIza***, Hugging Face hf_***, Replicate r8_***, Cohere, Azure OpenAI)
* Labeled dates of birth (生年月日: 1990-01-15 → ****-**-**)
* Labeled bank account numbers (口座番号: 1234567 → ***4567)
* Names in self-introduction phrases (山田太郎と申します → 山***と申します; opt-in, off by default)
* IP Addresses (192.168.1.1 → 192.***.***1)
* Hosting Account IDs (XServer, Sakura, AWS, Azure, GCP, ConoHa, Lolipop, mixhost)

= Supported Integrations =

* **WordPress Core**
  * Comments
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

== Support ==

For support, bug reports, or feature requests:
* Website: https://github.com/benridane/piip

== Development ==

Development happens on GitHub. Pull requests welcome!
* Follow coding standards
* All code must pass `composer run phpcs`
