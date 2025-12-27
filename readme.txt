=== PIIP - PII Protection ===
Contributors: benridane, presents111
Tags: privacy, pii, gdpr, security, data-protection
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 1.3.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically detects and masks PII in WordPress comments and forms to protect user privacy and ensure GDPR compliance.

== Description ==

PIIP (PII Protection) is a plugin that automatically detects and masks personally identifiable information (PII) in WordPress comments and community plugin content before the data is saved to your database. This helps protect user privacy and ensures GDPR compliance.

= Key Features =

* **Automatic PII Detection**: Intelligently detects multiple types of PII including emails, phone numbers, addresses, credit cards, SSN/My Number, passwords, API tokens, IP addresses, and hosting account IDs
* **Server-Side Masking**: All masking happens on the server (PHP) for maximum security - cannot be bypassed by users
* **WordPress Core Support**: Native support for WordPress comments
* **Community Plugin Support**: Works seamlessly with wpForo, BuddyPress, bbPress, and other popular community plugins
* **Configurable**: Choose which PII types to mask via easy-to-use settings page
* **Consent Opt-Out**: Users can include consent phrases to skip masking when sharing personal info publicly
* **Presidio-Level Detection**: High-accuracy detection with validation (Luhn for credit cards, check digits for My Number)

= Supported PII Types =

* Email addresses (example@domain.com → e***@domain.com)
* Phone numbers (Japanese mobile/landline, international formats)
* Addresses (masked to ***)
* Credit card numbers with Luhn validation (4532-1234-5678-9010 → ****-****-****-9010)
* Social Security Numbers / Japanese My Number with check digit validation
* Passwords (masked to [REDACTED])
* API Tokens/Keys (partial masking showing first and last 4 characters)
* IP Addresses (192.168.1.1 → 192.***.***1)
* Hosting Account IDs (XServer, Sakura, AWS, Azure, GCP, ConoHa, Lolipop, mixhost)

= Supported Integrations =

* **WordPress Core**
  * Comments
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

= Is this GDPR compliant? =

Yes. PIIP helps with GDPR compliance by:
- Minimizing data collection (masking PII)
- No third-party data sharing (everything stays on your server)
- No detailed logging to protect user privacy

== Screenshots ==

1. Settings page - Configure integrations and PII types to mask
2. Consent phrases configuration
3. Example of masked content in forum post

== Changelog ==

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
