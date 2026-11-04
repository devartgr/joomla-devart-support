# DevArt Support — Operations Notes

## Versioning

- Public-facing package version is `1.0.0`.
- Internal builds formerly numbered `1.0.1`–`1.0.46` were development-only.
- Sites that still show those internal versions must install the current ZIP manually; Joomla’s update server will not offer a lower version number as an upgrade.
- Stay on `1.0.0` until the first public release is explicitly declared. Rebuilds of the same version move the previous `builds/` ZIP into `archive/` first.

## Cloudflare / Full Page Cache

Origin responses for the portal call `allowCache(false)` and set `noindex`. Attachment downloads send `private, no-store, no-cache`.

A Cloudflare “Cache Everything” rule (or Edge Cache TTL override) can still store responses that ignore origin headers. Configure:

- Bypass cache for the support portal menu path and SEF URLs under `com_devartsupport`
- Bypass for attachment download POSTs and administrator support paths
- Keep normal caching for CSS/JS and public site pages

VPS QA checklist: with cache ON, two different logged-in users must not see each other’s portal pages; guests must not receive a cached authenticated view.

## Scheduled attachment purge

Plugin `plg_task_devartsupport` seeds one daily Scheduler task (`devartsupport.purge_attachments`) on install/update.

Component params may contain the internal marker `scheduler_seeded_attachment_purge` so the task is not duplicated. Retention stays off until enabled under Options → Attachments.

## Uninstall SQL

`admin/sql/uninstall.mysql.utf8.sql` is intentionally a no-op (`SELECT 1`). Ticket tables and private files are preserved on uninstall by design. Manual cleanup is required if data must be removed.
