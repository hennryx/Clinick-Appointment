-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 13, 2025 at 06:08 PM
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
-- Database: `mydatabase`
--

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `patient_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `gender` enum('MALE','FEMALE') NOT NULL,
  `age` int(11) NOT NULL,
  `birth_date` date NOT NULL,
  `delete_status` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`patient_id`, `full_name`, `gender`, `age`, `birth_date`, `delete_status`) VALUES
(1, 'ORTEGA, JELLO', 'MALE', 23, '2002-01-15', 0),
(2, 'MADRID, FRANCE PAUL', 'MALE', 24, '2001-07-10', 0),
(3, 'test', 'MALE', 18, '2025-05-13', 0);

-- --------------------------------------------------------

--
-- Table structure for table `pending_requests`
--

CREATE TABLE `pending_requests` (
  `id` int(11) NOT NULL,
  `date` datetime DEFAULT current_timestamp(),
  `patient_id` int(11) NOT NULL,
  `sample_id` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `station` varchar(50) NOT NULL,
  `station_ward` varchar(100) NOT NULL,
  `gender` enum('MALE','FEMALE') NOT NULL,
  `age` int(11) NOT NULL,
  `birth_date` date NOT NULL,
  `test_name` varchar(100) NOT NULL,
  `clinical_info` text DEFAULT NULL,
  `physician` varchar(100) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Completed') DEFAULT 'Pending',
  `requested_by` varchar(50) NOT NULL,
  `processed_by` varchar(50) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `reject_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pending_requests`
--

INSERT INTO `pending_requests` (`id`, `date`, `patient_id`, `sample_id`, `full_name`, `station`, `station_ward`, `gender`, `age`, `birth_date`, `test_name`, `clinical_info`, `physician`, `status`, `requested_by`, `processed_by`, `processed_at`, `reject_reason`) VALUES
(1, '2025-05-13 11:31:53', 1, 'SMP-001', 'ORTEGA, JELLO', 'Lab', 'General Ward', 'MALE', 23, '2002-01-15', 'Blood Test', NULL, NULL, 'Approved', 'admin', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reagent_consumption`
--

