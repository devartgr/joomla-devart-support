# Changelog

## Template 1.1.0 — 2026-08-04

- Restored generic documentation placeholders for new product bootstrap.
- Added Joomla production-series and workspace awareness to `scripts/validate.php`.
- Added PHP minimum, compatibility targets, and extension checks to validation.
- Added stronger package and extension naming validation.
- Made `scripts/build.php` validation-first with a source integrity guard.
- Preserved the original simple template architecture.

Product change history for bootstrapped repositories begins below this template
section.

## 1.0.0 — 2026-11-04

- Version numbering reset: public-facing line is `1.0.0` (former internal builds were `1.0.1`–`1.0.46`).
- Product state matches the former internal `1.0.46` package (full ticket desk).
- Stay on `1.0.0` for further pre-public changes until the first public release is declared.
- Old disposable ZIPs moved under `archive/`.
- Pre-public hardening: remove stale SQL update scripts; add messages/attachments indexes; normalise legacy statuses; narrow portal field updates; MIME tighten (octet-stream only for RAR); batched attachment purge; migrate storage only on install/update; portal create/reply rate limits; `createQuery()`; dead-code cleanup; Cloudflare/versioning ops docs.
- Author-copy email applies only to portal user replies (not administrator staff replies).
- Installer preflight purges leftover internal SQL update scripts so numbering-reset sites do not re-run `ALTER ADD` (e.g. duplicate `category_id`).
- Installer postflight no longer wipes packaged SQL migrations (safe for future `1.0.1.sql`).
- Attachment retention purge deletes private files before DB rows; failed file deletes keep the row for retry.
- Portal reply reopen uses SQL `IF(status=closed)` so concurrent staff status changes are not overwritten.
- Admin ticket save rejects stale forms via `expected_modified`.
- Configurable portal rate limits in Options (create/reply hourly caps).
- Outbound mail queue with Options delivery mode: Immediate (default) or Queued via Scheduler `devartsupport.send_mail` (test mail remains immediate).
- Split portal ticket logic into `PortalTicketService` (shared helpers in `AbstractTicketService`; staff desk remains `TicketService`).

## Development history (superseded numbering)

## 1.0.46 — 2026-11-04

- Options: configurable private storage folder name (safe name under private/, with migrate).
- Optional attachment retention (default off) with scheduled purge task plugin.
- Upload/list UI warning when retention is enabled.
- New `plg_task_devartsupport` seeds a daily Scheduler task.

## 1.0.45 — 2026-11-04

- Allow RAR in the safe attachment catalog (Options types list).

## 1.0.44 — 2026-11-04

- Create a Registered-access site portal menu item on install/update when none exists.
- Notify requester on staff status-only changes when requester notifications are enabled.
- Document uninstall data retention as intentional (tables + private files kept).

## 1.0.42 — 2026-11-04

- Fixed private attachment storage on subdirectory / multi-demo hosts where `dirname(JPATH_ROOT)/private` still sits inside `DOCUMENT_ROOT` (Herd parent roots and VPS demo folders).

## 1.0.41 — 2026-11-04

- Full site language pass for all 15 packaged locales (portal, priority/close/reopen, attachments).
- Action Log plugin messages translated for all packaged locales.
- Admin Options strings for allowed file types and portal attachments translated.

## 1.0.40 — 2026-11-04

- Portal attachments: upload on new ticket and reply; owner download (POST+CSRF); no portal delete.
- Options: allowed file types (subset of safe catalog) + Allow portal attachments switch.
- Includes Action Log plugin (`plg_actionlog_devartsupport`) and provider fix (no `setDatabase` on ActionLogPlugin).

## 1.0.39 — 2026-11-04

- Added `plg_actionlog_devartsupport` (superseded installable ZIP by 1.0.40 provider fix).

## 1.0.38 — 2026-11-04

