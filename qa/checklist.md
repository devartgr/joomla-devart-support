# DevArt Support QA Checklist

## Manual QA Execution Protocol

- [ ] Scope is manual QA in a real Joomla runtime (not only CLI validation).
- [ ] Test both installation paths: clean install and update from supported baseline.
- [ ] Execute checks on administrator and site portal runtime surfaces.
- [ ] Record each item as PASS/FAIL with a short note when failed.
- [ ] Treat any PHP fatal error, uncaught exception, or broken route as FAIL.
- [ ] Do not mark milestone verified before successful VPS QA confirmation.

## Mandatory Manual Runtime Checks (VPS)

- [ ] Install/update completes without runtime errors or missing dependencies.
- [ ] Frontend base page renders without fatal errors and without broken output.
- [ ] Administrator base page (`/administrator?eisodos`) renders without fatal errors.
- [ ] HTTP smoke checks pass for frontend and admin endpoints (2xx/3xx only).
- [ ] Response bodies for smoke endpoints do not contain `Fatal error` or `Joomla\CMS\Error`.
- [ ] Negative-path verification: unauthorized access is blocked (no data leakage, no privileged actions).
- [ ] Permissions and access control rules are enforced correctly.
- [ ] Caching behavior is functionally correct with cache OFF and ON.
- [ ] Logs show no new product-attributable fatal errors after test execution (PHP error log and Joomla error log should not contain new entries attributable to the product).

## Installation

- [ ] Clean installation succeeds
- [ ] Update installation succeeds
- [ ] No missing language-file warning
- [ ] No duplicate administrator menu error
- [ ] Package and every declared extension are installed
- [ ] No module or plugin is installed
- [ ] No undeclared extension is installed
- [ ] Uninstall removes the extension but leaves ticket tables and private attachment files in place
- [ ] Reinstall after uninstall restores access to previously preserved ticket data

## Administrator

- [ ] Dashboard opens with Tickets, Categories and Tools hubs
- [ ] Dashboard, Tickets, Categories and Tools show the centered header banner and the DevArt footer link
- [ ] Ticket and category edit forms show the footer and do not show a header banner
- [ ] The active submenu uses the DevArt red colour
- [ ] Tools opens with Email Settings and Options hubs
- [ ] Email Settings opens, saves and reloads without changing attachment Options
- [ ] Email Settings can use Joomla global mail or custom SMTP/Sendmail/PHP mail
- [ ] Send test email succeeds with a real recipient when transport is configured
- [ ] With email enabled and staff recipients set, creating a ticket sends a staff notification
- [ ] Adding a reply sends a staff notification when that switch is on
- [ ] Changing status sends a staff notification when that switch is on
- [ ] With email disabled, ticket save still works and no notification is required
- [ ] Missing recipients or mail failures show a clear administrator warning
- [ ] Options opens and permissions save and reload correctly
- [ ] Administrator menu shows Dashboard, Tickets, Categories and Tools
- [ ] On Settings, the Tools submenu stays active in red
- [ ] Permissions are enforced for every protected action
- [ ] User input is validated and output is escaped
- [ ] Extension status is reported correctly

## Frontend

- [ ] The Joomla site homepage still renders
- [ ] No DevArt Support frontend menu item is created automatically on install
- [ ] A manual menu item for `com_devartsupport` view `tickets` can be created with Access Registered
- [ ] Guest users cannot open the portal (login / Access Level enforced)
- [ ] Logged-in users without `core.create`/`core.edit` cannot open the portal by URL
- [ ] Registered users see only their own tickets
- [ ] Registered users can create a ticket (subject, category, message) without attachments
- [ ] Registered users can open an existing ticket and reply without being sent to the home page
- [ ] New ticket / open ticket URLs keep the portal menu Itemid under SEF
- [ ] Menu item type picker shows translated “My support tickets” labels (not raw `COM_…` keys)
- [ ] Ticket status is visible in the portal list
- [ ] Registered users can reply to their own tickets; closed tickets reopen on reply
- [ ] Users cannot open another user’s ticket by ID
- [ ] Staff replies and status changes remain administrator-only
- [ ] JavaScript has no console errors on the administrator dashboard or portal pages
- [ ] Browser behavior works without jQuery unless explicitly required

## Performance and Cache

- [ ] Joomla cache OFF
- [ ] Joomla cache ON
- [ ] Administrator dashboard still opens behind full-page cache of the public site
- [ ] Functional behavior works behind Cloudflare
- [ ] No unnecessary database queries, filesystem scans, or remote requests
- [ ] Shared-hosting and high-traffic behavior remains safe

## Multilingual

Supported languages under test:

