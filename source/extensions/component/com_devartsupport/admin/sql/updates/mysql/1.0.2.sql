CREATE TABLE IF NOT EXISTS `#__devartsupport_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `published` tinyint unsigned NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_published_ordering` (`published`,`ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `#__devartsupport_tickets`
  ADD COLUMN `category_id` int unsigned NOT NULL DEFAULT 0 AFTER `status`,
  ADD COLUMN `priority` varchar(16) NOT NULL DEFAULT 'normal' AFTER `category_id`,
  ADD KEY `idx_category_id` (`category_id`),
  ADD KEY `idx_priority` (`priority`);
