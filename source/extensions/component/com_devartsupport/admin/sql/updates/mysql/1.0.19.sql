-- DevArt Support 1.0.19: list indexes for category/status/priority filters.
ALTER TABLE `#__devartsupport_tickets`
  ADD KEY `idx_category_last_message` (`category_id`, `last_message_at`),
  ADD KEY `idx_status_priority_last_message` (`status`, `priority`, `last_message_at`);
