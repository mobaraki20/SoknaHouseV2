CREATE TABLE IF NOT EXISTS `{{prefix}}users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(80) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(120) NOT NULL,
  `role` ENUM('admin','reservation','viewer') NOT NULL DEFAULT 'reservation',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}settings` (
  `key` VARCHAR(100) NOT NULL,
  `value` LONGTEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}features` (
  `feature_key` VARCHAR(64) NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`feature_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}rooms` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `standard_capacity` SMALLINT UNSIGNED NOT NULL DEFAULT 2,
  `max_capacity` SMALLINT UNSIGNED NOT NULL DEFAULT 2,
  `base_rate` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `extra_person_rate` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `double_beds` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `single_beds` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `private_bathroom` TINYINT(1) NOT NULL DEFAULT 0,
  `public_profile_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `public_location_label` VARCHAR(120) NULL,
  `public_location_visible` TINYINT(1) NOT NULL DEFAULT 0,
  `available_from` DATE NULL,
  `retired_from` DATE NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_room_slug` (`slug`),
  KEY `idx_room_active_sort` (`active`,`sort_order`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}amenities` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope` ENUM('room','property') NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `public_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_amenity_scope_name` (`scope`,`name`),
  KEY `idx_amenity_scope_active_sort` (`scope`,`active`,`sort_order`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}room_amenities` (
  `room_id` BIGINT UNSIGNED NOT NULL,
  `amenity_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`room_id`,`amenity_id`),
  KEY `idx_room_amenity_amenity` (`amenity_id`,`room_id`),
  CONSTRAINT `fk_room_amenity_room` FOREIGN KEY (`room_id`) REFERENCES `{{prefix}}rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_room_amenity_amenity` FOREIGN KEY (`amenity_id`) REFERENCES `{{prefix}}amenities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}media_assets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `path` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NULL,
  `mime_type` VARCHAR(80) NULL,
  `file_size` BIGINT UNSIGNED NULL,
  `width` INT UNSIGNED NULL,
  `height` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_media_path` (`path`),
  KEY `idx_media_created` (`created_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}room_images` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_id` BIGINT UNSIGNED NOT NULL,
  `media_id` BIGINT UNSIGNED NOT NULL,
  `is_cover` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_room_media` (`room_id`,`media_id`),
  KEY `idx_room_images_room_sort` (`room_id`,`is_cover`,`sort_order`,`id`),
  KEY `idx_room_images_media` (`media_id`,`room_id`),
  CONSTRAINT `fk_room_image_room` FOREIGN KEY (`room_id`) REFERENCES `{{prefix}}rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_room_image_media` FOREIGN KEY (`media_id`) REFERENCES `{{prefix}}media_assets` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `{{prefix}}room_rate_overrides` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_id` BIGINT UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `rate_mode` ENUM('fixed','increase','decrease') NOT NULL DEFAULT 'fixed',
  `base_rate` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `extra_person_rate` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `label` VARCHAR(160) NULL,
  `batch_key` VARCHAR(32) NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_override_room_dates` (`room_id`,`start_date`,`end_date`),
  KEY `idx_override_batch` (`batch_key`),
  CONSTRAINT `fk_override_room` FOREIGN KEY (`room_id`) REFERENCES `{{prefix}}rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_override_user` FOREIGN KEY (`created_by`) REFERENCES `{{prefix}}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}guests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `display_name` VARCHAR(160) NOT NULL,
  `phone_normalized` VARCHAR(30) NULL,
  `whatsapp_normalized` VARCHAR(30) NULL,
  `city` VARCHAR(120) NULL,
  `tags` VARCHAR(500) NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_guest_phone` (`phone_normalized`),
  KEY `idx_guest_name` (`display_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}reservations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(40) NULL,
  `client_token` VARCHAR(64) NULL,
  `guest_id` BIGINT UNSIGNED NOT NULL,
  `guest_name_snapshot` VARCHAR(160) NOT NULL,
  `phone_snapshot` VARCHAR(30) NULL,
  `status` ENUM('hold','confirmed','cancelled') NOT NULL DEFAULT 'hold',
  `lodging_amount` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `final_adjustment` BIGINT NOT NULL DEFAULT 0,
  `discount_amount` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `discount_note` VARCHAR(255) NULL,
  `notes` TEXT NULL,
  `lock_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `cancellation_reason` ENUM('guest_cancelled','guest_no_show','property_cancelled','entry_error','other') NULL,
  `cancellation_note` VARCHAR(500) NULL,
  `cancellation_penalty` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `cancelled_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cancelled_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_res_code` (`code`),
  UNIQUE KEY `uniq_client_token` (`client_token`),
  KEY `idx_res_guest` (`guest_id`),
  KEY `idx_res_status` (`status`),
  CONSTRAINT `fk_res_guest` FOREIGN KEY (`guest_id`) REFERENCES `{{prefix}}guests` (`id`),
  CONSTRAINT `fk_res_creator` FOREIGN KEY (`created_by`) REFERENCES `{{prefix}}users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_res_updater` FOREIGN KEY (`updated_by`) REFERENCES `{{prefix}}users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_res_canceller` FOREIGN KEY (`cancelled_by`) REFERENCES `{{prefix}}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}reservation_rooms` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reservation_id` BIGINT UNSIGNED NOT NULL,
  `room_id` BIGINT UNSIGNED NOT NULL,
  `room_name_snapshot` VARCHAR(100) NOT NULL,
  `check_in` DATE NOT NULL,
  `check_out` DATE NOT NULL,
  `guests_count` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `calculated_subtotal` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `subtotal` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `pricing_snapshot` LONGTEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rr_room_dates` (`room_id`,`check_in`,`check_out`),
  KEY `idx_rr_res` (`reservation_id`),
  CONSTRAINT `fk_rr_res` FOREIGN KEY (`reservation_id`) REFERENCES `{{prefix}}reservations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rr_room` FOREIGN KEY (`room_id`) REFERENCES `{{prefix}}rooms` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}room_blocks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_id` BIGINT UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_block_room_dates` (`room_id`,`start_date`,`end_date`),
  CONSTRAINT `fk_block_room` FOREIGN KEY (`room_id`) REFERENCES `{{prefix}}rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_block_user` FOREIGN KEY (`created_by`) REFERENCES `{{prefix}}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reservation_id` BIGINT UNSIGNED NOT NULL,
  `amount` BIGINT UNSIGNED NOT NULL,
  `kind` ENUM('payment','refund') NOT NULL DEFAULT 'payment',
  `method` ENUM('cash','card','transfer','other') NOT NULL DEFAULT 'transfer',
  `reference` VARCHAR(160) NULL,
  `note` VARCHAR(500) NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `voided_at` DATETIME NULL,
  `voided_by` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payment_res` (`reservation_id`,`created_at`),
  CONSTRAINT `fk_payment_res` FOREIGN KEY (`reservation_id`) REFERENCES `{{prefix}}reservations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_user` FOREIGN KEY (`created_by`) REFERENCES `{{prefix}}users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payment_void_user` FOREIGN KEY (`voided_by`) REFERENCES `{{prefix}}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Review-only schema candidate: durable, cross-session financial commit tokens.
CREATE TABLE IF NOT EXISTS `{{prefix}}financial_commit_keys` (
  `client_token` CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `fingerprint` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `operation_type` ENUM('payment','refund','service_add') NOT NULL,
  `reservation_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`client_token`),
  KEY `idx_financial_commit_reservation` (`reservation_id`,`created_at`),
  CONSTRAINT `fk_financial_commit_reservation` FOREIGN KEY (`reservation_id`)
    REFERENCES `{{prefix}}reservations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}extra_services` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category` ENUM('tour','transfer','food','experience','rental','other') NOT NULL DEFAULT 'other',
  `name` VARCHAR(160) NOT NULL,
  `pricing_type` ENUM('fixed','per_person','quantity') NOT NULL DEFAULT 'fixed',
  `default_price` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `supplier_cost` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `provider_name` VARCHAR(160) NULL,
  `provider_phone` VARCHAR(40) NULL,
  `is_timed` TINYINT(1) NOT NULL DEFAULT 0,
  `capacity` SMALLINT UNSIGNED NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service_active_sort` (`active`,`sort_order`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}service_images` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` BIGINT UNSIGNED NOT NULL,
  `media_id` BIGINT UNSIGNED NOT NULL,
  `is_cover` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_service_image_media` (`service_id`,`media_id`),
  KEY `idx_service_images_service_sort` (`service_id`,`is_cover`,`sort_order`,`id`),
  KEY `idx_service_images_media` (`media_id`,`service_id`),
  CONSTRAINT `fk_service_image_service` FOREIGN KEY (`service_id`) REFERENCES `{{prefix}}extra_services` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_service_image_media` FOREIGN KEY (`media_id`) REFERENCES `{{prefix}}media_assets` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}reservation_services` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reservation_id` BIGINT UNSIGNED NOT NULL,
  `service_id` BIGINT UNSIGNED NULL,
  `name_snapshot` VARCHAR(160) NOT NULL,
  `pricing_type_snapshot` ENUM('fixed','per_person','quantity') NOT NULL DEFAULT 'fixed',
  `unit_price` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `quantity` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `people_count` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `total` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `supplier_cost` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `provider_snapshot` VARCHAR(160) NULL,
  `status` ENUM('requested','confirmed','done','cancelled') NOT NULL DEFAULT 'requested',
  `service_date` DATE NULL,
  `service_time` TIME NULL,
  `notes` VARCHAR(500) NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service_res` (`reservation_id`,`status`),
  KEY `idx_service_date` (`service_date`,`status`),
  CONSTRAINT `fk_service_res` FOREIGN KEY (`reservation_id`) REFERENCES `{{prefix}}reservations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_service_catalog` FOREIGN KEY (`service_id`) REFERENCES `{{prefix}}extra_services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_service_user` FOREIGN KEY (`created_by`) REFERENCES `{{prefix}}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}message_templates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_key` VARCHAR(80) NOT NULL,
  `title` VARCHAR(160) NOT NULL,
  `body` TEXT NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_template_key` (`template_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}waitlist` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `guest_name` VARCHAR(160) NOT NULL,
  `phone_normalized` VARCHAR(30) NULL,
  `check_in` DATE NOT NULL,
  `check_out` DATE NOT NULL,
  `guests_count` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `notes` VARCHAR(500) NULL,
  `status` ENUM('open','converted','closed') NOT NULL DEFAULT 'open',
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_waitlist_dates` (`status`,`check_in`,`check_out`),
  CONSTRAINT `fk_waitlist_user` FOREIGN KEY (`created_by`) REFERENCES `{{prefix}}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}reminders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reservation_id` BIGINT UNSIGNED NOT NULL,
  `due_at` DATETIME NOT NULL,
  `note` VARCHAR(500) NOT NULL,
  `done_at` DATETIME NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reminder_due` (`done_at`,`due_at`),
  CONSTRAINT `fk_reminder_res` FOREIGN KEY (`reservation_id`) REFERENCES `{{prefix}}reservations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reminder_user` FOREIGN KEY (`created_by`) REFERENCES `{{prefix}}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}cafe_charges` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reservation_id` BIGINT UNSIGNED NOT NULL,
  `external_order_id` VARCHAR(120) NOT NULL,
  `transaction_id` VARCHAR(64) NOT NULL,
  `invoice_number` VARCHAR(80) NOT NULL,
  `ordered_at` DATETIME NOT NULL,
  `amount` BIGINT UNSIGNED NOT NULL,
  `subtotal_amount` BIGINT UNSIGNED NOT NULL,
  `discount_amount` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `currency` VARCHAR(8) NOT NULL,
  `invoice_version` SMALLINT UNSIGNED NOT NULL,
  `table_name` VARCHAR(120) NOT NULL,
  `invoice_snapshot_json` JSON NOT NULL,
  `payload_hash` CHAR(64) NOT NULL,
  `status` ENUM('posted','voided') NOT NULL DEFAULT 'posted',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `voided_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cafe_order` (`external_order_id`),
  UNIQUE KEY `uniq_cafe_transaction` (`transaction_id`),
  KEY `idx_cafe_res` (`reservation_id`,`ordered_at`),
  CONSTRAINT `fk_cafe_res` FOREIGN KEY (`reservation_id`) REFERENCES `{{prefix}}reservations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}cafe_charge_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cafe_charge_id` BIGINT UNSIGNED NOT NULL,
  `item_name` VARCHAR(180) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  `unit_price` BIGINT UNSIGNED NOT NULL,
  `line_total` BIGINT UNSIGNED NOT NULL,
  `item_note` VARCHAR(500) NULL,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_cafe_item_charge` (`cafe_charge_id`,`sort_order`,`id`),
  CONSTRAINT `fk_cafe_item_charge` FOREIGN KEY (`cafe_charge_id`) REFERENCES `{{prefix}}cafe_charges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}cafe_charge_voids` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cafe_charge_id` BIGINT UNSIGNED NOT NULL,
  `transaction_id` VARCHAR(64) NOT NULL,
  `reason` VARCHAR(500) NOT NULL,
  `requested_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cafe_void_charge` (`cafe_charge_id`),
  UNIQUE KEY `uniq_cafe_void_transaction` (`transaction_id`),
  CONSTRAINT `fk_cafe_void_charge` FOREIGN KEY (`cafe_charge_id`) REFERENCES `{{prefix}}cafe_charges` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}cafe_api_request_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `action` ENUM('charge','void') NOT NULL,
  `external_order_id` VARCHAR(120) NOT NULL,
  `reservation_code` VARCHAR(40) NULL,
  `payload_hash` CHAR(64) NULL,
  `http_status` SMALLINT UNSIGNED NOT NULL,
  `result_code` VARCHAR(80) NOT NULL,
  `transaction_id` VARCHAR(64) NULL,
  `idempotent` TINYINT(1) NOT NULL DEFAULT 0,
  `tracking_id` VARCHAR(80) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cafe_api_external` (`external_order_id`,`created_at`),
  KEY `idx_cafe_api_result` (`action`,`result_code`,`created_at`),
  KEY `idx_cafe_api_tracking` (`tracking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(80) NULL,
  `ip_address` VARCHAR(64) NOT NULL,
  `successful` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_ip_time` (`ip_address`,`created_at`),
  KEY `idx_login_user_time` (`username`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}remember_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `selector` CHAR(24) NOT NULL,
  `validator_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `user_agent_hash` CHAR(64) NULL,
  `last_used_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_remember_selector` (`selector`),
  KEY `idx_remember_user` (`user_id`),
  CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(80) NOT NULL,
  `entity_type` VARCHAR(80) NOT NULL,
  `entity_id` BIGINT UNSIGNED NULL,
  `payload_json` LONGTEXT NULL,
  `ip_address` VARCHAR(64) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`,`created_at`),
  KEY `idx_audit_user` (`user_id`,`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `{{prefix}}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}schema_migrations` (
  `version` VARCHAR(40) NOT NULL,
  `checksum` CHAR(64) NOT NULL,
  `operation_id` VARCHAR(80) NULL,
  `status` ENUM('success','failed') NOT NULL DEFAULT 'success',
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}system_updates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `operation_id` VARCHAR(80) NOT NULL,
  `from_version` VARCHAR(40) NOT NULL,
  `to_version` VARCHAR(40) NOT NULL,
  `status` VARCHAR(40) NOT NULL,
  `message` VARCHAR(500) NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_update_operation` (`operation_id`),
  KEY `idx_update_created` (`created_at`),
  CONSTRAINT `fk_update_user` FOREIGN KEY (`created_by`) REFERENCES `{{prefix}}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 2.6.0 room/property public profile defaults.
INSERT IGNORE INTO `{{prefix}}amenities` (`scope`,`name`,`active`,`public_visible`,`sort_order`) VALUES
('room','یخچال',1,1,10),('room','سشوار',1,1,20),('room','کولر اسپلیت',1,1,30),('room','بخاری',1,1,40),
('property','صبحانه همراه اقامت',1,1,10),('property','وای‌فای رایگان',1,1,20),('property','حیاط مشترک',1,1,30),('property','دسترسی به کافه',1,1,40);
INSERT IGNORE INTO `{{prefix}}room_amenities` (`room_id`,`amenity_id`)
SELECT r.id,a.id FROM `{{prefix}}rooms` r JOIN `{{prefix}}amenities` a ON a.scope='room' AND a.active=1;