- Removed the Pending / Awaiting reply status; tickets are Open or Closed only (install migrates leftover pending → open).
- Admin toolbar no longer has a Pending bulk action.

## 1.0.37 — 2026-11-04

- Portal: priority on new ticket and on ticket view; close / reopen for the owner.
- (Superseded for status model by 1.0.38: Pending removed.)

## 1.0.36 — 2026-11-04

- Portal tickets table: soft row separators instead of a heavy outer frame; light hover rows; media asset version bumped for CSS cache bust.

## 1.0.34 — 2026-11-04

- Portal guest redirect seeds `users.login.form.data` with the return URL (no encoding API in extension source; com_users encodes the login form field).

## 1.0.32 — 2026-11-04

- Package manifest: update server + changelog URL for GitHub `joomla-devart-support` (`update.xml` / `changelog.xml` at repo root).
- Privacy plugin extends `\Joomla\Component\Privacy\Administrator\Plugin\PrivacyPlugin` by FQCN (JED Compatibility).
- Attachment path/name guards renamed; stored-name check avoids hex-looking regex; display-filename sanitize uses `[[:cntrl:]]` (JAMSS false positives).

## 1.0.31 — 2026-11-04

- Ticket threads load 100 messages by default with a “Load older messages” control (up to 1000 per request) on administrator and portal.
- Email Settings: optional author-copy email when someone posts a reply (includes their own message text).
- Removed the redundant Portal badge from the administrator tickets list (tickets are frontend-owned).

## 1.0.30 — 2026-11-04

- Staff notification emails load administrator language strings before `Text::sprintf` (fixes raw `COM_DEVARTSUPPORT_MAIL_*` keys on portal replies).
- Administrator ticket thread styles staff replies with a distinct background and Staff label.
- Tickets list shows a “Needs reply” badge (and row highlight) when the latest message is from the portal owner.
- Thread truncation note clarifies that older messages remain stored beyond the latest page.

## 1.0.29 — 2026-11-04

- Email Settings save clears the Joomla `_system` component cache so `email_notify_requester` (and other switches) persist after redirect.
- Switch fields missing from the posted form no longer reset to No; raw posted switch values are applied after form validate.
- Portal thread styles staff replies with a theme-tinted background and a Staff label.

## 1.0.28 — 2026-11-04

- Email Settings are stored in extension `custom_data` (not `params`), so saving Joomla Options no longer resets Enable email notifications to No.
- Failed Email Settings saves keep the submitted form state (switch stays Yes) and show the validation error (e.g. staff recipients required).

## 1.0.27 — 2026-11-04

- Email Settings: optional `email_notify_requester` (default off) emails the ticket requester when staff reply from the administrator, with a login portal link and no reply body or staff BCC.
- Guests hitting the portal are redirected to the login form with a return URL instead of a bare 403.
- Portal pages set `allowCache(false)` and `robots` noindex/nofollow.

## 1.0.26 — 2026-11-04

- Empty trash no longer fatals when private attachment storage is unavailable on the host.
- Restore / Empty trash toolbar labels use component language strings.
- Site portal hides ticket IDs; Options gains a Frontend tab for colour theme and date format.
- Portal filters add Clear and autosubmit on status change.

## 1.0.25 — 2026-11-04

- Administrator tickets filters: Clear button and autosubmit when Status/Priority/Category/State change.

## 1.0.24 — 2026-11-04

- Category is required for new and updated tickets (administrator and site portal).
- Replaced hard Delete with Joomla-native trash: Trash, Restore, Empty trash (`published = -2`).
- Active ticket lists, dashboard counts and the portal exclude trashed tickets.

## 1.0.23 — 2026-11-04

- Fixed portal open/create links falling back to the home page by keeping the component menu Itemid and adding a site SEF router.
- Fixed menu item type labels (`COM_DEVARTSUPPORT_MENU_TICKETS_*`) by shipping them in administrator `.sys.ini` packs.
- Portal list/view now require login plus `core.create` or `core.edit` (not login alone).
- Portal status badges use theme-safe CSS so status text stays visible.

