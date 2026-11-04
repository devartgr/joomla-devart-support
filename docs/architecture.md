# DevArt Support — Architecture

## Repository Layout

`source/` is the only development source:

- `source/package/` — outer Joomla package manifest and package language files.
- `source/extensions/component/com_devartsupport/` — administrator and site component source and media assets.
- `source/extensions/modules/` — intentionally empty.
- `source/extensions/plugins/` — privacy plugin and related extensions.

Generated packages belong only under `builds/`.

## Package Composition

Package: `pkg_devartsupport`

Declared extensions:

- `com_devartsupport`
- `plg_privacy_devartsupport`

The package manifest is the source of truth for the package name, version,
inner archive names, and inner archive folder.

The build script derives the outer package filename from the package manifest
filename:

`pkg_devartsupport_v1.0.26.zip`

## Product Architecture

DevArt Support is a Joomla 6 ticket desk with a registered-user site portal and
an administrator staff desk.

- Namespace: `Devart\Component\Devartsupport`
- Bootstrap: `admin/services/provider.php` and `site/services/provider.php` with
  `DevartsupportComponent`, `MVCFactory`, `ComponentDispatcherFactory`, and site
  `RouterFactory`
- Ticket requests follow Controller → TicketService → Storage
- Site portal (ownership-scoped): list, create, view and reply for the logged-in
  user’s tickets only (`user_id` match). No FE attachments or guest tickets.
- Portal links keep the component menu Itemid (`PortalRoute`) so SEF does not
  fall back to the home page
- Portal ACL: every portal screen requires login plus `core.create` or
  `core.edit`; those actions are granted to Registered on install when unset
- Menu item type strings live in administrator `.sys.ini` packs
- Default administrator view: Dashboard with Tickets, Categories and Tools hubs
- Tickets view: filtered list with pagination; portal-linked tickets show a badge
- Ticket view: create/edit form, message thread and attachments (staff only)
- Message authors fall back to “Deleted user” when the Joomla account is missing
- Categories view: title, published state, attachment override, delete
- Tools view: hub for Email Settings and Joomla Options
- Settings view: full email notification and mail-transport configuration with test send
- Statuses: `open`, `closed` (portal owners and staff may reopen)
- Portal owners set priority on create/view, and may close or reopen
- Ticket `published`: `1` active, `-2` trashed (Restore / Empty trash)
- Category is required on create and update
- Priorities: `low`, `normal`, `high`, `urgent`
- Attachments: private storage outside the web root, ACL download via POST + CSRF token, `finfo` MIME checks, no public paths (administrator only)
- Uninstall preserves database tables and private attachment files
- Options: attachment enable/disable, size and count limits, private-storage note,
  Frontend theme/date format, permissions
- Installer does not create a frontend menu item; operators add a menu item with Access Registered (or a narrower group)

Tables:

- `#__devartsupport_tickets`
- `#__devartsupport_messages`
- `#__devartsupport_categories`
- `#__devartsupport_attachments`

Ticket bodies are stored as plain text and escaped on output.

## Source of Truth

- Develop only in `source/`.
- Treat generated archives as disposable output.
- Keep all extension manifest versions identical to the package version.
- Keep every manifest `creationDate` in `YYYY-MM-DD` format.
- Keep every manifest `php_minimum` identical to the DevArt PHP minimum.

## Build and Validation

- `scripts/validate.php` validates manifests, references, versions, naming,
  XML, JSON, PHP syntax, PHP runtime extensions, and Joomla workspace awareness.
- `scripts/build.php` runs validation first, creates the declared inner
  extension ZIPs, the outer package, and its SHA-256 checksum.
- Build tooling does not modify `source/` and does not overwrite an existing
  build.
