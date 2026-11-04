# DevArt Support — Current State

## Development Source

Current manifest version:

`1.0.0`

Package:

`pkg_devartsupport`

Extensions:

- `com_devartsupport`
- `plg_privacy_devartsupport`
- `plg_actionlog_devartsupport`
- `plg_task_devartsupport`

## Implemented Features

Confirmed from repository source only:

- Installable package wrapper with GitHub update server (`update.xml`)
- Administrator staff desk and registered-user site portal
- Dashboard with Tickets, Categories and Tools hubs
- Ticket list, create, edit, reply, trash/restore/empty-trash (administrator)
- Site portal: ownership-scoped list, create, view and reply
- Statuses `open` and `closed` only; portal owners set priority and may close/reopen
- `published` for active/trash
- Priorities `low`, `normal`, `high` and `urgent`
- Administrator category CRUD; category required on tickets
- Administrator tickets filters with Clear and autosubmit
- Private ticket attachments (administrator upload/download/delete)
- Portal attachments when Options enable admin + portal switches (owner upload on new/reply; owner download; no portal delete)
- Configurable allowed extensions (subset of built-in safe MIME catalog, including RAR)
- Attachment storage outside the Joomla web root (relocates beside outermost DOCUMENT_ROOT on subdirectory hosts) with legacy migration
- Configurable private folder name under the private storage root (safe name only; migrate on rename)
- Optional attachment retention (default off) with `plg_task_devartsupport` daily Scheduler purge and UI warnings
- Upload MIME validation via `finfo`, forced download disposition, no-store cache headers
- Attachment downloads require POST with a CSRF token
- Uninstall preserves ticket tables and private attachment files
- Deleted Joomla users fall back safely in the message thread
- Staff email notifications for new tickets, replies and status changes
- Optional requester email when staff reply or change status (login link only; default off)
- Auto-create Registered-access site portal menu item on install/update when missing
- Portal guests redirect to login with return URL; portal pages are no-cache / noindex
- Full Email Settings transport options (global/SMTP/Sendmail/PHP mail) plus test email
- Attachment options and per-category disable override
- Tools administrator submenu with Email Settings and Options hubs
- Tables `#__devartsupport_tickets`, `#__devartsupport_messages`, `#__devartsupport_categories` and `#__devartsupport_attachments`
- Fifteen administrator, site and package language packs (site + Action Log fully translated)
- DevArt header banners on submenu pages, footer and red active submenu (parent link keeps Atum contrast)
- Bulk status updates without N+1 ticket loads; list indexes for category/status filters
- Ticket threads load in pages (100, expandable to 1000) with Load older messages
- Optional author-copy email of a portal user’s own reply (not staff admin replies)
- Configurable portal create/reply hourly rate limits (Options → Frontend)
- Admin stale-form guard (`expected_modified`) and portal status-safe reopen on reply
- Mail delivery Options: Immediate (default) or Queued (`#__devartsupport_mail_queue` + Scheduler `devartsupport.send_mail`)
- Tools attachment storage health without exposing absolute paths
- Joomla Privacy plugin export/remove for tickets, messages and attachments (`user_id` / `requester_email`)
- Action Log plugin for ticket and attachment mutations (admin + portal)
- Registered portal ACL defaults for `core.create` / `core.edit` when unset
- Staff manage/reply other users’ tickets only in administrator (portal is ownership-scoped)

Versioning: stay on `1.0.0` until the first public release is declared. Former internal builds `1.0.1`–`1.0.46` are archived locally.

Previous VPS-verified `1.0.0` (pre mail-queue): SHA-256 `0c150f9bab0c63f20f6e0fe220d31431a9371e8f45ce97e06910474b344acbb9`. Latest package pending VPS confirmation.

See `docs/operations.md` for Cloudflare bypass, versioning, mail queue, and Scheduler notes.

Next optional structural milestone: extract `PortalTicketService` from `TicketService` (core/pro prep).

## Known Limitations

- No guest tickets (deferred; see `docs/guest-tickets-prerequisites.md`)
- No assignment
- No modules
- No live chat yet (product decision pending: prefer Pro-later or separate product — not Core ticket desk)
- Uninstall preserves ticket tables and private files by design; manual cleanup if desired

## Repository Tooling

- Validation and build tooling follows the package manifest rather than a
  product-specific configuration file.
- Build output belongs under `builds/` and does not overwrite an existing
  build. Rebuilds of the same `1.0.0` move the previous ZIP into `archive/` first.
- The manifests require PHP `8.3.0` as the DevArt minimum.
- Joomla 6 is the current DevArt production series.
