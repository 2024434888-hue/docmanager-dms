-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 14, 2026 at 09:39 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dms`
--

-- --------------------------------------------------------

--
-- Table structure for table `document`
--

CREATE TABLE `document` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size_kb` int(11) DEFAULT 0,
  `uploaded_by` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `status` enum('draft','active','archived') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document`
--

INSERT INTO `document` (`id`, `title`, `description`, `file_path`, `file_type`, `file_size_kb`, `uploaded_by`, `category_id`, `status`, `created_at`, `updated_at`) VALUES
(2, 'ICM571', 'Individual Assignment\r\nMUHAMMAD FAIZ TAJUDDIN BIN BAHRI\r\n2024299426', '6a28f1260674f.pdf', 'pdf', 1017, 1, 1, 'active', '2026-06-10 13:07:50', '2026-06-10 13:07:50'),
(3, 'Haiqal Punya', '', '6a2a530235bdf.png', 'png', 2638, 2, 1, 'active', '2026-06-11 14:17:38', '2026-06-11 14:17:38'),
(4, 'faiz', '', '6a2a616815719.jpg', 'jpg', 3, 1, 5, 'active', '2026-06-11 15:19:04', '2026-06-11 15:19:04'),
(5, 'cukurukuk', 'tah', '6a2a65d880368.pdf', 'pdf', 1009, 1, 5, 'active', '2026-06-11 15:38:00', '2026-06-11 15:38:00'),
(6, 'hihi', '', '6a2a669fc3560.pdf', 'pdf', 1017, 2, 1, 'active', '2026-06-11 15:41:19', '2026-06-11 15:41:19'),
(7, 'kratos', 'wallpaper', '6a2df2da0279e.jpg', 'jpg', 4713, 1, 5, 'active', '2026-06-14 08:16:26', '2026-06-14 08:16:26');

-- --------------------------------------------------------

--
-- Table structure for table `document_access`
--

CREATE TABLE `document_access` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission` enum('viewer','editor') NOT NULL DEFAULT 'viewer',
  `granted_by` int(11) NOT NULL,
  `granted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_activity`
--

CREATE TABLE `document_activity` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('uploaded','viewed','downloaded','edited','deleted','shared','access_granted','access_revoked') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `acted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_activity`
--

INSERT INTO `document_activity` (`id`, `document_id`, `user_id`, `action`, `ip_address`, `acted_at`) VALUES
(1, 3, 2, 'uploaded', '::1', '2026-06-11 14:17:38'),
(2, 4, 1, 'uploaded', '::1', '2026-06-11 15:19:04'),
(3, 5, 1, 'uploaded', '::1', '2026-06-11 15:38:00'),
(4, 6, 2, 'uploaded', '::1', '2026-06-11 15:41:19'),
(5, 7, 1, 'uploaded', '::1', '2026-06-14 08:16:26'),
(6, 7, 2, 'downloaded', '::1', '2026-06-14 09:02:29');

-- --------------------------------------------------------

--
-- Table structure for table `document_category`
--

CREATE TABLE `document_category` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_category`
--

INSERT INTO `document_category` (`id`, `name`, `description`) VALUES
(1, 'assignment', ''),
(5, 'Confidential', '');

-- --------------------------------------------------------

--
-- Table structure for table `document_password`
--

CREATE TABLE `document_password` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_password`
--

INSERT INTO `document_password` (`id`, `document_id`, `password_hash`, `created_by`, `created_at`) VALUES
(1, 7, '$2y$10$cB7Dk9pGSeK/LxdTI9qoW.7zQ/u2tuHcnEUJIWRZp.2C6T9uAV7qC', 1, '2026-06-14 08:16:26');

-- --------------------------------------------------------

--
-- Table structure for table `document_password_request`
--

CREATE TABLE `document_password_request` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` datetime DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_password_request`
--

INSERT INTO `document_password_request` (`id`, `document_id`, `user_id`, `approved_by`, `status`, `requested_at`, `approved_at`) VALUES
(1, 7, 2, 1, 'approved', '2026-06-14 08:18:05', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `employee`
--

CREATE TABLE `employee` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `department` varchar(100) NOT NULL,
  `image` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee`
--

INSERT INTO `employee` (`id`, `name`, `address`, `department`, `image`) VALUES
(1, 'faiz', 'UITM', 'IT', 'uploads/profile/faiz.jpg'),
(2, 'haiqal', 'Alam Budiman', 'HR', 'uploads/profile/profile_6a27fffb4c238.png'),
(3, 'kocok', 'Shah Alam', 'SV', ''),
(4, 'ahmad', 'Alam Budiman', 'student', ''),
(6, 'kacok', 'Johor', 'HR', '');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset`
--

CREATE TABLE `password_reset` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL DEFAULT '',
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset`
--

INSERT INTO `password_reset` (`id`, `user_id`, `token_hash`, `is_used`, `expires_at`, `created_at`) VALUES
(1, 2, '', 1, '2026-06-11 11:58:04', '2026-06-10 11:58:04');

-- --------------------------------------------------------

--
-- Table structure for table `role_permission`
--

CREATE TABLE `role_permission` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'FK → users.id',
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `permission_key` varchar(100) NOT NULL COMMENT 'Dot-notation key, e.g. document.delete',
  `description` varchar(255) DEFAULT NULL COMMENT 'Human-readable explanation of this permission'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permission`
--

INSERT INTO `role_permission` (`id`, `user_id`, `role`, `permission_key`, `description`) VALUES
(1, 1, 'admin', 'document.upload', 'Upload new documents'),
(2, 1, 'admin', 'document.delete', 'Delete any document'),
(3, 1, 'admin', 'document.share', 'Share documents with other users'),
(4, 1, 'admin', 'document.edit', 'Edit document metadata'),
(5, 1, 'admin', 'category.manage', 'Create, rename, delete categories'),
(6, 1, 'admin', 'user.manage', 'Create and deactivate user accounts'),
(7, 1, 'admin', 'user.request.approve', 'Approve or reject new account requests'),
(8, 1, 'admin', 'report.view', 'View activity log and reports'),
(16, 2, 'user', 'document.upload', 'Upload new documents'),
(17, 2, 'user', 'document.share', 'Share documents with other users');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `employee_id`, `username`, `email`, `password_hash`, `role`, `is_active`, `last_login`, `created_at`) VALUES
(1, 1, 'faiz', 'faiz@gmail.com', '$2y$10$ZNlWih2nUiUzbCQ6DpPW8.466c0oIJCS6wPH9dYcGKOAEverQFuWW', 'admin', 1, '2026-06-14 14:19:04', '2026-06-09 15:14:22'),
(2, 2, 'haiqal', 'haiqal@gmail.com', '$2y$10$0cE2xbqWVZRwKPAc2VQQsO3rs0ISC5f9rhneaFDONUuZa7LSkuyFS', 'user', 1, '2026-06-14 14:27:07', '2026-06-09 19:55:21'),
(4, 6, 'kacok', 'kacok@gmail.com', '$2y$12$W/jhhTEdNld95vbYT8J6deNaMfxuB.M8XWkhFtCg/WSte1l7nEVGq', 'user', 1, NULL, '2026-06-11 15:45:39');

