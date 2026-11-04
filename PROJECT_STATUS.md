# Project
DevArt Support — Joomla 6 ticket desk with registered-user site portal and administrator staff desk.

# Current Version
`1.0.38`

# Package
`pkg_devartsupport`

# Extension Inventory
- `com_devartsupport`
- `plg_privacy_devartsupport`

# Repository Status
Registered-user site portal and administrator staff desk are in place: tickets, categories, trash, Frontend Options, private attachments (admin), Privacy plugin, staff/requester/author-copy emails, needs-reply list badge, load-older message threads, and portal/admin staff message styling. Joomla 6+ and PHP 8.3+ only. Versions stay on `1.0.x` until an explicit final `1.1.0` release. Live chat remains out of scope. Staff manage and reply only in the administrator; the portal is ownership-scoped. Package update server points at GitHub `joomla-devart-support` root `update.xml`. Portal guest redirect uses com_users login form user state (no encoding API in extension source). Portal tickets table uses soft row separators. Portal owners set priority and may close/reopen. Ticket statuses are open/closed only.

# Verified Package
`pkg_devartsupport_v1.0.38.zip`

# Verified SHA-256
`fec41f9a08d93e09f99b8563f0d88b762939578d33c491040e1bd30a3ce8bd04`

# Verified Creation Date
`2026-11-04`

# Last Completed
`1.0.38` (includes 1.0.37): portal priority + close/reopen; removed Pending status (open/closed only; install migrates legacy pending → open); soft portal table frame from 1.0.36.

Local Herd install and smoke QA passed. User confirmed the same ZIP on VPS (`https://dev.devart.gr/demo3`).

# Current Milestone
Closed: `1.0.38`.

# Next Milestone
Final public release `1.1.0`. Before release: full language pass for all 15 packaged locales (`en-GB` source of truth; fill non-English gaps for portal priority/close/reopen and any other recent keys still in English).

# Pending
Language coverage audit + translations for the 15 shipped locales before `1.1.0`.
