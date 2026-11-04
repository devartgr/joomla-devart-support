CREATE TABLE IF NOT EXISTS `#__devartsupport_attachments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` int unsigned NOT NULL,
  `filename` varchar(255) NOT NULL DEFAULT '',
  `stored_name` varchar(80) NOT NULL DEFAULT '',
  `mime` varchar(128) NOT NULL DEFAULT 'application/octet-stream',
  `size` int unsigned NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_stored_name` (`stored_name`),
  KEY `idx_ticket_id` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
