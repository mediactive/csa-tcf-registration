-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jan 28, 2026 at 06:48 PM
-- Server version: 8.3.0
-- PHP Version: 8.2.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `leadercsa_tcf`
--

-- --------------------------------------------------------

--
-- Table structure for table `tcf_registrations`
--

CREATE TABLE `tcf_registrations` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `gender` int NOT NULL,
  `birthday` date NOT NULL,
  `countryOfBirth` int NOT NULL,
  `cityOfBirth` int NOT NULL,
  `nationality` int NOT NULL,
  `identityDocumentNumber` varchar(50) NOT NULL,
  `language` int NOT NULL,
  `oldCandidateCode` char(20) NOT NULL,
  `address` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `postalCode` char(20) NOT NULL,
  `country` int NOT NULL,
  `phoneCountryCode` varchar(10) DEFAULT '+1',
  `phone` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `exam` int NOT NULL,
  `testCE` int NOT NULL,
  `testCO` int NOT NULL,
  `testEE` int NOT NULL,
  `testEO` int NOT NULL,
  `reasonsForRegistration` int NOT NULL,
  `disiredSession` int NOT NULL,
  `specialNeeds` int NOT NULL,
  `specialNeedsDetails` text NOT NULL,
  `dataUsageAgreement` int NOT NULL,
  `total_amount` decimal(6,2) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `payment_confirmed` tinyint(1) DEFAULT '0',
  `payment_confirmed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tcf_registrations`
--

INSERT INTO `tcf_registrations` (`id`, `name`, `firstname`, `gender`, `birthday`, `countryOfBirth`, `cityOfBirth`, `nationality`, `identityDocumentNumber`, `language`, `oldCandidateCode`, `address`, `city`, `postalCode`, `country`, `phoneCountryCode`, `phone`, `email`, `exam`, `testCE`, `testCO`, `testEE`, `testEO`, `reasonsForRegistration`, `disiredSession`, `specialNeeds`, `specialNeedsDetails`, `dataUsageAgreement`, `total_amount`, `created_at`, `updated_at`, `payment_confirmed`, `payment_confirmed_at`) VALUES
(14, 'Karmann', 'Jérôme', 1, '1972-11-27', 73, 25964, 65, '7627867288746', 134, '1234ewrererew', '249, rue Siméon-Delisle', 'Portneuf', 'G0A 2Y0', 39, '+1', '4182832793', 'jerome@mediactive.ca', 2, 0, 1, 0, 1, 5, 1, 2, '', 1, 4.20, '2026-01-27 13:15:03', '2026-01-28 13:08:39', 1, '2026-01-28 12:07:49'),
(15, 'Karmann', 'Jérôme Jean', 1, '1972-11-26', 73, 24282, 65, '762786728874688', 134, '1234', '249, rue Siméon-Delisle', 'Portneuf', 'G0A 2Y0', 39, '+1', '4182832793', 'jerome.karmann@gmail.com', 2, 0, 1, 0, 0, 6, 1, 1, 'sdasdsdsd', 1, 1.10, '2026-01-27 21:53:04', '2026-01-28 12:47:51', 1, '2026-01-28 12:47:51'),
(16, 'Karmann', 'Jérôme', 1, '1960-02-14', 39, 0, 85, '838942304938908', 197, 'djhfsdkh', '249, rue Siméon-Delisle', 'Portneuf', 'G0A 2Y0', 39, '+1', '4182832793', 'jerome@mediactive.ca', 2, 1, 0, 1, 0, 8, 1, 1, 'ksdasds\r\ndsasdljsaf\r\nTESTé,D\'ASd', 1, 2.00, '2026-01-28 12:32:43', '2026-01-28 12:33:36', 1, '2026-01-28 12:33:36'),
(17, 'Karmann', 'Jérôme', 1, '1972-11-27', 39, 0, 33, '838942304938908', 9, '1234', '249, rue Siméon-Delisle', 'Portneuf', 'G0A 2Y0', 19, '+1', '4182832793', 'jerome@mediactive.ca', 2, 0, 1, 1, 0, 3, 1, 1, '', 1, 2.10, '2026-01-28 12:54:22', '2026-01-28 12:54:22', 0, NULL),
(18, 'Karmann', 'Jérôme', 1, '1972-11-27', 39, 0, 33, '838942304938908', 9, '1234', '249, rue Siméon-Delisle', 'Portneuf', 'G0A 2Y0', 19, '+1', '4182832793', 'jerome@mediactive.ca', 2, 0, 1, 1, 0, 3, 1, 2, '', 1, 2.10, '2026-01-28 12:54:39', '2026-01-28 12:54:39', 0, NULL),
(19, 'Karmann', 'Jérôme', 1, '1972-11-27', 39, 0, 33, '838942304938908', 9, '1234', '249, rue Siméon-Delisle', 'Portneuf', 'G0A 2Y0', 19, '+1', '4182832793', 'jerome@mediactive.ca', 2, 0, 1, 1, 0, 3, 1, 2, '', 1, 2.10, '2026-01-28 12:56:25', '2026-01-28 12:56:25', 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tcf_registrations`
--
ALTER TABLE `tcf_registrations`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tcf_registrations`
--
ALTER TABLE `tcf_registrations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
