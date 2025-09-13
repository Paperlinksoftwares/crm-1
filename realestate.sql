-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 11, 2025 at 11:23 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `realestate`
--

-- --------------------------------------------------------

--
-- Table structure for table `blocks`
--

CREATE TABLE `blocks` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blocks`
--

INSERT INTO `blocks` (`id`, `project_id`, `name`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 1, 'A', 1, '2025-09-04 20:48:01', '2025-09-04 20:48:01'),
(2, 1, 'B', 2, '2025-09-04 20:48:01', '2025-09-04 20:48:01'),
(5, 3, 'Block D', 6, '2025-09-07 10:02:57', '2025-09-07 10:03:59');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `plot_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `buyer_name` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `booking_date` datetime DEFAULT NULL,
  `status` enum('partial','booked') DEFAULT 'booked',
  `amount_paid` decimal(12,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `plot_id`, `user_id`, `buyer_name`, `phone`, `booking_date`, `status`, `amount_paid`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'Koushik', '8425364585', '2025-09-07 13:44:00', 'partial', 999999.00, 'Test Booking', 1, '2025-09-07 08:14:24', '2025-09-07 11:25:28'),
(2, 1, NULL, 'Koushik 2', '5845652585', '2025-09-10 16:43:00', 'partial', 999999.00, '', NULL, '2025-09-07 11:13:13', '2025-09-07 11:13:13');

-- --------------------------------------------------------

--
-- Table structure for table `booking_history`
--

