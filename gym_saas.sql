-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 21, 2026 at 07:52 AM
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
-- Database: `gym_saas`
--

-- --------------------------------------------------------

--
-- Table structure for table `gyms`
--

CREATE TABLE `gyms` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','trial','suspended') DEFAULT 'active',
  `trial_ends_at` datetime DEFAULT NULL,
  `billing_status` enum('trial','active','suspended') NOT NULL DEFAULT 'trial',
  `plan` enum('free','basic','pro','enterprise') DEFAULT 'free',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `zip` varchar(10) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `primary_color` varchar(20) DEFAULT '#111827',
  `secondary_color` varchar(20) DEFAULT '#3b82f6',
  `timezone` varchar(50) DEFAULT 'Asia/Kolkata',
  `currency` varchar(10) DEFAULT 'INR'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gyms`
--

INSERT INTO `gyms` (`id`, `name`, `slug`, `email`, `phone`, `status`, `trial_ends_at`, `billing_status`, `plan`, `created_at`, `address`, `city`, `state`, `zip`, `country`, `logo`, `primary_color`, `secondary_color`, `timezone`, `currency`) VALUES
(1, 'Super Admin', 'SuperAdmin', 'admin@platform.com', '9999999999', 'active', NULL, 'trial', 'free', '2025-12-18 12:06:42', '', '', '', NULL, '', NULL, '#bb2f0c', '#16791d', 'Asia/Kolkata', 'INR'),
(34, 'Fitness', 'fitness', 'vipinwork844@gmail.com', NULL, 'active', '2026-02-03 14:05:06', 'trial', 'free', '2026-01-20 13:05:06', NULL, NULL, NULL, NULL, NULL, NULL, '#111827', '#3b82f6', 'Asia/Kolkata', 'INR');

-- --------------------------------------------------------

--
-- Table structure for table `gym_requests`
--

CREATE TABLE `gym_requests` (
  `id` int(11) NOT NULL,
  `gym_name` varchar(150) NOT NULL,
  `owner_name` varchar(150) NOT NULL,
  `owner_email` varchar(150) NOT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `plan_name` varchar(100) DEFAULT NULL,
  `payment_id` varchar(100) DEFAULT NULL,
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `amount` decimal(10,2) DEFAULT 0.00,
  `gym_id` int(11) DEFAULT NULL,
  `admin_user_id` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gym_requests`
--

