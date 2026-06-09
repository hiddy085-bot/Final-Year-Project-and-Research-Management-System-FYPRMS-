-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 09, 2026 at 12:10 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fd`
--

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `title` varchar(300) NOT NULL,
  `abstract` text DEFAULT NULL,
  `authors` varchar(300) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `university_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `title`, `abstract`, `authors`, `year`, `file_path`, `student_id`, `university_id`, `status`, `created_at`) VALUES
(1, 'online registration', 'it is good', 'Hussein', 2026, 'uploads/1780868713_CamScanner_24-05-2026_16_27.pdf', 2, 3, 'approved', '2026-06-07 21:45:13');

-- --------------------------------------------------------

--
-- Table structure for table `research`
--

CREATE TABLE `research` (
  `id` int(11) NOT NULL,
  `title` varchar(300) NOT NULL,
  `abstract` text DEFAULT NULL,
  `authors` varchar(300) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `university_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `universities`
--

CREATE TABLE `universities` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `universities`
--

INSERT INTO `universities` (`id`, `name`) VALUES
(2, 'Ardhi University'),
(8, 'Dar es Salaam Institute of Technology'),
(27, 'Dar es Salaam University College of Education'),
(26, 'Dominican University College'),
(22, 'Iringa University'),
(18, 'Kilimanjaro Christian Medical University College'),
(9, 'Mbeya University of Science and Technology'),
(15, 'Mkwawa University College of Education'),
(19, 'Mount Meru University'),
(3, 'Muhimbili University of Health and Allied Sciences'),
(17, 'Muslim University of Morogoro'),
(6, 'Mzumbe University'),
(10, 'Nelson Mandela African Institution of Science and Technology'),
(11, 'Open University of Tanzania'),
(24, 'Saint Joseph University College'),
(4, 'Sokoine University of Agriculture'),
(12, 'St. Augustine University of Tanzania'),
(14, 'St. John\'s University of Tanzania'),
(25, 'St. Mary\'s University College'),
(16, 'State University of Zanzibar'),
(7, 'Tanzania University of Science and Technology'),
(21, 'Teofilo Kisanji University'),
(13, 'Tumaini University Makumira'),
(20, 'University of Arusha'),
(1, 'University of Dar es Salaam'),
(23, 'University of Dar es Salaam - College of Engineering and Technology'),
(5, 'University of Dodoma');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','supervisor','administrator') DEFAULT 'student',
  `university_id` int(11) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `email`, `password`, `role`, `university_id`, `department`) VALUES
(2, 'Hussein idd samihu', 'hiddy085@gmail.com', '$2y$10$SntnLyyGD6JfIgav8/oWlO.S0Jf4cfADA5LMvriD.Hmcd1ikdgx9W', 'student', 3, 'science '),
(3, 'System Administrator', 'admin@fyprms.com', '$2y$10$aPsRJIZ.SL5HVytP9wSHvuSmRaruBZkcY1/sJVpliAJXGcHV1bGKy', 'administrator', 1, 'Administration'),
(5, 'Dr. John Supervisor', 'supervisor@fyprms.com', '$2y$10$3agu/X8lLg/GI9lmOEwwIeFJTISR/hRKYopjg6Zn7RgvAsGUXmqmm', 'supervisor', 1, 'Computer Science');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `university_id` (`university_id`);

--
-- Indexes for table `research`
--
ALTER TABLE `research`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `university_id` (`university_id`);

--
-- Indexes for table `universities`
--
ALTER TABLE `universities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `university_id` (`university_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `research`
--
ALTER TABLE `research`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `universities`
--
ALTER TABLE `universities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `projects_ibfk_2` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`);

--
-- Constraints for table `research`
--
ALTER TABLE `research`
  ADD CONSTRAINT `research_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `research_ibfk_2` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