CREATE TABLE `booking_history` (
  `id` int(11) NOT NULL,
  `plot_id` int(11) NOT NULL,
  `from_status` varchar(50) DEFAULT NULL,
  `to_status` varchar(50) DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` varchar(255) DEFAULT NULL,
  `data_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_history`
--

INSERT INTO `booking_history` (`id`, `plot_id`, `from_status`, `to_status`, `changed_by`, `changed_at`, `reason`, `data_json`) VALUES
(1, 1, 'available', 'booked', 1, '2025-09-07 08:14:24', 'Admin booking', '{\"booking_id\":\"1\",\"amount_paid\":999999,\"notes\":\"Test Booking\"}'),
(2, 6, 'available', 'available', NULL, '2025-09-07 10:27:24', NULL, '{\"booking_id\":null,\"buyer_name\":\"Koushik\",\"phone\":\"9547364458\",\"amount_paid\":\"\",\"notes\":\"\"}'),
(3, 4, 'available', 'partial', NULL, '2025-09-07 10:42:35', 'bulk_status_update', '{\"bulk_update\":true}'),
(4, 1, 'booked', 'available', NULL, '2025-09-07 10:42:47', 'bulk_status_update', '{\"bulk_update\":true}'),
(5, 6, 'available', 'booked', NULL, '2025-09-07 10:42:47', 'bulk_status_update', '{\"bulk_update\":true}'),
(6, 1, '', 'booked', NULL, '2025-09-07 11:10:50', 'booking_edit', '{\"old_row\":{\"id\":1,\"plot_id\":1,\"user_id\":null,\"buyer_name\":\"Koushik\",\"phone\":\"8425364585\",\"booking_date\":\"2025-09-07 13:44:24\",\"status\":\"\",\"amount_paid\":\"999999.00\",\"notes\":\"Test Booking\",\"created_by\":1,\"created_at\":\"2025-09-07 13:44:24\",\"updated_at\":\"2025-09-07 16:12:47\"},\"new_values\":{\"buyer_name\":\"Koushik\",\"phone\":\"8425364585\",\"booking_date\":\"2025-09-07T13:44\",\"status\":\"booked\",\"amount_paid\":\"999999.00\",\"notes\":\"Test Booking\"}}'),
(7, 1, 'booked', 'partial', NULL, '2025-09-07 11:11:56', 'booking_edit', '{\"old_row\":{\"id\":1,\"plot_id\":1,\"user_id\":null,\"buyer_name\":\"Koushik\",\"phone\":\"8425364585\",\"booking_date\":\"2025-09-07 13:44:00\",\"status\":\"booked\",\"amount_paid\":\"999999.00\",\"notes\":\"Test Booking\",\"created_by\":1,\"created_at\":\"2025-09-07 13:44:24\",\"updated_at\":\"2025-09-07 16:40:50\"},\"new_values\":{\"buyer_name\":\"Koushik\",\"phone\":\"8425364585\",\"booking_date\":\"2025-09-07T13:44\",\"status\":\"partial\",\"amount_paid\":\"999999.00\",\"notes\":\"Test Booking\"}}'),
(8, 1, 'partial', 'cancelled', NULL, '2025-09-07 11:12:10', 'soft_cancel', '{\"booking_id\":1,\"action\":\"soft_cancel\"}'),
(9, 1, '', 'cancelled', NULL, '2025-09-07 11:12:29', 'soft_cancel', '{\"booking_id\":1,\"action\":\"soft_cancel\"}'),
(10, 1, 'available', 'partial', NULL, '2025-09-07 11:13:13', NULL, '{\"booking_id\":\"2\",\"buyer_name\":\"Koushik 2\",\"phone\":\"5845652585\",\"amount_paid\":\"999999.00\",\"notes\":\"\"}'),
(11, 1, '', 'booked', NULL, '2025-09-07 11:14:36', 'booking_edit', '{\"old_row\":{\"id\":1,\"plot_id\":1,\"user_id\":null,\"buyer_name\":\"Koushik\",\"phone\":\"8425364585\",\"booking_date\":\"2025-09-07 13:44:00\",\"status\":\"\",\"amount_paid\":\"999999.00\",\"notes\":\"Test Booking\",\"created_by\":1,\"created_at\":\"2025-09-07 13:44:24\",\"updated_at\":\"2025-09-07 16:42:29\"},\"new_values\":{\"buyer_name\":\"Koushik\",\"phone\":\"8425364585\",\"booking_date\":\"2025-09-07T13:44\",\"status\":\"booked\",\"amount_paid\":\"999999.00\",\"notes\":\"Test Booking\"}}'),
(12, 1, 'booked', 'partial', NULL, '2025-09-07 11:25:28', 'booking_edit', '{\"old_row\":{\"id\":1,\"plot_id\":1,\"user_id\":null,\"buyer_name\":\"Koushik\",\"phone\":\"8425364585\",\"booking_date\":\"2025-09-07 13:44:00\",\"status\":\"booked\",\"amount_paid\":\"999999.00\",\"notes\":\"Test Booking\",\"created_by\":1,\"created_at\":\"2025-09-07 13:44:24\",\"updated_at\":\"2025-09-07 16:44:36\"},\"new_values\":{\"buyer_name\":\"Koushik\",\"phone\":\"8425364585\",\"booking_date\":\"2025-09-07T13:44\",\"status\":\"partial\",\"amount_paid\":\"999999.00\",\"notes\":\"Test Booking\"}}');

-- --------------------------------------------------------

--
-- Table structure for table `plots`
--

CREATE TABLE `plots` (
  `id` int(11) NOT NULL,
  `block_id` int(11) NOT NULL,
  `plot_no` varchar(50) NOT NULL,
  `size` text DEFAULT '0.00',
  `price` decimal(12,2) DEFAULT 0.00,
  `status` enum('available','partial','booked') DEFAULT 'available',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `plots`
--

INSERT INTO `plots` (`id`, `block_id`, `plot_no`, `size`, `price`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'A1', '120.00', 1000000.00, 'partial', NULL, '2025-09-04 20:48:01', '2025-09-07 11:25:28'),
(4, 2, 'B1', '130.00', 1150000.00, 'partial', NULL, '2025-09-04 20:48:01', '2025-09-07 10:42:35'),
(6, 5, 'D1', '25x25', 9999.00, 'booked', 'Notes 1', '2025-09-07 10:18:50', '2025-09-07 10:55:52');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `image_url` varchar(512) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `description`, `location`, `image_url`, `created_at`, `updated_at`) VALUES
(1, 'Lotus Residency', 'A modern gated project', 'Sector 12, City', 'uploads/lotus.jpg', '2025-09-04 20:48:01', '2025-09-04 20:48:01'),
(3, 'Project 1', 'Description 1', 'Location 1', '/uploads/projects/proj_68bd52f6600b65.87965670.jpg', '2025-09-07 09:40:06', '2025-09-07 09:40:06');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `role` enum('super_admin','project_manager','sales','viewer','tv') NOT NULL DEFAULT 'viewer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'admin@example.com', '9999999999', '$2y$10$dS5TEo1WMoDa32a/Xd8mqegOK1lzwQxywxvdKjnqE9vPsDcU8ktGW', 'super_admin', '2025-09-04 20:58:28', '2025-09-04 21:07:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `blocks`
--
ALTER TABLE `blocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_blocks_project` (`project_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plot_id` (`plot_id`);

--
-- Indexes for table `booking_history`
--
ALTER TABLE `booking_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plot_id` (`plot_id`);

--
-- Indexes for table `plots`
--
ALTER TABLE `plots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_plots_block` (`block_id`),
  ADD KEY `idx_plots_status` (`status`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `blocks`
--
ALTER TABLE `blocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `booking_history`
--
ALTER TABLE `booking_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `plots`
--
ALTER TABLE `plots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `blocks`
--
ALTER TABLE `blocks`
  ADD CONSTRAINT `blocks_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `booking_history`
--
ALTER TABLE `booking_history`
  ADD CONSTRAINT `booking_history_ibfk_1` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `plots`
--
ALTER TABLE `plots`
  ADD CONSTRAINT `plots_ibfk_1` FOREIGN KEY (`block_id`) REFERENCES `blocks` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
