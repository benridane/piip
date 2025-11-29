=== PIIP - PII Protection for WordPress Forms ===
Contributors: yourusername
Tags: privacy, pii, gdpr, forms, security, data-protection, contact-form-7
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically masks personally identifiable information (PII) in form submissions to protect user privacy and comply with GDPR.

== Description ==

PIIP (PII Protection) is a WordPress plugin that automatically detects and masks personally identifiable information (PII) in form submissions before the data is saved to your database. This helps protect user privacy and ensures GDPR compliance.

= Key Features =

* **Automatic PII Detection**: Intelligently detects 8 types of PII including emails, phone numbers, names, addresses, credit cards, SSN, passwords, and API tokens
* **Server-Side Masking**: All masking happens on the server (PHP) for maximum security - cannot be bypassed by users
* **Form Plugin Support**: Works seamlessly with Contact Form 7, Snow Monkey Forms, and other popular form plugins
* **Configurable**: Choose which PII types to mask via easy-to-use settings page
* **Audit Trail**: Complete logging of all masking events with export to CSV
* **GDPR Compliant**: Automatic log cleanup based on configurable retention period (30-365 days)
* **Zero Performance Impact**: Efficient processing with minimal overhead (<20ms per form submission)
* **WordPress Standards**: Follows all WordPress coding standards and best practices

= Supported PII Types =

* Email addresses (example@domain.com → e***@domain.com)
* Phone numbers (+1-234-567-8900 → ***-***-8900)
* Names (John Doe → J*** D*** / 山田太郎 → 山田**)
* Addresses (masked to ***)
* Credit card numbers (4532-1234-5678-9010 → ****-****-****-9010)
* Social Security Numbers (123-45-6789 → ***-**-6789)
* Passwords (masked to [REDACTED])
* API Tokens/Keys (partial masking showing first and last 4 characters)

= Supported Form Plugins =

* Contact Form 7
* Snow Monkey Forms
* More integrations coming soon!

= How It Works =

1. User submits a form on your website
2. PIIP intercepts the submission before database save
3. Automatically detects PII using field names, regex patterns, and validation
4. Masks detected PII according to your settings
5. Logs the masking event (optional)
6. Form processes normally with masked data

= Privacy & Security =

* All processing happens on YOUR server (no external API calls)
* Original values are NEVER stored (only SHA-256 hashes for audit purposes)
* Server-side processing prevents client-side bypass attempts
* Logs automatically deleted based on retention policy
* Full control over your data

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Go to Plugins → Add New
3. Search for "PIIP" or "PII Protection"
4. Click "Install Now" and then "Activate"
5. Go to Settings → PII Protection to configure

= Manual Installation =

1. Download the plugin ZIP file
2. Upload to `/wp-content/plugins/piip` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to Settings → PII Protection to configure

= After Activation =

1. Navigate to **Settings → PII Protection**
2. Review the default settings (all PII types are masked by default)
3. Adjust which PII types to mask according to your needs
4. Configure log retention period (default: 90 days)
5. Save settings
6. Test with a form submission to verify masking is working
7. Check **Tools → PII Masking Logs** to see masked entries

== Frequently Asked Questions ==

= Does this work with Contact Form 7? =

Yes! PIIP has native integration with Contact Form 7 and will automatically mask PII in all CF7 form submissions.

= Does this work with other form plugins? =

Currently supported:
- Contact Form 7
- Snow Monkey Forms

We plan to add support for Gravity Forms, WPForms, Ninja Forms, and more in future releases.

= Will this slow down my website? =

No. PIIP adds minimal processing time (<20ms per form submission) which is imperceptible to users. All processing happens server-side after form submission.

= Can users bypass the masking by disabling JavaScript? =

No. All masking happens on the server (PHP), so it cannot be bypassed by disabling JavaScript or using browser developer tools.

= Is the original data stored anywhere? =

No. The original data is never stored. We only store:
- The masked value
- A SHA-256 hash of the original (for audit purposes, cannot be reversed)

= How long are logs kept? =

By default, logs are kept for 90 days. You can configure this in Settings → PII Protection to anywhere from 30 to 365 days. Logs are automatically deleted after the retention period expires.

= Is this GDPR compliant? =

Yes. PIIP helps with GDPR compliance by:
- Minimizing data collection (masking PII)
- Providing audit trails
- Automatic data deletion based on retention policies
- No third-party data sharing (everything stays on your server)

= Can I export the masking logs? =

Yes. Go to Tools → PII Masking Logs and click "Export to CSV" to download all logs.

= What happens during uninstall? =

When you uninstall (not just deactivate) the plugin, all settings, database tables, and logs are completely removed from your system.

= Can I customize the masking patterns? =

The current version uses predefined masking patterns optimized for privacy and usability. Future versions may include customization options.

= Does this work with multisite? =

The plugin is compatible with multisite installations and can be activated per-site or network-wide.

== Screenshots ==

1. Settings page - Configure which PII types to mask
2. Masking logs - View audit trail of all masked submissions
3. Example of masked email in form submission
4. CSV export of masking logs

== Changelog ==

= 1.0.0 - 2025-01-15 =
* Initial release
* Support for 8 PII types (email, phone, name, address, card, SSN, password, token)
* Contact Form 7 integration
* Snow Monkey Forms integration
* Admin settings page
* Masking logs with CSV export
* Automatic log cleanup (GDPR compliance)
* WordPress Coding Standards compliant
* Full PHPDoc documentation

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
* Process form data locally on your server
* Store masked PII and audit logs in your WordPress database
* Automatically delete logs after the configured retention period

== Support ==

For support, bug reports, or feature requests:
* GitHub: https://github.com/yourusername/piip
* WordPress Support Forum: https://wordpress.org/support/plugin/piip/

== Development ==

Development happens on GitHub. Pull requests welcome!
* GitHub Repository: https://github.com/yourusername/piip
* Follow WordPress Coding Standards
* All code must pass `composer run phpcs`
* Test with `wp-env` before submitting PRs
