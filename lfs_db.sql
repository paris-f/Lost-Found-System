-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 04, 2025 at 06:12 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lfs_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `ip_address`, `timestamp`) VALUES
(1, 1, 'User logged in.', '::1', '2025-11-01 18:41:56'),
(2, 1, 'User logged in.', '::1', '2025-11-02 20:05:34'),
(3, 1, 'Used Chatbot to search: Watch', '::1', '2025-11-02 20:42:36'),
(4, 1, 'Used Chatbot to search: Pen', '::1', '2025-11-02 20:46:05'),
(5, 1, 'User logged in.', '::1', '2025-11-02 20:49:26'),
(6, 1, 'Used Chatbot to search: Hey', '::1', '2025-11-02 21:13:48'),
(7, 1, 'User logged in.', '::1', '2025-11-02 21:34:22'),
(8, 1, 'User logged in.', '::1', '2025-11-02 21:47:44'),
(9, 1, 'User logged in.', '::1', '2025-11-02 21:56:56'),
(10, 1, 'User logged in.', '::1', '2025-11-02 22:02:41'),
(11, 1, 'User logged in.', '::1', '2025-11-03 00:41:19'),
(12, 1, 'User logged in.', '::1', '2025-11-03 07:16:01'),
(13, 1, 'Reported found item: iPhone', '::1', '2025-11-03 07:18:46'),
(14, 2, 'User registered (Student).', '::1', '2025-11-03 07:19:48'),
(15, 2, 'User logged in.', '::1', '2025-11-03 07:20:34'),
(16, 2, 'Submitted claim for item ID: 1', '::1', '2025-11-03 07:20:52'),
(17, 1, 'User logged in.', '::1', '2025-11-03 07:21:05'),
(18, 1, 'Item ID 1 marked as Claimed (Claim 1).', '::1', '2025-11-03 07:21:17'),
(19, 1, 'Claim ID 1 set to Approved.', '::1', '2025-11-03 07:21:17'),
(20, 1, 'User logged in.', '::1', '2025-11-03 07:38:17'),
(21, 1, 'Reported found item: iPhone', '::1', '2025-11-03 07:38:46'),
(22, 2, 'User logged in.', '::1', '2025-11-03 07:38:59'),
(23, 2, 'Submitted claim for item ID: 2', '::1', '2025-11-03 07:39:21'),
(24, 1, 'User logged in.', '::1', '2025-11-03 07:39:40'),
(25, 1, 'Item ID 2 marked as Claimed (Claim 2).', '::1', '2025-11-03 07:40:14'),
(26, 1, 'Claim ID 2 set to Approved.', '::1', '2025-11-03 07:40:14'),
(27, 1, 'Reported found item: iPhone 13', '::1', '2025-11-03 08:15:06'),
(28, 2, 'User logged in.', '::1', '2025-11-03 08:15:49'),
(29, 2, 'Submitted claim for item ID: 3', '::1', '2025-11-03 08:16:01'),
(30, 1, 'User logged in.', '::1', '2025-11-03 08:16:18'),
(31, 1, 'Item ID 3 marked as Claimed (Claim 3).', '::1', '2025-11-03 08:16:27'),
(32, 1, 'Claim ID 3 set to Approved.', '::1', '2025-11-03 08:16:27'),
(33, 1, 'Reported lost item: ', '::1', '2025-11-03 13:51:09'),
(34, 1, 'Reported found item: ', '::1', '2025-11-03 13:51:34'),
(35, 1, 'Reported lost item: iPhone 13', '::1', '2025-11-03 23:34:42'),
(36, 1, 'Reported found item: iPhone 13', '::1', '2025-11-03 23:35:15');

-- --------------------------------------------------------

--
-- Table structure for table `claims`
--

CREATE TABLE `claims` (
  `claim_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `claimer_id` int(11) DEFAULT NULL,
  `claim_details` text NOT NULL,
  `claim_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `claims`
--

INSERT INTO `claims` (`claim_id`, `item_id`, `claimer_id`, `claim_details`, `claim_date`, `status`) VALUES
(1, 1, 2, 'Has scratches', '2025-11-03 07:20:52', 'Approved'),
(2, 2, 2, 'Scratches', '2025-11-03 07:39:21', 'Approved'),
(3, 3, 2, 'Has ', '2025-11-03 08:16:01', 'Approved');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `item_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `item_type` enum('Lost','Found') NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `item_image` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Resolved','Claimed') NOT NULL DEFAULT 'Pending',
  `report_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`item_id`, `user_id`, `item_type`, `item_name`, `category`, `description`, `location`, `item_image`, `status`, `report_date`) VALUES
(1, 1, 'Found', 'iPhone', 'Electronics', ' Found at Library [COLOR: Brown]', 'Library', 'item_690857565ced61.97224977.png', 'Claimed', '2025-11-03'),
(2, 1, 'Found', 'iPhone', 'Electronics', 'Found at Library [COLOR: Brown]', 'Library', 'item_69085c06d37690.40061421.png', 'Claimed', '2025-11-03'),
(3, 1, 'Found', 'iPhone 13', 'Electronics', 'Found at Library [COLOR: Brown]', 'Library', 'item_6908648a6f2ec9.98702848.png', 'Claimed', '2025-11-03'),
(4, 1, 'Lost', 'Airpods', 'Electronics', ' [COLOR: White] [OBJECT: Accessory]', '', NULL, 'Pending', '2025-11-03'),
(5, 1, 'Found', 'Headphones', 'Electronics', '[COLOR: Blue]', '[OBJECT: Accessory]', NULL, 'Pending', '2025-11-03'),
(6, 1, 'Lost', 'iPhone 13', 'Electronics', '[COLOR: Black] [OBJECT: Phone]', 'Library', 'item_69093c129d7fc.png', 'Pending', '2025-11-04'),
(7, 1, 'Found', 'iPhone 13', 'Electronics', '[COLOR: Purple]', '', 'item_69093c33e74996.13434708.png', 'Pending', '2025-11-04');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('Student','Staff','Admin') NOT NULL DEFAULT 'Student',
  `otp_secret` varchar(16) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password_hash`, `email`, `role`, `otp_secret`, `created_at`) VALUES
(1, 'admin', 'adminpass', 'admin@org.com', 'Admin', NULL, '2025-11-01 12:35:04'),
(2, 'Paris', 'Paris123', 'thabopn18@gmail.com', 'Student', NULL, '2025-11-03 07:19:48');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `claims`
--
ALTER TABLE `claims`
  ADD PRIMARY KEY (`claim_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `claimer_id` (`claimer_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `claims`
--
ALTER TABLE `claims`
  MODIFY `claim_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `claims`
--
ALTER TABLE `claims`
  ADD CONSTRAINT `claims_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`),
  ADD CONSTRAINT `claims_ibfk_2` FOREIGN KEY (`claimer_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;