CREATE TABLE `reagent_consumption` (
  `id` int(11) NOT NULL,
  `reagent_name` varchar(100) NOT NULL,
  `quantity_used` decimal(10,2) NOT NULL,
  `usage_date` date NOT NULL,
  `used_by` varchar(50) DEFAULT NULL,
  `test_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reagent_consumption`
--

INSERT INTO `reagent_consumption` (`id`, `reagent_name`, `quantity_used`, `usage_date`, `used_by`, `test_id`, `created_at`) VALUES
(1, 'CBC Reagent Kit', 1.50, '2025-05-11', 'admin', NULL, '2025-05-13 15:51:50'),
(2, 'Urinalysis Test Strips', 20.00, '2025-05-10', 'admin', NULL, '2025-05-13 15:51:50'),
(3, 'Glucose Test Reagent', 5.00, '2025-05-12', 'admin', NULL, '2025-05-13 15:51:50'),
(4, 'Creatinine Reagent', 4.00, '2025-05-13', 'admin', NULL, '2025-05-13 15:51:50'),
(5, 'Hemoglobin Reagent', 2.00, '2025-05-08', 'admin', NULL, '2025-05-13 15:51:50');

-- --------------------------------------------------------

--
-- Table structure for table `rejected_requests`
--

CREATE TABLE `rejected_requests` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `sample_id` varchar(50) DEFAULT NULL,
  `rejection_reason` text NOT NULL,
  `rejected_by` varchar(50) NOT NULL,
  `rejected_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `request_list`
--

CREATE TABLE `request_list` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `sample_id` varchar(50) NOT NULL,
  `patient_name` varchar(100) NOT NULL,
  `station_ward` varchar(100) NOT NULL,
  `gender` varchar(10) NOT NULL,
  `age` int(11) NOT NULL,
  `birth_date` date NOT NULL,
  `request_date` datetime NOT NULL,
  `test_name` varchar(100) NOT NULL,
  `clinical_info` text DEFAULT NULL,
  `physician` varchar(100) DEFAULT NULL,
  `status` enum('Approved','Completed','Cancelled') DEFAULT 'Approved',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `request_list`
--

INSERT INTO `request_list` (`id`, `patient_id`, `sample_id`, `patient_name`, `station_ward`, `gender`, `age`, `birth_date`, `request_date`, `test_name`, `clinical_info`, `physician`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'SMP-001', 'ORTEGA, JELLO', 'Lab', 'MALE', 23, '2002-01-15', '2025-05-13 11:31:53', 'Blood Test', NULL, NULL, 'Approved', '2025-05-13 16:05:10', '2025-05-13 16:05:10');

-- --------------------------------------------------------

--
-- Table structure for table `test_records`
--

CREATE TABLE `test_records` (
  `id` int(11) NOT NULL,
  `test_name` varchar(100) NOT NULL,
  `patient_name` varchar(100) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `sample_id` varchar(50) NOT NULL,
  `section` varchar(50) NOT NULL,
  `test_date` datetime NOT NULL,
  `result` text DEFAULT NULL,
  `status` enum('Pending','In Progress','Completed') DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `performed_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_records`
--

INSERT INTO `test_records` (`id`, `test_name`, `patient_name`, `patient_id`, `sample_id`, `section`, `test_date`, `result`, `status`, `remarks`, `performed_by`, `created_at`, `updated_at`) VALUES
(1, 'CBC', 'ORTEGA, JELLO', 1, 'LAB-220852', 'Hematology', '2025-04-17 23:51:50', 'WBC: 7.5 x10^9/L, RBC: 5.2 x10^12/L, Hgb: 15.1 g/dL, Hct: 45%', 'Completed', NULL, NULL, '2025-05-13 15:51:50', '2025-05-13 15:51:50'),
(2, 'CBC', 'MADRID, FRANCE PAUL', 2, 'LAB-171249', 'Hematology', '2025-04-22 23:51:50', 'WBC: 7.5 x10^9/L, RBC: 5.2 x10^12/L, Hgb: 15.1 g/dL, Hct: 45%', 'Completed', NULL, NULL, '2025-05-13 15:51:50', '2025-05-13 15:51:50'),
(3, 'CBC', 'test', 3, 'LAB-366063', 'Hematology', '2025-05-03 23:51:50', 'WBC: 7.5 x10^9/L, RBC: 5.2 x10^12/L, Hgb: 15.1 g/dL, Hct: 45%', 'Completed', NULL, NULL, '2025-05-13 15:51:50', '2025-05-13 15:51:50'),
(4, 'Urinalysis', 'ORTEGA, JELLO', 1, 'LAB-913959', 'Clinical Microscopy', '2025-04-30 23:51:50', 'Color: Yellow, Transparency: Clear, pH: 6.0, Protein: Negative, Glucose: Negative', 'Completed', NULL, NULL, '2025-05-13 15:51:50', '2025-05-13 15:51:50'),
(5, 'Urinalysis', 'MADRID, FRANCE PAUL', 2, 'LAB-570659', 'Clinical Microscopy', '2025-05-05 23:51:50', 'Color: Yellow, Transparency: Clear, pH: 6.0, Protein: Negative, Glucose: Negative', 'Completed', NULL, NULL, '2025-05-13 15:51:50', '2025-05-13 15:51:50'),
(6, 'Urinalysis', 'test', 3, 'LAB-816904', 'Clinical Microscopy', '2025-05-09 23:51:50', 'Color: Yellow, Transparency: Clear, pH: 6.0, Protein: Negative, Glucose: Negative', 'Completed', NULL, NULL, '2025-05-13 15:51:50', '2025-05-13 15:51:50'),
(7, 'Blood Chemistry', 'ORTEGA, JELLO', 1, 'LAB-485237', 'Chemistry', '2025-04-24 23:51:50', 'Glucose: 90 mg/dL, Creatinine: 0.9 mg/dL, BUN: 15 mg/dL, Uric Acid: 5.5 mg/dL', 'Pending', NULL, NULL, '2025-05-13 15:51:50', '2025-05-13 15:51:50'),
(8, 'Blood Chemistry', 'MADRID, FRANCE PAUL', 2, 'LAB-967381', 'Chemistry', '2025-04-17 23:51:50', 'Glucose: 90 mg/dL, Creatinine: 0.9 mg/dL, BUN: 15 mg/dL, Uric Acid: 5.5 mg/dL', 'Pending', NULL, NULL, '2025-05-13 15:51:50', '2025-05-13 15:51:50'),
(9, 'Blood Chemistry', 'test', 3, 'LAB-513111', 'Chemistry', '2025-04-23 23:51:50', 'Glucose: 90 mg/dL, Creatinine: 0.9 mg/dL, BUN: 15 mg/dL, Uric Acid: 5.5 mg/dL', 'Pending', NULL, NULL, '2025-05-13 15:51:50', '2025-05-13 15:51:50');

-- --------------------------------------------------------

--
-- Table structure for table `test_results`
--

CREATE TABLE `test_results` (
  `id` int(11) NOT NULL,
  `test_name` varchar(100) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `patient_name` varchar(100) NOT NULL,
  `test_date` datetime NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_results`
--

INSERT INTO `test_results` (`id`, `test_name`, `patient_id`, `patient_name`, `test_date`, `price`, `processed_by`, `created_at`) VALUES
(1, 'CBC', 1, 'ORTEGA, JELLO', '2025-05-12 23:51:50', 350.00, 1, '2025-05-13 15:51:50'),
(2, 'CBC', 2, 'MADRID, FRANCE PAUL', '2025-05-08 23:51:50', 350.00, 1, '2025-05-13 15:51:50'),
(3, 'CBC', 3, 'test', '2025-04-20 23:51:50', 350.00, 1, '2025-05-13 15:51:50'),
(4, 'Urinalysis', 1, 'ORTEGA, JELLO', '2025-05-04 23:51:50', 250.00, 1, '2025-05-13 15:51:50'),
(5, 'Urinalysis', 2, 'MADRID, FRANCE PAUL', '2025-05-07 23:51:50', 250.00, 1, '2025-05-13 15:51:50'),
(6, 'Urinalysis', 3, 'test', '2025-05-10 23:51:50', 250.00, 1, '2025-05-13 15:51:50'),
(7, 'Blood Chemistry', 1, 'ORTEGA, JELLO', '2025-04-17 23:51:50', 750.00, 1, '2025-05-13 15:51:50'),
(8, 'Blood Chemistry', 2, 'MADRID, FRANCE PAUL', '2025-05-08 23:51:50', 750.00, 1, '2025-05-13 15:51:50'),
(9, 'Blood Chemistry', 3, 'test', '2025-05-05 23:51:50', 750.00, 1, '2025-05-13 15:51:50');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff') DEFAULT 'staff'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(1, 'admin', 'password', 'admin');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`patient_id`),
  ADD KEY `idx_delete_status` (`delete_status`);

--
-- Indexes for table `pending_requests`
--
ALTER TABLE `pending_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sample_id` (`sample_id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `reagent_consumption`
--
ALTER TABLE `reagent_consumption`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rejected_requests`
--
ALTER TABLE `rejected_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `request_list`
--
ALTER TABLE `request_list`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sample_id` (`sample_id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `test_records`
--
ALTER TABLE `test_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `test_results`
--
ALTER TABLE `test_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pending_requests`
--
ALTER TABLE `pending_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reagent_consumption`
--
ALTER TABLE `reagent_consumption`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `rejected_requests`
--
ALTER TABLE `rejected_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_list`
--
ALTER TABLE `request_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `test_records`
--
ALTER TABLE `test_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `test_results`
--
ALTER TABLE `test_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `pending_requests`
--
ALTER TABLE `pending_requests`
  ADD CONSTRAINT `pending_requests_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `rejected_requests`
--
ALTER TABLE `rejected_requests`
  ADD CONSTRAINT `rejected_requests_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `request_list`
--
ALTER TABLE `request_list`
  ADD CONSTRAINT `request_list_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `test_records`
--
ALTER TABLE `test_records`
  ADD CONSTRAINT `test_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `test_results`
--
ALTER TABLE `test_results`
  ADD CONSTRAINT `test_results_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
