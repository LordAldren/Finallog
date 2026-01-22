--
-- Database: `logistics_db`
--
CREATE DATABASE IF NOT EXISTS `logistics_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `logistics_db`;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff','driver') NOT NULL DEFAULT 'staff',
  `failed_login_attempts` int(11) DEFAULT 0,
  `lockout_until` datetime DEFAULT NULL,
  `employee_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users` (UNCHANGED AS REQUESTED)
--
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `employee_id`, `created_at`) VALUES
(1, 'admin', 'admin@slate.com', '$2y$10$E.4a4/yLp2F.1rD.2n9R1e/U/Q.Da/3n29n4i.eJ.s2Q4.sW6w8.m', 'admin', 'SLATE-001', '2025-10-27 01:00:00'),
(2, 'staff', 'staff1@slate.com', '$2y$10$E.4a4/yLp2F.1rD.2n9R1e/U/Q.Da/3n29n4i.eJ.s2Q4.sW6w8.m', 'staff', 'SLATE-002', '2025-10-27 01:00:00'),
(3, 'driver_john', 'johndoe@email.com', '$2y$10$E.4a4/yLp2F.1rD.2n9R1e/U/Q.Da/3n29n4i.eJ.s2Q4.sW6w8.m', 'driver', 'SLATE-003', '2025-10-27 01:01:00'),
(4, 'driver_jane', 'janesmith@email.com', '$2y$10$E.4a4/yLp2F.1rD.2n9R1e/U/Q.Da/3n29n4i.eJ.s2Q4.sW6w8.m', 'driver', 'SLATE-004', '2025-10-27 01:02:00'),
(5, 'driver_peter', 'peterjones@email.com', '$2y$10$E.4a4/yLp2F.1rD.2n9R1e/U/Q.Da/3n29n4i.eJ.s2Q4.sW6w8.m', 'driver', 'SLATE-005', '2025-10-27 01:03:00'),
(6, 'vehicle_ELF001', 'vehicle_elf001@slate.com', '$2y$10$E.4a4/yLp2F.1rD.2n9R1e/U/Q.Da/3n29n4i.eJ.s2Q4.sW6w8.m', 'driver', 'SLATE-V01', '2025-10-27 01:04:00'),
(7, 'vehicle_HIA002', 'vehicle_hia002@slate.com', '$2y$10$E.4a4/yLp2F.1rD.2n9R1e/U/Q.Da/3n29n4i.eJ.s2Q4.sW6w8.m', 'driver', 'SLATE-V02', '2025-10-27 01:05:00'),
(8, 'pending_driver', 'newdriver@email.com', '$2y$10$E.4a4/yLp2F.1rD.2n9R1e/U/Q.Da/3n29n4i.eJ.s2Q4.sW6w8.m', 'driver', 'SLATE-008', '2025-10-27 01:06:00');

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--
DROP TABLE IF EXISTS `drivers`;
CREATE TABLE `drivers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `license_number` varchar(50) NOT NULL,
  `license_expiry_date` date DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `date_joined` date DEFAULT NULL,
  `status` enum('Active','Suspended','Inactive','Pending') NOT NULL DEFAULT 'Pending',
  `rating` decimal(3,1) DEFAULT 0.0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `license_number` (`license_number`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping new data for table `drivers`
--
INSERT INTO `drivers` (`id`, `user_id`, `name`, `license_number`, `license_expiry_date`, `contact_number`, `date_joined`, `status`, `rating`, `created_at`) VALUES
(1, 3, 'John Doe', 'D01-23-456789', '2028-05-20', '09171234567', '2023-01-15', 'Active', 4.8, '2025-10-27 02:01:00'),
(2, 4, 'Jane Smith', 'D02-34-567890', '2026-11-30', '09182345678', '2023-03-22', 'Active', 4.9, '2025-10-27 02:02:00'),
(3, 5, 'Peter Jones', 'D03-45-678901', '2027-08-10', '09283456789', '2024-06-01', 'Active', 4.5, '2025-10-27 02:03:00'),
(4, NULL, 'Robert Williams', 'D04-11-223344', '2029-01-15', '09194567890', '2024-08-01', 'Active', 4.6, '2025-10-27 02:04:00'),
(5, NULL, 'Maria Garcia', 'D05-22-334455', '2028-03-12', '09275678901', '2024-09-10', 'Active', 4.7, '2025-10-27 02:05:00'),
(6, 8, 'New Applicant Driver', 'D04-56-789012', '2029-01-01', '09123456789', '2025-10-27', 'Pending', 0.0, '2025-10-27 02:06:00');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--
DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aggregate_id` int(11) NOT NULL,
  `event_type` varchar(255) NOT NULL,
  `event_payload` json NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--
DROP TABLE IF EXISTS `vehicles`;
CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `tag_type` varchar(50) DEFAULT NULL,
  `tag_code` varchar(100) DEFAULT NULL,
  `load_capacity_kg` int(11) DEFAULT NULL,
  `plate_no` varchar(20) DEFAULT NULL,
  `status` enum('Active','Inactive','Maintenance','En Route','Idle','Breakdown') NOT NULL DEFAULT 'Active',
  `assigned_driver_id` int(11) DEFAULT NULL,
  `image_url` varchar(2083) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tag_code` (`tag_code`),
  UNIQUE KEY `plate_no` (`plate_no`),
  KEY `assigned_driver_id` (`assigned_driver_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping new data for table `vehicles`
--
INSERT INTO `vehicles` (`id`, `type`, `model`, `tag_type`, `tag_code`, `load_capacity_kg`, `plate_no`, `status`, `assigned_driver_id`, `image_url`, `created_at`) VALUES
(1, 'Light-Duty Truck', 'Isuzu Elf NPR', 'RFID', 'ELF001', 4500, 'ABC-1234', 'Active', 1, 'elf.PNG', '2025-10-27 04:01:00'),
(2, 'Van', 'Toyota Hiace', 'RFID', 'HIA002', 1200, 'DEF-5678', 'En Route', 2, 'hiace.PNG', '2025-10-27 04:02:00'),
(3, '4-Wheeler Truck', 'Mitsubishi Fuso Canter', 'RFID', 'CAN003', 4000, 'GHI-9012', 'Idle', 5, 'canter.PNG', '2025-10-27 04:03:00'),
(4, 'Light-Duty Truck', 'Isuzu Elf NKR', 'RFID', 'ELF004', 4200, 'JKL-3456', 'Maintenance', NULL, 'elf.PNG', '2025-10-27 04:04:00'),
(5, '6-Wheeler Truck', 'Hino 300', 'RFID', 'HIN005', 7500, 'MNO-7890', 'Active', NULL, NULL, '2025-10-27 04:05:00'),
(6, 'Motorcycle', 'Honda Click 150i', 'RFID', 'MOT006', 150, 'PQR-123', 'Active', NULL, NULL, '2025-10-27 04:06:00');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--
DROP TABLE IF EXISTS `reservations`;
CREATE TABLE `reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_code` varchar(20) NOT NULL,
  `client_name` varchar(100) NOT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `reserved_by_user_id` int(11) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `reservation_date` date NOT NULL,
  `status` enum('Confirmed','Pending','Cancelled','Rejected') NOT NULL DEFAULT 'Pending',
  `load_capacity_needed` int(11) DEFAULT NULL,
  `destination_address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reservation_code` (`reservation_code`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `reserved_by_user_id` (`reserved_by_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping new data for table `reservations`
--
INSERT INTO `reservations` (`id`, `reservation_code`, `client_name`, `vehicle_id`, `reserved_by_user_id`, `purpose`, `reservation_date`, `status`, `load_capacity_needed`, `destination_address`, `created_at`) VALUES
(1, 'R20251027001', 'ABC Corporation', 2, 2, 'Urgent delivery of documents.', '2025-10-28', 'Confirmed', 50, 'Makati City Hall, Makati', '2025-10-27 05:01:00'),
(2, 'R20251027002', 'XYZ Logistics', 1, 2, 'Pickup of cargo from port area.', '2025-10-29', 'Confirmed', 4000, 'Manila Port Area, Manila', '2025-10-27 05:02:00'),
(3, 'R20251027003', 'Sample Client Inc.', NULL, 2, 'Awaiting vehicle assignment for electronics transport.', '2025-10-30', 'Pending', 2000, 'Technohub, Quezon City', '2025-10-27 05:03:00'),
(4, 'R20251027004', 'Past Delivery Co.', 3, 2, 'Completed delivery last week.', '2025-10-20', 'Confirmed', 3500, 'Laguna Technopark, Biñan, Laguna', '2025-10-20 05:04:00'),
(5, 'R20251027005', 'Cancelled Booking', 1, 2, 'Client cancelled due to schedule changes.', '2025-11-05', 'Cancelled', 1500, 'SM Megamall, Mandaluyong', '2025-10-27 05:05:00');

-- --------------------------------------------------------

--
-- Table structure for table `trips`
--
DROP TABLE IF EXISTS `trips`;
CREATE TABLE `trips` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` int(11) DEFAULT NULL,
  `trip_code` varchar(20) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `pickup_time` datetime NOT NULL,
  `destination` varchar(255) NOT NULL,
  `status` enum('Scheduled','Completed','Cancelled','En Route','Breakdown','Idle','Arrived at Destination','Unloading') NOT NULL DEFAULT 'Scheduled',
  `current_location` varchar(255) DEFAULT NULL,
  `eta` datetime DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `proof_of_delivery_path` varchar(255) DEFAULT NULL,
  `delivery_notes` text DEFAULT NULL,
  `route_adherence_score` decimal(5,2) DEFAULT 100.00,
  `route_deviations` int(11) DEFAULT 0,
  `actual_arrival_time` datetime DEFAULT NULL,
  `arrival_status` enum('Pending','On-Time','Early','Late') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `trip_code` (`trip_code`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `driver_id` (`driver_id`),
  KEY `reservation_id` (`reservation_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping new data for table `trips` (MORE COMPLETED TRIPS)
--
INSERT INTO `trips` (`id`, `reservation_id`, `trip_code`, `vehicle_id`, `driver_id`, `client_name`, `pickup_time`, `destination`, `status`, `proof_of_delivery_path`, `delivery_notes`, `route_adherence_score`, `route_deviations`, `actual_arrival_time`, `arrival_status`, `created_at`) VALUES
(1, 1, 'T20251028001', 2, 2, 'ABC Corporation', '2025-10-28 09:00:00', 'Makati City Hall, Makati', 'En Route', NULL, NULL, 98.50, 1, NULL, 'Pending', '2025-10-27 06:01:00'),
(2, 2, 'T20251029002', 1, 1, 'XYZ Logistics', '2025-10-29 08:00:00', 'Manila Port Area, Manila', 'Scheduled', NULL, NULL, 100.00, 0, NULL, 'Pending', '2025-10-27 06:02:00'),
(3, 4, 'T20251020003', 3, 4, 'Past Delivery Co.', '2025-10-20 10:00:00', 'Laguna Technopark, Biñan, Laguna', 'Completed', 'uploads/pod/sample_pod.jpg', 'Received by accounting department.', 95.00, 3, '2025-10-20 11:55:00', 'Early', '2025-10-20 06:03:00'),
(4, 5, 'T20251105004', 1, 3, 'Cancelled Booking', '2025-11-05 13:00:00', 'SM Megamall, Mandaluyong', 'Cancelled', NULL, 'Client request.', 100.00, 0, NULL, 'Pending', '2025-10-27 06:04:00'),
(5, NULL, 'T20251001005', 1, 1, 'PharmaServe Inc.', '2025-10-01 08:00:00', 'St. Lukes Medical Center, Quezon City', 'Completed', NULL, 'Medical supplies delivered.', 100.00, 0, '2025-10-01 09:30:00', 'On-Time', '2025-10-01 06:05:00'),
(6, NULL, 'T20251002006', 3, 3, 'BuildRight Hardware', '2025-10-02 10:00:00', 'Construction Site, Taguig', 'Completed', NULL, 'Construction materials unloaded.', 97.00, 2, '2025-10-02 11:45:00', 'Early', '2025-10-02 06:06:00'),
(7, NULL, 'T20251003007', 2, 2, 'FreshFoods Corp.', '2025-10-03 05:00:00', 'Farmers Market, Cubao', 'Completed', NULL, 'Perishables delivered.', 99.00, 1, '2025-10-03 06:15:00', 'On-Time', '2025-10-03 06:07:00'),
(8, NULL, 'T20251004008', 5, 4, 'TechGiant Electronics', '2025-10-04 13:00:00', 'Cyberpark, Araneta City', 'Completed', NULL, 'Server equipment delivered.', 100.00, 0, '2025-10-04 14:00:00', 'On-Time', '2025-10-04 06:08:00'),
(9, NULL, 'T20251005009', 1, 5, 'Metro Apparels', '2025-10-05 09:30:00', 'Greenhills Shopping Center', 'Completed', NULL, 'Textile rolls received.', 96.50, 3, '2025-10-05 11:00:00', 'Late', '2025-10-05 06:09:00'),
(10, NULL, 'T20251006010', 3, 1, 'AgriProduce Co.', '2025-10-06 04:00:00', 'Balintawak Market, Quezon City', 'Completed', NULL, 'Vegetable crates delivered.', 100.00, 0, '2025-10-06 05:00:00', 'Early', '2025-10-06 06:10:00'),
(11, NULL, 'T20251007011', 2, 2, 'Corporate Solutions Ltd.', '2025-10-07 11:00:00', 'Ayala Avenue, Makati', 'Completed', NULL, 'Office documents delivered.', 98.00, 1, '2025-10-07 12:30:00', 'On-Time', '2025-10-07 06:11:00'),
(12, NULL, 'T20251008012', 5, 3, 'HomeBuilders Depot', '2025-10-08 14:00:00', 'Warehouse, Valenzuela', 'Completed', NULL, 'Cement bags delivered.', 95.00, 4, '2025-10-08 16:00:00', 'Late', '2025-10-08 06:12:00'),
(13, NULL, 'T20251009013', 1, 4, 'QuickMeds Pharmacy', '2025-10-09 08:30:00', 'Mercury Drug, Pasig', 'Completed', NULL, 'Medicine boxes delivered.', 100.00, 0, '2025-10-09 09:45:00', 'On-Time', '2025-10-09 06:13:00'),
(14, NULL, 'T20251010014', 3, 5, 'National Bookstore', '2025-10-10 10:00:00', 'SM City North EDSA', 'Completed', NULL, 'Books and supplies delivered.', 99.50, 1, '2025-10-10 11:10:00', 'Early', '2025-10-10 06:14:00'),
(15, NULL, 'T20251011015', 6, 1, 'FastDocs Courier', '2025-10-11 13:00:00', 'BGC, Taguig', 'Completed', NULL, 'Legal documents delivered.', 100.00, 0, '2025-10-11 14:00:00', 'On-Time', '2025-10-11 06:15:00'),
(16, NULL, 'T20251012016', 1, 2, 'Cebu Pacific Cargo', '2025-10-12 20:00:00', 'NAIA Terminal 3', 'Completed', NULL, 'Air cargo delivered.', 97.00, 2, '2025-10-12 21:30:00', 'On-Time', '2025-10-12 06:16:00'),
(17, NULL, 'T20251013017', 5, 3, 'San Miguel Corporation', '2025-10-13 07:00:00', 'SMC Complex, Ortigas', 'Completed', NULL, 'Product samples delivered.', 100.00, 0, '2025-10-13 07:45:00', 'Early', '2025-10-13 06:17:00'),
(18, NULL, 'T20251014018', 3, 4, 'Lazada Warehouse', '2025-10-14 09:00:00', 'Cabuyao, Laguna', 'Completed', NULL, 'Online orders for dispatch.', 94.00, 5, '2025-10-14 11:30:00', 'Late', '2025-10-14 06:18:00'),
(19, NULL, 'T20251015019', 2, 5, 'Philippine Red Cross', '2025-10-15 10:30:00', 'PRC Tower, Mandaluyong', 'Completed', NULL, 'Donation goods delivered.', 100.00, 0, '2025-10-15 11:15:00', 'On-Time', '2025-10-15 06:19:00'),
(20, NULL, 'T20251016020', 1, 1, 'Jollibee Foods Corp.', '2025-10-16 03:00:00', 'Jollibee Plaza, Ortigas', 'Completed', NULL, 'Frozen goods delivered.', 100.00, 0, '2025-10-16 04:00:00', 'On-Time', '2025-10-16 06:20:00');

-- --------------------------------------------------------

--
-- Table structure for table `trip_costs`
--
DROP TABLE IF EXISTS `trip_costs`;
CREATE TABLE `trip_costs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trip_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `fuel_cost` decimal(10,2) DEFAULT 0.00,
  `labor_cost` decimal(10,2) DEFAULT 0.00,
  `tolls_cost` decimal(10,2) DEFAULT 0.00,
  `other_cost` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) GENERATED ALWAYS AS (`fuel_cost` + `labor_cost` + `tolls_cost` + `other_cost`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `trip_id` (`trip_id`),
  KEY `vehicle_id` (`vehicle_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping new data for table `trip_costs` (FOR AI PREDICTOR)
--
INSERT INTO `trip_costs` (`id`, `trip_id`, `vehicle_id`, `fuel_cost`, `labor_cost`, `tolls_cost`, `other_cost`) VALUES
(1, 3, 3, 1250.75, 500.00, 450.00, 100.00),
(5, 5, 1, 850.00, 300.00, 150.00, 50.00),
(6, 6, 3, 1500.50, 600.00, 300.00, 0.00),
(7, 7, 2, 700.00, 250.00, 80.00, 25.00),
(8, 8, 5, 2800.00, 1000.00, 500.00, 150.00),
(9, 9, 1, 950.25, 400.00, 200.00, 75.00),
(10, 10, 3, 1300.00, 550.00, 100.00, 0.00),
(11, 11, 2, 800.75, 350.00, 120.00, 0.00),
(12, 12, 5, 3200.00, 1200.00, 650.00, 200.00),
(13, 13, 1, 900.00, 380.00, 180.00, 40.00),
(14, 14, 3, 1450.50, 580.00, 250.00, 60.00),
(15, 15, 6, 250.00, 150.00, 50.00, 10.00),
(16, 16, 1, 1100.00, 450.00, 400.00, 100.00),
(17, 17, 5, 2500.00, 900.00, 300.00, 50.00),
(18, 18, 3, 1800.00, 700.00, 800.00, 120.00),
(19, 19, 2, 750.00, 300.00, 100.00, 0.00),
(20, 20, 1, 1000.00, 400.00, 150.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `budgets`
--
DROP TABLE IF EXISTS `budgets`;
CREATE TABLE `budgets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `budget_title` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping new data for table `budgets`
--
INSERT INTO `budgets` (`id`, `budget_title`, `start_date`, `end_date`, `amount`, `created_at`) VALUES
(1, 'October 2025 Operations Budget', '2025-10-01', '2025-10-31', 250000.00, '2025-10-01 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping new data for table `expenses`
--
INSERT INTO `expenses` (`id`, `expense_category`, `description`, `amount`, `expense_date`, `created_at`) VALUES
(1, 'Fuel', 'Diesel for all trucks - Week 1', 35000.00, '2025-10-07', '2025-10-07 09:00:00'),
(2, 'Maintenance', 'Preventive Maintenance for ELF001', 15000.00, '2025-10-15', '2025-10-15 09:01:00'),
(3, 'Salaries', 'Driver Salaries - First Half', 80000.00, '2025-10-15', '2025-10-15 09:02:00');

-- --------------------------------------------------------

--
-- Table structure for table `usage_logs`
--
DROP TABLE IF EXISTS `usage_logs`;
CREATE TABLE `usage_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `metrics` varchar(255) NOT NULL,
  `fuel_usage` decimal(10,2) NOT NULL,
  `mileage` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `vehicle_id` (`vehicle_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping new data for table `usage_logs`
--
INSERT INTO `usage_logs` (`id`, `vehicle_id`, `log_date`, `metrics`, `fuel_usage`, `mileage`, `created_at`) VALUES
(1, 1, '2025-10-26', 'Completed 3 short trips', 55.50, 250, '2025-10-27 10:00:00'),
(2, 2, '2025-10-26', 'City driving for deliveries', 30.20, 150, '2025-10-27 10:01:00'),
(3, 3, '2025-10-20', 'Long-haul to Laguna', 85.00, 450, '2025-10-20 10:02:00'),
(4, 5, '2025-10-13', 'Multiple stops in Ortigas', 45.70, 180, '2025-10-13 10:03:00');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_approvals`
--
DROP TABLE IF EXISTS `maintenance_approvals`;
CREATE TABLE `maintenance_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `arrival_date` date NOT NULL,
  `date_of_return` date DEFAULT NULL,
  `status` enum('Pending','Approved','On-Queue','Completed','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `vehicle_id` (`vehicle_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping new data for table `maintenance_approvals`
--
INSERT INTO `maintenance_approvals` (`id`, `vehicle_id`, `arrival_date`, `date_of_return`, `status`, `created_at`) VALUES
(1, 4, '2025-10-25', '2025-10-28', 'Completed', '2025-10-25 11:00:00'),
(2, 1, '2025-11-01', NULL, 'Pending', '2025-10-27 11:01:00');

-- --------------------------------------------------------

--
-- Table structure for table `driver_behavior_logs`
--
DROP TABLE IF EXISTS `driver_behavior_logs`;
CREATE TABLE `driver_behavior_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `driver_id` int(11) NOT NULL,
  `trip_id` int(11) DEFAULT NULL,
  `log_date` date NOT NULL,
  `overspeeding_count` int(11) NOT NULL DEFAULT 0,
  `harsh_braking_count` int(11) NOT NULL DEFAULT 0,
  `idle_duration_minutes` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `driver_id` (`driver_id`),
  KEY `trip_id` (`trip_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping new data for table `driver_behavior_logs`
--
INSERT INTO `driver_behavior_logs` (`id`, `driver_id`, `trip_id`, `log_date`, `overspeeding_count`, `harsh_braking_count`, `idle_duration_minutes`, `notes`, `created_at`) VALUES
(1, 1, 3, '2025-10-20', 3, 1, 15, 'Overspeeding incidents detected on SLEX.', '2025-10-20 12:00:00'),
(2, 2, 1, '2025-10-28', 1, 5, 25, 'Multiple harsh braking events in city traffic.', '2025-10-28 12:01:00'),
(3, 3, 6, '2025-10-02', 0, 2, 10, 'Harsh braking near construction site.', '2025-10-02 12:02:00'),
(4, 4, 8, '2025-10-04', 0, 0, 5, 'Smooth driving reported.', '2025-10-04 12:03:00'),
(5, 5, 9, '2025-10-05', 2, 3, 30, 'Overspeeding and idle time in Greenhills area.', '2025-10-05 12:04:00');

-- --------------------------------------------------------

--
-- Table structure for table `tracking_log`
--
DROP TABLE IF EXISTS `tracking_log`;
CREATE TABLE `tracking_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `trip_id` int(11) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `speed_mph` int(11) DEFAULT 0,
  `status_message` varchar(255) DEFAULT NULL,
  `log_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `trip_id` (`trip_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping new data for table `tracking_log`
--
INSERT INTO `tracking_log` (`id`, `trip_id`, `latitude`, `longitude`, `speed_mph`, `status_message`, `log_time`) VALUES
(1, 1, 14.60910000, 121.02230000, 30, 'Departed from origin', '2025-10-28 09:05:00'),
(2, 1, 14.58290000, 121.03000000, 25, 'On EDSA, moderate traffic', '2025-10-28 09:20:00'),
(3, 1, 14.55470000, 121.02440000, 15, 'Approaching Ayala Avenue', '2025-10-28 09:45:00');

-- --------------------------------------------------------

--
-- Table structure for table `alerts`
--
DROP TABLE IF EXISTS `alerts`;
CREATE TABLE `alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trip_id` int(11) DEFAULT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `alert_type` enum('SOS','Breakdown','Other') NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Pending','Acknowledged','Resolved') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `trip_id` (`trip_id`),
  KEY `driver_id` (`driver_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping new data for table `alerts`
--
INSERT INTO `alerts` (`id`, `trip_id`, `driver_id`, `alert_type`, `description`, `status`, `created_at`) VALUES
(1, 1, 2, 'SOS', 'Minor accident on EDSA. No injuries, but vehicle is stopped.', 'Pending', '2025-10-28 09:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping new data for table `messages`
--
INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message_text`, `sent_at`, `is_read`) VALUES
(1, 1, 3, 'John, please check your schedule for tomorrow. New trip assigned.', '2025-10-27 15:00:00', 1),
(2, 3, 1, 'Copy, admin. Saw it. Will prepare accordingly.', '2025-10-27 15:05:00', 0);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping new data for table `password_resets`
--
INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `created_at`) VALUES
(1, 2, 'expiredtoken1234567890abcdefghijklmnopqrstuvwxyz', '2025-10-27 10:00:00', '2025-10-27 09:00:00');

--
-- Constraints for dumped tables
--
ALTER TABLE `drivers`
  ADD CONSTRAINT `drivers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`assigned_driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`reserved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `trips`
  ADD CONSTRAINT `trips_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `trips_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `trips_ibfk_3` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `trip_costs`
  ADD CONSTRAINT `trip_costs_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `trip_costs_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON UPDATE CASCADE;
  
ALTER TABLE `usage_logs`
  ADD CONSTRAINT `usage_logs_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
  
ALTER TABLE `maintenance_approvals`
  ADD CONSTRAINT `maintenance_approvals_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `driver_behavior_logs`
  ADD CONSTRAINT `driver_behavior_logs_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `driver_behavior_logs_ibfk_2` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `tracking_log`
  ADD CONSTRAINT `tracking_log_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `alerts`
  ADD CONSTRAINT `alerts_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `alerts_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

COMMIT;
