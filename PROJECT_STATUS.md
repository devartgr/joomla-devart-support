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
Registered-user site portal and administrator staff desk are in place: tickets, categories, trash, Frontend Options (including portal rate limits), private attachments (admin + portal), Privacy / Action Log / task plugins, staff/requester emails with Immediate (default) or Queued delivery, portal-only author-copy, needs-reply list badge, load-older message threads, and portal/admin staff message styling. Configurable private folder name and optional attachment retention (Scheduler purge, default off). Pre-public hardening includes schema safety, indexes, concurrency guards, MIME/purge/throttle, createQuery, installer SQL purge, and optional Scheduler mail outbox. Joomla 6+ and PHP 8.3+ only. Product version stays on `1.0.0` until the first public release is declared. Live chat remains out of scope. Staff manage and reply only in the administrator; the portal is ownership-scoped. Package update server points at GitHub `joomla-devart-support` root `update.xml`. Portal owners set priority and may close/reopen. Ticket statuses are open/closed only. Site + Action Log language packs covered for all 15 locales. Guest tickets are not implemented; prerequisites are documented.

# Verified Package
Previous VPS-verified: SHA-256 `5011f8f972eb5e91f155540124caf71aa6121ceed8ec0e00491e26fc6793fbac` (mail delivery).

Pending VPS confirmation of the latest `pkg_devartsupport_v1.0.0.zip` (atomic `version` lock + mail-queue hardening).

# Verified Creation Date
`2026-11-04` (mail delivery); concurrency/mail-queue package pending VPS

# Last Completed
Local atomic ticket `version` lock, mail-queue UTF-8 truncation, failed-queue purge.

# Current Milestone
Concurrency + mail-queue hardening on `1.0.0` — awaiting VPS confirmation.

# Next Milestone
Declare the first public release when ready (still `1.0.0` until then), or guest tickets after approving `docs/guest-tickets-prerequisites.md`. Assignment, modules and live chat remain out of scope for now.

# Pending
- VPS confirmation of latest `pkg_devartsupport_v1.0.0.zip`
- Guest tickets only after prerequisite confirmation
- First public release declaration when ready
