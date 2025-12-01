=== PIIP - PII Protection ===
Contributors: benridane
Tags: privacy, pii, gdpr, security, data-protection
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 0.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically masks personally identifiable information (PII) in WordPress comments and community plugin content to protect user privacy and comply with GDPR.

== Description ==

PIIP (PII Protection) is a plugin that automatically detects and masks personally identifiable information (PII) in WordPress comments and community plugin content before the data is saved to your database. This helps protect user privacy and ensures GDPR compliance.

= Key Features =

* **Automatic PII Detection**: Intelligently detects multiple types of PII including emails, phone numbers, addresses, credit cards, SSN/My Number, passwords, API tokens, IP addresses, and hosting account IDs
* **Server-Side Masking**: All masking happens on the server (PHP) for maximum security - cannot be bypassed by users
* **WordPress Core Support**: Native support for WordPress comments
* **Community Plugin Support**: Works seamlessly with wpForo, BuddyPress, bbPress, and other popular community plugins
* **Configurable**: Choose which PII types to mask via easy-to-use settings page
* **Consent Opt-Out**: Users can include consent phrases to skip masking when sharing personal info publicly
* **Audit Trail**: Complete logging of all masking events with export to CSV
* **GDPR Compliant**: Automatic log cleanup based on configurable retention period (30-365 days)
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
* Hosting Account IDs (Xserver, Sakura, AWS, Azure, GCP, ConoHa, Lolipop, mixhost)

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
5. Logs the masking event (optional)
6. Content saves normally with masked data

= Privacy & Security =

* All processing happens on YOUR server (no external API calls)
* Original values are NEVER stored (only SHA-256 hashes for audit purposes)
* Server-side processing prevents client-side bypass attempts
* Logs automatically deleted based on retention policy
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
5. Configure log retention period (default: 90 days)
6. Save settings
7. Test with a post or comment to verify masking is working

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
- A SHA-256 hash of the original (for audit purposes, cannot be reversed)

= How long are logs kept? =

By default, logs are kept for 90 days. You can configure this in Settings → PII Protection to anywhere from 30 to 365 days.

= Is this GDPR compliant? =

Yes. PIIP helps with GDPR compliance by:
- Minimizing data collection (masking PII)
- Providing audit trails
- Automatic data deletion based on retention policies
- No third-party data sharing (everything stays on your server)

== Screenshots ==

1. Settings page - Configure integrations and PII types to mask
2. Consent phrases configuration
3. Masking logs - View audit trail of all masked submissions
4. Example of masked content in forum post

== Changelog ==

= 1.0.0 - 2025-01-15 =
* Initial release
* Support for multiple PII types with validation (email, phone, address, credit card, SSN/My Number, password, token, IP, hosting IDs)
* WordPress Comments integration
* wpForo, BuddyPress, bbPress integrations
* Consent-based opt-out feature
* Admin settings page
* Masking logs with CSV export
* Automatic log cleanup (GDPR compliance)
* Hosting account ID detection (Japanese and international providers)
* Note: Name masking excluded due to accuracy limitations

== Upgrade Notice ==

= 1.0.0 =
Initial release of PIIP - PII Protection plugin.

== Privacy Policy ==

PIIP - PII Protection does NOT:
* Send any data to external servers
* Track users
* Use cookies
* Share data with third parties

PIIP DOES:
* Process content locally on your server
* Store masked PII and audit logs in your database
* Automatically delete logs after the configured retention period

== Support ==

For support, bug reports, or feature requests:
* Website: https://benridane.com/piip

== Development ==

Development happens on GitHub. Pull requests welcome!
* Follow coding standards
* All code must pass `composer run phpcs`
