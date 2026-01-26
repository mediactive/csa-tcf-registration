-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jan 26, 2026 at 06:19 PM
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
  `phone` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `exam` int NOT NULL,
  `tcfQuebecSelectedTests` int NOT NULL,
  `reasonsForRegistration` int NOT NULL,
  `disiredSession` int NOT NULL,
  `specialNeeds` int NOT NULL,
  `specialNeedsDetails` text NOT NULL,
  `dataUsageAgreement` int NOT NULL,
  `total_amount` decimal(6,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