## 1.0.22 — 2026-11-04

- Added registered-user frontend portal: list, create, view and reply for own tickets only.
- Ownership checks in `TicketService` / `TicketStorage`; staff replies remain administrator-only.
- Site MVC, forms, WebAsset CSS and fifteen site language packs; menu layout for tickets list.
- Installer grants Registered `core.create` / `core.edit` when those rules are still unset.
- Administrator ticket list shows a Portal badge when `user_id` is set.
- No FE attachments, guest tickets or automatic menu item in this milestone.

## 1.0.21 — 2026-10-05

- Added `plg_privacy_devartsupport` for Joomla Privacy export and remove of tickets, messages and attachments.
- Matches privacy subjects by ticket `user_id` and `requester_email`; anonymises subject messages on foreign tickets; deletes subject-owned attachments without exposing filesystem paths.
- Package enables the privacy plugin on install/update. Action Log plugin remains a later milestone.

## 1.0.20 — 2026-10-05

- Fixed Support parent sidebar link contrast: stop forcing `color: inherit` and keep Atum text colour while only the active leaf uses DevArt red.

## 1.0.19 — 2026-10-05

- Shared `BindsIntegerIds` / permission helpers and DI from the component container (`DatabaseInterface`, mail service).
- Bulk status changes use one `findMany` load; emails only for tickets that actually changed.
- Added ticket list indexes for category and status/priority filters.
- Cap ticket thread display to the latest 200 messages.
- Tools shows attachment storage health without exposing absolute paths; stronger outside-web-root checks; remove empty legacy file directories after migration.
- Tighten MIME allowlists for PDF, TXT and ZIP (no `application/octet-stream`).

## 1.0.18 — 2026-10-05

- Removed inline submenu script and CSS declarations for CSP-friendly admin chrome.
- Load admin assets through `joomla.asset.json` WebAsset registry; parent menu no longer matches translated title text.
- Replaced inline `checkAll` / legacy `behavior.*` helpers with WebAsset scripts and listeners.
- Ticket list orders by `last_message_at`, then `id`; filter changes reset pagination; `limit=0` clamps to max.
- Attachment display names keep Unicode; download headers send `filename` and `filename*=UTF-8''`.
- Message bodies are normalised without `strip_tags` (output remains escaped; mail plain text still strips tags).
- Installer schema sync reads the package manifest version; scripts use `DatabaseInterface`.
- Controllers use `$this->checkToken()`; form paths use `JPATH_ADMINISTRATOR`.

## 1.0.17 — 2026-09-12

- Fixed the Email Settings intro text that still said sending was inactive.
- Saving Email Settings with notifications enabled now requires at least one staff recipient.

## 1.0.16 — 2026-09-12

- Expanded Email Settings with mailer mode, SMTP, Sendmail, Reply-To, BCC and test recipient.
- Added a Send test email toolbar action that saves settings then sends a probe message.
- Notification failures now show the transport error detail; missing recipients are reported.
- Mailer uses an explicit configuration registry instead of relying only on silent PHP mail defaults.

## 1.0.15 — 2026-09-12

- Send staff notification emails for new tickets, replies and status changes using Email Settings.
- Mail failures show a warning and never block saving the ticket.
- Uses Joomla `MailerFactoryInterface` (Joomla 6 native mail API).

## 1.0.14 — 2026-09-12

- Message authors resolve safely when the Joomla user account was deleted.
- Deleted authors show as “Deleted user” instead of failing or being mislabelled as Staff.
- Saving a ticket no longer clears a historical `user_id` when that account is gone.

## 1.0.13 — 2026-09-12

- Uninstall preserves ticket tables and private attachment files.
- Removed DROP TABLE and attachment-directory deletion from component and package uninstall scripts.

## 1.0.12 — 2026-09-12

