-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 05, 2025 at 11:48 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `menu_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `active_menu`
--

CREATE TABLE `active_menu` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Active, 0 = Inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `active_menu`
--

INSERT INTO `active_menu` (`id`, `name`, `status`, `created_at`) VALUES
(1, 'Regular Menu', 1, '2025-11-04 11:31:41'),
(3, 'Diwali Special', 1, '2025-11-04 11:31:41'),
(7, 'October Menu', 1, '2025-11-05 10:42:36');

-- --------------------------------------------------------

--
-- Table structure for table `menu`
--

CREATE TABLE `menu` (
  `id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `active_menu_id` int(11) NOT NULL COMMENT 'FK -> active_menu.id',
  `food_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `discount_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `price` decimal(10,2) NOT NULL,
  `speciality_one` varchar(255) DEFAULT NULL,
  `speciality_two` varchar(255) DEFAULT NULL,
  `speciality_three` varchar(255) DEFAULT NULL,
  `speciality_four` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `is_special_item` tinyint(1) NOT NULL DEFAULT 0,
  `is_best_offer` tinyint(1) NOT NULL DEFAULT 0,
  `is_popular_item` tinyint(1) NOT NULL DEFAULT 0,
  `is_thaath_special` tinyint(1) NOT NULL DEFAULT 0,
  `show_in_shop` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu`
--

INSERT INTO `menu` (`id`, `item_name`, `category`, `active_menu_id`, `food_type`, `description`, `discount_percentage`, `price`, `speciality_one`, `speciality_two`, `speciality_three`, `speciality_four`, `image_path`, `is_special_item`, `is_best_offer`, `is_popular_item`, `is_thaath_special`, `show_in_shop`, `created_at`) VALUES
(1, 'Paneer Butter Masala', 'Main Course', 1, 'veg', 'Creamy paneer curry with aromatic spices', 10.00, 249.00, 'Best served with butter naan', 'Chef special', '', '', 'images/690afdb114b27_paneer_butter.jpg', 1, 0, 1, 0, 1, '2025-11-04 14:23:15'),
(4, 'Malai kofta', 'Main Course', 1, 'veg', 'Creamy paneer curry with aromatic spices', 10.00, 249.00, 'Best served with butter naan', 'Chef special', '', '', 'images/690b29d7832ed_malai.jpg', 1, 0, 1, 0, 1, '2025-11-05 10:41:09');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `active_menu`
--
ALTER TABLE `active_menu`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_active_menu_name` (`name`);

--
-- Indexes for table `menu`
--
ALTER TABLE `menu`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active_menu_id` (`active_menu_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `active_menu`
--
ALTER TABLE `active_menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `menu`
--
ALTER TABLE `menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `menu`
--
ALTER TABLE `menu`
  ADD CONSTRAINT `fk_menu_active_menu` FOREIGN KEY (`active_menu_id`) REFERENCES `active_menu` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
