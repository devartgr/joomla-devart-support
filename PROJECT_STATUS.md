# Project
DevArt Support — Joomla 6 ticket desk with registered-user site portal and administrator staff desk.

# Current Version
`1.0.42`

# Package
`pkg_devartsupport`

# Extension Inventory
- `com_devartsupport`
- `plg_privacy_devartsupport`
- `plg_actionlog_devartsupport`

# Repository Status
Registered-user site portal and administrator staff desk are in place: tickets, categories, trash, Frontend Options, private attachments (admin + portal), Privacy plugin, Action Log plugin, staff/requester/author-copy emails, needs-reply list badge, load-older message threads, and portal/admin staff message styling. Joomla 6+ and PHP 8.3+ only. Versions stay on `1.0.x` until an explicit final `1.1.0` release. Live chat remains out of scope. Staff manage and reply only in the administrator; the portal is ownership-scoped. Package update server points at GitHub `joomla-devart-support` root `update.xml`. Portal owners set priority and may close/reopen. Ticket statuses are open/closed only. Site + Action Log language packs covered for all 15 locales. Guest tickets are not implemented; prerequisites are documented.

# Verified Package
`pkg_devartsupport_v1.0.42.zip`

# Verified SHA-256
`4e779cf298edc8f13fa0a558149af930484bb19de862c93b8470cd0759f9358e`

# Verified Creation Date
`2026-11-04`

# Last Completed
`1.0.42` (includes 1.0.39–1.0.41): Action Log plugin; portal attachments with allowed-types setting and FE enable switch; full site/Action Log language pass; private storage relocates outside `DOCUMENT_ROOT` on subdirectory hosts.

Local Herd install and smoke QA passed. User confirmed the same ZIP on VPS (`https://dev.devart.gr/demo3`).

# Current Milestone
Closed: `1.0.42`.

# Next Milestone
Final public release `1.1.0`, or guest tickets after approving `docs/guest-tickets-prerequisites.md`. Assignment, modules and live chat remain out of scope for now.

# Pending
- Guest tickets only after prerequisite confirmation
- Final `1.1.0` release packaging when ready