-- --------------------------------------------------------

--
-- Table structure for table `user_request`
--

CREATE TABLE `user_request` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'Filled after account is created (FK → users.id)',
  `employee_id` int(11) NOT NULL COMMENT 'FK → employee.id, created at request time',
  `requested_role` enum('admin','user') NOT NULL DEFAULT 'user',
  `approved_by` int(11) DEFAULT NULL COMMENT 'Admin user who processed this request (FK → users.id)',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requested_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_request`
--

INSERT INTO `user_request` (`id`, `user_id`, `employee_id`, `requested_role`, `approved_by`, `status`, `requested_at`) VALUES
(1, NULL, 3, 'user', NULL, 'rejected', '2026-06-10 12:19:31'),
(2, NULL, 4, 'user', NULL, 'rejected', '2026-06-11 14:20:52'),
(3, 4, 6, 'user', NULL, 'approved', '2026-06-11 15:44:57');

-- --------------------------------------------------------

--
-- Table structure for table `user_session`
--

CREATE TABLE `user_session` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `document`
--
ALTER TABLE `document`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `document_access`
--
ALTER TABLE `document_access`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_doc_user` (`document_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `granted_by` (`granted_by`);

--
-- Indexes for table `document_activity`
--
ALTER TABLE `document_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `document_category`
--
ALTER TABLE `document_category`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `document_password`
--
ALTER TABLE `document_password`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_doc_password_document` (`document_id`),
  ADD KEY `fk_doc_password_admin` (`created_by`);

--
-- Indexes for table `document_password_request`
--
ALTER TABLE `document_password_request`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pwreq_document` (`document_id`),
  ADD KEY `fk_pwreq_user` (`user_id`),
  ADD KEY `fk_pwreq_admin` (`approved_by`);

--
-- Indexes for table `employee`
--
ALTER TABLE `employee`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset`
--
ALTER TABLE `password_reset`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `role_permission`
--
ALTER TABLE `role_permission`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_permission` (`user_id`,`permission_key`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_permission_key` (`permission_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_request`
--
ALTER TABLE `user_request`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `fk_ur_approved_by` (`approved_by`);

--
-- Indexes for table `user_session`
--
ALTER TABLE `user_session`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `document`
--
ALTER TABLE `document`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `document_access`
--
ALTER TABLE `document_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_activity`
--
ALTER TABLE `document_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `document_category`
--
ALTER TABLE `document_category`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `document_password`
--
ALTER TABLE `document_password`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `document_password_request`
--
ALTER TABLE `document_password_request`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employee`
--
ALTER TABLE `employee`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `password_reset`
--
ALTER TABLE `password_reset`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `role_permission`
--
ALTER TABLE `role_permission`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_request`
--
ALTER TABLE `user_request`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_session`
--
ALTER TABLE `user_session`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `document`
--
ALTER TABLE `document`
  ADD CONSTRAINT `document_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `document_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `document_category` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `document_access`
--
ALTER TABLE `document_access`
  ADD CONSTRAINT `document_access_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `document` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_access_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_access_ibfk_3` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `document_activity`
--
ALTER TABLE `document_activity`
  ADD CONSTRAINT `document_activity_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `document` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_activity_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_password`
--
ALTER TABLE `document_password`
  ADD CONSTRAINT `fk_doc_password_admin` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_doc_password_document` FOREIGN KEY (`document_id`) REFERENCES `document` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_password_request`
--
ALTER TABLE `document_password_request`
  ADD CONSTRAINT `fk_pwreq_admin` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pwreq_document` FOREIGN KEY (`document_id`) REFERENCES `document` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pwreq_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset`
--
ALTER TABLE `password_reset`
  ADD CONSTRAINT `password_reset_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permission`
--
ALTER TABLE `role_permission`
  ADD CONSTRAINT `fk_rp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_request`
--
ALTER TABLE `user_request`
  ADD CONSTRAINT `fk_ur_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ur_employee` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `user_session`
--
ALTER TABLE `user_session`
  ADD CONSTRAINT `user_session_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
