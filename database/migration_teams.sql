-- EventEase - Team Registration Migration
-- Adds team support to events and registrations tables

USE `eventease`;

-- 1. Add event_type, min_team_size, max_team_size to events if not exists
ALTER TABLE `events` 
  ADD COLUMN IF NOT EXISTS `event_type` ENUM('individual', 'team', 'both') NOT NULL DEFAULT 'both' AFTER `category`,
  ADD COLUMN IF NOT EXISTS `min_team_size` INT NOT NULL DEFAULT 1 AFTER `event_type`,
  ADD COLUMN IF NOT EXISTS `max_team_size` INT NOT NULL DEFAULT 4 AFTER `min_team_size`;

-- 2. Add team fields to registrations if not exists
ALTER TABLE `registrations`
  ADD COLUMN IF NOT EXISTS `registration_type` ENUM('individual', 'team') NOT NULL DEFAULT 'individual' AFTER `event_id`,
  ADD COLUMN IF NOT EXISTS `team_name` VARCHAR(100) NULL AFTER `registration_type`,
  ADD COLUMN IF NOT EXISTS `team_members` LONGTEXT NULL AFTER `team_name`,
  ADD COLUMN IF NOT EXISTS `team_size` INT NOT NULL DEFAULT 1 AFTER `team_members`;

-- Set TechNova to team event with 2-4 members
UPDATE `events` 
SET `event_type` = 'both', `min_team_size` = 2, `max_team_size` = 4 
WHERE `id` = 1;
