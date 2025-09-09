=== BIMI Checker ===
Contributors: mattvernhout, bimigroup
Tags: BIMI, DMARC, deliverability, email, authentication
Requires at least: 5.6
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Validate BIMI and DMARC settings for a domain directly in WordPress. Use shortcode [bimi_checker] to embed the checker anywhere on your site.

== Description ==

BIMI Checker is a WordPress plugin that allows you to validate **BIMI (Brand Indicators for Message Identification)** and **DMARC** records for any domain.

Features include:

* Input form with domain and optional selector (defaults to `default`)
* BIMI checks:
  - Locate BIMI record (`selector._bimi.domain.com`)
  - Parse `a=`, `l=`, and `avp=` tags
  - Ensure HTTPS URLs are used
  - Differentiate between self-asserted vs. VMC
* DMARC checks:
  - Verify `_dmarc.domain.com` exists
  - Confirm `p=` and `sp=` are `quarantine` or `reject`
  - Ensure `pct=100` (or default)
  - Warn if `rua=` is missing
* Results shown with ✅ / ⚠️ / ❌ indicators
* Preview card showing the domain logo and example inbox view
* Modern, mobile-friendly design

Use the shortcode `[bimi_checker]` to embed the form anywhere on your site.

Follow us: [BIMI Group on Bluesky](https://bsky.app/profile/bimigroup.bsky.social)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/bimi-checker`, or install via the WordPress plugins screen.
2. Activate the plugin.
3. Add the shortcode `[bimi_checker]` to any post, page, or widget area.

== Frequently Asked Questions ==

= What is BIMI? =
BIMI stands for *Brand Indicators for Message Identification*. It lets email senders display a verified brand logo in inboxes, provided the domain has strong authentication in place.

= What do I need for BIMI to work? =
A domain with SPF, DKIM, and DMARC in place (`p=quarantine` or `p=reject`), plus a properly formatted BIMI record. Some inbox providers also require a Verified Mark Certificate (VMC).

= Can I use this plugin to fix my records? =
No — this plugin only checks and reports. You’ll need to update your DNS records at your DNS host or provider.

= Does it work on mobile? =
Yes. The layout is responsive and adapts to smaller screens.

== Screenshots ==

1. BIMI Checker form with domain and selector inputs
2. Validation results with checkmarks, warnings, and errors
3. Example inbox preview with logo and subject line

== Changelog ==

= 1.0.3 =
* Added clickable links for `a=` and `l=` record values  
* Cleaned preview output (domain used as From, Subject updated)  
* Improved mobile responsiveness and wrapping for long values  
* Removed placeholder grey lines in preview  

= 1.0.2 =
* Added icons for Pass/Fail/Warning states  
* Introduced nonce-secured AJAX handler  

= 1.0.1 =
* Modularised code into classes and templates  
* Added BIMI and DMARC parsing helpers  

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.3 =
Improved results display (clickable links, cleaner preview), plus bug fixes for mobile output and long TXT values.

