-- ============================================================
-- EventEase - Safe Migration: Certificates, Rankings & WhatsApp
-- Run AFTER existing eventease.sql is already loaded.
-- Does NOT drop or modify existing tables.
-- ============================================================

USE `eventease`;

-- 1. Add whatsapp_consent column to users (if not exists)
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `whatsapp_consent` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Student consent to receive WhatsApp notifications',
    ADD COLUMN IF NOT EXISTS `whatsapp_number` VARCHAR(20) DEFAULT NULL
        COMMENT 'Explicit WhatsApp number (fallback to phone if null)';

-- 2. Event Rankings Table
CREATE TABLE IF NOT EXISTS `event_rankings` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `event_id`        INT NOT NULL,
    `user_id`         INT NOT NULL,
    `registration_id` INT NOT NULL,
    `rank`            TINYINT NOT NULL COMMENT '1=First, 2=Second, 3=Third',
    `published`       TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=Draft, 1=Published',
    `assigned_by`     INT DEFAULT NULL COMMENT 'Admin user id who assigned rank',
    `assigned_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_event_rank`  (`event_id`, `rank`),
    UNIQUE KEY `unique_event_user`  (`event_id`, `user_id`),
    FOREIGN KEY (`event_id`)        REFERENCES `events`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)         REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`registration_id`) REFERENCES `registrations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Certificates Table
CREATE TABLE IF NOT EXISTS `certificates` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `certificate_id`   VARCHAR(60) NOT NULL UNIQUE
        COMMENT 'Globally unique human-readable certificate ID e.g. CERT-2026-XXXXXX',
    `user_id`          INT NOT NULL,
    `event_id`         INT NOT NULL,
    `registration_id`  INT NOT NULL,
    `ranking_id`       INT DEFAULT NULL
        COMMENT 'Populated if this is a rank/achievement certificate',
    `cert_type`        ENUM('participation','achievement') NOT NULL DEFAULT 'participation',
    `rank`             TINYINT DEFAULT NULL COMMENT '1/2/3 for achievement certs',
    `college_name`     VARCHAR(200) NOT NULL DEFAULT 'EventEase College',
    `organizer_name`   VARCHAR(150) DEFAULT NULL,
    `signatory_name`   VARCHAR(150) DEFAULT NULL,
    `signatory_title`  VARCHAR(150) DEFAULT NULL,
    `issued_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `status`           ENUM('issued','revoked') NOT NULL DEFAULT 'issued',
    `notified_whatsapp` TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY `unique_user_event_type` (`user_id`, `event_id`, `cert_type`),
    FOREIGN KEY (`user_id`)          REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`event_id`)         REFERENCES `events`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`registration_id`)  REFERENCES `registrations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`ranking_id`)       REFERENCES `event_rankings`(`id`) ON DELETE SET NULL,
    INDEX `idx_cert_event`           (`event_id`),
    INDEX `idx_cert_user`            (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. WhatsApp Notification Log
CREATE TABLE IF NOT EXISTS `whatsapp_notifications` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`          INT NOT NULL,
    `event_id`         INT DEFAULT NULL,
    `certificate_id`   INT DEFAULT NULL,
    `notification_type` ENUM('registration','participation_cert','achievement_cert') NOT NULL,
    `recipient_phone`  VARCHAR(30) NOT NULL,
    `message_template` VARCHAR(100) DEFAULT NULL,
    `message_body`     TEXT DEFAULT NULL,
    `status`           ENUM('pending','sent','delivered','failed','skipped') NOT NULL DEFAULT 'pending',
    `provider_response` TEXT DEFAULT NULL COMMENT 'Raw API response for debugging',
    `provider_message_id` VARCHAR(150) DEFAULT NULL,
    `retry_count`      TINYINT NOT NULL DEFAULT 0,
    `last_attempted_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_notification` (`user_id`, `event_id`, `notification_type`),
    FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_wa_status`    (`status`),
    INDEX `idx_wa_event`     (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Certificate Settings Table (admin-configurable per event)
CREATE TABLE IF NOT EXISTS `certificate_settings` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `event_id`          INT NOT NULL UNIQUE,
    `college_name`      VARCHAR(200) NOT NULL DEFAULT 'EventEase College',
    `organizer_name`    VARCHAR(150) DEFAULT NULL,
    `signatory_name`    VARCHAR(150) DEFAULT NULL,
    `signatory_title`   VARCHAR(150) DEFAULT 'Event Coordinator',
    `enable_participation` TINYINT(1) NOT NULL DEFAULT 1,
    `enable_ranking`    TINYINT(1) NOT NULL DEFAULT 1,
    `results_published` TINYINT(1) NOT NULL DEFAULT 0,
    `certs_issued`      TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
