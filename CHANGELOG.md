# Changelog

All notable changes to DevArt Support are documented in this file.

The format is based on Keep a Changelog.

## [1.0.0] - 2026-11-04

First public release of DevArt Support for Joomla 6 / PHP 8.3+. Registered-user site portal and administrator staff desk, private attachments, notifications, Privacy / Action Log / task plugins, and pre-public hardening from the former internal `1.0.1`–`1.0.46` line (version numbering reset to `1.0.0`).

### Added

- Registered-user site portal: list, create, view, reply, priority, close/reopen for own tickets
- Administrator staff desk: tickets, categories, trash, Tools hubs (Email Settings / Options)
- Private attachments on portal and administrator with configurable allowed types
- Configurable private storage folder name under the private root, with optional retention purge (`plg_task_devartsupport`)
- Staff and requester email notifications; Immediate delivery (default) or Queued Scheduler outbox
- Portal-only author-copy for user replies
- Privacy plugin (`plg_privacy_devartsupport`) and Action Log plugin (`plg_actionlog_devartsupport`)
- Registered portal menu auto-create when none exists
- Fifteen packaged locales (en-GB source of truth; el-GR curated; 13 additional)

### Changed

- Ticket statuses are `open` / `closed` only; leftover pending rows migrate to open
- Portal ticket flows live in `PortalTicketService` (shared helpers in `AbstractTicketService`)
- Safer portal Close control: quiet outline styling and confirmation before close
- Author-copy email is portal-only (staff admin replies do not receive a personal copy)

### Fixed

- Atomic admin ticket save via integer `version` optimistic lock; `expected_version` bound on the form
- Portal reply reopen uses conditional SQL so concurrent staff status changes are not overwritten
- ZIP uploads accept additional host MIME detections and verify local-header magic bytes (`PK\x03\x04` / `PK\x05\x06`)
- Validated ZIP/RAR uploads are not blocked by Joomla `isSafeFile` archive content scans
- Portal TicketDesk falls back when the site Extension portal getter is missing
- Installer ensures ticket columns with per-ALTER `Log::WARNING` so one failed ALTER cannot skip `version`
- Installer preflight purges leftover internal SQL update scripts after the numbering reset
- Attachment retention purge deletes files before DB rows; failed deletes keep the row for retry
- Mail queue: UTF-8-safe truncation; purge failed rows older than 30 days
- Configurable portal create/reply rate limits in Options

### Notes

- Requires Joomla 6.0+ and PHP 8.3.0+
- Install and update only through `pkg_devartsupport`
- Installing over former internal `1.0.x` builds is supported (numbering reset)
- Guest tickets, assignment, modules, and live chat remain out of scope

## Development history (superseded numbering)

Former internal builds `1.0.1`–`1.0.46` are archived locally. Product state of this public `1.0.0` matches that line after the numbering reset and subsequent polish.

## Template 1.1.0 — 2026-08-04

- Restored generic documentation placeholders for new product bootstrap.
- Added Joomla production-series and workspace awareness to `scripts/validate.php`.
- Added PHP minimum, compatibility targets, and extension checks to validation.
- Added stronger package and extension naming validation.
- Made `scripts/build.php` validation-first with a source integrity guard.
- Preserved the original simple template architecture.
