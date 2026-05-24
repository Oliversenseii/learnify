-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql302.infinityfree.com
-- Generation Time: Nov 16, 2025 at 06:26 AM
-- Server version: 11.4.7-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_40036013_db_lms`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_events`
--

CREATE TABLE `academic_events` (
  `eventID` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `eventDate` date NOT NULL,
  `eventType` enum('Holiday','Event') NOT NULL,
  `archived` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_events`
--

INSERT INTO `academic_events` (`eventID`, `title`, `description`, `eventDate`, `eventType`, `archived`, `created_by`, `created_at`) VALUES
(17, 'final defense', '', '2025-11-04', 'Event', 0, 148, '2025-11-12 02:18:08');

-- --------------------------------------------------------

--
-- Table structure for table `admin_messages`
--

CREATE TABLE `admin_messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_messages`
--

INSERT INTO `admin_messages` (`message_id`, `sender_id`, `recipient_id`, `content`, `attachment_path`, `created_at`, `updated_at`, `archived`) VALUES
(34, 151, 148, '123', NULL, '2025-11-07 07:06:05', NULL, 0),
(35, 148, 151, '456', NULL, '2025-11-07 07:06:25', NULL, 0),
(36, 148, 151, 'HI SIR BORJ', NULL, '2025-11-12 02:13:24', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `advisory_professor_section`
--

CREATE TABLE `advisory_professor_section` (
  `advisoryID` int(11) NOT NULL,
  `professorID` int(11) NOT NULL,
  `sectionID` int(11) NOT NULL,
  `subjectID` int(11) NOT NULL,
  `assignedDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcementID` int(11) NOT NULL,
  `teacherSectionID` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `createdDate` datetime DEFAULT current_timestamp(),
  `archived` tinyint(4) DEFAULT 0,
  `fileName` varchar(255) DEFAULT NULL,
  `filePath` varchar(255) DEFAULT NULL,
  `fileType` varchar(100) DEFAULT NULL,
  `fileSize` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`announcementID`, `teacherSectionID`, `title`, `content`, `createdDate`, `archived`, `fileName`, `filePath`, `fileType`, `fileSize`) VALUES
(29, 25, '123', '123333', '2025-11-06 23:42:44', 0, 'image.jpg', '../../uploads/announcements/1762501364_image.jpg', 'image/jpeg', 165325);

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `assignmentID` int(11) NOT NULL,
  `teacherSectionID` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `dueDate` date DEFAULT NULL,
  `maxScore` int(11) DEFAULT 0,
  `createdDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `filePath` varchar(255) DEFAULT NULL,
  `fileName` varchar(255) DEFAULT NULL,
  `fileType` varchar(50) DEFAULT NULL,
  `fileSize` bigint(20) DEFAULT NULL,
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignment_scores`
--

CREATE TABLE `assignment_scores` (
  `scoreID` int(11) NOT NULL,
  `assignmentID` int(11) NOT NULL,
  `studentID` int(11) NOT NULL,
  `totalScore` int(11) NOT NULL DEFAULT 0,
  `maxScore` int(11) NOT NULL DEFAULT 0,
  `recordedDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignment_submissions`
--

CREATE TABLE `assignment_submissions` (
  `submissionID` int(11) NOT NULL,
  `assignmentID` int(11) NOT NULL,
  `studentID` int(11) NOT NULL,
  `filePath` varchar(255) DEFAULT NULL,
  `fileName` varchar(255) DEFAULT NULL,
  `fileType` varchar(50) DEFAULT NULL,
  `fileSize` bigint(20) DEFAULT NULL,
  `submissionDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendanceID` int(11) NOT NULL,
  `studentID` int(11) NOT NULL,
  `teacherSectionID` int(11) NOT NULL,
  `attendanceDate` date NOT NULL,
  `status` enum('Present','Absent','Late') NOT NULL,
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `brainpix_badges`
--

CREATE TABLE `brainpix_badges` (
  `badgeID` int(11) NOT NULL,
  `badgeName` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `imageURL` varchar(255) DEFAULT NULL,
  `mapID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `brainpix_badges`
--

INSERT INTO `brainpix_badges` (`badgeID`, `badgeName`, `description`, `imageURL`, `mapID`) VALUES
(9, 'Bronze Piece', 'The starter token', '../../uploads/BrainPix/badge_68d7e9d80d5c1.png', 4),
(11, 'Silver Shard', 'A step up in rarity', '../../uploads/BrainPix/badge_68d7ee23035ab.png', 5),
(12, 'Golden Crest', 'Shining with progress', '../../uploads/BrainPix/badge_68d7f1faf3e49.png', 6);

-- --------------------------------------------------------

--
-- Table structure for table `brainpix_levels`
--

CREATE TABLE `brainpix_levels` (
  `levelID` int(11) NOT NULL,
  `mapID` int(11) NOT NULL,
  `levelNum` int(11) NOT NULL,
  `imageURL` varchar(255) NOT NULL,
  `correctAnswer` varchar(255) NOT NULL,
  `hint` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `brainpix_levels`
--

INSERT INTO `brainpix_levels` (`levelID`, `mapID`, `levelNum`, `imageURL`, `correctAnswer`, `hint`) VALUES
(91, 4, 1, '../../uploads/BrainPix/level_68d7e9bc50de5.png', 'history repeats itself', ''),
(92, 4, 2, '../../uploads/BrainPix/level_68d7ea2c5cf0f.png', 'repeat after me', ''),
(93, 4, 3, '../../uploads/BrainPix/level_68d7ea477fe95.png', 'read between the lines', ''),
(94, 4, 4, '../../uploads/BrainPix/level_68d7ea5ed95ad.png', 'blanket', ''),
(95, 4, 5, '../../uploads/BrainPix/level_68d7ea83017f0.png', 'four meals a day', ''),
(96, 4, 6, '../../uploads/BrainPix/level_68d7eaa733bb1.png', 'banana split', ''),
(97, 4, 7, '../../uploads/BrainPix/level_68d7eac509cd5.png', 'nothing at all', ''),
(98, 4, 8, '../../uploads/BrainPix/level_68d7eae256427.png', 'ice cube', ''),
(99, 4, 9, '../../uploads/BrainPix/level_68d7eaffbd768.png', 'big show', ''),
(100, 4, 10, '../../uploads/BrainPix/level_68d7eb515ca29.png', 'excuse me', ''),
(101, 4, 11, '../../uploads/BrainPix/level_68d7eb9865836.png', 'multiple issues', ''),
(102, 4, 12, '../../uploads/BrainPix/level_68d7eba994edc.png', 'settle down', ''),
(103, 4, 13, '../../uploads/BrainPix/level_68d7ebbbbed1a.png', 'right beside me', ''),
(104, 4, 14, '../../uploads/BrainPix/level_68d7ebc91f673.png', 'time after time', ''),
(105, 4, 15, '../../uploads/BrainPix/level_68d7ebd88aca3.png', 'one in a million', ''),
(106, 4, 16, '../../uploads/BrainPix/level_68d7ec12ea90f.png', 'safety in numbers', ''),
(107, 4, 17, '../../uploads/BrainPix/level_68d7ec26a4ad0.png', 'bad influence', ''),
(108, 4, 18, '../../uploads/BrainPix/level_68d7ec38a1c54.png', 'little by little', ''),
(109, 4, 19, '../../uploads/BrainPix/level_68d7ec5105f17.png', 'to do list', ''),
(110, 4, 20, '../../uploads/BrainPix/level_68d7ec6450004.png', 'broken promise', ''),
(111, 4, 21, '../../uploads/BrainPix/level_68d7ed0a8cfb1.png', 'think outside the box', ''),
(112, 4, 22, '../../uploads/BrainPix/level_68d7ed1f7e3bd.png', 'standing ovation', ''),
(113, 4, 23, '../../uploads/BrainPix/level_68d7ed324fa49.png', 'pie in the sky', ''),
(114, 4, 24, '../../uploads/BrainPix/level_68d7ed455f5d4.png', 'kiss and make up', ''),
(115, 4, 25, '../../uploads/BrainPix/level_68d7ed5551fe6.png', 'forgive and forget', ''),
(116, 4, 26, '../../uploads/BrainPix/level_68d7ed688d3d0.png', 'seven seas', ''),
(117, 4, 27, '../../uploads/BrainPix/level_68d7ed86243de.png', 'repair', ''),
(118, 4, 28, '../../uploads/BrainPix/level_68d7ed952a1c7.png', 'an inside job', ''),
(119, 4, 29, '../../uploads/BrainPix/level_68d7eda3bf99f.png', 'forever and a day', ''),
(120, 4, 30, '../../uploads/BrainPix/level_68d7edb594ee2.png', 'by and large', ''),
(121, 5, 1, '../../uploads/BrainPix/level_68d7eea0abc56.png', 'search high and low', ''),
(122, 5, 2, '../../uploads/BrainPix/level_68d7eeb3b204e.png', 'a spell of bad weather', ''),
(123, 5, 3, '../../uploads/BrainPix/level_68d7eec3529ea.png', 'a step above the rest', ''),
(124, 5, 4, '../../uploads/BrainPix/level_68d7eed28dcf7.png', 'every dog has its day', ''),
(125, 5, 5, '../../uploads/BrainPix/level_68d7eee337e4e.png', 'quarterback', ''),
(126, 5, 6, '../../uploads/BrainPix/level_68d7eef621d62.png', 'seaside', ''),
(127, 5, 7, '../../uploads/BrainPix/level_68d7ef15dc0fc.png', 'too good too be true', ''),
(128, 5, 8, '../../uploads/BrainPix/level_68d7ef2644d62.png', 'american pie pizza', ''),
(129, 5, 9, '../../uploads/BrainPix/level_68d7ef37a7cde.png', 'there s no i in team', ''),
(130, 5, 10, '../../uploads/BrainPix/level_68d7ef52153ae.png', 'breakfast', ''),
(131, 5, 11, '../../uploads/BrainPix/level_68d7efa22838e.png', 'fork in the road', ''),
(132, 5, 12, '../../uploads/BrainPix/level_68d7efb4e7cca.png', 'bury your head in the sand', ''),
(133, 5, 13, '../../uploads/BrainPix/level_68d7efc7bcd43.png', 'a drop in the ocean', ''),
(134, 5, 14, '../../uploads/BrainPix/level_68d7efd954f96.png', 'in the middle of nowhere', ''),
(135, 5, 15, '../../uploads/BrainPix/level_68d7efeee0f22.png', 'going in circles', ''),
(136, 5, 16, '../../uploads/BrainPix/level_68d7efffd9ffb.png', 'middle age spread', ''),
(137, 5, 17, '../../uploads/BrainPix/level_68d7f010e282d.png', 'mind over matter', ''),
(138, 5, 18, '../../uploads/BrainPix/level_68d7f0220220f.png', 'what goes up must come down', ''),
(139, 5, 19, '../../uploads/BrainPix/level_68d7f0318d4a2.png', 'once in a blue moon', ''),
(140, 5, 20, '../../uploads/BrainPix/level_68d7f0401fab4.png', 'just between you and me', ''),
(141, 5, 21, '../../uploads/BrainPix/level_68d7f08d6d267.png', 'down to earth', ''),
(142, 5, 22, '../../uploads/BrainPix/level_68d7f09d99237.png', 'mark my words', ''),
(143, 5, 23, '../../uploads/BrainPix/level_68d7f0adc54a6.png', 'high chair', ''),
(144, 5, 24, '../../uploads/BrainPix/level_68d7f0bfd7cef.png', 'space invaders', ''),
(145, 5, 25, '../../uploads/BrainPix/level_68d7f0cf96853.png', 'double cross', ''),
(146, 5, 26, '../../uploads/BrainPix/level_68d7f0e524953.png', 'temperatures rising', ''),
(147, 5, 27, '../../uploads/BrainPix/level_68d7f0f67fb2d.png', 'no idea', ''),
(148, 5, 28, '../../uploads/BrainPix/level_68d7f10841089.png', 'wish upon a star', ''),
(149, 5, 29, '../../uploads/BrainPix/level_68d7f117798c6.png', 'just in case', ''),
(150, 5, 30, '../../uploads/BrainPix/level_68d7f1283212b.png', 'all roads lead to rome', ''),
(151, 6, 1, '../../uploads/BrainPix/level_68d7f225ede11.png', 'life begins at 40', ''),
(152, 6, 2, '../../uploads/BrainPix/level_68d7f23d8fe44.png', 'no tv for a week', ''),
(153, 6, 3, '../../uploads/BrainPix/level_68d7f2626fb48.png', 'odds and ends', ''),
(154, 6, 4, '../../uploads/BrainPix/level_68d7f276cac87.png', 'going for gold', ''),
(155, 6, 5, '../../uploads/BrainPix/level_68d7f2866093f.png', 'left for dead two', ''),
(156, 6, 6, '../../uploads/BrainPix/level_68d7f296c80dc.png', 'back in a minute', ''),
(157, 6, 7, '../../uploads/BrainPix/level_68d7f2a82c4fd.png', 'a picture is worth a thousand words', ''),
(158, 6, 8, '../../uploads/BrainPix/level_68d7f2bb85c63.png', 'green elephant', ''),
(159, 6, 9, '../../uploads/BrainPix/level_68d7f2c91cbce.png', 'raising the red flag', ''),
(160, 6, 10, '../../uploads/BrainPix/level_68d7f2d6bd205.png', 'water', ''),
(161, 6, 11, '../../uploads/BrainPix/level_68d7f32919ba2.png', 'the only game in town', ''),
(162, 6, 12, '../../uploads/BrainPix/level_68d7f33946a32.png', 'methapor', ''),
(163, 6, 13, '../../uploads/BrainPix/level_68d7f351d0fde.png', 'foreign language', ''),
(164, 6, 14, '../../uploads/BrainPix/level_68d7f361e328e.png', 'balanced meal', ''),
(165, 6, 15, '../../uploads/BrainPix/level_68d7f37131a5b.png', 'for once in my life', ''),
(166, 6, 16, '../../uploads/BrainPix/level_68d7f381717c0.png', 'right between the eyes', ''),
(167, 6, 17, '../../uploads/BrainPix/level_68d7f391a01c4.png', 'no one is to be blame', ''),
(168, 6, 18, '../../uploads/BrainPix/level_68d7f3a2ba240.png', 'to be or not to be', ''),
(169, 6, 19, '../../uploads/BrainPix/level_68d7f3b403593.png', 'ducks in a row', ''),
(170, 6, 20, '../../uploads/BrainPix/level_68d7f3c2dea57.png', 'over my dead body', ''),
(171, 6, 21, '../../uploads/BrainPix/level_68d7f40edc1d7.png', 'afternoon tea', ''),
(172, 6, 22, '../../uploads/BrainPix/level_68d7f41e0d8f4.png', 'bags under your eyes', ''),
(173, 6, 23, '../../uploads/BrainPix/level_68d7f42c002eb.png', 'a splitting headache', ''),
(174, 6, 24, '../../uploads/BrainPix/level_68d7f43b1ec87.png', 'corners of the earth', ''),
(175, 6, 25, '../../uploads/BrainPix/level_68d7f44891178.png', 'a touching moment', ''),
(176, 6, 26, '../../uploads/BrainPix/level_68d7f458382fb.png', 'day in day out', ''),
(177, 6, 27, '../../uploads/BrainPix/level_68d7f4663c945.png', 'mixed feelings', ''),
(178, 6, 28, '../../uploads/BrainPix/level_68d7f47b4e0e1.png', 'milkshake', ''),
(179, 6, 29, '../../uploads/BrainPix/level_68d7f48b21534.png', 'arm in arm', ''),
(180, 6, 30, '../../uploads/BrainPix/level_68d7f49b78005.png', 'no spring red chicken', ''),
(181, 7, 1, '../../uploads/BrainPix/level_68d8428718fff.png', 'John Cena', ''),
(182, 7, 2, '../../uploads/BrainPix/level_68d843b7ab9a9.png', 'Britney Spears', ''),
(183, 7, 3, '../../uploads/BrainPix/level_68d844897ffe0.png', 'Adele', ''),
(184, 7, 4, '../../uploads/BrainPix/level_68d8462c361c7.png', 'buzz lightyear', ''),
(185, 7, 5, '../../uploads/BrainPix/level_68d891ad1b02d.png', 'Rose', ''),
(186, 7, 6, '../../uploads/BrainPix/level_68d892945abf1.png', 'Captain America', ''),
(187, 7, 7, '../../uploads/BrainPix/level_68d8934f8126d.png', 'The Terminator', ''),
(188, 7, 8, '../../uploads/BrainPix/level_68d8955a90ddd.png', 'Uncle Ben', ''),
(189, 7, 9, '../../uploads/BrainPix/level_68d896de03a93.png', 'Annie and Hallie', ''),
(190, 7, 10, '../../uploads/BrainPix/level_68d89908c14e0.png', 'Pikachu', '');

-- --------------------------------------------------------

--
-- Table structure for table `brainpix_maps`
--

CREATE TABLE `brainpix_maps` (
  `mapID` int(11) NOT NULL,
  `mapName` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `orderNum` int(11) DEFAULT 0,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `brainpix_maps`
--

INSERT INTO `brainpix_maps` (`mapID`, `mapName`, `description`, `orderNum`, `createdAt`) VALUES
(4, 'Brain Teasers I', '', 30, '2025-09-27 13:41:24'),
(5, 'Brain Teasers II', '', 30, '2025-09-27 13:59:39'),
(6, 'Brain Treasure III', '', 30, '2025-09-27 14:16:57'),
(7, 'Guess who?', '', 30, '2025-09-27 19:09:55');

-- --------------------------------------------------------

--
-- Table structure for table `brainpix_user_badges`
--

CREATE TABLE `brainpix_user_badges` (
  `userBadgeID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `badgeID` int(11) NOT NULL,
  `awardedDate` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `brainpix_user_badges`
--

INSERT INTO `brainpix_user_badges` (`userBadgeID`, `userID`, `badgeID`, `awardedDate`) VALUES
(3, 149, 9, '2025-10-04 06:37:43');

-- --------------------------------------------------------

--
-- Table structure for table `brainpix_user_progress`
--

CREATE TABLE `brainpix_user_progress` (
  `progressID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `levelID` int(11) NOT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `attempts` int(11) DEFAULT 0,
  `lastAttempt` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `brainpix_user_progress`
--

INSERT INTO `brainpix_user_progress` (`progressID`, `userID`, `levelID`, `completed`, `attempts`, `lastAttempt`) VALUES
(115, 149, 91, 1, 1, '2025-09-29 20:48:20'),
(116, 155, 91, 1, 1, '2025-10-03 20:52:42'),
(117, 149, 92, 1, 1, '2025-10-03 23:22:03'),
(118, 149, 93, 1, 1, '2025-10-03 23:22:41'),
(119, 149, 94, 1, 1, '2025-10-03 23:22:52'),
(120, 149, 95, 1, 1, '2025-10-03 23:23:24'),
(121, 149, 96, 1, 1, '2025-10-03 23:23:53'),
(122, 149, 97, 1, 1, '2025-10-03 23:24:19'),
(123, 149, 98, 1, 1, '2025-10-03 23:24:28'),
(124, 149, 99, 1, 1, '2025-10-03 23:24:52'),
(125, 149, 100, 1, 1, '2025-10-03 23:25:41'),
(126, 149, 101, 1, 1, '2025-10-03 23:26:43'),
(127, 149, 102, 1, 1, '2025-10-03 23:26:56'),
(128, 149, 103, 1, 1, '2025-10-03 23:27:54'),
(129, 149, 104, 1, 1, '2025-10-03 23:28:20'),
(130, 149, 105, 1, 1, '2025-10-03 23:28:33'),
(131, 149, 106, 1, 1, '2025-10-03 23:28:56'),
(132, 149, 107, 1, 1, '2025-10-03 23:30:08'),
(133, 149, 108, 1, 1, '2025-10-03 23:30:41'),
(134, 149, 109, 1, 1, '2025-10-03 23:30:52'),
(135, 149, 110, 1, 1, '2025-10-03 23:31:09'),
(136, 149, 111, 1, 1, '2025-10-03 23:31:26'),
(137, 149, 112, 1, 1, '2025-10-03 23:31:43'),
(138, 149, 113, 1, 1, '2025-10-03 23:32:26'),
(139, 149, 114, 1, 1, '2025-10-03 23:33:20'),
(140, 149, 115, 1, 1, '2025-10-03 23:33:58'),
(141, 149, 116, 1, 1, '2025-10-03 23:34:21'),
(142, 149, 117, 1, 1, '2025-10-03 23:35:33'),
(143, 149, 118, 1, 1, '2025-10-03 23:35:47'),
(144, 149, 119, 1, 1, '2025-10-03 23:36:30'),
(145, 149, 120, 1, 1, '2025-10-03 23:37:43'),
(146, 152, 91, 1, 1, '2025-11-06 23:51:21');

-- --------------------------------------------------------

--
-- Table structure for table `branding`
--

CREATE TABLE `branding` (
  `id` int(11) NOT NULL,
  `logo_image_path` varchar(255) NOT NULL DEFAULT './img/darky-1.png',
  `logo_text` varchar(100) NOT NULL DEFAULT 'Learnify',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) NOT NULL,
  `archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branding`
--

INSERT INTO `branding` (`id`, `logo_image_path`, `logo_text`, `updated_at`, `updated_by`, `archived`) VALUES
(32, '../../uploads/logo/1758960327_1753587546_learnify-logo.png', 'Learnify', '2025-10-04 03:22:16', 147, 1),
(33, '../../uploads/logo/1758960327_1753587546_learnify-logo.png', 'Learniko', '2025-10-04 03:22:42', 147, 1),
(34, '../../uploads/logo/1758960327_1753587546_learnify-logo.png', 'Learnify', '2025-11-12 02:11:27', 147, 1),
(35, '../../uploads/logo/1758960327_1753587546_learnify-logo.png', 'Learnifys', '2025-11-12 02:11:36', 147, 1),
(36, '../../uploads/logo/1758960327_1753587546_learnify-logo.png', 'Learnify', '2025-11-12 02:11:36', 147, 0);

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `commentID` int(11) NOT NULL,
  `teacherSectionID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `content` text NOT NULL,
  `parentCommentID` int(11) DEFAULT NULL,
  `createdDate` datetime NOT NULL,
  `updatedDate` datetime DEFAULT NULL,
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `comment_notifications`
--

CREATE TABLE `comment_notifications` (
  `notificationID` int(11) NOT NULL,
  `commentID` int(11) NOT NULL,
  `notifiedUserID` int(11) NOT NULL,
  `seen` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_feedback`
--

CREATE TABLE `contact_feedback` (
  `messageID` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `csv_upload_logs`
--

CREATE TABLE `csv_upload_logs` (
  `log_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_timestamp` datetime NOT NULL,
  `successful_records` int(11) NOT NULL,
  `failed_records` int(11) NOT NULL,
  `errors` text DEFAULT NULL,
  `archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_login_attempts`
--

CREATE TABLE `failed_login_attempts` (
  `attempt_id` int(11) NOT NULL,
  `userID` int(11) DEFAULT NULL,
  `attempt_time` datetime NOT NULL,
  `ip_address` varchar(255) DEFAULT NULL,
  `archived` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `failed_login_attempts`
--

INSERT INTO `failed_login_attempts` (`attempt_id`, `userID`, `attempt_time`, `ip_address`, `archived`) VALUES
(124, NULL, '2025-09-27 00:51:04', '123.253.50.127', 0),
(125, 147, '2025-09-29 02:28:00', '123.253.50.127', 0),
(126, 149, '2025-09-29 20:03:04', '123.253.50.127', 0),
(127, 147, '2025-09-29 20:57:45', '123.253.50.127', 0),
(128, 151, '2025-09-30 09:29:32', '123.253.50.127', 0),
(129, NULL, '2025-09-30 20:16:07', '123.253.50.127', 0),
(130, NULL, '2025-09-30 20:16:26', '123.253.50.127', 0),
(131, 152, '2025-10-01 02:10:06', '103.91.141.107', 0),
(132, 151, '2025-10-01 23:09:03', '123.253.50.127', 0),
(133, NULL, '2025-10-03 06:50:38', '103.137.205.126', 0),
(134, NULL, '2025-10-03 19:16:57', '103.91.141.107', 0),
(135, NULL, '2025-10-03 20:04:00', '103.91.141.107', 0),
(136, 148, '2025-10-03 20:05:26', '103.91.141.107', 0),
(137, 148, '2025-10-03 20:07:16', '103.91.141.107', 0),
(138, 148, '2025-10-03 20:07:22', '103.91.141.107', 0),
(139, NULL, '2025-10-03 20:19:03', '103.91.141.107', 0),
(140, 151, '2025-10-03 23:12:43', '103.91.141.107', 0),
(141, 152, '2025-10-05 05:17:31', '103.91.141.107', 0),
(142, NULL, '2025-10-07 23:50:52', '103.137.205.126', 0),
(143, NULL, '2025-10-07 23:53:59', '103.137.205.126', 0),
(144, NULL, '2025-10-08 00:02:22', '103.137.205.126', 0),
(145, NULL, '2025-10-08 00:03:01', '103.137.205.126', 0),
(146, NULL, '2025-10-08 00:03:02', '103.137.205.126', 0),
(147, NULL, '2025-10-08 00:04:24', '103.137.205.126', 0),
(148, 152, '2025-10-08 00:06:14', '103.137.205.126', 0),
(149, 152, '2025-10-08 00:06:34', '103.137.205.126', 0),
(150, 147, '2025-10-08 03:53:07', '131.226.107.154', 0),
(151, NULL, '2025-10-08 03:53:33', '131.226.107.154', 0),
(152, NULL, '2025-10-08 04:03:50', '110.54.142.232', 0),
(153, NULL, '2025-10-08 04:13:37', '131.226.104.125', 0),
(154, NULL, '2025-10-08 05:02:39', '64.224.98.95', 0),
(155, NULL, '2025-10-08 06:16:29', '123.253.50.235', 0),
(156, 147, '2025-10-08 21:38:05', '123.253.50.127', 0),
(157, 147, '2025-10-14 11:11:45', '123.253.50.127', 0),
(158, 147, '2025-10-14 11:11:50', '123.253.50.127', 0),
(159, 147, '2025-10-20 22:19:11', '123.253.50.127', 0),
(160, NULL, '2025-10-23 07:30:01', '123.253.50.127', 0),
(161, 149, '2025-10-23 07:30:17', '123.253.50.127', 0),
(162, 149, '2025-10-23 19:41:29', '123.253.50.127', 0),
(163, NULL, '2025-10-23 19:47:42', '123.253.50.127', 0),
(164, 148, '2025-10-29 22:40:19', '123.253.49.176', 0),
(165, NULL, '2025-10-29 23:32:48', '103.91.141.107', 0),
(166, NULL, '2025-10-29 23:32:58', '103.91.141.107', 0),
(167, NULL, '2025-10-29 23:33:17', '103.91.141.107', 0),
(168, NULL, '2025-11-06 03:36:34', '123.253.50.127', 0),
(169, NULL, '2025-11-06 03:38:27', '123.253.50.127', 0),
(170, NULL, '2025-11-06 19:14:35', '103.91.141.89', 0),
(171, NULL, '2025-11-06 19:14:49', '103.91.141.89', 0),
(172, NULL, '2025-11-06 19:14:56', '103.91.141.89', 0),
(173, NULL, '2025-11-06 19:17:26', '103.91.141.89', 0),
(174, NULL, '2025-11-06 22:10:49', '103.91.141.89', 0),
(175, NULL, '2025-11-11 10:16:23', '209.38.173.77', 0),
(176, NULL, '2025-11-11 10:16:25', '209.38.173.77', 0),
(177, NULL, '2025-11-11 10:16:26', '209.38.173.77', 0),
(178, NULL, '2025-11-11 10:16:28', '209.38.173.77', 0),
(179, NULL, '2025-11-11 10:21:49', '82.133.49.225', 0),
(180, NULL, '2025-11-11 10:21:50', '82.133.49.225', 0),
(181, NULL, '2025-11-11 10:21:51', '82.133.49.225', 0),
(182, NULL, '2025-11-11 10:21:53', '82.133.49.225', 0),
(183, NULL, '2025-11-11 10:28:30', '109.70.100.68', 0),
(184, NULL, '2025-11-11 10:28:31', '109.70.100.68', 0),
(185, NULL, '2025-11-11 10:28:33', '109.70.100.68', 0),
(186, NULL, '2025-11-11 10:28:34', '109.70.100.68', 0),
(187, NULL, '2025-11-11 10:36:38', '81.105.225.207', 0),
(188, NULL, '2025-11-11 10:36:40', '81.105.225.207', 0),
(189, NULL, '2025-11-11 10:36:42', '81.105.225.207', 0),
(190, NULL, '2025-11-11 10:36:44', '81.105.225.207', 0),
(191, NULL, '2025-11-11 10:49:43', '88.97.176.211', 0),
(192, NULL, '2025-11-11 10:49:45', '88.97.176.211', 0),
(193, NULL, '2025-11-11 10:49:46', '88.97.176.211', 0),
(194, NULL, '2025-11-11 10:49:48', '88.97.176.211', 0),
(195, NULL, '2025-11-11 13:31:05', '209.38.171.212', 0),
(196, NULL, '2025-11-11 13:31:06', '209.38.171.212', 0),
(197, NULL, '2025-11-11 13:31:07', '209.38.171.212', 0),
(198, NULL, '2025-11-11 13:31:08', '209.38.171.212', 0),
(199, NULL, '2025-11-11 13:37:41', '209.38.171.212', 0),
(200, NULL, '2025-11-11 13:37:43', '209.38.171.212', 0),
(201, NULL, '2025-11-11 13:37:44', '209.38.171.212', 0),
(202, NULL, '2025-11-11 13:37:45', '209.38.171.212', 0),
(203, NULL, '2025-11-11 13:44:27', '139.99.170.109', 0),
(204, NULL, '2025-11-11 13:44:29', '139.99.170.109', 0),
(205, NULL, '2025-11-11 13:44:31', '139.99.170.109', 0),
(206, NULL, '2025-11-11 13:44:33', '139.99.170.109', 0),
(207, NULL, '2025-11-11 13:46:13', '82.133.49.225', 0),
(208, NULL, '2025-11-11 13:46:15', '82.133.49.225', 0),
(209, NULL, '2025-11-11 13:46:17', '82.133.49.225', 0),
(210, NULL, '2025-11-11 13:46:18', '82.133.49.225', 0),
(211, NULL, '2025-11-11 13:46:23', '82.133.49.225', 0),
(212, NULL, '2025-11-11 13:47:46', '45.254.247.28', 0),
(213, NULL, '2025-11-11 13:47:48', '45.254.247.28', 0),
(214, NULL, '2025-11-11 13:47:50', '45.254.247.28', 0),
(215, NULL, '2025-11-11 13:47:52', '45.254.247.28', 0),
(216, NULL, '2025-11-11 13:52:48', '31.94.34.140', 0),
(217, NULL, '2025-11-11 13:52:49', '31.94.34.140', 0),
(218, NULL, '2025-11-11 13:52:51', '31.94.34.140', 0),
(219, NULL, '2025-11-11 13:52:52', '31.94.34.140', 0),
(220, NULL, '2025-11-11 15:10:42', '123.253.50.127', 0);

-- --------------------------------------------------------

--
-- Table structure for table `feedbacks`
--

CREATE TABLE `feedbacks` (
  `id` int(11) NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `feedback` text NOT NULL,
  `rating` int(11) NOT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `feedback_message`
--

CREATE TABLE `feedback_message` (
  `feedbackID` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) DEFAULT 0,
  `rating` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback_message`
--

INSERT INTO `feedback_message` (`feedbackID`, `name`, `email`, `message`, `created_at`, `archived`, `rating`) VALUES
(8, 'Gwen Arlegui', 'gwenarlegui@gmail.com', 'This is very helpful!', '2025-10-08 11:10:30', 0, 5),
(9, 'Whendy Mei', 'gmieux500@gmail.com', 'I just wanted to express my gratitude for the amazing resources and support provided through the LMS. It\'s been incredibly helpful in my learning journey. Thank you to the entire team for creating such a wonderful platform!', '2025-10-08 11:17:12', 0, 5),
(10, 'Alex  Rivera', 'alexrivera@gmail.com', 'Thank you for the awesome LMS platform! The resources and tools are so helpful and easy to use. Big thanks to the team for creating such a great system for learning!', '2025-10-08 15:51:11', 0, 5);

-- --------------------------------------------------------

--
-- Table structure for table `grading_weights`
--

CREATE TABLE `grading_weights` (
  `weightID` int(11) NOT NULL,
  `teacherSectionID` int(11) NOT NULL,
  `attendance_weight` decimal(5,2) NOT NULL
) ;

--
-- Dumping data for table `grading_weights`
--

INSERT INTO `grading_weights` (`weightID`, `teacherSectionID`, `attendance_weight`, `quiz_weight`, `assignment_weight`, `created_at`, `updated_at`) VALUES
(44, 28, '20.00', '40.00', '40.00', '2025-10-02 07:03:10', '2025-10-04 04:01:49'),
(45, 27, '20.00', '40.00', '40.00', '2025-10-02 07:03:10', '2025-11-12 02:18:37'),
(46, 25, '20.00', '40.00', '40.00', '2025-10-02 07:03:10', '2025-11-12 02:18:37'),
(47, 26, '20.00', '40.00', '40.00', '2025-10-02 07:03:10', '2025-10-04 04:01:49'),
(56, 29, '20.00', '40.00', '40.00', '2025-10-04 04:01:49', '2025-10-04 04:01:49'),
(59, 32, '20.00', '40.00', '40.00', '2025-11-12 02:18:37', '2025-11-12 02:18:37');

-- --------------------------------------------------------

--
-- Table structure for table `images`
--

CREATE TABLE `images` (
  `imageID` int(11) NOT NULL,
  `backgroundImg` varchar(255) NOT NULL,
  `archived` tinyint(1) DEFAULT 0,
  `dateCreated` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `learn_quiz_answers`
--

CREATE TABLE `learn_quiz_answers` (
  `answerID` int(11) NOT NULL,
  `sessionID` int(11) NOT NULL,
  `questionID` int(11) NOT NULL,
  `userAnswer` char(1) DEFAULT NULL,
  `isCorrect` tinyint(1) NOT NULL,
  `answerTime` timestamp NOT NULL DEFAULT current_timestamp(),
  `pointsEarned` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `learn_quiz_answers`
--

INSERT INTO `learn_quiz_answers` (`answerID`, `sessionID`, `questionID`, `userAnswer`, `isCorrect`, `answerTime`, `pointsEarned`) VALUES
(293, 48, 39, 'B', 1, '2025-10-04 06:00:56', 100),
(294, 48, 40, 'A', 1, '2025-10-04 06:01:03', 100),
(295, 49, 39, 'B', 1, '2025-10-04 06:02:39', 100),
(296, 49, 40, 'A', 1, '2025-10-04 06:02:42', 100),
(297, 46, 39, 'B', 1, '2025-10-04 06:40:10', 100),
(298, 46, 40, 'A', 1, '2025-10-04 06:40:15', 100),
(299, 46, 40, 'A', 1, '2025-10-04 06:40:15', 100);

-- --------------------------------------------------------

--
-- Table structure for table `learn_quiz_games`
--

CREATE TABLE `learn_quiz_games` (
  `gameID` int(11) NOT NULL,
  `professorID` int(11) NOT NULL,
  `gameCode` varchar(8) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `isActive` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `learn_quiz_games`
--

INSERT INTO `learn_quiz_games` (`gameID`, `professorID`, `gameCode`, `title`, `description`, `createdAt`, `isActive`) VALUES
(6, 151, 'EDD97E', 'Basic Calculus', 'Read each question carefully before answering.', '2025-09-27 11:02:14', 1),
(7, 151, '8FC740', 'dsadsa', 'dasdsa', '2025-10-04 05:21:36', 1);

-- --------------------------------------------------------

--
-- Table structure for table `learn_quiz_questions`
--

CREATE TABLE `learn_quiz_questions` (
  `questionID` int(11) NOT NULL,
  `gameID` int(11) NOT NULL,
  `questionText` text NOT NULL,
  `optionA` varchar(255) NOT NULL,
  `optionB` varchar(255) NOT NULL,
  `optionC` varchar(255) NOT NULL,
  `optionD` varchar(255) NOT NULL,
  `correctAnswer` char(1) NOT NULL
) ;

--
-- Dumping data for table `learn_quiz_questions`
--

INSERT INTO `learn_quiz_questions` (`questionID`, `gameID`, `questionText`, `optionA`, `optionB`, `optionC`, `optionD`, `correctAnswer`, `timeLimit`, `points`) VALUES
(34, 6, 'Which concept describes the behavior of a function as the input approaches a specific value, even if the function is not defined there?', 'Derivative', 'Limit', 'Tangent', 'Concavity', 'B', 60, 10),
(35, 6, 'A function is differentiable at a point if and only if it is:', 'Continuous at that point', 'Smooth and has no sharp corner at that point', 'None of the above', 'Both a and b', 'D', 60, 10),
(36, 6, 'What does the second derivative of a function tell us?', 'The slope of the tangent line', 'The rate of change of the slope', 'The total area under the curve', 'The average change in a function', 'B', 60, 10),
(37, 6, 'Which of the following best illustrates the importance of limits in calculus?', 'Understanding infinite processes', 'Measuring shapes exactly', 'Solving arithmetic problems', 'Balancing equations', 'A', 60, 10),
(38, 6, 'Which term refers to the point where a function changes from concave upward to concave downward (or vice versa)?', 'Tangent Point', 'Turning Point', 'Inflection Point', 'Boundary Point', 'C', 60, 10),
(39, 7, 'What is the food we ate todaysss?', 'adobo', 'bopis', 'sinigang', 'gulaman', 'B', 30, 100),
(40, 7, 'what did you drink today?', 'water', 'coke', 'gatorade', 'gulaman', 'A', 30, 100);

-- --------------------------------------------------------

--
-- Table structure for table `learn_quiz_sessions`
--

CREATE TABLE `learn_quiz_sessions` (
  `sessionID` int(11) NOT NULL,
  `gameID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `startTime` timestamp NOT NULL DEFAULT current_timestamp(),
  `endTime` timestamp NULL DEFAULT NULL,
  `totalScore` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `learn_quiz_sessions`
--

INSERT INTO `learn_quiz_sessions` (`sessionID`, `gameID`, `userID`, `startTime`, `endTime`, `totalScore`) VALUES
(45, 6, 149, '2025-10-02 05:48:51', NULL, 0),
(46, 7, 155, '2025-10-04 05:22:53', NULL, 300),
(47, 6, 155, '2025-10-04 05:57:09', NULL, 0),
(48, 7, 149, '2025-10-04 06:00:32', NULL, 200),
(49, 7, 154, '2025-10-04 06:02:33', NULL, 200);

-- --------------------------------------------------------

--
-- Table structure for table `login_history`
--

CREATE TABLE `login_history` (
  `history_id` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `login_time` datetime NOT NULL,
  `logout_time` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `ip_address` varchar(255) DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL,
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `login_method` varchar(50) NOT NULL DEFAULT 'manual'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_history`
--

INSERT INTO `login_history` (`history_id`, `userID`, `login_time`, `logout_time`, `ip_address`, `device_info`, `archived`, `login_method`) VALUES
(123, 151, '2025-11-09 19:54:40', '2025-11-10 04:03:41', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(124, 151, '2025-11-09 20:05:45', '2025-11-10 04:09:41', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(125, 151, '2025-11-09 20:16:08', '2025-11-10 04:24:44', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(126, 151, '2025-11-09 21:00:18', '2025-11-10 05:03:18', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(127, 151, '2025-11-09 21:13:21', '2025-11-10 05:16:42', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(128, 151, '2025-11-09 22:25:42', '2025-11-12 04:16:03', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(129, 151, '2025-11-09 22:46:13', '2025-11-12 04:16:03', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 CCleaner/141.0.0.0', 0, 'manual'),
(130, 151, '2025-11-09 23:42:53', '2025-11-12 04:16:03', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(131, 148, '2025-11-10 13:49:03', '2025-11-10 21:50:22', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(132, 149, '2025-11-10 13:51:07', '2025-11-10 21:53:20', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(133, 148, '2025-11-10 13:53:31', '2025-11-10 21:54:43', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(134, 148, '2025-11-10 14:04:57', '2025-11-10 22:05:13', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(135, 151, '2025-11-10 14:05:29', '2025-11-12 04:16:03', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(136, 149, '2025-11-10 14:20:56', '2025-11-10 22:32:05', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(137, 149, '2025-11-10 01:23:16', '2025-11-10 22:32:05', '123.253.50.127', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 0, 'qr'),
(138, 151, '2025-11-10 19:48:06', '2025-11-12 04:16:03', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(139, 151, '2025-11-10 22:45:10', '2025-11-12 04:16:03', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(140, 313, '2025-11-11 12:46:55', '2025-11-11 20:48:10', '103.91.141.218', 'Mozilla/5.0 (Linux; Android 10; VOG-L29; HMSCore 6.15.0.332; GMSCore 25.43.34) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.5735.196 HuaweiBrowser/16.0.6.300 Mobile Safari/537.36', 0, 'manual'),
(141, 148, '2025-11-11 13:03:19', '2025-11-12 07:46:47', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(142, 147, '2025-11-11 19:05:53', '2025-11-12 03:48:11', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(143, 149, '2025-11-11 19:48:20', '2025-11-12 03:49:10', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(144, 149, '2025-11-11 19:49:18', '2025-11-12 04:15:57', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(145, 151, '2025-11-11 19:49:52', '2025-11-12 04:16:03', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 CCleaner/141.0.0.0', 0, 'manual'),
(146, 149, '2025-11-11 20:16:30', '2025-11-12 06:22:16', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(147, 151, '2025-11-11 20:18:01', '2025-11-12 15:17:29', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 CCleaner/141.0.0.0', 0, 'manual'),
(148, 149, '2025-11-11 22:11:41', '2025-11-12 06:22:16', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(149, 147, '2025-11-11 23:28:01', '2025-11-12 15:12:21', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(150, 148, '2025-11-11 23:46:22', '2025-11-12 07:46:47', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 CCleaner/141.0.0.0', 0, 'manual'),
(151, 147, '2025-11-12 07:10:59', '2025-11-12 15:12:21', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 CCleaner/141.0.0.0', 0, 'manual'),
(152, 148, '2025-11-12 07:13:23', '2025-11-12 15:15:32', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(153, 151, '2025-11-12 07:15:48', '2025-11-12 15:17:29', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(154, 149, '2025-11-12 07:18:26', '2025-11-12 15:21:24', '123.253.50.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 0, 'manual'),
(155, 152, '2025-11-12 08:37:35', '2025-11-12 16:43:26', '216.247.83.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 0, 'manual'),
(156, 147, '2025-11-12 08:38:02', '2025-11-12 18:01:08', '216.247.83.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 CCleaner/141.0.0.0', 0, 'manual'),
(157, 152, '2025-11-12 09:10:35', '2025-11-12 17:14:52', '216.247.80.87', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 0, 'manual'),
(158, 152, '2025-11-12 09:19:02', '2025-11-12 17:20:16', '216.247.80.87', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 0, 'manual'),
(159, 152, '2025-11-12 09:20:57', '2025-11-12 17:44:24', '216.247.80.87', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 0, 'manual'),
(160, 147, '2025-11-12 09:38:58', '2025-11-12 18:01:08', '209.35.169.174', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 CCleaner/141.0.0.0', 0, 'manual'),
(161, 148, '2025-11-12 10:01:14', '2025-11-12 18:03:46', '209.35.169.174', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 CCleaner/141.0.0.0', 0, 'manual'),
(162, 152, '2025-11-12 10:01:21', '2025-11-12 18:02:16', '209.35.169.174', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 0, 'manual'),
(163, 148, '2025-11-12 10:02:56', '2025-11-12 18:03:46', '209.35.169.174', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 0, 'manual'),
(164, 147, '2025-11-12 10:10:23', '2025-11-12 18:11:43', '209.35.169.174', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 CCleaner/141.0.0.0', 0, 'manual'),
(165, 148, '2025-11-12 10:11:47', '2025-11-12 18:18:46', '209.35.169.174', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 CCleaner/141.0.0.0', 0, 'manual'),
(166, 151, '2025-11-12 10:19:14', NULL, '209.35.169.174', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 0, 'manual');

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `moduleID` int(11) NOT NULL,
  `teacherSectionID` int(11) NOT NULL,
  `fileName` varchar(255) NOT NULL,
  `filePath` varchar(255) NOT NULL,
  `fileType` varchar(50) NOT NULL,
  `fileSize` bigint(20) NOT NULL,
  `description` varchar(255) NOT NULL,
  `uploadDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `module_comments`
--

CREATE TABLE `module_comments` (
  `commentID` int(11) NOT NULL,
  `moduleID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `comment` text NOT NULL,
  `commentDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `parentCommentID` int(11) DEFAULT NULL,
  `archived` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `module_comment_views`
--

CREATE TABLE `module_comment_views` (
  `userID` int(11) NOT NULL,
  `moduleID` int(11) NOT NULL,
  `last_viewed` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_views`
--

CREATE TABLE `notification_views` (
  `id` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `announcementID` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) DEFAULT 0 COMMENT '1 if announcement is archived by user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_views`
--

INSERT INTO `notification_views` (`id`, `userID`, `announcementID`, `viewed_at`, `archived`) VALUES
(49, 152, 29, '2025-11-07 07:42:55', 0);

-- --------------------------------------------------------

--
-- Table structure for table `private_messages`
--

CREATE TABLE `private_messages` (
  `messageID` int(11) NOT NULL,
  `senderID` int(11) NOT NULL,
  `recipientID` int(11) NOT NULL,
  `teacherSectionID` int(11) NOT NULL,
  `content` text NOT NULL,
  `createdDate` datetime NOT NULL,
  `updatedDate` datetime DEFAULT NULL,
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `public_data`
--

CREATE TABLE `public_data` (
  `publicDataID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `country` varchar(10) DEFAULT NULL,
  `loc` varchar(50) DEFAULT NULL,
  `org` varchar(255) DEFAULT NULL,
  `postal` varchar(20) DEFAULT NULL,
  `timezone` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `questionID` int(11) NOT NULL,
  `quizID` int(11) NOT NULL,
  `questionText` text NOT NULL,
  `option1` varchar(255) DEFAULT NULL,
  `option2` varchar(255) DEFAULT NULL,
  `option3` varchar(255) DEFAULT NULL,
  `option4` varchar(255) DEFAULT NULL,
  `correctOption` int(11) DEFAULT NULL,
  `points` int(11) NOT NULL DEFAULT 1,
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `createdDate` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `quizID` int(11) NOT NULL,
  `teacherSectionID` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `quizType` enum('Multiple Choice','True/False','Essay') NOT NULL,
  `dueDate` datetime DEFAULT NULL,
  `releaseDate` datetime DEFAULT NULL,
  `numQuestions` int(11) NOT NULL DEFAULT 0,
  `createdDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedDate` timestamp NULL DEFAULT NULL,
  `archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_answers`
--

CREATE TABLE `quiz_answers` (
  `answerID` int(11) NOT NULL,
  `quizID` int(11) NOT NULL,
  `studentID` int(11) NOT NULL,
  `questionID` int(11) NOT NULL,
  `answerText` text DEFAULT NULL,
  `isCorrect` tinyint(1) DEFAULT NULL,
  `submittedDate` datetime NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_scores`
--

CREATE TABLE `quiz_scores` (
  `scoreID` int(11) NOT NULL,
  `quizID` int(11) NOT NULL,
  `studentID` int(11) NOT NULL,
  `recordedDate` datetime NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `approved` tinyint(1) NOT NULL DEFAULT 0,
  `totalScore` int(11) NOT NULL DEFAULT 0,
  `maxScore` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `sectionID` int(11) NOT NULL,
  `sectionCode` varchar(10) NOT NULL,
  `sectionName` varchar(50) NOT NULL,
  `gradeLevel` enum('Grade 11','Grade 12') NOT NULL,
  `semester` enum('1st Sem','2nd Sem') NOT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) DEFAULT 0,
  `strandID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`sectionID`, `sectionCode`, `sectionName`, `gradeLevel`, `semester`, `dateCreated`, `archived`, `strandID`) VALUES
(11, '', 'AS 11A', 'Grade 11', '1st Sem', '2025-09-27 08:24:43', 0, 14),
(12, '', 'AS 11B', 'Grade 11', '1st Sem', '2025-09-27 08:25:22', 0, 19),
(13, '', 'AS 12A', 'Grade 12', '1st Sem', '2025-09-27 08:25:43', 0, 19),
(14, '', 'AS 11B', 'Grade 12', '1st Sem', '2025-09-27 08:25:57', 0, 19),
(15, '', 'STEM 11A', 'Grade 11', '1st Sem', '2025-09-27 08:28:02', 0, 14),
(16, '', 'STEM 11B', 'Grade 11', '1st Sem', '2025-09-27 08:28:20', 0, 14),
(17, '', 'STEM 12A', 'Grade 12', '1st Sem', '2025-09-27 08:28:32', 0, 14),
(18, '', 'STEM 12B', 'Grade 12', '1st Sem', '2025-09-27 08:28:44', 0, 14),
(19, '', 'STEM 12C', 'Grade 12', '1st Sem', '2025-11-07 07:47:20', 0, 22),
(20, '', 'BSIS403', 'Grade 12', '1st Sem', '2025-11-12 02:13:07', 0, 14);

-- --------------------------------------------------------

--
-- Table structure for table `seen_academic_events`
--

CREATE TABLE `seen_academic_events` (
  `id` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `eventID` int(11) NOT NULL,
  `seen_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `speech_to_text_certificate`
--

CREATE TABLE `speech_to_text_certificate` (
  `certificateID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `completionDate` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `speech_to_text_daily_bonus`
--

CREATE TABLE `speech_to_text_daily_bonus` (
  `bonusID` int(11) NOT NULL,
  `userID` int(11) DEFAULT NULL,
  `claimDate` date DEFAULT NULL,
  `points` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `speech_to_text_daily_bonus`
--

INSERT INTO `speech_to_text_daily_bonus` (`bonusID`, `userID`, `claimDate`, `points`) VALUES
(7, 149, '2025-09-27', 35),
(8, 149, '2025-10-04', 35),
(9, 149, '2025-10-23', 25);

-- --------------------------------------------------------

--
-- Table structure for table `speech_to_text_game_images`
--

CREATE TABLE `speech_to_text_game_images` (
  `imageID` int(11) NOT NULL,
  `imageFile1` varchar(255) NOT NULL,
  `imageFile2` varchar(255) NOT NULL,
  `imageFile3` varchar(255) DEFAULT NULL,
  `correctAnswer` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `level` int(11) NOT NULL,
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `speech_to_text_game_images`
--

INSERT INTO `speech_to_text_game_images` (`imageID`, `imageFile1`, `imageFile2`, `imageFile3`, `correctAnswer`, `description`, `level`, `archived`) VALUES
(31, '../../uploads/speech_game/68d7b21410a5a-img1.jpg', '../../uploads/speech_game/68d7b21411224-img2.jpg', '../../uploads/speech_game/68d7b2141167c-img3.jpg', 'Photosynthesis', 'Photosynthesis is the process by which green plants and certain other organisms transform light energy into chemical energy. During photosynthesis in green plants, light energy is captured and used to convert water, carbon dioxide, and minerals into oxygen and energy-rich organic compound.', 1, 0),
(32, '../../uploads/speech_game/68d7b27fa5d48-img1.jpg', '../../uploads/speech_game/68d7b27fa671a-img2.jpg', '../../uploads/speech_game/68d7b27fa7121-img3.jpg', 'Probiotics', 'Probiotics are live bacteria and yeasts that have beneficial effects on your body. These species already live in your body, along with many others. Probiotic supplements add to your existing supply of friendly microbes. They help fight off the less friendly types and boost your immunity against infections.', 2, 0),
(33, '../../uploads/speech_game/68d7b2e0ab4c1-img1.jpg', '../../uploads/speech_game/68d7b2e0ab999-img2.jpg', '../../uploads/speech_game/68d7b2e0abe7a-img3.jpg', 'Dynamics', 'Dynamics means the science of the motion of bodies and the action of forces in producing or changing their motion. It also means the forces or processes that produce change inside a group or system. In music, dynamics means the variation in loudness between notes or phrases.', 3, 0),
(34, '../../uploads/speech_game/68d7d4b25f252-img1.jpg', '../../uploads/speech_game/68d7d4b25f847-img2.jpg', '../../uploads/speech_game/68d7d4b25fe9d-img3.jpg', 'Physical', 'Physical refers to anything that is related to the material or tangible aspects of the world. It encompasses the characteristics, properties, and phenomena that can be observed, measured, and experienced through the senses or physical interactions. Physical can also refer to the properties of matter and energy other than those peculiar to living matter. It is also used to describe things that are related to natural science or physics.', 4, 0),
(35, '../../uploads/speech_game/68d7d4ff0eca0-img1.1.jpg', '../../uploads/speech_game/68d7d4ff0f56e-img1.2.jpg', '../../uploads/speech_game/68d7d4ff0fab9-img1.3.jpg', 'Networking', 'Networking, also known as computer networking, is the practice of transporting and exchanging data between nodes over a shared medium in an information system. Networking comprises not only the design, construction and use of a network, but also the management, maintenance and operation of the network infrastructure, software and policies.', 5, 0),
(36, '../../uploads/speech_game/68d7d565e4469-img1.jpg', '../../uploads/speech_game/68d7d565e4940-img1.1.jpg', '../../uploads/speech_game/68d7d565e4d99-img1.3.jpg', 'Formula', 'A formula is generally a fixed pattern that is used to achieve consistent results. It might be made up of words, numbers, or ideas that work together to define a procedure to be followed for the desired outcome.', 6, 0),
(37, '../../uploads/speech_game/68d7d59ad5edd-img2.jpg', '../../uploads/speech_game/68d7d59ad645d-img2.1.jpg', '../../uploads/speech_game/68d7d59ad68be-img2.3.jpg', 'Industry', 'An industry is a classification for a group of companies that are related in terms of their primary business activities. It is the aggregate of manufacturing or technically productive enterprises in a particular field, often named after its principal product. Industry refers to the production of goods from raw materials, especially in factories.', 7, 0),
(38, '../../uploads/speech_game/68d7d5d293d22-img3.webp', '../../uploads/speech_game/68d7d5d2942c2-img3.1.jpg', '../../uploads/speech_game/68d7d5d294747-img3.2.jpg', 'Reality', 'The meaning of reality refers to the state or quality of being real or existing objectively. It encompasses what is real or existent, including facts and phenomena, whether observable or not. In philosophy, reality is often discussed in terms of its independence from perceptions or ideas about it', 8, 0),
(39, '../../uploads/speech_game/68d7d6052b759-img4.jpg', '../../uploads/speech_game/68d7d6052c17f-img4.1.jpg', '../../uploads/speech_game/68d7d6052cc58-img4.2.jpg', 'Arithmetic', 'Arithmetic is a fundamental branch of mathematics that deals with numbers and their relationships through basic operations such as addition, subtraction, multiplication, and division.', 9, 0),
(40, '../../uploads/speech_game/68d7d637732ca-img5.jpg', '../../uploads/speech_game/68d7d63773c4b-img5.1.jpg', '../../uploads/speech_game/68d7d63774548-img5.2.jpg', 'Conductor', 'A conductor is a material that allows electricity and heat to flow through it easily. Common examples include metals like copper and aluminum, as well as the human body.', 10, 0),
(41, '../../uploads/speech_game/68d7d678c3f9e-img1.png', '../../uploads/speech_game/68d7d678c4af8-img1.1.jpg', '../../uploads/speech_game/68d7d678c549f-img1.2.jpg', 'Writing Pad', 'A writing pad is defined as a book containing pieces of paper for you to write on. It typically consists of a collection of blank pages of writing paper, bound together, often with a cardboard cover. This makes it a convenient tool for jotting down notes, ideas, or sketches.', 11, 0),
(42, '../../uploads/speech_game/68d7d6ab2d2fe-img2.jpg', '../../uploads/speech_game/68d7d6ab2d7ee-img2.1.png', '../../uploads/speech_game/68d7d6ab2dcc1-img2.2.jpg', 'Time Zone Map', 'A time zone map is a visual representation that shows the different standard time zones around the world. It typically uses a color scheme to designate each time zone, indicating the local time in various regions.', 12, 0),
(43, '../../uploads/speech_game/68d7d6dfaf19c-img3.jpg', '../../uploads/speech_game/68d7d6dfaf789-img3.1.jpg', '../../uploads/speech_game/68d7d6dfafbf8-img3.2.jpg', 'Position', 'Position has many meanings. As a noun it can be a job or post in an organization (an \"open position\" is a job opening); the role assigned to the individual player of a team sport (guard, forward, and center are basketball positions); a view or perspective on a particular issue; or the place an item occupies on a list or sequence (in racing, \"pole position\" is first, on the inside). As a verb it can mean lay, place, pose, or set.', 13, 0),
(44, '../../uploads/speech_game/68d7d77680813-img4.jpg', '../../uploads/speech_game/68d7d77680d3a-img4.1.jpg', '../../uploads/speech_game/68d7d7768122d-img4.2.jpg', 'Oxygen', 'Oxygen is a chemical element with the symbol O and atomic number 8. It constitutes about 21 percent of the Earth\'s atmosphere and exists primarily as a diatomic gas. Oxygen is essential for respiration in most living organisms and is involved in combustion processes. It is a colorless, odorless, and tasteless gas at room temperature.', 14, 0),
(45, '../../uploads/speech_game/68d7d7b0db254-img5.jpg', '../../uploads/speech_game/68d7d7b0db74e-img5.1.jpg', '../../uploads/speech_game/68d7d7b0dbcd2-img5.2.jpg', 'Apprentice', 'The term \"apprentice\" refers to a person who is learning a trade or skill from a skilled worker through practical experience, often as part of an agreed time period.', 15, 0),
(46, '../../uploads/speech_game/68d7d7e5ce240-img6.jpg', '../../uploads/speech_game/68d7d7e5ce6f4-img6.1.jpg', '../../uploads/speech_game/68d7d7e5ceb4f-img6.2.jpg', 'Philippines', 'The Philippines is a beautiful archipelago in Southeast Asia made up of thousands of islands surrounded by crystal-clear waters and lush tropical landscapes. It is known for its vibrant culture, warm and friendly people, and deep sense of community and hospitality. With influences from indigenous traditions, Spanish heritage, and modern global culture, the country is a rich blend of history and diversity.', 16, 0),
(47, '../../uploads/speech_game/68d7d817ad418-img7.jpg', '../../uploads/speech_game/68d7d817ad92b-img7.1.jpg', '../../uploads/speech_game/68d7d817addad-img7.2.png', 'Cabinet', 'The Cabinet of the Philippines (Filipino: Gabinete ng Pilipinas, usually referred to as the Cabinet or Gabinete) consists of the heads of the largest part of the executive branch of the national government of the Philippines. Currently, it includes the secretaries of 23 executive departments and the heads of other several other minor agencies and offices that are subordinate to the president of the Philippines.', 17, 0),
(48, '../../uploads/speech_game/68d7d84c84dec-img8.jpg', '../../uploads/speech_game/68d7d84c8529f-img8.1.jpg', '../../uploads/speech_game/68d7d84c85787-img8.2.jpg', 'Bill of Rights', 'The Bill of Rights refers to the first ten amendments to the U.S. Constitution, adopted in 1791. It serves to guarantee individual rights and liberties, such as freedom of speech, press, and religion, while also placing limitations on the powers of the federal and state governments. The Bill of Rights was created to address concerns raised during the ratification of the Constitution, particularly by the Anti-Federalists, who feared that a strong central government could infringe upon individual freedoms.', 18, 0),
(49, '../../uploads/speech_game/68d7d87f0a4d0-img9.webp', '../../uploads/speech_game/68d7d87f0aa5c-img9.1.jpg', '../../uploads/speech_game/68d7d87f0aed6-img9.2.webp', 'Psychopath', 'A psychopath is defined as a mentally unstable person, particularly one with an egocentric and antisocial personality characterized by a lack of remorse and empathy for others, often leading to criminal behavior.', 19, 0),
(50, '../../uploads/speech_game/68d7df43be7d3-img10.png', '../../uploads/speech_game/68d7df43bfa47-img10.1.webp', '../../uploads/speech_game/68d7df43bfebe-img10.2.jpg', 'World Wide Web', 'The World Wide Web (WWW) is a system of interconnected public webpages accessible through the Internet. It is often referred to as \"the Web\" and is distinct from the Internet itself, which is the underlying network that supports various applications, including the Web.', 20, 0),
(51, '../../uploads/speech_game/68d7e038b0640-img1.jpg', '../../uploads/speech_game/68d7e038b0b07-img1.1.avif', '../../uploads/speech_game/68d7e038b0f5f-img1.2.avif', 'Visitor', 'The term \"visitor\" refers to a person who visits a person or place. This can include someone who comes to stay for social, business, or sightseeing purposes. For example, a visitor might be a guest at someone\'s home or a tourist at a theme park.', 21, 0),
(52, '../../uploads/speech_game/68d7e065d0627-img2.jpg', '../../uploads/speech_game/68d7e065d0d60-img2.1.webp', '../../uploads/speech_game/68d7e065d1200-img2.2.jpg', 'Feasible', 'The term feasible refers to something that is possible or achievable within specified constraints or conditions. It can describe tasks, projects, or plans that can be accomplished given the available resources and circumstances. In essence, it means that something can be done or made.', 22, 0),
(53, '../../uploads/speech_game/68d7e08bb9ae0-img3.webp', '../../uploads/speech_game/68d7e08bba003-img3.1.webp', '../../uploads/speech_game/68d7e08bba49c-img3.2.webp', 'Flashpoint Codes', 'Flashpoint codes are special codes used in the game Flashpoint: Worlds Collide that provide players with various rewards such as cash and experience points', 23, 0),
(54, '../../uploads/speech_game/68d7e0bd6b182-img4.jpeg', '../../uploads/speech_game/68d7e0bd6b953-img4.1.webp', '../../uploads/speech_game/68d7e0bd6bf45-img4.2.webp', 'Chairwoman', 'The term chairwoman refers to a woman who presides over a meeting, committee, or organization. It is a gender-specific term, indicating that the individual in this role is female. In essence, a chairwoman serves as the leader or head of a group or organization.', 24, 0),
(55, '../../uploads/speech_game/68d7e26c0805e-img5.jpg', '../../uploads/speech_game/68d7e26c08761-img5.1.jpg', '../../uploads/speech_game/68d7e26c08bef-img5.2.jpg', 'Ring of Fire', 'The Ring of Fire is a horseshoe-shaped seismically active belt that encircles the Pacific Ocean, characterized by a high frequency of earthquakes and active volcanoes.', 25, 0),
(56, '../../uploads/speech_game/68d7e2a801ff2-img6.webp', '../../uploads/speech_game/68d7e2a802514-img6.2.jpg', '../../uploads/speech_game/68d7e2a80298f-img6.3.jpg', 'Capitalism', 'Capitalism is an economic system based on the private ownership of the means of production and their use for the purpose of obtaining profit. This socioeconomic system has developed historically through several stages and is defined by a number of basic constituent elements: private property, profit motive, capital accumulation, competitive markets, commodification, wage labor, and an emphasis on innovation and economic growth. Capitalist economies tend to experience a business cycle of economic growth followed by recessions.', 26, 0),
(57, '../../uploads/speech_game/68d7e2cfc0049-img7.webp', '../../uploads/speech_game/68d7e2cfc04cd-img7.2.webp', '../../uploads/speech_game/68d7e2cfc08ff-img7.3.jpg', 'Adjacent', '\"Adjacent\" means next to or adjoining something else, often sharing a common boundary or point. For example, in geometry, adjacent angles share a common side and vertex. In everyday use, it refers to things that are nearby or side by side, like adjacent rooms in a building.', 27, 0),
(58, '../../uploads/speech_game/68d7e2fb1d390-img8.jpg', '../../uploads/speech_game/68d7e2fb1de73-img8.1.webp', '../../uploads/speech_game/68d7e2fb1e880-img8.2.jpg', 'Monarchy', 'A monarchy is a form of government in which a person, the monarch, reigns as head of state for the rest of their life, or until abdication. The extent of the authority of the monarch may vary from restricted and largely symbolic (constitutional monarchy), to fully autocratic (absolute monarchy), and may have representational, executive, legislative, and judicial functions.', 28, 0),
(59, '../../uploads/speech_game/68d7e3294d8a1-img9.jpg', '../../uploads/speech_game/68d7e3294dd50-img9.1.webp', '../../uploads/speech_game/68d7e3294e1f9-img9.2.webp', 'Velocity', 'Velocity is the rate at which an object changes position with time. An object is displaced when it changes its position. The amount of displacement over the time in which the displacement occurred gives the velocity. It is a vector quantity that has both magnitude and direction.', 29, 0),
(60, '../../uploads/speech_game/68d7e3650eeee-img10.jpg', '../../uploads/speech_game/68d7e3650f3c8-img10.1.webp', '../../uploads/speech_game/68d7e3650f84b-img10.2.webp', 'Philosophy', 'Philosophy (\'love of wisdom\' in Ancient Greek) is a systematic study of general and fundamental questions concerning topics like existence, reason, knowledge, value, mind, and language. It is a rational and critical inquiry that reflects on its methods and assumptions.', 30, 0);

-- --------------------------------------------------------

--
-- Table structure for table `speech_to_text_user_attempts`
--

CREATE TABLE `speech_to_text_user_attempts` (
  `attemptID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `imageID` int(11) NOT NULL,
  `userAnswer` varchar(255) NOT NULL,
  `isCorrect` tinyint(1) NOT NULL,
  `points` int(11) NOT NULL DEFAULT 0,
  `attemptDate` datetime DEFAULT current_timestamp(),
  `stars` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `speech_to_text_user_attempts`
--

INSERT INTO `speech_to_text_user_attempts` (`attemptID`, `userID`, `imageID`, `userAnswer`, `isCorrect`, `points`, `attemptDate`, `stars`) VALUES
(274, 154, 31, 'PHOTOSYNTHESIS', 1, 15, '2025-10-03 23:03:57', 3),
(275, 154, 32, 'PORO', 0, 0, '2025-10-03 23:05:10', 0),
(276, 149, 31, 'PHOTOSYNTHESIS', 1, 15, '2025-10-03 23:10:05', 3),
(277, 149, 32, 'PROBIOTICS', 1, 15, '2025-10-03 23:10:59', 3),
(278, 149, 33, 'DYNAMICS', 1, 15, '2025-10-03 23:14:21', 3),
(279, 149, 34, 'PHYSICAL', 1, 15, '2025-10-03 23:14:48', 3),
(280, 149, 35, 'NETWORKING', 1, 15, '2025-10-03 23:15:49', 3),
(281, 149, 36, 'FORMULA', 1, 15, '2025-10-03 23:16:00', 3),
(282, 149, 37, 'INDUSTRY', 1, 15, '2025-10-03 23:16:41', 3),
(283, 149, 38, 'REALITY', 1, 15, '2025-10-03 23:17:01', 3),
(284, 149, 39, 'ARITHMETIC', 1, 15, '2025-10-03 23:17:59', 3),
(285, 149, 40, 'CONDUCTOR', 1, 15, '2025-10-03 23:18:25', 3),
(286, 149, 41, 'WRITING PAD', 1, 15, '2025-10-03 23:19:36', 3);

-- --------------------------------------------------------

--
-- Table structure for table `speech_to_text_user_progress_image`
--

CREATE TABLE `speech_to_text_user_progress_image` (
  `progressID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `currentLevel` int(11) DEFAULT 100,
  `totalAttempts` int(11) DEFAULT 0,
  `totalCorrect` int(11) DEFAULT 0,
  `totalPoints` int(11) DEFAULT 0,
  `lastAttemptDate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `speech_to_text_user_progress_image`
--

INSERT INTO `speech_to_text_user_progress_image` (`progressID`, `userID`, `currentLevel`, `totalAttempts`, `totalCorrect`, `totalPoints`, `lastAttemptDate`) VALUES
(274, 154, 2, 2, 1, 15, '2025-10-03 23:05:10'),
(276, 149, 12, 11, 11, 225, '2025-10-03 23:19:36');

-- --------------------------------------------------------

--
-- Table structure for table `strand_modules`
--

CREATE TABLE `strand_modules` (
  `moduleID` int(11) NOT NULL,
  `strandID` int(11) NOT NULL,
  `moduleTitle` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `fileName` varchar(255) DEFAULT NULL,
  `filePath` varchar(255) DEFAULT NULL,
  `fileType` varchar(50) DEFAULT NULL,
  `fileSize` bigint(20) DEFAULT NULL,
  `uploadDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `strand_modules`
--

INSERT INTO `strand_modules` (`moduleID`, `strandID`, `moduleTitle`, `description`, `fileName`, `filePath`, `fileType`, `fileSize`, `uploadDate`, `archived`) VALUES
(10, 23, 'capstone', 'etc', '1762913768_Lab_Activity4_Samson.pdf', './modules/1762913768_Lab_Activity4_Samson.pdf', 'application/pdf', 642762, '2025-11-12 02:16:08', 0);

-- --------------------------------------------------------

--
-- Table structure for table `student_enrollments`
--

CREATE TABLE `student_enrollments` (
  `enrollmentID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `sectionID` int(11) NOT NULL,
  `strandID` int(11) NOT NULL,
  `subjectID` int(11) NOT NULL,
  `professorID` int(11) DEFAULT NULL,
  `numDays` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Monday/Tuesday','Monday/Wednesday','Monday/Thursday','Monday/Friday','Monday/Saturday','Tuesday/Wednesday','Tuesday/Thursday','Tuesday/Friday','Tuesday/Saturday','Wednesday/Thursday','Wednesday/Friday','Wednesday/Saturday','Thursday/Friday','Thursday/Saturday','Friday/Saturday') NOT NULL,
  `startTime` time NOT NULL,
  `endTime` time NOT NULL,
  ` NOTroom` varchar(100) NOT NULL,
  `dateEnrolled` timestamp NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_section`
--

CREATE TABLE `student_section` (
  `studentSectionID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `sectionID` int(11) NOT NULL,
  `enrollmentDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Enrolled','Pending','Dropped','Completed') NOT NULL,
  `academicSession` enum('2025 - 2026','2026 - 2027','2027 - 2028','2028 - 2029','2029 - 2030') NOT NULL,
  `archived` tinyint(1) DEFAULT 0,
  `card_order` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_section`
--

INSERT INTO `student_section` (`studentSectionID`, `userID`, `sectionID`, `enrollmentDate`, `status`, `academicSession`, `archived`, `card_order`) VALUES
(61, 149, 11, '2025-10-17 16:38:47', 'Enrolled', '2025 - 2026', 0, 1),
(62, 152, 11, '2025-10-17 16:38:56', 'Enrolled', '2025 - 2026', 0, NULL),
(63, 313, 11, '2025-11-07 14:50:00', 'Enrolled', '2025 - 2026', 0, NULL),
(64, 314, 11, '2025-11-07 14:50:00', 'Enrolled', '2025 - 2026', 0, NULL),
(65, 357, 11, '2025-11-12 02:16:27', 'Enrolled', '2025 - 2026', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `subjectID` int(11) NOT NULL,
  `subjectCode` varchar(50) NOT NULL,
  `subjectName` varchar(100) NOT NULL,
  `subjectType` enum('Core Subject','Applied Subject','Specialized Subject') NOT NULL,
  `description` text DEFAULT NULL,
  `yearLevel` enum('Grade 11','Grade 12') NOT NULL,
  `semester` enum('1st Sem','2nd Sem') NOT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) DEFAULT 0,
  `professorID` int(11) DEFAULT NULL,
  `strandID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subjectID`, `subjectCode`, `subjectName`, `subjectType`, `description`, `yearLevel`, `semester`, `dateCreated`, `archived`, `professorID`, `strandID`) VALUES
(76, 'DRRR', 'Disaster Readiness and Risk Reduction', 'Core Subject', NULL, 'Grade 11', '2nd Sem', '2025-09-27 08:30:24', 0, NULL, 14),
(77, 'RW', 'Reading and Writing', 'Core Subject', NULL, 'Grade 11', '2nd Sem', '2025-09-27 08:30:54', 0, NULL, 14),
(78, 'SP', 'Statistics and Probability', 'Core Subject', NULL, 'Grade 11', '2nd Sem', '2025-09-27 08:31:15', 0, NULL, 14),
(79, 'PR1', 'Practical Research 1', 'Applied Subject', NULL, 'Grade 11', '2nd Sem', '2025-09-27 08:31:48', 0, NULL, 14),
(80, 'BC', 'Basic Calculus', 'Specialized Subject', NULL, 'Grade 11', '2nd Sem', '2025-09-27 08:32:06', 0, NULL, 14),
(81, 'GC2', 'General Chemistry 2', 'Specialized Subject', NULL, 'Grade 11', '2nd Sem', '2025-09-27 08:32:23', 0, NULL, 14),
(82, 'Math2000', 'Modern World', 'Core Subject', NULL, 'Grade 12', '1st Sem', '2025-11-07 04:16:03', 0, NULL, 15);

-- --------------------------------------------------------

--
-- Table structure for table `subject_professor`
--

CREATE TABLE `subject_professor` (
  `subjectID` int(11) NOT NULL,
  `professorID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_section`
--

CREATE TABLE `teacher_section` (
  `teacherSectionID` int(11) NOT NULL,
  `token` varchar(32) NOT NULL,
  `teacherID` int(11) NOT NULL,
  `sectionID` int(11) NOT NULL,
  `subjectID` int(11) NOT NULL,
  `startTime` time NOT NULL,
  `endTime` time NOT NULL,
  `day` varchar(10) NOT NULL,
  `assignmentDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) DEFAULT 0,
  `advisory` tinyint(1) DEFAULT 0,
  `card_order` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_section`
--

INSERT INTO `teacher_section` (`teacherSectionID`, `token`, `teacherID`, `sectionID`, `subjectID`, `startTime`, `endTime`, `day`, `assignmentDate`, `archived`, `advisory`, `card_order`) VALUES
(25, '261981e6adb856d951dfbc5313cc9f74', 151, 11, 80, '06:00:00', '08:00:00', 'Monday', '2025-09-27 08:47:09', 0, 1, 0),
(27, '5bd66a9681f3aba23d4bf701a87870b6', 150, 11, 79, '08:00:00', '10:00:00', 'Monday', '2025-09-27 08:47:37', 0, 1, NULL),
(32, '403a127614eff0967294f42208725cf2', 151, 11, 80, '06:00:00', '08:00:00', 'Tuesday', '2025-11-12 02:17:10', 0, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tests`
--

CREATE TABLE `tests` (
  `testID` int(11) NOT NULL,
  `strandID` int(11) DEFAULT NULL,
  `testTypeID` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `test_types`
--

CREATE TABLE `test_types` (
  `testTypeID` int(11) NOT NULL,
  `testTypeName` varchar(50) NOT NULL,
  `totalQuestions` int(11) NOT NULL,
  `passingScore` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `todos`
--

CREATE TABLE `todos` (
  `todoID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `dueDate` date NOT NULL,
  `status` enum('Pending','Completed') DEFAULT 'Pending',
  `createdDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `todos`
--

INSERT INTO `todos` (`todoID`, `userID`, `title`, `description`, `dueDate`, `status`, `createdDate`, `updatedDate`, `archived`) VALUES
(13, 151, 'Midterm Exam', 'aghdfkHSfl', '2025-10-25', 'Pending', '2025-10-08 07:05:15', '2025-10-17 09:35:21', 0),
(14, 151, 'cGzhg', 'czxgvfZG', '2025-10-07', 'Completed', '2025-10-08 07:44:38', '2025-10-08 07:46:24', 0),
(15, 151, 'quize', 'Take the quiz', '2025-10-31', 'Completed', '2025-10-29 23:00:27', '2025-10-29 23:00:39', 0);

-- --------------------------------------------------------

--
-- Table structure for table `track_strands`
--

CREATE TABLE `track_strands` (
  `strandID` int(11) NOT NULL,
  `strandCode` varchar(20) NOT NULL,
  `strandName` varchar(100) NOT NULL,
  `trackName` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(150) NOT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `track_strands`
--

INSERT INTO `track_strands` (`strandID`, `strandCode`, `strandName`, `trackName`, `description`, `image`, `dateCreated`, `archived`) VALUES
(14, 'STEM', 'Science, Technology, Engineering and Mathematics', 'Academic', NULL, '', '2025-09-27 08:19:40', 0),
(15, 'ABM', 'Accountancy, Business, and Managements', 'Academic', NULL, '', '2025-09-27 08:19:55', 0),
(16, 'HUMSS	', 'Humanities and Social Sciences', 'Academic', NULL, '', '2025-09-27 08:20:13', 0),
(17, 'FLTH', 'Front Office, Tourism Promotion, Local Tour Guiding, Housekeeping', 'TVL', NULL, '', '2025-09-27 08:20:40', 0),
(18, 'FLTH', 'Front Office, Tourism Promotion, Local Tour Guiding, Housekeeping', 'TVL', NULL, '', '2025-09-27 08:20:58', 1),
(19, 'AS', 'Automotive Servicing', 'TVL', NULL, '', '2025-09-27 08:21:19', 0),
(20, 'CB-PP-FBS', 'Cookery, Bread and Pastry Production, Food and Beverage Services', 'TVL', NULL, '', '2025-09-27 08:21:38', 0),
(21, 'EIM', 'Electrical Installation and Maintenance', 'TVL', NULL, '', '2025-09-27 08:21:53', 0),
(22, 'SP', 'Sports', 'Academic', NULL, '', '2025-11-07 07:46:06', 1),
(23, 'r3r33', 'IS', 'Academic', NULL, '', '2025-11-12 02:12:35', 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `userID` int(11) NOT NULL,
  `password` varchar(255) NOT NULL,
  `firstName` varchar(50) NOT NULL,
  `middleName` varchar(50) DEFAULT NULL,
  `lastName` varchar(50) NOT NULL,
  `birthday` date DEFAULT NULL,
  `sex` enum('Male','Female') NOT NULL,
  `address` text DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `contactNumber` varchar(20) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `userType` enum('Student','Professor','SuperAdmin','Admin') NOT NULL,
  `status` enum('Active','Inactive','Suspended') NOT NULL,
  `lrn` varchar(12) NOT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) DEFAULT 0,
  `enrollment_notification_seen` tinyint(1) DEFAULT 0,
  `otp` varchar(6) DEFAULT NULL,
  `otp_expiration` datetime DEFAULT NULL,
  `level` int(11) NOT NULL DEFAULT 1,
  `generated_code` varchar(255) NOT NULL,
  `password_last_changed` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `uploaded_via` enum('csv','manual') DEFAULT 'manual',
  `dashboard_view` varchar(10) DEFAULT 'table'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`userID`, `password`, `firstName`, `middleName`, `lastName`, `birthday`, `sex`, `address`, `email`, `contactNumber`, `nationality`, `image`, `userType`, `status`, `lrn`, `dateCreated`, `archived`, `enrollment_notification_seen`, `otp`, `otp_expiration`, `level`, `generated_code`, `password_last_changed`, `created_at`, `uploaded_via`, `dashboard_view`) VALUES
(147, '$2y$10$i41DEA1pMGnZd/FsQeynmeYRP1JA2W7gCLdDEVPB14kKYpqupPbJ.', 'SA', 'SA', 'SA', '2000-04-04', 'Male', 'BLK 1 lot 1', 'superadmin@gmail.com', '09948698933', 'Filipino', '../../lib/file_uploads/68d79b88aa94a6.73289144.png', 'SuperAdmin', 'Active', '', '2025-09-27 07:59:55', 0, 0, NULL, NULL, 1, 'HR9ubNcQjj', NULL, '2025-09-27 00:59:55', 'manual', 'table'),
(148, '$2y$10$5cpecNZ23zNsUT3tntW8wuNtwgPW6l.nxoGN.EPs0sk4G.fm8ZmjG', 'Admin', 'Admin', 'Admin', '2001-10-27', 'Male', 'BLK 79 lot E2', 'admin1@gmail.com', '09103567181', 'Filipino', '../../lib/file_uploads/68Bd7b88aa94a6.73289144.png', 'Admin', 'Active', '68444', '2025-09-27 08:02:23', 0, 0, NULL, NULL, 1, 'iePYicoAZS', '2025-10-03 00:00:00', '2025-09-27 01:02:23', 'manual', 'table'),
(149, '$2y$10$DW9A4kVKzZPPR6qKx.VF3e6syUEboZQ/ktRngiD7b2bBfrohZDBUa', 'John', 'Oliver', 'Martillos', '2000-12-12', 'Male', 'BLK 17 Lot 12 BRGY. ST PETER 2', 'martillos.johnnemuelo.kld@gmail.com', '09663857154', 'Filipino', '../../lib/file_uploads/6913229170c8e6.37887710.jpg', 'Student', 'Active', '123432123456', '2025-09-27 08:38:00', 0, 0, '814424', '2025-11-09 07:02:52', 1, '8ZhnMjXYDq', NULL, '2025-09-27 01:38:00', 'manual', 'card'),
(150, '$2y$10$ppd/XjEM/vOmRtgXVGQaCeiXcNk4u.0sn8gA8SnQJHTLacgQ4Vxlq', 'Mark Christopher', 'B', 'Borja', '1986-11-05', 'Male', 'Blk 1 lot 1', 'mcborja@gmail.com', '09236574154', 'Filipino', './img/noprofile.png', 'Professor', 'Active', '06479', '2025-09-27 08:41:44', 0, 0, NULL, NULL, 1, 'G1zrmdZLIW', NULL, '2025-09-27 01:41:44', 'manual', 'table'),
(151, '$2y$10$krjDLR2NRYVanVatgh4/I.si7B5x9Jt9kZDLaZ9P0P23KFzQsm6jm', 'John', 'Luna', 'Ververal', '1994-10-15', 'Male', 'Blk 9 lot 13 Brgy. St. Lucia', 'teacher1@gmail.com', '09105479166', 'Filipino', '../../lib/file_uploads/68d7b82b40a1c3.41444804.png', 'Professor', 'Active', '09164', '2025-09-27 08:46:28', 0, 0, NULL, NULL, 1, '7raFm47hup', NULL, '2025-09-27 01:46:28', 'manual', 'card'),
(152, '$2y$10$lW0wQqUgg9ezQ7vjq3Yive0zu4U2iyKMhWyKpQOVvThEeFwgQAxYC', 'Emily', 'Mendiola', 'Lapid', '1999-12-28', 'Female', 'Blk  2 Lot 23', 'emilylapid33@gmail.com', '09072056515', 'Filipino', './img/noprofile.png', 'Student', 'Active', '202513262312', '2025-09-27 15:57:31', 0, 0, NULL, NULL, 1, 'HzDZND6oLZ', NULL, '2025-09-27 08:57:31', 'csv', 'table'),
(313, '$2y$10$JVdB740RHL/xHCq6nWV2k.EZjbJlhAzu9/KViYupfi8FqgZr/PHLe', 'Diana Rose', '', 'Aquino', '1991-06-24', 'Female', 'Blk 1 lot 99', 'jaydhane71@gmail.com', '09103563181', 'Filipino', './img/noprofile.png', 'Student', 'Active', '123212321236', '2025-11-07 14:42:53', 0, 0, NULL, NULL, 1, 'l1quzXDKur', NULL, '2025-11-07 06:42:53', 'manual', 'table'),
(314, '$2y$10$9c/MzbQoTWzQaD3kYcvHrOOsydRRzooadyLB3WKIjdHgK/FzXH7t2', 'Angelo', '', 'Periodico', '2000-03-08', 'Male', 'Blk 1 lot 18', 'periodicogelo@gmail.com', '09102336363', 'Filipino', './img/noprofile.png', 'Student', 'Active', '123443289012', '2025-11-07 14:49:26', 0, 0, NULL, NULL, 1, 'UqpHgDar3a', NULL, '2025-11-07 06:49:26', 'manual', 'table'),
(357, '$2y$10$LCL6tPk3qxYrAGNsU6pyq..UAgFRBGpRTyL34OdDUDAhWgfi1TLoe', 'BENCH', 'PUPA', 'SAMSON', '2000-06-12', 'Male', 'acacia b16 l4', 'pupateng@gmail.com', '09953015723', 'Filipino', './img/noprofile.png', 'Student', 'Active', '123456789101', '2025-11-12 02:15:13', 0, 0, NULL, NULL, 1, 'x5oiy55tct', NULL, '2025-11-11 18:15:13', 'manual', 'table');

-- --------------------------------------------------------

--
-- Table structure for table `user_modal_status`
--

CREATE TABLE `user_modal_status` (
  `userID` int(11) NOT NULL,
  `has_seen_welcome_modals` tinyint(1) DEFAULT 0,
  `last_seen_timestamp` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `user_modal_status`
--

INSERT INTO `user_modal_status` (`userID`, `has_seen_welcome_modals`, `last_seen_timestamp`) VALUES
(151, 1, '2025-11-11 18:19:31'),
(148, 1, '2025-11-11 18:01:33'),
(152, 1, '2025-11-11 16:37:47');

-- --------------------------------------------------------

--
-- Table structure for table `user_timeout_settings`
--

CREATE TABLE `user_timeout_settings` (
  `userID` int(11) NOT NULL,
  `timeout_duration` int(11) NOT NULL DEFAULT 300,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_timeout_settings`
--

INSERT INTO `user_timeout_settings` (`userID`, `timeout_duration`, `last_updated`) VALUES
(147, 86400, '2025-09-27 08:12:13'),
(148, 86400, '2025-11-10 06:05:06'),
(149, 86400, '2025-10-17 16:43:38'),
(151, 86400, '2025-11-09 14:25:51'),
(152, 60, '2025-11-07 07:52:40');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_events`
--
ALTER TABLE `academic_events`
  ADD PRIMARY KEY (`eventID`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `admin_messages`
--
ALTER TABLE `admin_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `recipient_id` (`recipient_id`);

--
-- Indexes for table `advisory_professor_section`
--
ALTER TABLE `advisory_professor_section`
  ADD PRIMARY KEY (`advisoryID`),
  ADD KEY `professorID` (`professorID`),
  ADD KEY `sectionID` (`sectionID`),
  ADD KEY `subjectID` (`subjectID`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcementID`),
  ADD KEY `teacherSectionID` (`teacherSectionID`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`assignmentID`),
  ADD KEY `teacherSectionID` (`teacherSectionID`);

--
-- Indexes for table `assignment_scores`
--
ALTER TABLE `assignment_scores`
  ADD PRIMARY KEY (`scoreID`),
  ADD KEY `assignmentID` (`assignmentID`),
  ADD KEY `studentID` (`studentID`),
  ADD KEY `idx_teacherSectionID_studentID` (`assignmentID`,`studentID`);

--
-- Indexes for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  ADD PRIMARY KEY (`submissionID`),
  ADD KEY `assignmentID` (`assignmentID`),
  ADD KEY `studentID` (`studentID`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendanceID`),
  ADD KEY `studentID` (`studentID`),
  ADD KEY `idx_teacherSectionID` (`teacherSectionID`),
  ADD KEY `idx_attendanceDate` (`attendanceDate`),
  ADD KEY `idx_teacherSectionID_studentID` (`teacherSectionID`,`studentID`);

--
-- Indexes for table `brainpix_badges`
--
ALTER TABLE `brainpix_badges`
  ADD PRIMARY KEY (`badgeID`),
  ADD KEY `mapID` (`mapID`);

--
-- Indexes for table `brainpix_levels`
--
ALTER TABLE `brainpix_levels`
  ADD PRIMARY KEY (`levelID`),
  ADD KEY `mapID` (`mapID`);

--
-- Indexes for table `brainpix_maps`
--
ALTER TABLE `brainpix_maps`
  ADD PRIMARY KEY (`mapID`);

--
-- Indexes for table `brainpix_user_badges`
--
ALTER TABLE `brainpix_user_badges`
  ADD PRIMARY KEY (`userBadgeID`),
  ADD UNIQUE KEY `userID` (`userID`,`badgeID`),
  ADD KEY `badgeID` (`badgeID`);

--
-- Indexes for table `brainpix_user_progress`
--
ALTER TABLE `brainpix_user_progress`
  ADD PRIMARY KEY (`progressID`),
  ADD UNIQUE KEY `userID` (`userID`,`levelID`),
  ADD KEY `levelID` (`levelID`);

--
-- Indexes for table `branding`
--
ALTER TABLE `branding`
  ADD PRIMARY KEY (`id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`commentID`),
  ADD KEY `teacherSectionID` (`teacherSectionID`),
  ADD KEY `userID` (`userID`),
  ADD KEY `parentCommentID` (`parentCommentID`);

--
-- Indexes for table `comment_notifications`
--
ALTER TABLE `comment_notifications`
  ADD PRIMARY KEY (`notificationID`),
  ADD KEY `commentID` (`commentID`),
  ADD KEY `notifiedUserID` (`notifiedUserID`);

--
-- Indexes for table `contact_feedback`
--
ALTER TABLE `contact_feedback`
  ADD PRIMARY KEY (`messageID`);

--
-- Indexes for table `csv_upload_logs`
--
ALTER TABLE `csv_upload_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `failed_login_attempts`
--
ALTER TABLE `failed_login_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `userID` (`userID`);

--
-- Indexes for table `feedback_message`
--
ALTER TABLE `feedback_message`
  ADD PRIMARY KEY (`feedbackID`);

--
-- Indexes for table `images`
--
ALTER TABLE `images`
  ADD PRIMARY KEY (`imageID`);

--
-- Indexes for table `learn_quiz_answers`
--
ALTER TABLE `learn_quiz_answers`
  ADD PRIMARY KEY (`answerID`),
  ADD KEY `sessionID` (`sessionID`),
  ADD KEY `questionID` (`questionID`);

--
-- Indexes for table `learn_quiz_games`
--
ALTER TABLE `learn_quiz_games`
  ADD PRIMARY KEY (`gameID`),
  ADD UNIQUE KEY `gameCode` (`gameCode`),
  ADD KEY `professorID` (`professorID`),
  ADD KEY `idx_gameCode` (`gameCode`);

--
-- Indexes for table `learn_quiz_sessions`
--
ALTER TABLE `learn_quiz_sessions`
  ADD PRIMARY KEY (`sessionID`),
  ADD UNIQUE KEY `unique_session` (`gameID`,`userID`),
  ADD KEY `userID` (`userID`);

--
-- Indexes for table `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `userID` (`userID`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`moduleID`),
  ADD KEY `teacherSectionID` (`teacherSectionID`);

--
-- Indexes for table `module_comments`
--
ALTER TABLE `module_comments`
  ADD PRIMARY KEY (`commentID`),
  ADD KEY `moduleID` (`moduleID`),
  ADD KEY `userID` (`userID`),
  ADD KEY `parentCommentID` (`parentCommentID`);

--
-- Indexes for table `module_comment_views`
--
ALTER TABLE `module_comment_views`
  ADD PRIMARY KEY (`userID`,`moduleID`),
  ADD KEY `moduleID` (`moduleID`);

--
-- Indexes for table `notification_views`
--
ALTER TABLE `notification_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_view` (`userID`,`announcementID`),
  ADD KEY `announcementID` (`announcementID`);

--
-- Indexes for table `private_messages`
--
ALTER TABLE `private_messages`
  ADD PRIMARY KEY (`messageID`),
  ADD KEY `senderID` (`senderID`),
  ADD KEY `recipientID` (`recipientID`),
  ADD KEY `teacherSectionID` (`teacherSectionID`);

--
-- Indexes for table `public_data`
--
ALTER TABLE `public_data`
  ADD PRIMARY KEY (`publicDataID`),
  ADD KEY `userID` (`userID`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`questionID`),
  ADD KEY `idx_quizID` (`quizID`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`quizID`),
  ADD KEY `idx_teacherSectionID` (`teacherSectionID`),
  ADD KEY `idx_createdDate` (`createdDate`);

--
-- Indexes for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  ADD PRIMARY KEY (`answerID`),
  ADD KEY `studentID` (`studentID`),
  ADD KEY `idx_quizID_studentID` (`quizID`,`studentID`),
  ADD KEY `idx_questionID` (`questionID`);

--
-- Indexes for table `quiz_scores`
--
ALTER TABLE `quiz_scores`
  ADD PRIMARY KEY (`scoreID`),
  ADD KEY `studentID` (`studentID`),
  ADD KEY `idx_quizID_studentID` (`quizID`,`studentID`),
  ADD KEY `idx_teacherSectionID_studentID` (`quizID`,`studentID`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`sectionID`),
  ADD KEY `fk_strandID` (`strandID`);

--
-- Indexes for table `seen_academic_events`
--
ALTER TABLE `seen_academic_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_event` (`userID`,`eventID`),
  ADD KEY `eventID` (`eventID`);

--
-- Indexes for table `speech_to_text_certificate`
--
ALTER TABLE `speech_to_text_certificate`
  ADD PRIMARY KEY (`certificateID`),
  ADD UNIQUE KEY `userID` (`userID`);

--
-- Indexes for table `speech_to_text_daily_bonus`
--
ALTER TABLE `speech_to_text_daily_bonus`
  ADD PRIMARY KEY (`bonusID`),
  ADD KEY `userID` (`userID`);

--
-- Indexes for table `speech_to_text_game_images`
--
ALTER TABLE `speech_to_text_game_images`
  ADD PRIMARY KEY (`imageID`),
  ADD UNIQUE KEY `level` (`level`);

--
-- Indexes for table `speech_to_text_user_attempts`
--
ALTER TABLE `speech_to_text_user_attempts`
  ADD PRIMARY KEY (`attemptID`),
  ADD KEY `userID` (`userID`),
  ADD KEY `imageID` (`imageID`);

--
-- Indexes for table `speech_to_text_user_progress_image`
--
ALTER TABLE `speech_to_text_user_progress_image`
  ADD PRIMARY KEY (`progressID`),
  ADD UNIQUE KEY `userID` (`userID`);

--
-- Indexes for table `strand_modules`
--
ALTER TABLE `strand_modules`
  ADD PRIMARY KEY (`moduleID`),
  ADD KEY `fk_strandID` (`strandID`);

--
-- Indexes for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  ADD PRIMARY KEY (`enrollmentID`),
  ADD KEY `userID` (`userID`),
  ADD KEY `sectionID` (`sectionID`),
  ADD KEY `strandID` (`strandID`),
  ADD KEY `subjectID` (`subjectID`),
  ADD KEY `professorID` (`professorID`);

--
-- Indexes for table `student_section`
--
ALTER TABLE `student_section`
  ADD PRIMARY KEY (`studentSectionID`),
  ADD KEY `userID` (`userID`),
  ADD KEY `sectionID` (`sectionID`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subjectID`),
  ADD KEY `professorID` (`professorID`),
  ADD KEY `fk_subjects_strandID` (`strandID`);

--
-- Indexes for table `subject_professor`
--
ALTER TABLE `subject_professor`
  ADD PRIMARY KEY (`subjectID`,`professorID`),
  ADD KEY `professorID` (`professorID`);

--
-- Indexes for table `teacher_section`
--
ALTER TABLE `teacher_section`
  ADD PRIMARY KEY (`teacherSectionID`),
  ADD UNIQUE KEY `unique_teacher_section` (`teacherID`,`sectionID`,`subjectID`,`day`,`archived`),
  ADD KEY `teacherID` (`teacherID`),
  ADD KEY `sectionID` (`sectionID`),
  ADD KEY `subjectID` (`subjectID`);

--
-- Indexes for table `tests`
--
ALTER TABLE `tests`
  ADD PRIMARY KEY (`testID`),
  ADD KEY `strandID` (`strandID`),
  ADD KEY `testTypeID` (`testTypeID`);

--
-- Indexes for table `test_types`
--
ALTER TABLE `test_types`
  ADD PRIMARY KEY (`testTypeID`);

--
-- Indexes for table `todos`
--
ALTER TABLE `todos`
  ADD PRIMARY KEY (`todoID`),
  ADD KEY `userID` (`userID`);

--
-- Indexes for table `track_strands`
--
ALTER TABLE `track_strands`
  ADD PRIMARY KEY (`strandID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`userID`);

--
-- Indexes for table `user_modal_status`
--
ALTER TABLE `user_modal_status`
  ADD PRIMARY KEY (`userID`);

--
-- Indexes for table `user_timeout_settings`
--
ALTER TABLE `user_timeout_settings`
  ADD PRIMARY KEY (`userID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_events`
--
ALTER TABLE `academic_events`
  MODIFY `eventID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `admin_messages`
--
ALTER TABLE `admin_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `advisory_professor_section`
--
ALTER TABLE `advisory_professor_section`
  MODIFY `advisoryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcementID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `assignmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `assignment_scores`
--
ALTER TABLE `assignment_scores`
  MODIFY `scoreID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  MODIFY `submissionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendanceID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=361;

--
-- AUTO_INCREMENT for table `brainpix_badges`
--
ALTER TABLE `brainpix_badges`
  MODIFY `badgeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `brainpix_levels`
--
ALTER TABLE `brainpix_levels`
  MODIFY `levelID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=191;

--
-- AUTO_INCREMENT for table `brainpix_maps`
--
ALTER TABLE `brainpix_maps`
  MODIFY `mapID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `brainpix_user_badges`
--
ALTER TABLE `brainpix_user_badges`
  MODIFY `userBadgeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `brainpix_user_progress`
--
ALTER TABLE `brainpix_user_progress`
  MODIFY `progressID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=147;

--
-- AUTO_INCREMENT for table `branding`
--
ALTER TABLE `branding`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `commentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `comment_notifications`
--
ALTER TABLE `comment_notifications`
  MODIFY `notificationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `contact_feedback`
--
ALTER TABLE `contact_feedback`
  MODIFY `messageID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `csv_upload_logs`
--
ALTER TABLE `csv_upload_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `failed_login_attempts`
--
ALTER TABLE `failed_login_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=221;

--
-- AUTO_INCREMENT for table `feedbacks`
--
ALTER TABLE `feedbacks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback_message`
--
ALTER TABLE `feedback_message`
  MODIFY `feedbackID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `grading_weights`
--
ALTER TABLE `grading_weights`
  MODIFY `weightID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `images`
--
ALTER TABLE `images`
  MODIFY `imageID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `learn_quiz_answers`
--
ALTER TABLE `learn_quiz_answers`
  MODIFY `answerID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=300;

--
-- AUTO_INCREMENT for table `learn_quiz_games`
--
ALTER TABLE `learn_quiz_games`
  MODIFY `gameID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `learn_quiz_questions`
--
ALTER TABLE `learn_quiz_questions`
  MODIFY `questionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `learn_quiz_sessions`
--
ALTER TABLE `learn_quiz_sessions`
  MODIFY `sessionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=167;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `moduleID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `module_comments`
--
ALTER TABLE `module_comments`
  MODIFY `commentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `notification_views`
--
ALTER TABLE `notification_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `private_messages`
--
ALTER TABLE `private_messages`
  MODIFY `messageID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `public_data`
--
ALTER TABLE `public_data`
  MODIFY `publicDataID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `questionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=229;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `quizID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  MODIFY `answerID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=336;

--
-- AUTO_INCREMENT for table `quiz_scores`
--
ALTER TABLE `quiz_scores`
  MODIFY `scoreID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `sectionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `seen_academic_events`
--
ALTER TABLE `seen_academic_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `speech_to_text_certificate`
--
ALTER TABLE `speech_to_text_certificate`
  MODIFY `certificateID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `speech_to_text_daily_bonus`
--
ALTER TABLE `speech_to_text_daily_bonus`
  MODIFY `bonusID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `speech_to_text_game_images`
--
ALTER TABLE `speech_to_text_game_images`
  MODIFY `imageID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `speech_to_text_user_attempts`
--
ALTER TABLE `speech_to_text_user_attempts`
  MODIFY `attemptID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=287;

--
-- AUTO_INCREMENT for table `speech_to_text_user_progress_image`
--
ALTER TABLE `speech_to_text_user_progress_image`
  MODIFY `progressID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=287;

--
-- AUTO_INCREMENT for table `strand_modules`
--
ALTER TABLE `strand_modules`
  MODIFY `moduleID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  MODIFY `enrollmentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_section`
--
ALTER TABLE `student_section`
  MODIFY `studentSectionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subjectID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `teacher_section`
--
ALTER TABLE `teacher_section`
  MODIFY `teacherSectionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `tests`
--
ALTER TABLE `tests`
  MODIFY `testID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `test_types`
--
ALTER TABLE `test_types`
  MODIFY `testTypeID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `todos`
--
ALTER TABLE `todos`
  MODIFY `todoID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `track_strands`
--
ALTER TABLE `track_strands`
  MODIFY `strandID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=358;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `academic_events`
--
ALTER TABLE `academic_events`
  ADD CONSTRAINT `academic_events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `admin_messages`
--
ALTER TABLE `admin_messages`
  ADD CONSTRAINT `admin_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `advisory_professor_section`
--
ALTER TABLE `advisory_professor_section`
  ADD CONSTRAINT `advisory_professor_section_ibfk_1` FOREIGN KEY (`professorID`) REFERENCES `users` (`userID`),
  ADD CONSTRAINT `advisory_professor_section_ibfk_2` FOREIGN KEY (`sectionID`) REFERENCES `sections` (`sectionID`),
  ADD CONSTRAINT `advisory_professor_section_ibfk_3` FOREIGN KEY (`subjectID`) REFERENCES `subjects` (`subjectID`);

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`teacherSectionID`) REFERENCES `teacher_section` (`teacherSectionID`) ON DELETE CASCADE;

--
-- Constraints for table `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`teacherSectionID`) REFERENCES `teacher_section` (`teacherSectionID`) ON DELETE CASCADE;

--
-- Constraints for table `assignment_scores`
--
ALTER TABLE `assignment_scores`
  ADD CONSTRAINT `assignment_scores_ibfk_1` FOREIGN KEY (`assignmentID`) REFERENCES `assignments` (`assignmentID`) ON DELETE CASCADE,
  ADD CONSTRAINT `assignment_scores_ibfk_2` FOREIGN KEY (`studentID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  ADD CONSTRAINT `assignment_submissions_ibfk_1` FOREIGN KEY (`assignmentID`) REFERENCES `assignments` (`assignmentID`) ON DELETE CASCADE,
  ADD CONSTRAINT `assignment_submissions_ibfk_2` FOREIGN KEY (`studentID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`studentID`) REFERENCES `users` (`userID`),
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`teacherSectionID`) REFERENCES `teacher_section` (`teacherSectionID`);

--
-- Constraints for table `brainpix_badges`
--
ALTER TABLE `brainpix_badges`
  ADD CONSTRAINT `brainpix_badges_ibfk_1` FOREIGN KEY (`mapID`) REFERENCES `brainpix_maps` (`mapID`) ON DELETE CASCADE;

--
-- Constraints for table `brainpix_levels`
--
ALTER TABLE `brainpix_levels`
  ADD CONSTRAINT `brainpix_levels_ibfk_1` FOREIGN KEY (`mapID`) REFERENCES `brainpix_maps` (`mapID`) ON DELETE CASCADE;

--
-- Constraints for table `brainpix_user_badges`
--
ALTER TABLE `brainpix_user_badges`
  ADD CONSTRAINT `brainpix_user_badges_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `brainpix_user_badges_ibfk_2` FOREIGN KEY (`badgeID`) REFERENCES `brainpix_badges` (`badgeID`) ON DELETE CASCADE;

--
-- Constraints for table `brainpix_user_progress`
--
ALTER TABLE `brainpix_user_progress`
  ADD CONSTRAINT `brainpix_user_progress_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `brainpix_user_progress_ibfk_2` FOREIGN KEY (`levelID`) REFERENCES `brainpix_levels` (`levelID`) ON DELETE CASCADE;

--
-- Constraints for table `branding`
--
ALTER TABLE `branding`
  ADD CONSTRAINT `branding_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`userID`);

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`teacherSectionID`) REFERENCES `teacher_section` (`teacherSectionID`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_3` FOREIGN KEY (`parentCommentID`) REFERENCES `comments` (`commentID`) ON DELETE CASCADE;

--
-- Constraints for table `comment_notifications`
--
ALTER TABLE `comment_notifications`
  ADD CONSTRAINT `comment_notifications_ibfk_1` FOREIGN KEY (`commentID`) REFERENCES `module_comments` (`commentID`) ON DELETE CASCADE,
  ADD CONSTRAINT `comment_notifications_ibfk_2` FOREIGN KEY (`notifiedUserID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `csv_upload_logs`
--
ALTER TABLE `csv_upload_logs`
  ADD CONSTRAINT `csv_upload_logs_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`userID`);

--
-- Constraints for table `failed_login_attempts`
--
ALTER TABLE `failed_login_attempts`
  ADD CONSTRAINT `failed_login_attempts_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`);

--
-- Constraints for table `learn_quiz_answers`
--
ALTER TABLE `learn_quiz_answers`
  ADD CONSTRAINT `learn_quiz_answers_ibfk_1` FOREIGN KEY (`sessionID`) REFERENCES `learn_quiz_sessions` (`sessionID`),
  ADD CONSTRAINT `learn_quiz_answers_ibfk_2` FOREIGN KEY (`questionID`) REFERENCES `learn_quiz_questions` (`questionID`);

--
-- Constraints for table `learn_quiz_games`
--
ALTER TABLE `learn_quiz_games`
  ADD CONSTRAINT `learn_quiz_games_ibfk_1` FOREIGN KEY (`professorID`) REFERENCES `users` (`userID`);

--
-- Constraints for table `learn_quiz_sessions`
--
ALTER TABLE `learn_quiz_sessions`
  ADD CONSTRAINT `learn_quiz_sessions_ibfk_1` FOREIGN KEY (`gameID`) REFERENCES `learn_quiz_games` (`gameID`),
  ADD CONSTRAINT `learn_quiz_sessions_ibfk_2` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`);

--
-- Constraints for table `login_history`
--
ALTER TABLE `login_history`
  ADD CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`);

--
-- Constraints for table `modules`
--
ALTER TABLE `modules`
  ADD CONSTRAINT `modules_ibfk_1` FOREIGN KEY (`teacherSectionID`) REFERENCES `teacher_section` (`teacherSectionID`) ON DELETE CASCADE;

--
-- Constraints for table `module_comments`
--
ALTER TABLE `module_comments`
  ADD CONSTRAINT `module_comments_ibfk_1` FOREIGN KEY (`moduleID`) REFERENCES `modules` (`moduleID`) ON DELETE CASCADE,
  ADD CONSTRAINT `module_comments_ibfk_2` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `module_comments_ibfk_3` FOREIGN KEY (`parentCommentID`) REFERENCES `module_comments` (`commentID`) ON DELETE SET NULL;

--
-- Constraints for table `module_comment_views`
--
ALTER TABLE `module_comment_views`
  ADD CONSTRAINT `module_comment_views_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `module_comment_views_ibfk_2` FOREIGN KEY (`moduleID`) REFERENCES `modules` (`moduleID`) ON DELETE CASCADE;

--
-- Constraints for table `notification_views`
--
ALTER TABLE `notification_views`
  ADD CONSTRAINT `notification_views_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`),
  ADD CONSTRAINT `notification_views_ibfk_2` FOREIGN KEY (`announcementID`) REFERENCES `announcements` (`announcementID`);

--
-- Constraints for table `private_messages`
--
ALTER TABLE `private_messages`
  ADD CONSTRAINT `private_messages_ibfk_1` FOREIGN KEY (`senderID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `private_messages_ibfk_2` FOREIGN KEY (`recipientID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `private_messages_ibfk_3` FOREIGN KEY (`teacherSectionID`) REFERENCES `teacher_section` (`teacherSectionID`) ON DELETE CASCADE;

--
-- Constraints for table `public_data`
--
ALTER TABLE `public_data`
  ADD CONSTRAINT `public_data_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`quizID`) REFERENCES `quizzes` (`quizID`) ON DELETE CASCADE;

--
-- Constraints for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`teacherSectionID`) REFERENCES `teacher_section` (`teacherSectionID`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  ADD CONSTRAINT `quiz_answers_ibfk_1` FOREIGN KEY (`quizID`) REFERENCES `quizzes` (`quizID`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_answers_ibfk_2` FOREIGN KEY (`studentID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_answers_ibfk_3` FOREIGN KEY (`questionID`) REFERENCES `questions` (`questionID`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_scores`
--
ALTER TABLE `quiz_scores`
  ADD CONSTRAINT `quiz_scores_ibfk_1` FOREIGN KEY (`quizID`) REFERENCES `quizzes` (`quizID`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_scores_ibfk_2` FOREIGN KEY (`studentID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `fk_strandID` FOREIGN KEY (`strandID`) REFERENCES `track_strands` (`strandID`);

--
-- Constraints for table `seen_academic_events`
--
ALTER TABLE `seen_academic_events`
  ADD CONSTRAINT `seen_academic_events_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `seen_academic_events_ibfk_2` FOREIGN KEY (`eventID`) REFERENCES `academic_events` (`eventID`) ON DELETE CASCADE;

--
-- Constraints for table `speech_to_text_certificate`
--
ALTER TABLE `speech_to_text_certificate`
  ADD CONSTRAINT `speech_to_text_certificate_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`);

--
-- Constraints for table `speech_to_text_daily_bonus`
--
ALTER TABLE `speech_to_text_daily_bonus`
  ADD CONSTRAINT `speech_to_text_daily_bonus_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`);

--
-- Constraints for table `speech_to_text_user_attempts`
--
ALTER TABLE `speech_to_text_user_attempts`
  ADD CONSTRAINT `speech_to_text_user_attempts_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`),
  ADD CONSTRAINT `speech_to_text_user_attempts_ibfk_2` FOREIGN KEY (`imageID`) REFERENCES `speech_to_text_game_images` (`imageID`);

--
-- Constraints for table `speech_to_text_user_progress_image`
--
ALTER TABLE `speech_to_text_user_progress_image`
  ADD CONSTRAINT `speech_to_text_user_progress_image_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`);

--
-- Constraints for table `strand_modules`
--
ALTER TABLE `strand_modules`
  ADD CONSTRAINT `fk_strandID_modules` FOREIGN KEY (`strandID`) REFERENCES `track_strands` (`strandID`) ON DELETE CASCADE;

--
-- Constraints for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  ADD CONSTRAINT `student_enrollments_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`),
  ADD CONSTRAINT `student_enrollments_ibfk_2` FOREIGN KEY (`sectionID`) REFERENCES `sections` (`sectionID`),
  ADD CONSTRAINT `student_enrollments_ibfk_3` FOREIGN KEY (`strandID`) REFERENCES `track_strands` (`strandID`),
  ADD CONSTRAINT `student_enrollments_ibfk_4` FOREIGN KEY (`subjectID`) REFERENCES `subjects` (`subjectID`),
  ADD CONSTRAINT `student_enrollments_ibfk_5` FOREIGN KEY (`professorID`) REFERENCES `users` (`userID`) ON DELETE SET NULL;

--
-- Constraints for table `student_section`
--
ALTER TABLE `student_section`
  ADD CONSTRAINT `student_section_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`),
  ADD CONSTRAINT `student_section_ibfk_2` FOREIGN KEY (`sectionID`) REFERENCES `sections` (`sectionID`);

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `fk_subjects_strandID` FOREIGN KEY (`strandID`) REFERENCES `track_strands` (`strandID`),
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`professorID`) REFERENCES `users` (`userID`);

--
-- Constraints for table `subject_professor`
--
ALTER TABLE `subject_professor`
  ADD CONSTRAINT `subject_professor_ibfk_1` FOREIGN KEY (`subjectID`) REFERENCES `subjects` (`subjectID`),
  ADD CONSTRAINT `subject_professor_ibfk_2` FOREIGN KEY (`professorID`) REFERENCES `users` (`userID`);

--
-- Constraints for table `teacher_section`
--
ALTER TABLE `teacher_section`
  ADD CONSTRAINT `teacher_section_ibfk_1` FOREIGN KEY (`teacherID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_section_ibfk_2` FOREIGN KEY (`sectionID`) REFERENCES `sections` (`sectionID`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_section_ibfk_3` FOREIGN KEY (`subjectID`) REFERENCES `subjects` (`subjectID`) ON DELETE CASCADE;

--
-- Constraints for table `tests`
--
ALTER TABLE `tests`
  ADD CONSTRAINT `tests_ibfk_1` FOREIGN KEY (`strandID`) REFERENCES `track_strands` (`strandID`),
  ADD CONSTRAINT `tests_ibfk_2` FOREIGN KEY (`testTypeID`) REFERENCES `test_types` (`testTypeID`);

--
-- Constraints for table `todos`
--
ALTER TABLE `todos`
  ADD CONSTRAINT `todos_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `user_timeout_settings`
--
ALTER TABLE `user_timeout_settings`
  ADD CONSTRAINT `user_timeout_settings_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
