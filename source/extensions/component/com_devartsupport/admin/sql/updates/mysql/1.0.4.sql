ALTER TABLE `#__devartsupport_categories`
  ADD COLUMN `attachments_enabled` tinyint unsigned NOT NULL DEFAULT 1 AFTER `published`;
