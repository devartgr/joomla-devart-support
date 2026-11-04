# DevArt Support — Operations Notes

## Versioning

- Public-facing package version is `1.0.0`.
- Internal builds formerly numbered `1.0.1`–`1.0.46` were development-only.
- Sites that still show those internal versions must install the current ZIP manually; Joomla’s update server will not offer a lower version number as an upgrade.
- The installer **preflight** deletes leftover `administrator/components/com_devartsupport/sql/updates/mysql/*.sql` files before the new package is copied, so old internal `ALTER ADD` scripts cannot fire against an already-migrated database. Postflight does **not** wipe packaged migrations (e.g. future `1.0.1.sql`).
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

Purge deletes private files first; database rows are removed only after the file is gone (or was already missing). Failed file deletes leave the row for the next run.

## Portal rate limits

Options → Frontend: `portal_create_hourly_cap` (default 10, range 1–100) and `portal_reply_hourly_cap` (default 30, range 1–300) per logged-in portal user per rolling hour.

## Mail delivery

Options → Mail delivery (default **Immediate**): ticket notifications send in the same request — best for most small sites.

**Queued** mode writes notifications to `#__devartsupport_mail_queue` and the Scheduler task `devartsupport.send_mail` (seeded every 5 minutes) delivers them — better under heavy concurrent traffic or tight SMTP quotas. Ticket saves still never fail because of SMTP errors (fail-soft).

- Email Settings **test mail** always sends immediately.
- When Queued: Options show batch size (default 20) and max attempts (default 5); sent rows older than 7 days are purged by the task; Tools shows pending count.
- Queued mode needs frequent Scheduled Tasks / cron.

## Uninstall SQL

`admin/sql/uninstall.mysql.utf8.sql` is intentionally a no-op (`SELECT 1`). Ticket tables and private files are preserved on uninstall by design. Manual cleanup is required if data must be removed.
