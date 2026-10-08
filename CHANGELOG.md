# Changelog

All notable changes to BIMI Checker are documented in this file.

## 1.1.0

### Added

- Added BIMI Organizational Domain fallback when the Organizational Domain can be determined safely.
- Added validation and display support for the BIMI `lps=` Local-part as Selector tag.
- Added parsing of comma-separated `lps=` prefixes.
- Added support for an empty `lps=` value as a valid and meaningful configuration.
- Added validation status information for `l=` logo URIs.
- Added validation status information for `a=` evidence-document URIs.
- Added reporting of the domain and selector that supplied a BIMI record when fallback is used.

### Changed

- BIMI TXT processing now discards unrelated TXT responses that are not `v=BIMI1` records.
- DMARC TXT processing now discards unrelated TXT responses that are not `v=DMARC1` records.
- Improved `l=` validation to distinguish HTTPS URI validity from expected SVG/SVGZ resource formats.
- An `l=` URI pointing to a `.pem` resource is now flagged as a likely incorrect logo resource.
- Improved `a=` validation so populated values require HTTPS.
- `.pem` is recognized as the expected/common evidence-document filename format, while other formats generate a warning rather than an automatic failure.
- Removed the obsolete A/V/P flag interpretation.
- Removed inference of Brand versus Personal status based on the presence of `a=`.
- `avp=` is interpreted directly from the published BIMI record.
- Existing results styling, status colours, icons, DMARC presentation, and inbox preview are preserved.

### UI follow-up

- Added an `lps=` row to the existing BIMI results card.
- An absent `lps=` tag now displays an amber status icon and `—`, matching the existing `avp=` presentation.
- Published `lps=` values are shown using the existing status-row styling, without CSS changes.
- The compact LPS row currently distinguishes presence from absence; it does not yet display a separate red error state for malformed published values.

### Notes

- Organizational Domain fallback is only performed when the plugin can determine the Organizational Domain safely. It does not blindly remove DNS labels where doing so could produce an incorrect result for multi-label public suffixes.
- Complete `lps=` secondary lookup testing requires a sender local-part. The checker validates and reports the published LPS configuration without assuming that a published prefix is itself the resulting selector.
- Resource filename checks are advisory where the BIMI specification permits an HTTPS URI without a particular filename extension.

## 1.0.4

### Added

- Added a WordPress settings page.
- Added support for external DNS resolvers.
- Added configurable DNS resolver behaviour.
- Added improved AJAX error trapping and descriptions.

### Changed

- Added optional BIMI selector support, defaulting to `default`.
- Added display of the complete BIMI TXT record.
- Added direct parsing and display of the BIMI `avp=` attribute.
- Improved DMARC policy, `sp=`, `pct=`, and `rua=` reporting.

## 1.0.3

### Changed

- Added clickable links for `a=` and `l=` record values.
- Cleaned up preview output, including use of the domain in the From display.
- Improved mobile responsiveness and wrapping for long values.
- Removed placeholder grey lines from the preview.

## 1.0.2

### Added

- Added pass, warning, and fail status icons.
- Added nonce-secured AJAX handling.

## 1.0.1

### Changed

- Modularized plugin code into classes and templates.
- Added BIMI and DMARC parsing helpers.

## 1.0.0

- Initial release.
