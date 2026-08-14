# Release Notes for Verified Elements

## 2.0.0 - 2026-08-17

### Added
- Added asset verification: verify assets, assign reviewers, and configure per-volume
  defaults, with change notifications on file replacement, alt text, and custom field
  changes (Pro edition).
- Added multi-site support: verification state, settings, dashboards, widgets, and
  notifications are now tracked per site (Pro edition).
- Added editions: Lite and Pro.
- Added a "Verify assets" user permission.
- Added the Verify and Assign Reviewer bulk actions to asset indexes.
- Added verification condition rules to asset indexes.
- Added a Subscription Plan settings page.

### Changed
- Renamed the plugin from "Verified Entries" to "Verified Elements". The handle is now
  `verified-elements` and the Composer package `webhubworks/craft-verified-elements`.
- The plugin dashboard, review digest emails, and the account screen now cover entries
  and assets, grouped by element type.
- Dashboard widgets are now available to everyone who can access the plugin, not only
  users who can verify entries.
- The Unassigned dashboard source now lists all elements without a reviewer; its badge
  counts only unassigned elements with a verification date.

### Fixed
- Fixed a bug where the plugin dashboard could show stale verification state after an
  entry was saved.
- Fixed a bug where the Imminent dashboard source used a window that collapsed near the
  end of a month.
- Settings actions now require the "Manage settings" permission server-side.

---

## 1.1.0 - 2025-10-17
### Added
- Added Screenshots to the plugin's store page.

### Changed
- Updated the description on the plugin's store page.
- Refined some styling.
- Updated license.

### Fixed
- Added missing translations for German.
- Updated changelog.

## 1.0.6 - 2025-07-21
### Fixed
- Fixed a bug when querying entries in verification-enabled sections.

## 1.0.5 - 2025-07-14
### Fixed
- Fixed inline editing of entries in verification-enabled sections.

## 1.0.4 - 2025-06-10
### Fixed
- Fixed `PHP 8.2` compatibility.

## 1.0.3 - 2025-06-10
### Fixed
- Improved widget clarity.

## 1.0.2 - 2025-06-06
### Fixed
- Updated changelog.

## 1.0.1 - 2025-06-06
### Fixed
- Fixed a type error in the entry event handler.

## 1.0.0 - 2025-05-09
### Added
- Initial release
