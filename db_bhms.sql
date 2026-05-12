-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 08, 2026 at 09:51 PM
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
-- Database: `db_bhms`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `corporate_account`
--

CREATE TABLE `corporate_account` (
  `corporate_id` int(11) NOT NULL,
  `guest_id` int(11) NOT NULL,
  `billing_contact` varchar(100) NOT NULL,
  `contract_rate` decimal(10,0) NOT NULL,
  `conpany_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `digital_keys`
--

CREATE TABLE `digital_keys` (
  `key_id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `access_code` varchar(255) NOT NULL,
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `status` enum('Active','Expired','Revoked') NOT NULL DEFAULT 'Active',
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `guest_id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL COMMENT '1-5 stars',
  `comment` text DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `folios`
--

CREATE TABLE `folios` (
  `folio_id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'EGP',
  `deposit_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Open','PartiallyPaid','Closed') NOT NULL DEFAULT 'Open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `folio_items`
--

CREATE TABLE `folio_items` (
  `item_id` int(11) NOT NULL,
  `folio_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `Tax` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `item_type` enum('Room','Service','Penalty','Deposit','Minibar','Other') NOT NULL,
  `added_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_booking`
--

CREATE TABLE `group_booking` (
  `group_booking_id` int(11) NOT NULL,
  `billing_mode` enum('Prepaid','Postpaid','Direct','Corporate') NOT NULL,
  `group_name` varchar(100) NOT NULL,
  `group_size` int(11) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `guests`
--

CREATE TABLE `guests` (
  `guest_id` int(11) NOT NULL,
  `F_name` varchar(50) NOT NULL,
  `L_name` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `vip_status` tinyint(1) NOT NULL DEFAULT 0,
  `loyalty_tier` enum('Bronze','Silver','Gold','Platinum') NOT NULL DEFAULT 'Bronze',
  `points` int(11) NOT NULL DEFAULT 0,
  `blacklisted` tinyint(1) NOT NULL DEFAULT 0,
  `blacklist_reason` text DEFAULT NULL,
  `anniversary` date DEFAULT NULL,
  `nationality` varchar(60) DEFAULT NULL,
  `passport_no` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `guest_phones`
--

CREATE TABLE `guest_phones` (
  `phone_id` int(11) NOT NULL,
  `guest_id` int(11) NOT NULL,
  `phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hk_tasks`
--

CREATE TABLE `hk_tasks` (
  `task_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `type` enum('Cleaning','Inspection','Turndown','DeepClean','Special') NOT NULL DEFAULT 'Cleaning',
  `status` enum('Pending','InProgress','Done','Skipped') NOT NULL DEFAULT 'Pending',
  `priority` tinyint(4) NOT NULL DEFAULT 2 COMMENT '1=Low,2=Med,3=High,4=Critical',
  `notes` text DEFAULT NULL,
  `score` tinyint(4) DEFAULT NULL COMMENT 'Inspection score 1-10',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inspection`
--

CREATE TABLE `inspection` (
  `inspect_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `date` datetime DEFAULT NULL,
  `note` text DEFAULT NULL,
  `status` enum('Pending','InProgress','Completed','Failed','RequiresMaintenance') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `item_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `threshold` int(11) NOT NULL DEFAULT 10,
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`item_id`, `name`, `category`, `quantity`, `threshold`, `unit`, `updated_at`) VALUES
(1, 'Bath Towels', 'Linen', 200, 50, 'pcs', '2026-05-08 02:45:09'),
(2, 'Hand Towels', 'Linen', 300, 80, 'pcs', '2026-05-08 02:45:09'),
(3, 'Bed Sheets', 'Linen', 100, 30, 'sets', '2026-05-08 02:45:09'),
(4, 'Shampoo Bottles', 'Consumable', 500, 100, 'pcs', '2026-05-08 02:45:09'),
(5, 'Soap Bars', 'Consumable', 500, 100, 'pcs', '2026-05-08 02:45:09'),
(6, 'Coffee Sachets', 'Minibar', 300, 60, 'pcs', '2026-05-08 02:45:09'),
(7, 'Water Bottles', 'Minibar', 400, 80, 'pcs', '2026-05-08 02:45:09');

-- --------------------------------------------------------

--
-- Table structure for table `item_usage`
--

CREATE TABLE `item_usage` (
  `item_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `quantity_used` decimal(10,2) NOT NULL,
  `usage_date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lost_found`
--

CREATE TABLE `lost_found` (
  `item_id` int(11) NOT NULL,
  `room_id` int(11) DEFAULT NULL,
  `guest_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `status` enum('Found','Claimed','Discarded') NOT NULL DEFAULT 'Found',
  `found_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_requests`
--

CREATE TABLE `maintenance_requests` (
  `request_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `reported_by` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `issue` text NOT NULL,
  `priority` enum('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
  `status` enum('Open','InProgress','Resolved','Escalated','OutOfOrder') NOT NULL DEFAULT 'Open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `folio_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `exchange_rate` decimal(8,4) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'EGP',
  `method` enum('Cash','CreditCard','ForeignCash','Split','BankTransfer') NOT NULL DEFAULT 'Cash',
  `status` enum('Pending','Completed','Refunded','Failed') NOT NULL DEFAULT 'Pending',
  `processed_by` int(11) DEFAULT NULL,
  `paid_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `preferences`
--

CREATE TABLE `preferences` (
  `pref_id` int(11) NOT NULL,
  `guest_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `value` varchar(255) NOT NULL,
  `likes` varchar(255) NOT NULL,
  `dislikes` varchar(255) NOT NULL,
  `allergies` varchar(255) NOT NULL,
  `sentiment_Score` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pricing_rules`
--

CREATE TABLE `pricing_rules` (
  `rule_id` int(11) NOT NULL,
  `type_id` int(11) DEFAULT NULL COMMENT 'NULL = applies to all room types',
  `rule_name` varchar(100) NOT NULL,
  `strategy` enum('Standard','Weekend','VIP','Occupancy','Seasonal') NOT NULL DEFAULT 'Standard',
  `adjustment` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'percentage e.g. 25.00 = +25%',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pricing_rules`
--

INSERT INTO `pricing_rules` (`rule_id`, `type_id`, `rule_name`, `strategy`, `adjustment`, `start_date`, `end_date`, `active`) VALUES
(1, NULL, 'Standard Rate', 'Standard', 0.00, NULL, NULL, 1),
(2, NULL, 'Weekend Premium', 'Weekend', 25.00, NULL, NULL, 1),
(3, NULL, 'VIP Discount', 'VIP', -15.00, NULL, NULL, 1),
(4, NULL, 'High Occupancy', 'Occupancy', 20.00, NULL, NULL, 1),
(5, NULL, 'Low Season', 'Seasonal', -10.00, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `reservation_id` int(11) NOT NULL,
  `guest_id` int(11) NOT NULL,
  `room_id` int(11) DEFAULT NULL,
  `group_booking_id` int(11) NOT NULL,
  `check_in_date` date NOT NULL,
  `check_out_date` date NOT NULL,
  `adults` int(11) NOT NULL DEFAULT 1,
  `children` int(11) NOT NULL DEFAULT 0,
  `status` enum('Inquiry','Confirmed','CheckedIn','CheckedOut','Cancelled','NoShow','FolioClosed') NOT NULL DEFAULT 'Inquiry',
  `special_request` text DEFAULT NULL,
  `deposit_amount` decimal(10,2) DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservation_state_log`
--

CREATE TABLE `reservation_state_log` (
  `log_id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `from_state` enum('Inquiry','Confirmed','CheckedIn','CheckedOut','Cancelled','NoShow','Closed') DEFAULT NULL,
  `to_state` enum('Inquiry','Confirmed','CheckedIn','CheckedOut','Cancelled','NoShow','Closed') NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `type_id` int(11) NOT NULL,
  `room_number` varchar(10) NOT NULL,
  `floor` int(11) NOT NULL,
  `status` enum('Clean','Dirty','Occupied','InCleaning','Inspecting','Ready','OutOfOrder') NOT NULL DEFAULT 'Ready',
  `notes` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `type_id`, `room_number`, `floor`, `status`, `notes`, `updated_at`) VALUES
(1, 1, '101', 1, 'Ready', NULL, '2026-05-08 02:45:09'),
(2, 1, '102', 1, 'Ready', NULL, '2026-05-08 02:45:09'),
(3, 1, '103', 1, 'Ready', NULL, '2026-05-08 02:45:09'),
(4, 2, '201', 2, 'Ready', NULL, '2026-05-08 02:45:09'),
(5, 2, '202', 2, 'Ready', NULL, '2026-05-08 02:45:09'),
(6, 2, '203', 2, 'Ready', NULL, '2026-05-08 02:45:09'),
(7, 3, '301', 3, 'Ready', NULL, '2026-05-08 02:45:09'),
(8, 3, '302', 3, 'Ready', NULL, '2026-05-08 02:45:09'),
(9, 4, '401', 4, 'Ready', NULL, '2026-05-08 02:45:09'),
(10, 5, '501', 5, 'Ready', NULL, '2026-05-08 02:45:09');

-- --------------------------------------------------------

--
-- Table structure for table `room_types`
--

CREATE TABLE `room_types` (
  `type_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 2,
  `base_price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `amenities` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_types`
--

INSERT INTO `room_types` (`type_id`, `name`, `capacity`, `base_price`, `description`, `amenities`) VALUES
(1, 'Standard Single', 1, 500.00, 'Comfortable single room with all basic amenities', NULL),
(2, 'Standard Double', 2, 800.00, 'Spacious double room with twin or double bed', NULL),
(3, 'Deluxe Double', 2, 1200.00, 'Deluxe room with premium furnishings and city view', NULL),
(4, 'Junior Suite', 3, 2000.00, 'Suite with separate living area', NULL),
(5, 'Presidential', 4, 5000.00, 'Top floor presidential suite with butler service', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `category` enum('Spa','Cafe','Minibar','Tour','Laundry','Other') NOT NULL DEFAULT 'Other',
  `price` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `name`, `description`, `category`, `price`, `is_active`) VALUES
(1, 'Swedish Massage', '', 'Spa', 250.00, 1),
(2, 'Room Service', '', 'Cafe', 80.00, 1),
(3, 'Mini Bar Package', '', 'Minibar', 150.00, 1),
(4, 'Airport Transfer', '', 'Other', 200.00, 1),
(5, 'Late Checkout', '', 'Other', 100.00, 1),
(6, 'Early Checkin', '', 'Other', 100.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `staff_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Receptionist','Housekeeper','Supervisor','Manager','Accountant','Admin','NightAuditor') NOT NULL DEFAULT 'Receptionist'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`staff_id`, `name`, `email`, `password_hash`, `role`) VALUES
(1, 'System Admin', 'admin@bhms.com', '$2y$10$examplehashADMIN', 'Admin'),
(2, 'John Manager', 'manager@bhms.com', '$2y$10$examplehashMANAGER', 'Manager'),
(3, 'Sara Reception', 'reception@bhms.com', '$2y$10$examplehashRECEPTION', 'Receptionist'),
(4, 'Omar HK', 'housekeeper@bhms.com', '$2y$10$examplehashHOUSEKEEPER', 'Housekeeper'),
(5, 'Alex Auditor', 'auditor@bhms.com', '$2y$10$examplehashAUDITOR', 'NightAuditor');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_active_reservations`
-- (See below for the actual view)
--
CREATE TABLE `v_active_reservations` (
`reservation_id` int(11)
,`guest_name` varchar(101)
,`guest_email` varchar(150)
,`vip_status` tinyint(1)
,`room_number` varchar(10)
,`room_type` varchar(50)
,`check_in_date` date
,`check_out_date` date
,`nights` int(7)
,`status` enum('Inquiry','Confirmed','CheckedIn','CheckedOut','Cancelled','NoShow','FolioClosed')
,`total_amount` decimal(10,2)
,`folio_status` enum('Open','PartiallyPaid','Closed')
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_room_status`
-- (See below for the actual view)
--
CREATE TABLE `v_room_status` (
`room_id` int(11)
,`room_number` varchar(10)
,`floor` int(11)
,`status` enum('Clean','Dirty','Occupied','InCleaning','Inspecting','Ready','OutOfOrder')
,`room_type` varchar(50)
,`base_price` decimal(10,2)
,`current_guest` varchar(101)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_staff_tasks`
-- (See below for the actual view)
--
CREATE TABLE `v_staff_tasks` (
`staff_id` int(11)
,`name` varchar(100)
,`role` enum('Receptionist','Housekeeper','Supervisor','Manager','Accountant','Admin','NightAuditor')
,`total_tasks` bigint(21)
,`pending` decimal(23,0)
,`in_progress` decimal(23,0)
,`done` decimal(23,0)
,`avg_score` decimal(5,1)
);

-- --------------------------------------------------------

--
-- Structure for view `v_active_reservations`
--
DROP TABLE IF EXISTS `v_active_reservations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_active_reservations`  AS SELECT `r`.`reservation_id` AS `reservation_id`, concat(`g`.`F_name`,' ',`g`.`L_name`) AS `guest_name`, `g`.`email` AS `guest_email`, `g`.`vip_status` AS `vip_status`, `rm`.`room_number` AS `room_number`, `rt`.`name` AS `room_type`, `r`.`check_in_date` AS `check_in_date`, `r`.`check_out_date` AS `check_out_date`, to_days(`r`.`check_out_date`) - to_days(`r`.`check_in_date`) AS `nights`, `r`.`status` AS `status`, `f`.`total_amount` AS `total_amount`, `f`.`status` AS `folio_status` FROM ((((`reservations` `r` join `guests` `g` on(`r`.`guest_id` = `g`.`guest_id`)) left join `rooms` `rm` on(`r`.`room_id` = `rm`.`room_id`)) left join `room_types` `rt` on(`rm`.`type_id` = `rt`.`type_id`)) left join `folios` `f` on(`r`.`reservation_id` = `f`.`reservation_id`)) WHERE `r`.`status` in ('Confirmed','CheckedIn') ;

-- --------------------------------------------------------

--
-- Structure for view `v_room_status`
--
DROP TABLE IF EXISTS `v_room_status`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_room_status`  AS SELECT `rm`.`room_id` AS `room_id`, `rm`.`room_number` AS `room_number`, `rm`.`floor` AS `floor`, `rm`.`status` AS `status`, `rt`.`name` AS `room_type`, `rt`.`base_price` AS `base_price`, coalesce(concat(`g`.`F_name`,' ',`g`.`L_name`),'Vacant') AS `current_guest` FROM (((`rooms` `rm` join `room_types` `rt` on(`rm`.`type_id` = `rt`.`type_id`)) left join `reservations` `r` on(`rm`.`room_id` = `r`.`room_id` and `r`.`status` = 'CheckedIn')) left join `guests` `g` on(`r`.`guest_id` = `g`.`guest_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_staff_tasks`
--
DROP TABLE IF EXISTS `v_staff_tasks`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_staff_tasks`  AS SELECT `s`.`staff_id` AS `staff_id`, `s`.`name` AS `name`, `s`.`role` AS `role`, count(`h`.`task_id`) AS `total_tasks`, sum(`h`.`status` = 'Pending') AS `pending`, sum(`h`.`status` = 'InProgress') AS `in_progress`, sum(`h`.`status` = 'Done') AS `done`, round(avg(`h`.`score`),1) AS `avg_score` FROM (`staff` `s` left join `hk_tasks` `h` on(`s`.`staff_id` = `h`.`assigned_to`)) GROUP BY `s`.`staff_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_audit_staff` (`staff_id`),
  ADD KEY `idx_audit_time` (`timestamp`);

--
-- Indexes for table `corporate_account`
--
ALTER TABLE `corporate_account`
  ADD PRIMARY KEY (`corporate_id`),
  ADD KEY `guest_id` (`guest_id`);

--
-- Indexes for table `digital_keys`
--
ALTER TABLE `digital_keys`
  ADD PRIMARY KEY (`key_id`),
  ADD KEY `fk_key_res` (`reservation_id`),
  ADD KEY `fk_key_staff` (`created_by`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `fk_fb_guest` (`guest_id`),
  ADD KEY `fk_fb_res` (`reservation_id`);

--
-- Indexes for table `folios`
--
ALTER TABLE `folios`
  ADD PRIMARY KEY (`folio_id`),
  ADD UNIQUE KEY `uq_folio_reservation` (`reservation_id`);

--
-- Indexes for table `folio_items`
--
ALTER TABLE `folio_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `fk_service` (`service_id`),
  ADD KEY `fk_charge_staff` (`added_by`),
  ADD KEY `idx_charge_folio` (`folio_id`);

--
-- Indexes for table `group_booking`
--
ALTER TABLE `group_booking`
  ADD PRIMARY KEY (`group_booking_id`);

--
-- Indexes for table `guests`
--
ALTER TABLE `guests`
  ADD PRIMARY KEY (`guest_id`),
  ADD UNIQUE KEY `uq_email` (`email`);

--
-- Indexes for table `guest_phones`
--
ALTER TABLE `guest_phones`
  ADD PRIMARY KEY (`phone_id`),
  ADD KEY `FK_geust_no` (`guest_id`);

--
-- Indexes for table `hk_tasks`
--
ALTER TABLE `hk_tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `fk_hk_staff` (`assigned_to`),
  ADD KEY `idx_hk_room` (`room_id`),
  ADD KEY `idx_hk_status` (`status`);

--
-- Indexes for table `inspection`
--
ALTER TABLE `inspection`
  ADD PRIMARY KEY (`inspect_id`),
  ADD KEY `fk_inspect_staff` (`staff_id`),
  ADD KEY `fk_inspect_room` (`room_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`item_id`);

--
-- Indexes for table `item_usage`
--
ALTER TABLE `item_usage`
  ADD PRIMARY KEY (`task_id`,`item_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `lost_found`
--
ALTER TABLE `lost_found`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `fk_lf_room` (`room_id`),
  ADD KEY `fk_lf_guest` (`guest_id`);

--
-- Indexes for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `fk_maint_room` (`room_id`),
  ADD KEY `fk_maint_reporter` (`reported_by`),
  ADD KEY `fk_maint_assigned` (`assigned_to`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `fk_pay_staff` (`processed_by`),
  ADD KEY `idx_pay_folio` (`folio_id`);

--
-- Indexes for table `preferences`
--
ALTER TABLE `preferences`
  ADD PRIMARY KEY (`pref_id`),
  ADD KEY `fk_pref_guest` (`guest_id`);

--
-- Indexes for table `pricing_rules`
--
ALTER TABLE `pricing_rules`
  ADD PRIMARY KEY (`rule_id`),
  ADD KEY `fk_price_type` (`type_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`reservation_id`),
  ADD KEY `fk_res_room` (`room_id`),
  ADD KEY `fk_res_created` (`created_by`),
  ADD KEY `idx_res_guest` (`guest_id`),
  ADD KEY `idx_res_status` (`status`),
  ADD KEY `idx_res_dates` (`check_in_date`,`check_out_date`),
  ADD KEY `group_booking_id` (`group_booking_id`);

--
-- Indexes for table `reservation_state_log`
--
ALTER TABLE `reservation_state_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_statelog_res` (`reservation_id`),
  ADD KEY `fk_statelog_staff` (`changed_by`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD UNIQUE KEY `uq_room_number` (`room_number`),
  ADD KEY `fk_rooms_type` (`type_id`);

--
-- Indexes for table `room_types`
--
ALTER TABLE `room_types`
  ADD PRIMARY KEY (`type_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `uq_staff_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `corporate_account`
--
ALTER TABLE `corporate_account`
  MODIFY `corporate_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `digital_keys`
--
ALTER TABLE `digital_keys`
  MODIFY `key_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `folios`
--
ALTER TABLE `folios`
  MODIFY `folio_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `folio_items`
--
ALTER TABLE `folio_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_booking`
--
ALTER TABLE `group_booking`
  MODIFY `group_booking_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `guests`
--
ALTER TABLE `guests`
  MODIFY `guest_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `guest_phones`
--
ALTER TABLE `guest_phones`
  MODIFY `phone_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hk_tasks`
--
ALTER TABLE `hk_tasks`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inspection`
--
ALTER TABLE `inspection`
  MODIFY `inspect_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `lost_found`
--
ALTER TABLE `lost_found`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `preferences`
--
ALTER TABLE `preferences`
  MODIFY `pref_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pricing_rules`
--
ALTER TABLE `pricing_rules`
  MODIFY `rule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `reservation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservation_state_log`
--
ALTER TABLE `reservation_state_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `room_types`
--
ALTER TABLE `room_types`
  MODIFY `type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`);

--
-- Constraints for table `corporate_account`
--
ALTER TABLE `corporate_account`
  ADD CONSTRAINT `corporate_account_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`guest_id`);

--
-- Constraints for table `digital_keys`
--
ALTER TABLE `digital_keys`
  ADD CONSTRAINT `fk_key_res` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`),
  ADD CONSTRAINT `fk_key_staff` FOREIGN KEY (`created_by`) REFERENCES `staff` (`staff_id`);

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `fk_fb_guest` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`guest_id`),
  ADD CONSTRAINT `fk_fb_res` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`);

--
-- Constraints for table `folios`
--
ALTER TABLE `folios`
  ADD CONSTRAINT `fk_folio_res` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`);

--
-- Constraints for table `folio_items`
--
ALTER TABLE `folio_items`
  ADD CONSTRAINT `fk_charge_folio` FOREIGN KEY (`folio_id`) REFERENCES `folios` (`folio_id`),
  ADD CONSTRAINT `fk_charge_staff` FOREIGN KEY (`added_by`) REFERENCES `staff` (`staff_id`),
  ADD CONSTRAINT `fk_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`);

--
-- Constraints for table `guest_phones`
--
ALTER TABLE `guest_phones`
  ADD CONSTRAINT `FK_geust_no` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`guest_id`);

--
-- Constraints for table `hk_tasks`
--
ALTER TABLE `hk_tasks`
  ADD CONSTRAINT `fk_hk_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`),
  ADD CONSTRAINT `fk_hk_staff` FOREIGN KEY (`assigned_to`) REFERENCES `staff` (`staff_id`);

--
-- Constraints for table `inspection`
--
ALTER TABLE `inspection`
  ADD CONSTRAINT `fk_inspect_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`),
  ADD CONSTRAINT `fk_inspect_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`);

--
-- Constraints for table `item_usage`
--
ALTER TABLE `item_usage`
  ADD CONSTRAINT `item_usage_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `hk_tasks` (`task_id`),
  ADD CONSTRAINT `item_usage_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory` (`item_id`);

--
-- Constraints for table `lost_found`
--
ALTER TABLE `lost_found`
  ADD CONSTRAINT `fk_lf_guest` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`guest_id`),
  ADD CONSTRAINT `fk_lf_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`);

--
-- Constraints for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD CONSTRAINT `fk_maint_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `staff` (`staff_id`),
  ADD CONSTRAINT `fk_maint_reporter` FOREIGN KEY (`reported_by`) REFERENCES `staff` (`staff_id`),
  ADD CONSTRAINT `fk_maint_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_pay_folio` FOREIGN KEY (`folio_id`) REFERENCES `folios` (`folio_id`),
  ADD CONSTRAINT `fk_pay_staff` FOREIGN KEY (`processed_by`) REFERENCES `staff` (`staff_id`);

--
-- Constraints for table `preferences`
--
ALTER TABLE `preferences`
  ADD CONSTRAINT `fk_pref_guest` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`guest_id`);

--
-- Constraints for table `pricing_rules`
--
ALTER TABLE `pricing_rules`
  ADD CONSTRAINT `fk_price_type` FOREIGN KEY (`type_id`) REFERENCES `room_types` (`type_id`);

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `fk_res_created` FOREIGN KEY (`created_by`) REFERENCES `staff` (`staff_id`),
  ADD CONSTRAINT `fk_res_guest` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`guest_id`),
  ADD CONSTRAINT `fk_res_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`),
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`group_booking_id`) REFERENCES `group_booking` (`group_booking_id`);

--
-- Constraints for table `reservation_state_log`
--
ALTER TABLE `reservation_state_log`
  ADD CONSTRAINT `fk_statelog_res` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`),
  ADD CONSTRAINT `fk_statelog_staff` FOREIGN KEY (`changed_by`) REFERENCES `staff` (`staff_id`);

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `fk_rooms_type` FOREIGN KEY (`type_id`) REFERENCES `room_types` (`type_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
