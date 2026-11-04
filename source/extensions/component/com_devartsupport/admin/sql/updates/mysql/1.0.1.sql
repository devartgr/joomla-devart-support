CREATE TABLE IF NOT EXISTS `#__devartsupport_tickets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) NOT NULL DEFAULT '',
  `status` varchar(16) NOT NULL DEFAULT 'open',
  `user_id` int unsigned NOT NULL DEFAULT 0,
  `requester_name` varchar(255) NOT NULL DEFAULT '',
  `requester_email` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  `last_message_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_last` (`status`,`last_message_at`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_last_message` (`last_message_at`),
  KEY `idx_requester_email` (`requester_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__devartsupport_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL DEFAULT 0,
  `body` mediumtext NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ticket_created` (`ticket_id`,`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