INSERT INTO `gym_requests` (`id`, `gym_name`, `owner_name`, `owner_email`, `plan_id`, `invoice_id`, `phone`, `city`, `note`, `plan_name`, `payment_id`, `payment_status`, `amount`, `gym_id`, `admin_user_id`, `approved_at`, `approved_by`, `rejected_at`, `rejected_by`, `status`, `created_at`, `rejection_reason`) VALUES
(10, 'Fitness', 'Vipin Parihar', 'vipinwork844@gmail.com', 2, 3, '1234567890', 'indore', 'just test flow', 'Basic', NULL, '', 399.00, 34, 25, '2026-01-20 18:35:06', 4, NULL, NULL, 'approved', '2026-01-20 13:04:20', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `gym_settings`
--

CREATE TABLE `gym_settings` (
  `id` int(11) NOT NULL,
  `gym_id` int(11) NOT NULL,
  `settings_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gym_subscriptions`
--

CREATE TABLE `gym_subscriptions` (
  `id` int(11) NOT NULL,
  `gym_id` int(11) NOT NULL,
  `plan` varchar(50) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `trial_days` int(11) NOT NULL DEFAULT 0,
  `trial_ends_at` datetime DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gym_subscriptions`
--

INSERT INTO `gym_subscriptions` (`id`, `gym_id`, `plan`, `plan_id`, `assigned_by`, `trial_days`, `trial_ends_at`, `start_date`, `end_date`, `status`, `created_at`, `updated_at`) VALUES
(17, 34, 'Basic', 2, 4, 14, '2026-02-03 14:05:06', '2026-01-20 18:35:06', NULL, 'trial', '2026-01-20 13:05:06', '2026-01-20 13:05:06');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `gym_id` int(11) NOT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'INR',
  `status` enum('unpaid','paid','void','refunded') NOT NULL DEFAULT 'unpaid',
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `due_date` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_number`, `gym_id`, `plan_id`, `amount`, `currency`, `status`, `issued_at`, `due_date`, `paid_at`, `notes`, `created_at`, `updated_at`) VALUES
(3, 'INV-20260120-5D1475', 34, 2, 399.00, 'INR', 'unpaid', '2026-01-20 18:35:06', '2026-02-03 14:05:06', NULL, 'Auto invoice generated on approval (trial 14 days).', '2026-01-20 13:05:06', '2026-01-20 13:05:06');

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(11) NOT NULL,
  `gym_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `join_date` date DEFAULT curdate(),
  `status` enum('active','inactive','blocked') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `membership_plans`
--

CREATE TABLE `membership_plans` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'INR',
  `billing_cycle` enum('monthly','yearly','lifetime') NOT NULL DEFAULT 'monthly',
  `duration_days` int(11) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `features_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `membership_plans`
--

INSERT INTO `membership_plans` (`id`, `name`, `slug`, `description`, `price`, `currency`, `billing_cycle`, `duration_days`, `status`, `sort_order`, `features_json`, `created_at`, `updated_at`) VALUES
(2, 'Basic', 'basic', 'This is Basic Plan for New Gym Owners', 399.00, 'INR', 'monthly', 30, 'active', 0, '[\"Member Management\",\"Attendance Tracking\",\"Online Payments\",\"ETC\"]', '2026-01-19 11:58:40', '2026-01-20 11:09:57'),
(3, 'Pro', 'pro', 'This Plan is medium level gyms owner', 999.00, 'INR', 'monthly', 30, 'active', 0, '[\"Member Management\",\"Attendance Tracking\",\"Online Payments\",\"staff management\",\"Steam Bath\"]', '2026-01-20 05:47:10', '2026-01-20 11:10:09');

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` int(11) NOT NULL,
  `site_type` enum('platform','gym') DEFAULT 'platform',
  `gym_id` int(11) DEFAULT NULL,
  `slug` varchar(100) NOT NULL,
  `template_id` int(11) NOT NULL,
  `structure_json` longtext DEFAULT NULL,
  `page_data_json` longtext DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`id`, `site_type`, `gym_id`, `slug`, `template_id`, `structure_json`, `page_data_json`, `status`, `created_at`, `updated_at`) VALUES
(12, 'platform', NULL, 'home', 13, '{\"sections\":[{\"id\":\"header_1768890921910\",\"type\":\"header\"},{\"id\":\"hero_1768890925390\",\"type\":\"hero\"},{\"id\":\"features_1768890927206\",\"type\":\"features\"},{\"id\":\"pricing_1768890927894\",\"type\":\"pricing\"},{\"id\":\"testimonials_1768890928422\",\"type\":\"testimonials\"},{\"id\":\"gallery_1768890929014\",\"type\":\"gallery\"},{\"id\":\"cta_1768890929669\",\"type\":\"cta\"},{\"id\":\"register_form_1768890930118\",\"type\":\"register_form\"},{\"id\":\"footer_1768890930758\",\"type\":\"footer\"}]}', '{\"header_1768890921910\":{\"logo_text\":\"\",\"menu\":\"\",\"button_text\":\"\",\"button_link\":\"\"},\"hero_1768890925390\":{\"title\":\"\",\"subtitle\":\"\",\"button_text\":\"\",\"button_link\":\"\"},\"features_1768890927206\":{\"heading\":\"\",\"subheading\":\"\",\"items\":\"[\\n  \\\"Manage Members\\\",\\n  \\\"Track Attendance\\\",\\n  \\\"Online Payments\\\",\\n  \\\"Automated Reminders\\\"\\n]\"},\"pricing_1768890927894\":{\"heading\":\"Pricing Plans\",\"subheading\":\"Choose a plan that fits your gym\",\"plans\":\"[\\n  {\\n    \\\"name\\\": \\\"Basic\\\",\\n    \\\"price\\\": \\\"399.00\\\",\\n    \\\"button_text\\\": \\\"Get Started\\\",\\n    \\\"features\\\": [\\n      \\\"Member Management\\\",\\n      \\\"Attendance Tracking\\\",\\n      \\\"Online Payments\\\",\\n      \\\"ETC\\\"\\n    ]\\n  },\\n  {\\n    \\\"name\\\": \\\"Pro\\\",\\n    \\\"price\\\": \\\"999.00\\\",\\n    \\\"button_text\\\": \\\"Get Started\\\",\\n    \\\"features\\\": [\\n      \\\"Member Management\\\",\\n      \\\"Attendance Tracking\\\",\\n      \\\"Online Payments\\\",\\n      \\\"staff management\\\",\\n      \\\"Steam Bath\\\"\\n    ]\\n  }\\n]\"},\"testimonials_1768890928422\":{\"heading\":\"\",\"items\":\"[\\n  { \\\"name\\\": \\\"Rahul\\\", \\\"role\\\": \\\"Gym Owner\\\", \\\"message\\\": \\\"This platform saved us hours daily.\\\" },\\n  { \\\"name\\\": \\\"Ankit\\\", \\\"role\\\": \\\"Trainer\\\", \\\"message\\\": \\\"Members tracking is super easy now.\\\" }\\n]\"},\"gallery_1768890929014\":{\"heading\":\"\",\"images\":\"[\\n  \\\"https:\\/\\/picsum.photos\\/600\\/400?random=1\\\",\\n  \\\"https:\\/\\/picsum.photos\\/600\\/400?random=2\\\",\\n  \\\"https:\\/\\/picsum.photos\\/600\\/400?random=3\\\"\\n]\"},\"cta_1768890929669\":{\"heading\":\"\",\"subheading\":\"\",\"button_text\":\"\",\"button_link\":\"\"},\"register_form_1768890930118\":{\"title\":\"\",\"subtitle\":\"\",\"submit_text\":\"Submit Request\"},\"footer_1768890930758\":{\"brand\":\"\",\"tagline\":\"\",\"links\":\"\",\"email\":\"\"}}', 'active', '2026-01-20 06:36:36', '2026-01-20 12:07:44'),
(13, 'gym', 32, 'home', 14, NULL, '{}', 'active', '2026-01-20 06:59:09', NULL),
(14, 'gym', 32, 'about', 15, NULL, '{}', 'active', '2026-01-20 06:59:09', NULL),
(15, 'gym', 32, 'contact', 16, NULL, '{}', 'active', '2026-01-20 06:59:09', NULL),
(16, 'gym', 33, 'home', 14, NULL, '{}', 'active', '2026-01-20 12:33:59', NULL),
(17, 'gym', 33, 'about', 15, NULL, '{}', 'active', '2026-01-20 12:33:59', NULL),
(18, 'gym', 33, 'contact', 16, NULL, '{}', 'active', '2026-01-20 12:33:59', NULL),
(19, 'gym', 34, 'home', 14, NULL, '{}', 'active', '2026-01-20 13:05:06', NULL),
(20, 'gym', 34, 'about', 15, NULL, '{}', 'active', '2026-01-20 13:05:06', NULL),
(21, 'gym', 34, 'contact', 16, NULL, '{}', 'active', '2026-01-20 13:05:06', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `method` enum('cash','upi','card','netbanking','stripe','razorpay','paypal') DEFAULT NULL,
  `payment_ref` varchar(100) DEFAULT NULL,
  `gym_id` int(11) NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'INR',
  `payment_method` enum('cash','card','upi','net_banking','wallet') NOT NULL,
  `payment_for` enum('membership','subscription','personal_training','other') DEFAULT 'membership',
  `status` enum('pending','paid','failed','refunded') DEFAULT 'paid',
  `transaction_id` varchar(100) DEFAULT NULL,
  `paid_at` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `templates`
--

CREATE TABLE `templates` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` enum('platform','gym') DEFAULT 'platform',
  `structure_json` longtext NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `page_data_json` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `templates`
--

INSERT INTO `templates` (`id`, `name`, `type`, `structure_json`, `status`, `created_at`, `updated_at`, `page_data_json`) VALUES
(13, 'Plateform Landing', 'platform', '{\"sections\":[{\"id\":\"header_1768890921910\",\"type\":\"header\"},{\"id\":\"hero_1768890925390\",\"type\":\"hero\"},{\"id\":\"features_1768890927206\",\"type\":\"features\"},{\"id\":\"pricing_1768890927894\",\"type\":\"pricing\"},{\"id\":\"testimonials_1768890928422\",\"type\":\"testimonials\"},{\"id\":\"gallery_1768890929014\",\"type\":\"gallery\"},{\"id\":\"cta_1768890929669\",\"type\":\"cta\"},{\"id\":\"register_form_1768890930118\",\"type\":\"register_form\"},{\"id\":\"footer_1768890930758\",\"type\":\"footer\"}]}', 'active', '2026-01-20 06:36:18', '2026-01-20 12:06:18', '{\"header_1768890921910\":{\"logo_text\":\"\",\"menu\":\"\",\"button_text\":\"\",\"button_link\":\"\"},\"hero_1768890925390\":{\"title\":\"\",\"subtitle\":\"\",\"button_text\":\"\",\"button_link\":\"\"},\"features_1768890927206\":{\"heading\":\"\",\"subheading\":\"\",\"items\":\"[\\n  \\\"Manage Members\\\",\\n  \\\"Track Attendance\\\",\\n  \\\"Online Payments\\\",\\n  \\\"Automated Reminders\\\"\\n]\"},\"pricing_1768890927894\":{\"heading\":\"Pricing Plans\",\"subheading\":\"Choose a plan that fits your gym\",\"plans\":\"[\\n  {\\n    \\\"name\\\": \\\"Basic\\\",\\n    \\\"price\\\": \\\"399.00\\\",\\n    \\\"button_text\\\": \\\"Get Started\\\",\\n    \\\"features\\\": [\\n      \\\"Member Management\\\",\\n      \\\"Attendance Tracking\\\",\\n      \\\"Online Payments\\\",\\n      \\\"ETC\\\"\\n    ]\\n  },\\n  {\\n    \\\"name\\\": \\\"Pro\\\",\\n    \\\"price\\\": \\\"999.00\\\",\\n    \\\"button_text\\\": \\\"Get Started\\\",\\n    \\\"features\\\": [\\n      \\\"Member Management\\\",\\n      \\\"Attendance Tracking\\\",\\n      \\\"Online Payments\\\",\\n      \\\"staff management\\\",\\n      \\\"Steam Bath\\\"\\n    ]\\n  }\\n]\"},\"testimonials_1768890928422\":{\"heading\":\"\",\"items\":\"[\\n  { \\\"name\\\": \\\"Rahul\\\", \\\"role\\\": \\\"Gym Owner\\\", \\\"message\\\": \\\"This platform saved us hours daily.\\\" },\\n  { \\\"name\\\": \\\"Ankit\\\", \\\"role\\\": \\\"Trainer\\\", \\\"message\\\": \\\"Members tracking is super easy now.\\\" }\\n]\"},\"gallery_1768890929014\":{\"heading\":\"\",\"images\":\"[\\n  \\\"https:\\/\\/picsum.photos\\/600\\/400?random=1\\\",\\n  \\\"https:\\/\\/picsum.photos\\/600\\/400?random=2\\\",\\n  \\\"https:\\/\\/picsum.photos\\/600\\/400?random=3\\\"\\n]\"},\"cta_1768890929669\":{\"heading\":\"\",\"subheading\":\"\",\"button_text\":\"\",\"button_link\":\"\"},\"register_form_1768890930118\":{\"title\":\"\",\"subtitle\":\"\",\"submit_text\":\"Submit Request\"},\"footer_1768890930758\":{\"brand\":\"\",\"tagline\":\"\",\"links\":\"\",\"email\":\"\"}}'),
(14, 'Gym Home', 'gym', '{\"sections\":[{\"id\":\"header_1\",\"type\":\"header\"},{\"id\":\"hero_1\",\"type\":\"hero\"},{\"id\":\"features_1\",\"type\":\"features\"},{\"id\":\"footer_1\",\"type\":\"footer\"}]}', 'active', '2026-01-20 06:59:04', '2026-01-20 12:29:04', '{\"header_1\":{\"logo_text\":\"My Gym\",\"button_text\":\"Join Now\",\"button_link\":\"#register\"},\r\n  \"hero_1\":{\"title\":\"Welcome to My Gym\",\"subtitle\":\"Train. Transform. Repeat.\",\"button_text\":\"Get Started\",\"button_link\":\"#register\"},\r\n  \"features_1\":{\"heading\":\"Why Join Us\",\"subheading\":\"\",\"items\":\"[\"Modern Equipments\",\"Certified Trainers\",\"Flexible Timings\"]\"},\r\n  \"footer_1\":{\"brand\":\"My Gym\",\"tagline\":\"Fitness made easy\",\"email\":\"support@mygym.com\",\"links\":\"\"}\r\n}'),
(15, 'Gym About', 'gym', '{\"sections\":[{\"id\":\"header_1\",\"type\":\"header\"},{\"id\":\"cta_1\",\"type\":\"cta\"},{\"id\":\"footer_1\",\"type\":\"footer\"}]}', 'active', '2026-01-20 06:59:04', '2026-01-20 12:29:04', '{\"header_1\":{\"logo_text\":\"My Gym\",\"button_text\":\"Join Now\",\"button_link\":\"#register\"},\r\n  \"cta_1\":{\"heading\":\"About Our Gym\",\"subheading\":\"We help you stay fit and strong.\",\"button_text\":\"Contact Us\",\"button_link\":\"/g/mygym/contact\"},\r\n  \"footer_1\":{\"brand\":\"My Gym\",\"tagline\":\"Fitness made easy\",\"email\":\"support@mygym.com\",\"links\":\"\"}\r\n}'),
(16, 'Gym Contact', 'gym', '{\"sections\":[{\"id\":\"header_1\",\"type\":\"header\"},{\"id\":\"register_form_1\",\"type\":\"register_form\"},{\"id\":\"footer_1\",\"type\":\"footer\"}]}', 'active', '2026-01-20 06:59:04', '2026-01-20 12:29:04', '{\"header_1\":{\"logo_text\":\"My Gym\",\"button_text\":\"Join Now\",\"button_link\":\"#register\"},\r\n  \"register_form_1\":{\"title\":\"Contact Us\",\"subtitle\":\"Send your details\",\"submit_text\":\"Submit\"},\r\n  \"footer_1\":{\"brand\":\"My Gym\",\"tagline\":\"Fitness made easy\",\"email\":\"support@mygym.com\",\"links\":\"\"}\r\n}');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `gym_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('super_admin','gym_admin','trainer','member') NOT NULL,
  `status` enum('active','blocked') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `force_password_change` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `gym_id`, `first_name`, `last_name`, `email`, `phone`, `avatar`, `password`, `role`, `status`, `created_at`, `force_password_change`) VALUES
(4, 1, 'Super', 'Admin', 'Superadmin@gmail.com', NULL, '/storage/uploads/users/user_4_1768397379.jpg', '$2y$10$0CtOpDXOfnmOcZyp8a3Qm.ddNh83aAr81MnYPeUH3GQR9KbvyPbHK', 'super_admin', 'active', '2025-12-18 12:08:04', 0),
(25, 34, 'Vipin', 'Parihar', 'vipinwork844@gmail.com', NULL, NULL, '$2y$10$/i3H2mU0Yh9zSv75EVDbyOyC2lu2C9hN56d7/hTznxfGRcxVVh76u', 'gym_admin', 'active', '2026-01-20 13:05:06', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `gyms`
--
ALTER TABLE `gyms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `slug_2` (`slug`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `gym_requests`
--
ALTER TABLE `gym_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_owner_email_pending` (`owner_email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_owner_email` (`owner_email`),
  ADD KEY `idx_plan_id` (`plan_id`);

--
-- Indexes for table `gym_settings`
--
ALTER TABLE `gym_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_gym_settings` (`gym_id`);

--
-- Indexes for table `gym_subscriptions`
--
ALTER TABLE `gym_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gym_id` (`gym_id`),
  ADD KEY `idx_plan_id` (`plan_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `gym_id` (`gym_id`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `status` (`status`),
  ADD KEY `issued_at` (`issued_at`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_gym_status` (`gym_id`,`status`);

--
-- Indexes for table `membership_plans`
--
ALTER TABLE `membership_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `template_id` (`template_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gym_id` (`gym_id`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `payments_fk_invoice` (`invoice_id`);

--
-- Indexes for table `templates`
--
ALTER TABLE `templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_gym` (`gym_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `gyms`
--
ALTER TABLE `gyms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `gym_requests`
--
ALTER TABLE `gym_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `gym_settings`
--
ALTER TABLE `gym_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `gym_subscriptions`
--
ALTER TABLE `gym_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `membership_plans`
--
ALTER TABLE `membership_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `templates`
--
ALTER TABLE `templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `gym_requests`
--
ALTER TABLE `gym_requests`
  ADD CONSTRAINT `fk_gym_requests_plan` FOREIGN KEY (`plan_id`) REFERENCES `membership_plans` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `gym_settings`
--
ALTER TABLE `gym_settings`
  ADD CONSTRAINT `fk_set_gym` FOREIGN KEY (`gym_id`) REFERENCES `gyms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `gym_subscriptions`
--
ALTER TABLE `gym_subscriptions`
  ADD CONSTRAINT `fk_gym_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `membership_plans` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sub_gym` FOREIGN KEY (`gym_id`) REFERENCES `gyms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_fk_gym` FOREIGN KEY (`gym_id`) REFERENCES `gyms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoices_fk_plan` FOREIGN KEY (`plan_id`) REFERENCES `membership_plans` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `members_ibfk_1` FOREIGN KEY (`gym_id`) REFERENCES `gyms` (`id`),
  ADD CONSTRAINT `members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `pages`
--
ALTER TABLE `pages`
  ADD CONSTRAINT `pages_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_fk_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_gym` FOREIGN KEY (`gym_id`) REFERENCES `gyms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`gym_id`) REFERENCES `gyms` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
