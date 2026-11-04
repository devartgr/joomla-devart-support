CREATE TABLE IF NOT EXISTS `#__devartsupport_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `published` tinyint unsigned NOT NULL DEFAULT 1,
  `attachments_enabled` tinyint unsigned NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_published_ordering` (`published`,`ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__devartsupport_tickets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) NOT NULL DEFAULT '',
  `status` varchar(16) NOT NULL DEFAULT 'open',
  `published` tinyint NOT NULL DEFAULT 1,
  `category_id` int unsigned NOT NULL DEFAULT 0,
  `priority` varchar(16) NOT NULL DEFAULT 'normal',
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
  KEY `idx_published` (`published`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_category_last_message` (`category_id`,`last_message_at`),
  KEY `idx_status_priority_last_message` (`status`,`priority`,`last_message_at`),
  KEY `idx_priority` (`priority`),
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
  KEY `idx_ticket_created` (`ticket_id`,`created`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

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
  KEY `idx_ticket_id` (`ticket_id`),
  KEY `idx_created` (`created`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