- English (`en-GB`), Greek (`el-GR`), French (`fr-FR`), German (`de-DE`), Spanish (`es-ES`), Italian (`it-IT`), Brazilian Portuguese (`pt-BR`), Czech (`cs-CZ`), Dutch (`nl-NL`), Polish (`pl-PL`), Russian (`ru-RU`), Ukrainian (`uk-UA`), Japanese (`ja-JP`), Turkish (`tr-TR`), Chinese Simplified (`zh-CN`)

- [ ] Every packaged administrator, site and package language loads
- [ ] Language switching uses the matching translations
- [ ] Missing optional translations fall back safely
- [ ] No untranslated language keys are visible in admin UI (no raw `COM_...` / `PKG_...` strings)
- [ ] All translated strings keep placeholders/format specifiers intact (e.g. `%s`, `%d`, `{name}`) and do not break UI formatting

## PHP Compatibility Matrix

Validate in the target Joomla hosting environment, not only on the CLI used
for repository scripts.

| Check | Expected |
|-------|----------|
| PHP minimum | `8.3.0` in every package and extension manifest |
| Web SAPI PHP version | Meets or exceeds manifest `php_minimum` |
| Required extensions | `json`, `mbstring`, `dom`, `simplexml`, `zlib` |
| Database driver | `mysqli` or `pdo_mysql` |
| Recommended extensions | `curl`, `gd`, `openssl`, `xml`, `zip`, `opcache` |

- [ ] Web-server PHP version meets manifest minimum
- [ ] Required PHP extensions are available in the web SAPI
- [ ] At least one supported MySQL driver is available in the web SAPI
- [ ] Recommended PHP extensions are available or their absence is accepted
- [ ] CLI validation result does not replace Joomla runtime QA

## Product-Specific QA

- [ ] Components → DevArt Support opens the Dashboard
- [ ] Dashboard shows open/pending/closed counts
- [ ] Tickets list opens, filters by search, status, priority, category and Active/Trashed, and paginates
- [ ] Ticket filter dropdowns autosubmit on change; Clear resets filters without requiring Search
- [ ] Categories can be created, edited and deleted
- [ ] Deleting a category leaves related tickets uncategorised
- [ ] A new ticket requires a category (admin and portal); save without category is rejected
- [ ] A new ticket can be created with subject, requester, required category, priority and first message
- [ ] Trash moves tickets out of the active list; Restore returns them; Empty trash permanently deletes only trashed tickets
- [ ] Ticket subject links open the ticket edit form, not the Dashboard
- [ ] Attachment names download the file via POST + CSRF token, not a GET link and not the Dashboard
- [ ] A GET request to `task=ticket.download` is rejected without a valid token
- [ ] Attachments can be uploaded on create and edit when enabled
- [ ] Attachments download only for authorised administrators
- [ ] Downloads use `Content-Disposition: attachment` and are not cached publicly
- [ ] Attachments can be deleted from a ticket
- [ ] Ticket delete also removes attachment files
- [ ] After update, previously uploaded attachments still download (legacy migration)
- [ ] Options shows enable/disable, max size, max files and a private-storage note without absolute paths
- [ ] Options Frontend tab saves portal colour theme and date format
- [ ] Portal list/view do not show ticket IDs; dates follow the Frontend date format
- [ ] Portal status filter autosubmits; Clear resets portal filters
- [ ] Empty trash permanently deletes trashed tickets without fatal attachment-storage errors
- [ ] Trash view Restore / Empty trash buttons show translated labels (not raw keys)
- [ ] A category can disable attachments only when attachments are enabled in Options
- [ ] Ticket screens and Options never show filesystem paths
- [ ] A reply can be added and appears in the thread
- [ ] Messages from a deleted Joomla user show “Deleted user” (or translation) without PHP warnings
- [ ] Saving a ticket that still references a deleted linked user keeps history and does not fatal
- [ ] Status can be changed to open, pending and closed
- [ ] Tickets can be deleted
- [ ] Unauthorized users cannot open ticket content
- [ ] Ticket output is escaped and no raw `COM_DEVARTSUPPORT_*` keys appear
- [ ] Options saves component permissions
- [ ] No frontend component menu item is auto-created; manual Registered menu works
- [ ] Portal tickets appear in the administrator list with the Portal badge when linked to a user
- [ ] After every installable package, local Herd QA is completed before VPS QA
- [ ] The VPS checklist names the exact ZIP and the exact screens to open

## Package Quality

- [ ] PHP syntax validation
- [ ] XML validation
- [ ] Manifest references validation
- [ ] Version consistency
- [ ] PHP minimum is `8.3.0` in every package and extension manifest
- [ ] Every manifest has a valid `creationDate` in `YYYY-MM-DD` format
- [ ] Package inventory contains only declared extensions
- [ ] Inner ZIP paths match the package manifest folder
- [ ] SHA-256 generated
