-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 27, 2024 at 08:18 AM
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
-- Database: `solayaodb`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact_number` varchar(15) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `extension_name` varchar(10) DEFAULT NULL,
  `age` int(255) NOT NULL,
  `birthday` date NOT NULL,
  `sex` enum('male','female') NOT NULL,
  `password` varchar(255) NOT NULL,
  `street_purok` varchar(100) DEFAULT NULL,
  `barangay` varchar(50) DEFAULT NULL,
  `city_municipal` varchar(50) DEFAULT NULL,
  `province` varchar(50) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `zipcode` varchar(10) DEFAULT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `id_number`, `email`, `contact_number`, `first_name`, `username`, `middle_name`, `last_name`, `extension_name`, `age`, `birthday`, `sex`, `password`, `street_purok`, `barangay`, `city_municipal`, `province`, `country`, `zipcode`, `registration_date`) VALUES
(31, '2022-0945', 'florencecris.solayao@csucc.edu.ph', '09234432111', 'Cris', 'admin', '', 'Cris', '', 0, '2003-04-04', 'male', '$2y$10$aNF8Eh6KuuabwveZ2n3it.VkcjOCxTnD70/A2XEujay63NA.8o0cq', 'Asdsad', 'Asdasd', 'Asdad', 'Adasd', 'Asdasd', '1234', '2024-11-25 22:25:23'),
(70, '2022-1111', 'florencecris.solayao111@csucc.edu.ph', '09127195265', 'Richard', 'sad123', 'Ligalig', 'Junio', '', 0, '0200-11-11', 'female', '$2y$10$5tvFCEf3BVq/G026yrrmOO1VG5u5pBotDKH9L6Ij0BRB4UMu5vpXi', 'Purok 8', 'Kk', 'Magallanes', 'Agusan Del Norte', 'Philippines', '8604', '2024-11-27 06:27:57'),
(71, '2022-0934', 'florencecris.solayao24@csucc.edu.ph', '09383852392', 'Richard', 'cirs24.solayao', 'Ligalig', 'Junio', '', 0, '2003-02-22', 'male', '$2y$10$BvhOGQgVtgHV9DectDRZo.67Ct1Wf.9..tRCJkbQ2om25n9TC7XZG', 'Purok 8', 'Kk', 'Magallanes', 'Agusan Del Norte', 'Philippines', '8604', '2024-11-27 06:39:11');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
-- 
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD UNIQUE KEY `password` (`password`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
