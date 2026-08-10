SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `categories` (`category_id`, `category_name`, `created_at`) VALUES
(1, 'Engine Oil', '2026-02-22 09:24:23'),
(2, 'Transmission Fluid', '2026-02-22 09:24:23'),
(3, 'Brake Fluid', '2026-02-22 09:24:23'),
(4, 'Coolant', '2026-02-22 09:24:23'),
(5, 'Gear Oil', '2026-02-22 09:24:23'),
(6, 'Filters', '2026-02-22 09:24:23'),
(7, 'Accessories', '2026-02-22 09:24:23');

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `expenses` (`id`, `expense_date`, `category`, `description`, `amount`, `created_by`, `created_at`) VALUES
(31, '2026-03-09', 'Salaries', 'sdfgh', 100.00, 1, '2026-03-09 02:49:09');

CREATE TABLE `inventory_transactions` (
  `transaction_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `transaction_date` date NOT NULL,
  `quantity_change` int(11) NOT NULL,
  `transaction_type` enum('initial','purchase','sale','adjustment','restock') NOT NULL DEFAULT 'sale',
  `remarks` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `inventory_transactions` (`transaction_id`, `product_id`, `transaction_date`, `quantity_change`, `transaction_type`, `remarks`, `created_by`, `created_at`) VALUES
(1, 1, '2026-01-25', -3, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(2, 4, '2026-01-26', -2, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(3, 12, '2026-01-26', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(4, 3, '2026-01-27', -2, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(5, 12, '2026-01-27', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(6, 14, '2026-01-27', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(7, 6, '2026-01-28', -2, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(8, 14, '2026-01-28', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(9, 2, '2026-01-29', -4, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(10, 8, '2026-01-29', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(11, 1, '2026-02-01', -3, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(12, 12, '2026-02-01', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(13, 5, '2026-02-02', -4, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(14, 13, '2026-02-02', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(15, 10, '2026-02-02', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(16, 7, '2026-02-03', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(17, 9, '2026-02-04', -2, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(18, 14, '2026-02-04', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(19, 15, '2026-02-04', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(20, 3, '2026-02-05', -2, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(21, 12, '2026-02-05', -2, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(22, 6, '2026-02-06', -2, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(23, 8, '2026-02-06', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(24, 1, '2026-02-08', -4, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(25, 4, '2026-02-09', -2, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(26, 13, '2026-02-09', -2, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(27, 2, '2026-02-10', -4, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(28, 11, '2026-02-10', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(29, 14, '2026-02-10', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(30, 8, '2026-02-11', -2, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(31, 14, '2026-02-11', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(32, 1, '2026-02-12', -2, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(33, 12, '2026-02-12', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(34, 3, '2026-02-13', -2, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(35, 15, '2026-02-13', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(36, 13, '2026-02-13', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(37, 5, '2026-02-15', -4, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(38, 11, '2026-02-15', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(39, 14, '2026-02-15', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(40, 15, '2026-02-15', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(41, 6, '2026-02-16', -2, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(42, 12, '2026-02-16', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(43, 1, '2026-02-17', -2, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(44, 4, '2026-02-17', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(45, 8, '2026-02-17', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(46, 13, '2026-02-17', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(47, 2, '2026-02-18', -4, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(48, 11, '2026-02-18', -2, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(49, 14, '2026-02-18', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(50, 3, '2026-02-19', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(51, 9, '2026-02-19', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(52, 12, '2026-02-19', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(53, 5, '2026-02-20', -2, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(54, 7, '2026-02-20', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(55, 10, '2026-02-20', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(56, 15, '2026-02-20', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(57, 1, '2026-02-21', -4, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(58, 2, '2026-02-22', -3, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(59, 9, '2026-02-22', -2, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(60, 13, '2026-02-22', -1, 'sale', NULL, 1, '2026-02-22 09:36:30'),
(61, 15, '0000-00-00', -1, 'sale', 'Sale #51', 1, '2026-02-22 11:26:58'),
(62, 13, '0000-00-00', -1, 'sale', 'Sale #51', 1, '2026-02-22 11:26:58'),
(63, 15, '2026-02-23', -29, 'sale', 'Sale #54 - jeryl dagunan', 1, '2026-02-23 09:23:58'),
(64, 15, '2026-03-05', -1, 'sale', 'Sale #56 - HANAH', 2, '2026-03-05 12:01:34'),
(65, 12, '2026-03-05', -1, 'sale', 'Sale #57 - HANAH', 2, '2026-03-05 12:03:29'),
(66, 15, '2026-03-08', 20, 'restock', '123', NULL, '2026-03-08 10:48:43'),
(67, 12, '2026-03-08', -1, 'sale', 'Sale #58 - Hanah', 1, '2026-03-08 11:37:03'),
(68, 12, '2026-03-08', -1, 'sale', 'Sale #59 - Jeryl', 1, '2026-03-08 11:37:46'),
(69, 12, '2026-03-08', -1, 'sale', 'Sale #60 - Thea', 1, '2026-03-08 11:38:19'),
(70, 12, '2026-03-08', -1, 'sale', 'Sale #61 - Loise', 1, '2026-03-08 11:38:47'),
(71, 15, '2026-03-09', -1, 'sale', 'Sale #62 - HANAH', 1, '2026-03-09 01:05:21'),
(72, 15, '2026-03-09', -12, 'sale', 'Sale #63 - jery', 1, '2026-03-09 02:39:02'),
(73, 15, '2026-03-09', -7, 'sale', 'Sale #64 - HANAH', 1, '2026-03-09 02:39:20'),
(74, 11, '2026-03-09', -1, 'sale', 'Sale #65 - THEA', 1, '2026-03-09 02:41:28'),
(75, 15, '2026-03-09', 20, 'restock', '123', 1, '2026-03-09 02:47:44'),
(76, 12, '2026-03-09', -1, 'sale', 'Sale #66 - HANAH', 1, '2026-03-09 02:48:02'),
(77, 15, '2026-03-09', -2, 'sale', 'Sale #67 - HANAH', 1, '2026-03-09 02:57:05'),
(78, 15, '2026-03-14', -1, 'sale', 'Sale #68 - jeryl', 1, '2026-03-14 02:35:38'),
(79, 4, '2026-03-16', -1, 'sale', 'Sale #69 - loise', 1, '2026-03-16 07:36:48'),
(80, 15, '2026-03-19', -2, 'sale', 'Sale #70 - jeryl', 1, '2026-03-19 10:10:06'),
(81, 15, '2026-03-21', -1, 'sale', 'Sale #71 - dokdok', 2, '2026-03-21 03:00:10');

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  `unit` varchar(50) NOT NULL DEFAULT 'Liter',
  `unit_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `margin` decimal(10,2) GENERATED ALWAYS AS (`selling_price` - `unit_cost`) STORED,
  `initial_quantity` int(11) NOT NULL DEFAULT 0,
  `reorder_threshold` int(11) DEFAULT 5,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `products` (`product_id`, `category_id`, `code`, `description`, `unit`, `unit_cost`, `selling_price`, `initial_quantity`, `reorder_threshold`, `created_at`) VALUES
(1, 1, 'EO-001', 'Castrol GTX 20W-50 Engine Oil', 'Liter', 350.00, 450.00, 80, 10, '2026-02-22 09:25:45'),
(2, 1, 'EO-002', 'Shell Helix HX5 15W-40', 'Liter', 380.00, 480.00, 60, 10, '2026-02-22 09:25:45'),
(3, 1, 'EO-003', 'Motul 5100 10W-40 Semi-Synthetic', 'Liter', 550.00, 700.00, 40, 8, '2026-02-22 09:25:45'),
(4, 2, 'TF-001', 'Dexron III ATF Transmission Fluid', 'Liter', 280.00, 380.00, 50, 8, '2026-02-22 09:25:45'),
(5, 2, 'TF-002', 'Honda ATF Z1 Transmission Fluid', 'Liter', 320.00, 420.00, 35, 5, '2026-02-22 09:25:45'),
(6, 3, 'BF-001', 'Motul DOT 4 Brake Fluid', 'Liter', 220.00, 320.00, 45, 8, '2026-02-22 09:25:45'),
(7, 3, 'BF-002', 'Castrol React DOT 5.1 Brake Fluid', 'Liter', 350.00, 480.00, 30, 5, '2026-02-22 09:25:45'),
(8, 4, 'CL-001', 'Prestone All-Season Coolant', 'Liter', 200.00, 290.00, 55, 10, '2026-02-22 09:25:45'),
(9, 4, 'CL-002', 'Toyota Long Life Coolant', 'Liter', 250.00, 360.00, 40, 8, '2026-02-22 09:25:45'),
(10, 5, 'GO-001', 'Hypoid Gear Oil 90', 'Liter', 180.00, 260.00, 70, 12, '2026-02-22 09:25:45'),
(11, 5, 'GO-002', 'Castrol Syntrax 75W-90 Gear Oil', 'Liter', 420.00, 560.00, 25, 5, '2026-02-22 09:25:45'),
(12, 6, 'FL-001', 'Bosch Oil Filter Universal', 'Gallon', 120.00, 200.00, 90, 15, '2026-02-22 09:25:45'),
(13, 6, 'FL-002', 'Toyota Genuine Oil Filter', 'Gallon', 150.00, 250.00, 60, 10, '2026-02-22 09:25:45'),
(14, 7, 'AC-001', 'WD-40 Multi-Purpose Spray 400ml', 'Gallon', 180.00, 280.00, 50, 8, '2026-02-22 09:25:45'),
(15, 7, 'AC-002', '3M Silicone Spray Lubricant', 'Gallon', 250.00, 380.00, 35, 5, '2026-02-22 09:25:45');

CREATE TABLE `product_stock` (
`product_id` int(11)
,`code` varchar(50)
,`description` varchar(255)
,`unit` varchar(50)
,`unit_cost` decimal(10,2)
,`selling_price` decimal(10,2)
,`margin` decimal(10,2)
,`initial_quantity` int(11)
,`reorder_threshold` int(11)
,`category_id` int(11)
,`category_name` varchar(100)
,`current_stock` decimal(33,0)
);

CREATE TABLE `reorder_preparations` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `recommended_qty` int(11) NOT NULL,
  `confirmed_qty` int(11) DEFAULT NULL,
  `status` enum('pending','confirmed','received') DEFAULT 'pending',
  `confirmed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `sale_date` date NOT NULL,
  `customer_name` varchar(255) NOT NULL DEFAULT '',
  `plate_number` varchar(50) NOT NULL DEFAULT '',
  `parts_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `labor_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sales` (`id`, `sale_date`, `customer_name`, `plate_number`, `parts_total`, `labor_total`, `created_by`, `created_at`) VALUES
(67, '2026-03-09', 'HANAH', 'BBI-987', 760.00, 0.00, 1, '2026-03-09 02:57:05'),
(68, '2026-03-14', 'jeryl', 'BBC', 380.00, 0.00, 1, '2026-03-14 02:35:38'),
(69, '2026-03-16', 'loise', 'ABC-123', 380.00, 0.00, 1, '2026-03-16 07:36:48'),
(70, '2026-03-19', 'jeryl', 'BBI-987', 760.00, 0.00, 1, '2026-03-19 10:10:06');

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `line_type` enum('parts','labor') NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sale_items` (`id`, `sale_id`, `line_type`, `product_id`, `description`, `quantity`, `unit_price`, `amount`, `created_at`) VALUES
(343, 67, '', 15, '3', 2, 380.00, 760.00, '2026-03-09 02:57:05'),
(344, 68, '', 15, '3', 1, 380.00, 380.00, '2026-03-14 02:35:38'),
(345, 69, '', 4, '0', 1, 380.00, 380.00, '2026-03-16 07:36:48'),
(346, 70, '', 15, '3', 2, 380.00, 760.00, '2026-03-19 10:10:06');

CREATE TABLE `sms_history` (
  `id` int(11) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `recipient` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` enum('success','failed','pending') DEFAULT 'pending',
  `provider` varchar(50) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `product_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`product_ids`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `sms_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sms_settings` (`id`, `setting_key`, `setting_value`, `updated_at`, `updated_by`) VALUES
(1, 'recipients', '[]', '2026-02-22 09:30:37', NULL);

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`) VALUES
(1, 'esrah', 'ez@gmail.com', '$2y$10$34IhsM5bbrXEevYoJ7G//u3G/1BLp6PtDDHJ7BJKm3Iz5bJW7Mpi.', 'owner'),
(2, 'manager', 'manager@gmail.com', '$2y$10$nICcpM3Eujepp8fLUDSgF.1FL4dBRle/G8W2JBtJJfYQ0BAIwwKBm', 'manager'),
(4, 'jeryl', 'dagunan@gmail.com', '$2y$12$2etttUfcfmGho34d.UPzduY4tIZJuuErORRV4x2urYcJFC4gYTaBW', 'manager');

