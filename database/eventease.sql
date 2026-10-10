-- EventEase Database Schema & Initial Data
CREATE DATABASE IF NOT EXISTS `eventease` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `eventease`;

-- Drop tables if they exist (for clean setup)
DROP TABLE IF EXISTS `registrations`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `users`;

-- 1. Users Table
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(20) NOT NULL,
  `college_id` VARCHAR(50) NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `year` VARCHAR(20) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('student', 'admin') DEFAULT 'student',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Events Table
CREATE TABLE `events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `category` VARCHAR(50) NOT NULL DEFAULT 'General',
  `description` TEXT NOT NULL,
  `rules` TEXT,
  `event_date` DATE NOT NULL,
  `event_time` TIME NOT NULL,
  `venue` VARCHAR(150) NOT NULL,
  `organizer` VARCHAR(100) NOT NULL DEFAULT 'Campus Event Committee',
  `capacity` INT NOT NULL,
  `registered_count` INT DEFAULT 0,
  `image` VARCHAR(255) DEFAULT 'default-event.jpg',
  `status` ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Registrations Table
CREATE TABLE `registrations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `event_id` INT NOT NULL,
  `registration_code` VARCHAR(50) NOT NULL UNIQUE,
  `qr_code` VARCHAR(255) NOT NULL,
  `checkin_status` ENUM('pending', 'checked_in') DEFAULT 'pending',
  `checkin_time` DATETIME DEFAULT NULL,
  `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_user_event` (`user_id`, `event_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Data: System Users & Registered Students
-- Real student and administrator profiles for campus events.

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `college_id`, `department`, `year`, `password`, `role`) VALUES
(1, 'Het Kalariya (Admin)', 'bavh@gmail.com', '9876543210', 'ADM-2024-001', 'Administration', 'N/A', '$2y$10$XHtnvy97TWB8GvoYUg1y5OIZDIDydUY848tJzkOtTgIZ6udpKFwHG', 'admin'),
(2, 'Aarav Patel', 'aarav.patel@campus.edu', '9825012345', 'CS-2024-041', 'Computer Science & Engineering', '3rd Year', '$2y$10$0CQRAR3EgXe85.uIp0NncuveLct0d.Pe9LX8S9sK/CQ94OGN/ZijS', 'student'),
(3, 'Priya Sharma', 'priya.sharma@campus.edu', '9825023456', 'EC-2024-028', 'Electronics & Communication', '2nd Year', '$2y$10$OtfFLA4Jgjsl.ZVvgJY.EOcbyO8SyyNR/39vQYTsRG39zk4GF7O4a', 'student'),
(4, 'Rohan Mehta', 'rohan.mehta@campus.edu', '9825034567', 'IT-2024-015', 'Information Technology', '1st Year', '$2y$10$ddAEF865RMPOOJIx8pywPOiBDngMqi2HEnN3VK1pUGkGXTTxx8cZa', 'student'),
(5, 'Het Kalariya', 'bav@gmail.com', '9000000001', '85', 'Information Technology', '3rd Year', '$2y$10$6sKuft/k3hTUmAAIulM/4edSv53JSB46Mghy2yFtULRrxIPUjzuFe', 'student');

-- Seed Data: Campus Events
INSERT INTO `events` (`id`, `title`, `category`, `description`, `rules`, `event_date`, `event_time`, `venue`, `organizer`, `capacity`, `registered_count`, `image`, `status`) VALUES
(1, 'TechNova 2026 - Hackathon & AI Summit', 'Technology', 'Join the largest 24-hour hackathon on campus! Build innovative AI, Web, and Mobile solutions. Exciting cash prizes, free swags, food coupons, and mentorship from industry leaders.', '1. Teams of 2 to 4 members.\n2. Laptops and chargers must be brought by participants.\n3. Plagiarism in code will lead to disqualification.\n4. Decisions of judges will be final.', '2026-10-15', '09:00:00', 'Main University Auditorium', 'Department of Computer Science', 150, 4, 'technova.jpg', 'active'),

(2, 'RoboWars & Drone Racing Championship', 'Robotics', 'High-octane bot battles and aerial obstacle racing! Witness custom-built combat robots clash in the arena and FPV drones race at breakneck speeds.', '1. Bot weight limits: Under 15kg for featherweight combat.\n2. Drones must pass safety inspections.\n3. Safety goggles mandatory inside the arena area.', '2026-10-18', '10:30:00', 'Engineering Quadrangle Ground', 'Robotics Club & Maker Lab', 100, 1, 'robowars.jpg', 'active'),

(3, 'Symphony 2026 - Annual Cultural Fest', 'Cultural', 'An unforgettable night of live musical performances, battle of the bands, classical fusion, and high-energy dance competitions. Star night guest performance included!', '1. College ID card mandatory for entry.\n2. No outside food or beverages allowed.\n3. Registration passes must be shown at main gate check-in.', '2026-10-22', '17:00:00', 'Campus Open Air Theatre', 'Cultural Student Council', 300, 2, 'symphony.jpg', 'active'),

(4, 'Cyber Security & Ethical Hacking Workshop', 'Workshop', 'Hands-on practical workshop covering vulnerability assessment, network scanning, web application security, and Capture The Flag (CTF) challenges.', '1. Basic knowledge of networking and Linux recommended.\n2. Bring a laptop with Wi-Fi capability.\n3. Software tools will be provided on-site.', '2026-10-28', '11:00:00', 'Advanced Computing Lab 3', 'Cyber Security Cell', 60, 1, 'cybersecurity.jpg', 'active'),

(5, 'Inter-College Cricket Premier League', 'Sports', 'The ultimate T20 cricket showdown among top engineering and management department teams. Trophies, medals, and individual awards up for grabs!', '1. Standard ICC T20 match rules apply.\n2. Teams must report 30 minutes before toss.\n3. Sports outfit and proper shoes required.', '2026-11-05', '08:30:00', 'University Sports Ground', 'Department of Physical Education', 200, 0, 'cricket.jpg', 'active');

-- Seed Data: Initial Registrations for gate check-in and dashboard
INSERT INTO `registrations` (`id`, `user_id`, `event_id`, `registration_code`, `qr_code`, `checkin_status`, `checkin_time`, `registered_at`) VALUES
(1, 2, 1, 'EE-2026-T7A91B', 'EE-2026-T7A91B', 'pending', NULL, '2026-09-28 10:15:00'),
(2, 3, 1, 'EE-2026-R8B42C', 'EE-2026-R8B42C', 'checked_in', '2026-09-29 09:12:30', '2026-09-28 11:20:00'),
(3, 4, 1, 'EE-2026-P3C53D', 'EE-2026-P3C53D', 'pending', NULL, '2026-09-28 14:00:00'),
(4, 2, 3, 'EE-2026-S1M99E', 'EE-2026-S1M99E', 'pending', NULL, '2026-09-29 08:30:00'),
(5, 3, 3, 'EE-2026-S2N88F', 'EE-2026-S2N88F', 'checked_in', '2026-09-29 16:05:00', '2026-09-29 09:10:00'),
(6, 2, 2, 'EE-2026-W4X55G', 'EE-2026-W4X55G', 'pending', NULL, '2026-09-29 12:00:00'),
(7, 4, 4, 'EE-2026-C9Y66H', 'EE-2026-C9Y66H', 'pending', NULL, '2026-09-29 15:45:00'),
(8, 5, 1, 'EE-2026-62A8A6', 'EE-2026-62A8A6', 'checked_in', '2026-10-07 14:41:19', '2026-10-07 14:40:45');
