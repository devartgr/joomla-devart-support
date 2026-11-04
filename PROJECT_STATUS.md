# Project
DevArt Support — Joomla 6 ticket desk with registered-user site portal and administrator staff desk.

# Current Version
`1.0.0`

# Package
`pkg_devartsupport`

# Extension Inventory
- `com_devartsupport`
- `plg_privacy_devartsupport`
- `plg_actionlog_devartsupport`
- `plg_task_devartsupport`

# Repository Status
Registered-user site portal and administrator staff desk are in place: tickets, categories, trash, Frontend Options (including portal rate limits), private attachments (admin + portal), Privacy / Action Log / task plugins, staff/requester emails with Immediate (default) or Queued delivery, portal-only author-copy, needs-reply list badge, load-older message threads, and portal/admin staff message styling. Configurable private folder name and optional attachment retention (Scheduler purge, default off). Pre-public hardening includes schema safety, indexes, concurrency guards, MIME/purge/throttle, createQuery, installer SQL purge, optional Scheduler mail outbox, atomic ticket `version` lock, UTF-8 mail-queue truncation, failed-queue purge, ZIP package upload fix, portal TicketDesk fallback, safer portal Close (quiet + confirm), ZIP local-header content check, and per-ALTER installer column ensure with logging. Joomla 6+ and PHP 8.3+ only. Product version stays on `1.0.0` until the first public release is declared. Live chat remains out of scope. Staff manage and reply only in the administrator; the portal is ownership-scoped. Package update server points at GitHub `joomla-devart-support` root `update.xml`. Portal owners set priority and may close/reopen. Ticket statuses are open/closed only. Site + Action Log language packs covered for all 15 locales. Guest tickets are not implemented; prerequisites are documented.

# Verified Package
`builds/pkg_devartsupport_v1.0.0.zip`
SHA-256 `17bf1faef4cbb8c5f2d43381bf8b4d47f0be6a34e4b692c56ea632f2c805fca0`

# Verified Creation Date
`2026-11-04`

# Last Completed
Safer portal Close, ZIP magic-byte validation, and installer per-ALTER column ensure — VPS confirmed.

# Current Milestone
Pre-public `1.0.0` polish complete (VPS OK). Ready for first public release declaration when chosen.

# Next Milestone
Declare the first public release when ready (still `1.0.0` until then), or guest tickets after approving `docs/guest-tickets-prerequisites.md`. Assignment, modules and live chat remain out of scope for now.

# Pending
- Guest tickets only after prerequisite confirmation
- First public release declaration when ready