CREATE TABLE `work_orders` (
  `id` int(11) NOT NULL,
  `service_name` varchar(255) NOT NULL,
  `status` enum('open','completed') NOT NULL DEFAULT 'open',
  `labor_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `work_orders` (`id`, `service_name`, `status`, `labor_amount`, `completed_at`, `created_at`) VALUES
(1, 'Oil Change Service - Carlos Reyes', 'completed', 500.00, '2026-01-25 17:37:39', '2026-02-22 09:37:39'),
(2, 'ATF Flush - Maria Santos', 'completed', 800.00, '2026-01-26 17:37:39', '2026-02-22 09:37:39'),
(3, 'Brake Replacement - Ana Garcia', 'completed', 500.00, '2026-01-28 17:37:39', '2026-02-22 09:37:39'),
(4, 'Full Service - Pedro Lim', 'completed', 1200.00, '2026-01-29 17:37:39', '2026-02-22 09:37:39'),
(5, 'Oil Change - Roberto Cruz', 'completed', 600.00, '2026-02-01 17:37:39', '2026-02-22 09:37:39'),
(6, 'Transmission Service - Liza Tan', 'completed', 800.00, '2026-02-02 17:37:39', '2026-02-22 09:37:39'),
(7, 'Brake Fluid Flush - Michael Ong', 'completed', 500.00, '2026-02-03 17:37:39', '2026-02-22 09:37:39'),
(8, 'Cooling System - Jenny Ramos', 'completed', 1000.00, '2026-02-04 17:37:39', '2026-02-22 09:37:39'),
(9, 'Brake & Cooling - Grace Villanueva', 'completed', 500.00, '2026-02-06 17:37:39', '2026-02-22 09:37:39'),
(10, 'Full Alignment 4W - Ramon Mendoza', 'completed', 800.00, '2026-02-08 17:37:39', '2026-02-22 09:37:39'),
(11, 'Transmission Check - Cynthia Aquino', 'completed', 500.00, '2026-02-09 17:37:39', '2026-02-22 09:37:39'),
(12, 'Complete Lube Service - Dennis Castro', 'completed', 1500.00, '2026-02-10 17:37:39', '2026-02-22 09:37:39'),
(13, 'Standard Oil Change - Felix Torres', 'completed', 800.00, '2026-02-12 17:37:39', '2026-02-22 09:37:39'),
(14, 'Engine Service - Gloria Diaz', 'completed', 1000.00, '2026-02-13 17:37:39', '2026-02-22 09:37:39'),
(15, 'Complete Transmission - Hector Flores', 'completed', 1200.00, '2026-02-15 17:37:39', '2026-02-22 09:37:39'),
(16, 'Brake Service - Isabel Gomez', 'completed', 500.00, '2026-02-16 17:37:39', '2026-02-22 09:37:39'),
(17, 'Full Service - Julius Hernandez', 'completed', 800.00, '2026-02-17 17:37:39', '2026-02-22 09:37:39'),
(18, 'Premium Service - Karen Iglesias', 'completed', 1500.00, '2026-02-18 17:37:39', '2026-02-22 09:37:39'),
(19, 'Premium Lube & Brakes - Mina Kho', 'completed', 1000.00, '2026-02-20 17:37:39', '2026-02-22 09:37:39'),
(20, 'Alignment Service - Nathan Lopez', 'completed', 800.00, '2026-02-21 17:37:39', '2026-02-22 09:37:39'),
(21, 'Full Service VIP - Olivia Mercado', 'completed', 1200.00, '2026-02-22 17:37:39', '2026-02-22 09:37:39'),
(22, 'Battery Check Pending', 'open', 0.00, NULL, '2026-02-22 09:37:39'),
(23, 'Tire Rotation Pending', 'open', 0.00, NULL, '2026-02-22 09:37:39'),
(24, 'fracis (BBI-987)', 'completed', 600.00, '2026-02-22 12:26:58', '2026-02-22 11:26:58'),
(25, 'DOKDOK (HIG-333)', 'completed', 720.00, '2026-02-22 12:39:34', '2026-02-22 11:39:34'),
(26, 'HANAH (BBI-987)', 'completed', 3000.00, '2026-02-03 12:49:54', '2026-02-22 11:49:54'),
(27, 'thea sorro (BBI-987)', 'completed', 5000.00, '2026-03-05 10:14:09', '2026-03-05 09:14:09');

DROP TABLE IF EXISTS `product_stock`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `product_stock`  AS SELECT `p`.`product_id` AS `product_id`, `p`.`code` AS `code`, `p`.`description` AS `description`, `p`.`unit` AS `unit`, `p`.`unit_cost` AS `unit_cost`, `p`.`selling_price` AS `selling_price`, `p`.`margin` AS `margin`, `p`.`initial_quantity` AS `initial_quantity`, `p`.`reorder_threshold` AS `reorder_threshold`, `c`.`category_id` AS `category_id`, `c`.`category_name` AS `category_name`, `p`.`initial_quantity`+ coalesce(sum(`t`.`quantity_change`),0) AS `current_stock` FROM ((`products` `p` join `categories` `c` on(`p`.`category_id` = `c`.`category_id`)) left join `inventory_transactions` `t` on(`p`.`product_id` = `t`.`product_id`)) GROUP BY `p`.`product_id`, `p`.`code`, `p`.`description`, `p`.`unit`, `p`.`unit_cost`, `p`.`selling_price`, `p`.`margin`, `p`.`initial_quantity`, `p`.`reorder_threshold`, `c`.`category_id`, `c`.`category_name` ;

ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `created_by` (`created_by`);

ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `category_id` (`category_id`);

ALTER TABLE `reorder_preparations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

ALTER TABLE `sms_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sent_at` (`sent_at`),
  ADD KEY `idx_status` (`status`);

ALTER TABLE `sms_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `work_orders`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

ALTER TABLE `inventory_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

ALTER TABLE `reorder_preparations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=348;

ALTER TABLE `sms_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `sms_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `work_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_transactions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);

ALTER TABLE `reorder_preparations`
  ADD CONSTRAINT `reorder_preparations_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE SET NULL;

ALTER TABLE `sms_settings`
  ADD CONSTRAINT `sms_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

UPDATE sale_items
SET line_type = 'parts'
WHERE line_type = '' AND product_id IS NOT NULL;

UPDATE sale_items
SET line_type = 'labor'
WHERE line_type = '' AND product_id IS NULL;

UPDATE sale_items si
JOIN products p ON si.product_id = p.product_id
SET si.description = p.description
WHERE si.line_type = 'parts'
  AND (si.description IS NULL OR si.description = '' OR si.description REGEXP '^[0-9]+$');