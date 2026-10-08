# BIMI Checker

A WordPress plugin for checking BIMI and DMARC records for a domain. Use the `[bimi_checker]` shortcode to embed the checker on a WordPress site.

## Features

### BIMI

- Look up BIMI records using `selector._bimi.domain`
- Optional selector input, defaulting to `default`
- Ignore unrelated TXT responses that are not BIMI records
- Support BIMI Organizational Domain fallback where the Organizational Domain can be determined safely
- Display the full BIMI TXT record used for validation
- Parse and display `l=`, `a=`, `avp=`, and `lps=` tags
- Validate HTTPS requirements for `l=` and populated `a=` values
- Identify expected SVG/SVGZ logo URI formats and flag suspicious logo resources such as `.pem`
- Recognize `.pem` as the expected/common evidence-document URI format and warn about other formats
- Validate `avp=brand` and `avp=personal` values
- Detect and validate the BIMI `lps=` Local-part as Selector tag
- Recognize an empty `lps=` value as valid and meaningful
- Parse comma-separated LPS prefixes and report malformed values
- Explain when a sender local-part is required to complete an LPS selector lookup

### DMARC

- Look up `_dmarc.domain`
- Ignore unrelated TXT responses that are not DMARC records
- Check `p=` for `quarantine` or `reject`
- Check explicitly published `sp=` policy
- Treat an omitted `pct=` as the effective default of `100`
- Warn when an explicitly published `pct=` is not `100`
- Report whether `rua=` is present

### DNS and diagnostics

- WordPress settings page for DNS resolver configuration
- Use the system/local DNS resolver or configured external DNS resolvers
- Configurable DNS timeout and retry settings
- AJAX diagnostics with clearer error reporting

### Display

- Pass, warning, and error status indicators
- Clickable `a=` and `l=` URLs
- Responsive results layout
- Inbox-style BIMI logo preview

## Current release

**Version 1.1.0**

The results display uses the existing green, amber, and red status indicators. The `lps=` row is always shown: absent values appear as an amber `—`, while published values are displayed directly. The validator also evaluates malformed LPS values, although the current compact LPS display only distinguishes published from absent values.

## Installation

1. Upload the plugin files to `/wp-content/plugins/bimi-checker`, or install the plugin through the WordPress plugins screen.
2. Activate **BIMI Checker**.
3. Configure DNS resolver options under **Settings → BIMI Checker**, if required.
4. Add the `[bimi_checker]` shortcode to a post, page, or widget area.

## Usage

Basic shortcode:

```
[bimi_checker]
```

Visitors can enter a domain and optionally specify a BIMI selector. If no selector is supplied, the checker uses `default`.

## BIMI validation notes

The checker performs DNS and URI-format checks. It does not fetch and validate the SVG/SVGZ file contents or the evidence document itself.

A BIMI `l=` value must use an HTTPS URI. A `.svg` or `.svgz` filename is expected/common, but the BIMI specification does not require the URI itself to end with one of those extensions. Suspicious formats, such as an `l=` URI pointing to a `.pem` file, are flagged.

An `a=` value may be empty. When populated, it must use HTTPS. `.pem` is the expected/common filename format for BIMI evidence documents; other formats are reported as warnings rather than automatically treated as invalid.

For `lps=`, the validator checks the published Local-part as Selector configuration. The current compact display shows whether the tag is published and its value, but does not visually distinguish malformed published values from valid ones. A complete LPS lookup depends on the normalized RFC5322.From local-part, so the DNS record alone is not always sufficient to test the resulting secondary selector.

## Frequently Asked Questions

### What is BIMI?

BIMI stands for **Brand Indicators for Message Identification**. It allows participating mailbox providers to associate an authenticated email domain with a published brand indicator.

### What do I need for BIMI to work?

BIMI requires an appropriately configured BIMI record and qualifying DMARC enforcement. Additional requirements, including evidence documents or other eligibility criteria, can vary by mailbox provider.

### Does the checker modify DNS?

No. BIMI Checker only looks up, validates, and reports records. DNS changes must be made through the applicable DNS hosting provider.

### Does the plugin validate every possible BIMI implementation requirement?

No. The checker evaluates the BIMI and DMARC information it can determine from DNS and the implemented validation tests. Mailbox providers can apply additional requirements and policies before displaying an indicator.

### Does it work on mobile?

Yes. The results layout is responsive and adapts to smaller screens.

## Screenshots

1. BIMI Checker form with domain and selector inputs
2. BIMI and DMARC validation results
3. Inbox-style BIMI logo preview

## Requirements

- WordPress 5.6 or later
- PHP 7.4 or later

## Licence

GPLv2 or later.

https://www.gnu.org/licenses/gpl-2.0.html

## Project

BIMI Group: https://bimigroup.org/

Bluesky: https://bsky.app/profile/bimigroup.bsky.social

## Repository

https://github.com/authindicators/bimi-checker
