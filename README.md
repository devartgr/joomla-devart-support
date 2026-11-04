# DevArt Support for Joomla

Professional ticket desk for Joomla 6 with a registered-user site portal and an administrator staff desk. Built for large sites, high traffic, Cloudflare-backed production environments, and simple day-to-day support work.

![Joomla](https://img.shields.io/badge/Joomla-6.x-blue)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-green)
![Release](https://img.shields.io/badge/Version-1.0.0-orange)
![License](https://img.shields.io/badge/License-GPLv3-red)

---

## Overview

DevArt Support is a modern Joomla 6 ticket desk for managing support conversations between registered site users and staff.

The product is intentionally simpler than full helpdesk suites: users open and reply on the **site portal**; staff manage and reply only in the **administrator**. Ticket data is private. Login, ACL, and ownership are enforced before any frontend ticket content is served.

DevArt Support supports:

- Registered-user portal (own tickets: list, create, view, reply, priority, close/reopen)
- Administrator staff desk (tickets, categories, trash, Tools hubs)
- Private attachments (admin + portal) with configurable types and optional retention
- Staff and requester email notifications (Immediate or Queued delivery)
- Needs-reply list badge and load-older message threads
- Privacy, Action Log, and scheduled attachment-purge plugins
- Fifteen packaged locales

Guest tickets, assignment, frontend modules, and live chat are out of scope for this product line.

---

## Version 1.0.0

DevArt Support 1.0.0 is the first public release for Joomla 6 / PHP 8.3+. It delivers the full registered portal and staff desk, private attachments, notifications, and production hardening (atomic ticket version lock, safer ZIP handling, installer column ensure, mail queue, Cloudflare-oriented ops notes).

### Version 1.0.0 Highlights

- Registered portal + administrator staff desk; statuses `open` / `closed` only
- Private attachments with configurable types, folder name, and optional Scheduler purge
- Email: Immediate (default) or Queued via Scheduler; portal-only author-copy
- Atomic admin saves via integer `version` lock; portal concurrency-safe reopen
- ZIP uploads: host MIME tolerance + local-header magic check; safer portal Close confirm
- Privacy / Action Log / task plugins; 15 locales; GitHub update server

See `CHANGELOG.md` for the full public changelog.

---

## Requirements

- Joomla 6.0+
- PHP 8.3+
- Modern MariaDB/MySQL

No Joomla 3/4/5 compatibility. No PHP 7.x or PHP 8.0–8.2 compatibility.

---

## Package Contents

The installable package includes:

- `com_devartsupport` — administrator staff desk and site portal
- `plg_privacy_devartsupport` — Joomla Privacy export/remove
- `plg_actionlog_devartsupport` — Joomla Action Log for ticket mutations
- `plg_task_devartsupport` — scheduled purge of expired private attachments (and mail outbox when Queued)

Always install or update using the full `pkg_devartsupport` package.

Do not install a standalone component ZIP on a clean website.

---

## Site Portal

Registered users (menu Access Level + component ACL) can:

- List and open their own tickets
- Create tickets and replies
- Set priority
- Close or reopen their tickets (Close asks for confirmation)
- Upload and download private attachments when enabled in Options

Guests are redirected to login. Staff do not manage tickets from the frontend.

---

## Administrator Staff Desk

Staff manage tickets only in the administrator:

- Ticket list with needs-reply badge, categories, priorities, trash
- Reply with private attachments
- Tools submenu: Email Settings and Options
- Bulk status and priority actions

---

## Attachments

- Stored outside the web root under a configurable private folder name
- Allowed types are a subset of a safe catalog (including ZIP/RAR with content checks)
- Downloads require authenticated POST + CSRF; `nosniff` and no-store oriented headers
- Optional retention with Scheduler purge (default off)

---

## Email Notifications

- Immediate delivery (default) or Queued via Scheduler task `devartsupport.send_mail`
- Staff notifications for new tickets, replies, and status changes
- Optional requester notifications
- Portal-only author-copy on user replies
- Test mail always sends immediately

---

## Languages

Administrator, site, package, and Action Log language packs:

- English (`en-GB`) — source of truth
- Greek (`el-GR`) — curated
- French, German, Spanish, Italian, Brazilian Portuguese
- Czech, Dutch, Polish, Russian, Ukrainian, Japanese, Turkish, Chinese Simplified

Language keys and format specifiers are never translated.

---

## Cloudflare / Cache

Ticket content is private. Portal and authenticated responses are intended to stay out of shared full-page cache. See `docs/operations.md` for update and Cloudflare operator notes. After ACL or notification changes behind edge cache, purge as needed.

---

## Install / Update

1. Install or update `pkg_devartsupport_v1.0.0.zip` via Joomla Installer (or Joomla Update when advertised).
2. Confirm Components → DevArt Support works in administrator and that a Registered portal menu item exists.
3. Review Options (attachments, portal rate limits) and Email Settings (Immediate vs Queued).

### Package

- Package: `pkg_devartsupport`
- Version: `1.0.0`
- SHA-256: `17bf1faef4cbb8c5f2d43381bf8b4d47f0be6a34e4b692c56ea632f2c805fca0`
- File: `pkg_devartsupport_v1.0.0.zip`

---

## Repository Layout

- `source/` — the only development source
- `builds/` — packages generated by the build script
- `releases/` — official release copies
- `scripts/` — validation and packaging
- `docs/` — architecture and operations
- `qa/` — local QA notes

```shell
php scripts/validate.php
php scripts/build.php
```

---

## Links

- Releases: https://github.com/devartgr/joomla-devart-support/releases
- Update stream: https://raw.githubusercontent.com/devartgr/joomla-devart-support/main/update.xml
- Changelog: https://raw.githubusercontent.com/devartgr/joomla-devart-support/main/changelog.xml
- Site: https://devart.gr

---

## License

GNU General Public License version 3 or later.