- Attachment downloads require POST with a CSRF token; GET download links are removed.
- Ticket attachment names submit a download form instead of opening a direct URL.

## 1.0.11 — 2026-09-12

- Moved ticket attachment storage outside the Joomla web root and migrate legacy administrator files on update.
- Validated upload MIME types with `finfo` against an extension allowlist.
- Forced download responses to `Content-Disposition: attachment` with `Cache-Control: private, no-store`.
- Made attachment upload and delete rollback-safe when the database transaction fails.
- Removed the misleading Options path field; Options now states that storage is private without exposing filesystem paths.

## 1.0.10 — 2026-09-08

- Replaced the Tools placeholder with hub cards for Email Settings and Joomla Options.
- Added a separate Settings screen for email notification switches, sender and staff recipients.
- Stored email settings in component params without mixing them into the Options form.
- Email sending is not included yet; settings are saved for the next mail milestone.

## 1.0.9 — 2026-09-08

- Limited the DevArt red active colour to the leaf submenu item so the parent DevArt Support link stays unstyled.

## 1.0.8 — 2026-09-08

- Fixed administrator script registration so Joomla no longer treats `defer` as a missing WebAsset dependency.

## 1.0.7 — 2026-09-08

- Loaded administrator CSS and JS with Joomla media URIs so the red submenu, centered headers and hub icons actually apply.
- Constrained submenu headers to 886px and dashboard headers to 1024px, centered and responsive like Article Tools.
- Rendered dashboard hub icons as white glyphs inside the coloured circles.

## 1.0.6 — 2026-09-08

- Applied the DevArt red active colour with the same submenu class used by the other administrator apps.
- Centered the header banners and constrained submenu images to 886px so they scale like Article Tools.
- Removed header banners from ticket and category edit forms. Headers stay on Dashboard, Tickets, Categories and Tools.
- Changed the Tickets hub icon to the list glyph used by the other DevArt dashboards.

## 1.0.5 — 2026-09-08

- Added DevArt header banners on Dashboard, Tickets, Categories and Tools.
- Added a centered footer link to `https://devart.gr` on every administrator view.
- Replaced the dashboard actions with Tickets, Categories and Tools hubs.
- Used the DevArt red active colour on the administrator submenu.

## 1.0.4 — 2026-09-08

- Fixed ticket and attachment links that sent administrators back to the Dashboard.
- Attachment names now open the file instead of navigating inside the ticket form.
- Added a Tools administrator submenu as a placeholder for later utilities.
- Added attachment options: enable/disable, maximum file size, maximum files per save, and the private storage location.
- Categories can disable attachments only when attachments are enabled in Options.

## 1.0.3 — 2026-09-08

- Added private ticket attachments with administrator upload, download and delete.
- Stored files use random names and are served only after access checks.
- Added `#__devartsupport_attachments`.
- Allowed types: PDF, images, Office, ZIP, TXT and CSV. Maximum 5 files and 8 MB each.

## 1.0.2 — 2026-09-08

- Added administrator category management (create, edit, delete).
- Added ticket category assignment and list filter.
- Added ticket priorities `low`, `normal`, `high` and `urgent`.
- Added `#__devartsupport_categories` and ticket `category_id` / `priority` columns.
- Deleting a category leaves tickets uncategorised.

## 1.0.1 — 2026-09-07

- Added administrator ticket list, create/edit form and message thread.
- Added ticket statuses `open`, `pending` and `closed`.
- Added `#__devartsupport_tickets` and `#__devartsupport_messages`.
- Attachments, email, categories and frontend access are not included.

## 1.0.0 — 2026-08-29

- Bootstrapped the DevArt Support package and administrator-only component skeleton.
- Added a dashboard and Options permissions screen.
- Added 15 administrator and package language packs.
- Ticket desk functionality is not included in this release.

Verification status belongs in `PROJECT_STATUS.md` and must record only
confirmed Joomla testing.
