-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 09, 2026 at 01:30 PM
-- Server version: 8.4.5-5
-- PHP Version: 8.2.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dbw37mqkzieajt`
--

-- --------------------------------------------------------

--
-- Table structure for table `achievements`
--

CREATE TABLE `achievements` (
  `id` smallint UNSIGNED NOT NULL,
  `slug` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text,
  `icon_url` varchar(500) DEFAULT NULL,
  `badge_color` varchar(7) DEFAULT NULL,
  `xp_reward` smallint NOT NULL DEFAULT '0',
  `criteria_json` json NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `achievements`
--

INSERT INTO `achievements` (`id`, `slug`, `name`, `description`, `icon_url`, `badge_color`, `xp_reward`, `criteria_json`, `is_active`, `created_at`) VALUES
(1, 'onboarding_complete', 'Welcome Aboard', 'Complete the onboarding process', NULL, '#cd7f32', 50, '{\"key\": \"onboarding_complete\", \"tier\": \"bronze\"}', 1, '2026-03-03 16:20:40');

-- --------------------------------------------------------

--
-- Table structure for table `admin_accounts`
--

CREATE TABLE `admin_accounts` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `admin_role` enum('super_admin','content_admin','support_admin') NOT NULL DEFAULT 'content_admin',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','teacher') NOT NULL DEFAULT 'admin',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `name`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`) VALUES
(2, 'Admin', 'admin@avidmock.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', '2026-02-23 20:35:50', '2026-02-24 17:28:08');

-- --------------------------------------------------------

--
-- Table structure for table `ai_tutor_conversations`
--

CREATE TABLE `ai_tutor_conversations` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `subject_id` tinyint UNSIGNED DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `mode` enum('general','review_coach','strategy_advisor') NOT NULL DEFAULT 'general',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ai_tutor_messages`
--

CREATE TABLE `ai_tutor_messages` (
  `id` int UNSIGNED NOT NULL,
  `conversation_id` int UNSIGNED NOT NULL,
  `role` enum('user','assistant') NOT NULL,
  `content` longtext NOT NULL,
  `tokens_used` smallint DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auth_tokens`
--

CREATE TABLE `auth_tokens` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `type` enum('email_verify','password_reset','remember_me') NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `category_performance`
--

CREATE TABLE `category_performance` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_quizzes` int UNSIGNED NOT NULL DEFAULT '0',
  `correct_answers` int UNSIGNED NOT NULL DEFAULT '0',
  `avg_score` decimal(5,1) NOT NULL DEFAULT '0.0',
  `mastery_level` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `college_matches`
--

CREATE TABLE `college_matches` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `avg_sat_score` smallint NOT NULL,
  `score_range_low` smallint NOT NULL,
  `score_range_high` smallint NOT NULL,
  `acceptance_rate` decimal(5,2) DEFAULT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `website_url` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `diagnostic_attempts`
--

CREATE TABLE `diagnostic_attempts` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `math_theta` decimal(5,3) NOT NULL DEFAULT '0.000',
  `rw_theta` decimal(5,3) NOT NULL DEFAULT '0.000',
  `answers_json` json DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `diagnostic_attempts`
--

INSERT INTO `diagnostic_attempts` (`id`, `user_id`, `math_theta`, `rw_theta`, `answers_json`, `completed_at`, `created_at`) VALUES
(1, 18, -0.600, -0.600, '[{\"topic\": \"linear_equations\", \"correct\": true, \"subject\": \"math\", \"difficulty\": 1, \"question_id\": \"diag_m1\", \"user_answer\": \"B\"}, {\"topic\": \"functions\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 2, \"question_id\": \"diag_m2\", \"user_answer\": \"A\"}, {\"topic\": \"statistics\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 2, \"question_id\": \"diag_m3\", \"user_answer\": \"D\"}, {\"topic\": \"geometry\", \"correct\": true, \"subject\": \"math\", \"difficulty\": 3, \"question_id\": \"diag_m4\", \"user_answer\": \"A\"}, {\"topic\": \"quadratics\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 3, \"question_id\": \"diag_m5\", \"user_answer\": \"C\"}, {\"topic\": \"main_idea\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 1, \"question_id\": \"diag_r1\", \"user_answer\": \"A\"}, {\"topic\": \"words_in_context\", \"correct\": true, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r2\", \"user_answer\": \"C\"}, {\"topic\": \"transitions\", \"correct\": true, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r3\", \"user_answer\": \"B\"}, {\"topic\": \"agreement\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r4\", \"user_answer\": \"D\"}, {\"topic\": \"inferences\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 3, \"question_id\": \"diag_r5\", \"user_answer\": \"A\"}]', '2026-03-03 21:03:43', '2026-03-03 21:03:43'),
(2, 19, -1.800, -3.000, '[{\"topic\": \"linear_equations\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 1, \"question_id\": \"diag_m1\", \"user_answer\": \"A\"}, {\"topic\": \"functions\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 2, \"question_id\": \"diag_m2\", \"user_answer\": \"A\"}, {\"topic\": \"statistics\", \"correct\": true, \"subject\": \"math\", \"difficulty\": 2, \"question_id\": \"diag_m3\", \"user_answer\": \"B\"}, {\"topic\": \"geometry\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 3, \"question_id\": \"diag_m4\", \"user_answer\": \"C\"}, {\"topic\": \"quadratics\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 3, \"question_id\": \"diag_m5\", \"user_answer\": \"C\"}, {\"topic\": \"main_idea\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 1, \"question_id\": \"diag_r1\", \"user_answer\": \"A\"}, {\"topic\": \"words_in_context\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r2\", \"user_answer\": \"B\"}, {\"topic\": \"transitions\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r3\", \"user_answer\": \"A\"}, {\"topic\": \"agreement\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r4\", \"user_answer\": \"C\"}, {\"topic\": \"inferences\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 3, \"question_id\": \"diag_r5\", \"user_answer\": \"A\"}]', '2026-03-03 21:06:30', '2026-03-03 21:06:30'),
(3, 20, -1.800, -1.800, '[{\"topic\": \"linear_equations\", \"correct\": true, \"subject\": \"math\", \"difficulty\": 1, \"question_id\": \"diag_m1\", \"user_answer\": \"B\"}, {\"topic\": \"functions\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 2, \"question_id\": \"diag_m2\", \"user_answer\": \"A\"}, {\"topic\": \"statistics\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 2, \"question_id\": \"diag_m3\", \"user_answer\": \"D\"}, {\"topic\": \"geometry\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 3, \"question_id\": \"diag_m4\", \"user_answer\": \"B\"}, {\"topic\": \"quadratics\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 3, \"question_id\": \"diag_m5\", \"user_answer\": \"C\"}, {\"topic\": \"main_idea\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 1, \"question_id\": \"diag_r1\", \"user_answer\": \"A\"}, {\"topic\": \"words_in_context\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r2\", \"user_answer\": \"D\"}, {\"topic\": \"transitions\", \"correct\": true, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r3\", \"user_answer\": \"B\"}, {\"topic\": \"agreement\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r4\", \"user_answer\": \"C\"}, {\"topic\": \"inferences\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 3, \"question_id\": \"diag_r5\", \"user_answer\": \"A\"}]', '2026-03-04 13:26:36', '2026-03-04 13:26:36'),
(4, 21, -3.000, -1.800, '[{\"topic\": \"linear_equations\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 1, \"question_id\": \"diag_m1\", \"user_answer\": \"C\"}, {\"topic\": \"functions\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 2, \"question_id\": \"diag_m2\", \"user_answer\": \"B\"}, {\"topic\": \"statistics\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 2, \"question_id\": \"diag_m3\", \"user_answer\": \"A\"}, {\"topic\": \"geometry\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 3, \"question_id\": \"diag_m4\", \"user_answer\": \"D\"}, {\"topic\": \"quadratics\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 3, \"question_id\": \"diag_m5\", \"user_answer\": \"C\"}, {\"topic\": \"main_idea\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 1, \"question_id\": \"diag_r1\", \"user_answer\": \"A\"}, {\"topic\": \"words_in_context\", \"correct\": true, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r2\", \"user_answer\": \"C\"}, {\"topic\": \"transitions\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r3\", \"user_answer\": \"A\"}, {\"topic\": \"agreement\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r4\", \"user_answer\": \"D\"}, {\"topic\": \"inferences\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 3, \"question_id\": \"diag_r5\", \"user_answer\": \"A\"}]', '2026-03-05 08:58:33', '2026-03-05 08:58:33'),
(5, 22, -0.600, -1.800, '[{\"topic\": \"linear_equations\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 1, \"question_id\": \"diag_m1\", \"user_answer\": \"D\"}, {\"topic\": \"functions\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 2, \"question_id\": \"diag_m2\", \"user_answer\": \"A\"}, {\"topic\": \"statistics\", \"correct\": true, \"subject\": \"math\", \"difficulty\": 2, \"question_id\": \"diag_m3\", \"user_answer\": \"B\"}, {\"topic\": \"geometry\", \"correct\": true, \"subject\": \"math\", \"difficulty\": 3, \"question_id\": \"diag_m4\", \"user_answer\": \"A\"}, {\"topic\": \"quadratics\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 3, \"question_id\": \"diag_m5\", \"user_answer\": \"C\"}, {\"topic\": \"main_idea\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 1, \"question_id\": \"diag_r1\", \"user_answer\": \"A\"}, {\"topic\": \"words_in_context\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r2\", \"user_answer\": \"B\"}, {\"topic\": \"transitions\", \"correct\": true, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r3\", \"user_answer\": \"B\"}, {\"topic\": \"agreement\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r4\", \"user_answer\": \"A\"}, {\"topic\": \"inferences\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 3, \"question_id\": \"diag_r5\", \"user_answer\": \"D\"}]', '2026-03-05 10:00:24', '2026-03-05 10:00:24'),
(6, 23, -1.800, -0.600, '[{\"topic\": \"linear_equations\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 1, \"question_id\": \"diag_m1\", \"user_answer\": \"A\"}, {\"topic\": \"functions\", \"correct\": true, \"subject\": \"math\", \"difficulty\": 2, \"question_id\": \"diag_m2\", \"user_answer\": \"C\"}, {\"topic\": \"statistics\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 2, \"question_id\": \"diag_m3\", \"user_answer\": \"C\"}, {\"topic\": \"geometry\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 3, \"question_id\": \"diag_m4\", \"user_answer\": \"C\"}, {\"topic\": \"quadratics\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 3, \"question_id\": \"diag_m5\", \"user_answer\": \"C\"}, {\"topic\": \"main_idea\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 1, \"question_id\": \"diag_r1\", \"user_answer\": \"C\"}, {\"topic\": \"words_in_context\", \"correct\": true, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r2\", \"user_answer\": \"C\"}, {\"topic\": \"transitions\", \"correct\": true, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r3\", \"user_answer\": \"B\"}, {\"topic\": \"agreement\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r4\", \"user_answer\": \"C\"}, {\"topic\": \"inferences\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 3, \"question_id\": \"diag_r5\", \"user_answer\": \"C\"}]', '2026-03-05 21:59:24', '2026-03-05 21:59:24'),
(7, 24, 0.600, -1.800, '[{\"topic\": \"linear_equations\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 1, \"question_id\": \"diag_m1\", \"user_answer\": \"A\"}, {\"topic\": \"functions\", \"correct\": true, \"subject\": \"math\", \"difficulty\": 2, \"question_id\": \"diag_m2\", \"user_answer\": \"C\"}, {\"topic\": \"statistics\", \"correct\": true, \"subject\": \"math\", \"difficulty\": 2, \"question_id\": \"diag_m3\", \"user_answer\": \"B\"}, {\"topic\": \"geometry\", \"correct\": true, \"subject\": \"math\", \"difficulty\": 3, \"question_id\": \"diag_m4\", \"user_answer\": \"A\"}, {\"topic\": \"quadratics\", \"correct\": false, \"subject\": \"math\", \"difficulty\": 3, \"question_id\": \"diag_m5\", \"user_answer\": \"D\"}, {\"topic\": \"main_idea\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 1, \"question_id\": \"diag_r1\", \"user_answer\": \"A\"}, {\"topic\": \"words_in_context\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r2\", \"user_answer\": \"A\"}, {\"topic\": \"transitions\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r3\", \"user_answer\": \"D\"}, {\"topic\": \"agreement\", \"correct\": false, \"subject\": \"reading_writing\", \"difficulty\": 2, \"question_id\": \"diag_r4\", \"user_answer\": \"A\"}, {\"topic\": \"inferences\", \"correct\": true, \"subject\": \"reading_writing\", \"difficulty\": 3, \"question_id\": \"diag_r5\", \"user_answer\": \"B\"}]', '2026-03-07 14:16:04', '2026-03-07 14:16:04');

-- --------------------------------------------------------

--
-- Table structure for table `explanation_feedback`
--

CREATE TABLE `explanation_feedback` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `question_id` int UNSIGNED NOT NULL,
  `feedback_type` enum('up','down') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

CREATE TABLE `lessons` (
  `id` int UNSIGNED NOT NULL,
  `module_id` smallint UNSIGNED NOT NULL,
  `micro_topic_id` smallint UNSIGNED DEFAULT NULL,
  `slug` varchar(150) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `video_url` varchar(500) DEFAULT NULL,
  `video_duration` smallint DEFAULT NULL,
  `pdf_url` varchar(500) DEFAULT NULL,
  `content_html` longtext,
  `sort_order` smallint NOT NULL DEFAULT '0',
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `is_micro_lesson` tinyint(1) NOT NULL DEFAULT '0',
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` varchar(500) DEFAULT NULL,
  `seo_keywords` varchar(500) DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lesson_completions`
--

CREATE TABLE `lesson_completions` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `lesson_id` int UNSIGNED NOT NULL,
  `completed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `micro_topics`
--

CREATE TABLE `micro_topics` (
  `id` smallint UNSIGNED NOT NULL,
  `module_id` smallint UNSIGNED NOT NULL,
  `slug` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text,
  `sort_order` tinyint NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `micro_topics`
--

INSERT INTO `micro_topics` (`id`, `module_id`, `slug`, `name`, `description`, `sort_order`, `is_active`) VALUES
(1, 1, 'linear-equations', 'Linear Equations', NULL, 1, 1),
(2, 1, 'systems-equations', 'Systems of Equations', NULL, 2, 1),
(3, 1, 'inequalities', 'Inequalities', NULL, 3, 1),
(4, 1, 'absolute-value', 'Absolute Value', NULL, 4, 1),
(5, 1, 'word-problems-alg', 'Word Problems', NULL, 5, 1),
(6, 2, 'quadratics', 'Quadratics', NULL, 1, 1),
(7, 2, 'polynomials', 'Polynomials', NULL, 2, 1),
(8, 2, 'functions', 'Functions', NULL, 3, 1),
(9, 2, 'radicals-exponents', 'Radicals & Exponents', NULL, 4, 1),
(10, 2, 'rational-expressions', 'Rational Expressions', NULL, 5, 1),
(11, 3, 'ratios-proportions', 'Ratios & Proportions', NULL, 1, 1),
(12, 3, 'percentages', 'Percentages', NULL, 2, 1),
(13, 3, 'statistics', 'Statistics', NULL, 3, 1),
(14, 3, 'probability', 'Probability', NULL, 4, 1),
(15, 3, 'scatterplots', 'Scatterplots', NULL, 5, 1),
(16, 4, 'geometry-basics', 'Geometry Basics', NULL, 1, 1),
(17, 4, 'circles', 'Circles', NULL, 2, 1),
(18, 4, 'triangles', 'Triangles', NULL, 3, 1),
(19, 4, 'trigonometry', 'Trigonometry', NULL, 4, 1),
(20, 4, 'complex-numbers', 'Complex Numbers', NULL, 5, 1),
(21, 5, 'main-idea', 'Main Idea', NULL, 1, 1),
(22, 5, 'details-evidence', 'Details & Evidence', NULL, 2, 1),
(23, 5, 'inferences', 'Inferences', NULL, 3, 1),
(24, 5, 'quantitative-info', 'Quantitative Info', NULL, 4, 1),
(25, 6, 'words-in-context', 'Words in Context', NULL, 1, 1),
(26, 6, 'text-structure', 'Text Structure', NULL, 2, 1),
(27, 6, 'cross-text', 'Cross-Text Connections', NULL, 3, 1),
(28, 7, 'rhetorical-synthesis', 'Rhetorical Synthesis', NULL, 1, 1),
(29, 7, 'transitions', 'Transitions', NULL, 2, 1),
(30, 7, 'argumentation', 'Argumentation', NULL, 3, 1),
(31, 8, 'boundaries', 'Sentence Boundaries', NULL, 1, 1),
(32, 8, 'form-structure-sense', 'Form, Structure & Sense', NULL, 2, 1),
(33, 8, 'agreement', 'Agreement', NULL, 3, 1),
(34, 8, 'punctuation', 'Punctuation', NULL, 4, 1);

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `id` smallint UNSIGNED NOT NULL,
  `subject_id` tinyint UNSIGNED NOT NULL,
  `slug` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text,
  `sort_order` tinyint NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id`, `subject_id`, `slug`, `name`, `description`, `sort_order`, `is_active`) VALUES
(1, 1, 'heart-of-algebra', 'Heart of Algebra', NULL, 1, 1),
(2, 1, 'passport-advanced-math', 'Passport to Advanced Math', NULL, 2, 1),
(3, 1, 'problem-solving-data', 'Problem Solving & Data Analysis', NULL, 3, 1),
(4, 1, 'additional-topics', 'Additional Topics in Math', NULL, 4, 1),
(5, 2, 'information-ideas', 'Information & Ideas', NULL, 1, 1),
(6, 2, 'craft-structure', 'Craft & Structure', NULL, 2, 1),
(7, 2, 'expression-of-ideas', 'Expression of Ideas', NULL, 3, 1),
(8, 2, 'standard-english', 'Standard English Conventions', NULL, 4, 1);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `token_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`id`, `user_id`, `token_hash`, `expires_at`, `used`, `created_at`) VALUES
(1, 24, '46736a6656120ba3612efb391c5c440bc7c87f87dacc66f44ae8cdadf146614f', '2026-03-07 15:18:57', 1, '2026-03-07 14:18:57'),
(2, 24, 'd0b51c83aa756e5f3964d21a9ea9fc37f0893211dfc36f09801e069b72058050', '2026-03-07 15:26:02', 0, '2026-03-07 14:26:02');

-- --------------------------------------------------------

--
-- Table structure for table `practice_tests`
--

CREATE TABLE `practice_tests` (
  `id` int UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `instructions` text,
  `section` enum('full','math','reading_writing') NOT NULL DEFAULT 'full',
  `type` enum('full_length','mini','topic','timed') NOT NULL DEFAULT 'full_length',
  `total_time` smallint NOT NULL DEFAULT '3600',
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `avg_score` float DEFAULT NULL,
  `best_score` float DEFAULT NULL,
  `total_attempts` int UNSIGNED NOT NULL DEFAULT '0',
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `practice_tests`
--

INSERT INTO `practice_tests` (`id`, `title`, `description`, `instructions`, `section`, `type`, `total_time`, `is_published`, `avg_score`, `best_score`, `total_attempts`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'sdfdsfsdfesdfdsffsdf', '', '', 'full', 'full_length', 8040, 1, NULL, NULL, 0, NULL, '2026-03-02 14:45:06', '2026-03-02 14:45:06'),
(2, 'sdfdsfsdfesdfdsffsdf', '', '', 'full', 'full_length', 8040, 1, NULL, NULL, 0, NULL, '2026-03-02 16:47:16', '2026-03-02 16:47:16'),
(3, 'sdfdsfsdfesdfdsffsdf', '', '', 'full', 'full_length', 8040, 1, NULL, NULL, 0, NULL, '2026-03-02 16:47:25', '2026-03-02 16:47:25'),
(4, 'sdfdsfsdfesdfdsffsdf', '', '', 'full', 'full_length', 8040, 1, NULL, NULL, 0, NULL, '2026-03-02 17:19:54', '2026-03-02 17:19:54'),
(5, 'sdfdsfsdfesdfdsffsdf', '', '', 'full', 'full_length', 8040, 1, NULL, NULL, 0, NULL, '2026-03-02 17:23:33', '2026-03-02 17:23:33'),
(6, 'sdfdsfsdfesdfdsffsdf', '', '', 'full', 'full_length', 8040, 1, NULL, NULL, 0, NULL, '2026-03-02 17:23:48', '2026-03-02 17:23:48');

-- --------------------------------------------------------

--
-- Table structure for table `practice_test_answers`
--

CREATE TABLE `practice_test_answers` (
  `id` int UNSIGNED NOT NULL,
  `attempt_id` int UNSIGNED NOT NULL,
  `question_id` int UNSIGNED NOT NULL,
  `section_id` int UNSIGNED NOT NULL,
  `given_answer` varchar(50) DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `is_flagged` tinyint(1) NOT NULL DEFAULT '0',
  `time_spent` smallint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `practice_test_attempts`
--

CREATE TABLE `practice_test_attempts` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `test_id` int UNSIGNED NOT NULL,
  `status` enum('in_progress','submitted','abandoned') NOT NULL DEFAULT 'in_progress',
  `total_score` smallint DEFAULT NULL,
  `math_score` smallint DEFAULT NULL,
  `rw_score` smallint DEFAULT NULL,
  `percentile` tinyint DEFAULT NULL,
  `predicted_sat` smallint DEFAULT NULL,
  `time_taken` smallint DEFAULT NULL,
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submitted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `practice_test_results`
--

CREATE TABLE `practice_test_results` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `subject` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` int UNSIGNED NOT NULL DEFAULT '0',
  `total_qs` int UNSIGNED NOT NULL DEFAULT '0',
  `correct_qs` int UNSIGNED NOT NULL DEFAULT '0',
  `taken_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `practice_test_sections`
--

CREATE TABLE `practice_test_sections` (
  `id` int UNSIGNED NOT NULL,
  `test_id` int UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `time_limit` smallint NOT NULL,
  `sort_order` tinyint NOT NULL DEFAULT '0',
  `subject` enum('reading_writing','math') NOT NULL DEFAULT 'math',
  `module` tinyint NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `practice_test_sections`
--

INSERT INTO `practice_test_sections` (`id`, `test_id`, `title`, `time_limit`, `sort_order`, `subject`, `module`) VALUES
(2, 5, 'Reading & Writing — Module 1', 1920, 1, 'reading_writing', 1),
(3, 5, 'Reading & Writing — Module 2', 1920, 2, 'reading_writing', 2),
(4, 5, 'Math — Module 1', 2100, 3, 'math', 1),
(5, 5, 'Math — Module 2', 2100, 4, 'math', 2),
(6, 6, 'Reading & Writing — Module 1', 1920, 1, 'reading_writing', 1),
(7, 6, 'Reading & Writing — Module 2', 1920, 2, 'reading_writing', 2),
(8, 6, 'Math — Module 1', 2100, 3, 'math', 1),
(9, 6, 'Math — Module 2', 2100, 4, 'math', 2);

-- --------------------------------------------------------

--
-- Table structure for table `practice_test_section_questions`
--

CREATE TABLE `practice_test_section_questions` (
  `id` int UNSIGNED NOT NULL,
  `section_id` int UNSIGNED NOT NULL,
  `question_id` int UNSIGNED NOT NULL,
  `sort_order` smallint NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` int UNSIGNED NOT NULL,
  `quiz_id` int NOT NULL DEFAULT '0',
  `micro_topic_id` smallint UNSIGNED DEFAULT NULL,
  `module_id` smallint UNSIGNED DEFAULT NULL,
  `subject_id` tinyint UNSIGNED DEFAULT NULL,
  `type` enum('mcq','grid_in','true_false','multi_select') NOT NULL DEFAULT 'mcq',
  `stem` longtext NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `choice_a` text,
  `choice_b` text,
  `choice_c` text,
  `choice_d` text,
  `correct_answer` varchar(10) NOT NULL,
  `explanation` longtext,
  `hint` text,
  `difficulty` tinyint NOT NULL DEFAULT '2',
  `tags` json DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `is_ai_generated` tinyint(1) NOT NULL DEFAULT '0',
  `ai_review_status` enum('pending','approved','rejected') DEFAULT NULL,
  `irt_difficulty` float DEFAULT NULL,
  `irt_discrimination` float DEFAULT NULL,
  `irt_guessing` float DEFAULT NULL,
  `irt_calibrated` tinyint(1) NOT NULL DEFAULT '0',
  `total_attempts` int UNSIGNED NOT NULL DEFAULT '0',
  `total_correct` int UNSIGNED NOT NULL DEFAULT '0',
  `avg_time_secs` float DEFAULT NULL,
  `hint_usage_count` int UNSIGNED NOT NULL DEFAULT '0',
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `order_index` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `id` int UNSIGNED NOT NULL,
  `lesson_id` int UNSIGNED DEFAULT NULL,
  `module_id` smallint UNSIGNED DEFAULT NULL,
  `subject_id` tinyint UNSIGNED DEFAULT NULL,
  `slug` varchar(150) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `time_limit` smallint DEFAULT NULL,
  `timer_type` enum('per_quiz','per_question','none') NOT NULL DEFAULT 'per_quiz',
  `pass_threshold` tinyint NOT NULL DEFAULT '70',
  `hints_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `flags_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `shuffle_questions` tinyint(1) NOT NULL DEFAULT '0',
  `shuffle_choices` tinyint(1) NOT NULL DEFAULT '0',
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `is_adaptive` tinyint(1) NOT NULL DEFAULT '0',
  `total_attempts` int UNSIGNED NOT NULL DEFAULT '0',
  `avg_score` float DEFAULT NULL,
  `pass_rate` float DEFAULT NULL,
  `median_time` float DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_attempts`
--

CREATE TABLE `quiz_attempts` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `quiz_id` int UNSIGNED NOT NULL,
  `status` enum('in_progress','submitted','abandoned') NOT NULL DEFAULT 'in_progress',
  `score_pct` float DEFAULT NULL,
  `score_raw` smallint DEFAULT NULL,
  `total_questions` smallint NOT NULL DEFAULT '0',
  `correct_count` smallint NOT NULL DEFAULT '0',
  `time_taken` smallint DEFAULT NULL,
  `xp_earned` smallint NOT NULL DEFAULT '0',
  `passed` tinyint(1) DEFAULT NULL,
  `rank_at_submit` smallint DEFAULT NULL,
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submitted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_attempt_answers`
--

CREATE TABLE `quiz_attempt_answers` (
  `id` int UNSIGNED NOT NULL,
  `attempt_id` int UNSIGNED NOT NULL,
  `question_id` int UNSIGNED NOT NULL,
  `given_answer` varchar(50) DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `is_flagged` tinyint(1) NOT NULL DEFAULT '0',
  `hint_used` tinyint(1) NOT NULL DEFAULT '0',
  `time_spent` smallint DEFAULT NULL,
  `feedback` enum('thumbs_up','thumbs_down') DEFAULT NULL,
  `answered_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `quiz_attempt_answers`
--

INSERT INTO `quiz_attempt_answers` (`id`, `attempt_id`, `question_id`, `given_answer`, `is_correct`, `is_flagged`, `hint_used`, `time_spent`, `feedback`, `answered_at`) VALUES
(109, 152, 1, 'B', 0, 0, 0, 3, NULL, '2026-03-03 10:05:15');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_progress`
--

CREATE TABLE `quiz_progress` (
  `id` int UNSIGNED NOT NULL,
  `attempt_id` int UNSIGNED NOT NULL,
  `state_json` longtext NOT NULL,
  `last_saved_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

CREATE TABLE `quiz_questions` (
  `id` int UNSIGNED NOT NULL,
  `quiz_id` int UNSIGNED NOT NULL,
  `question_id` int UNSIGNED NOT NULL,
  `sort_order` smallint NOT NULL DEFAULT '0',
  `points` tinyint NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sat_quizzes`
--

CREATE TABLE `sat_quizzes` (
  `id` int UNSIGNED NOT NULL,
  `lesson_slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Matches URL slug of the lesson. e.g. linear-equations',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Display title shown in topbar and lesson button',
  `instructions` text COLLATE utf8mb4_unicode_ci COMMENT 'Optional intro paragraph shown on quiz start screen',
  `section` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'math | reading-writing — for filtering in admin',
  `domain` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SAT domain. e.g. Heart of Algebra',
  `time_limit` smallint UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Total seconds. 0 = untimed. 600 = 10 min',
  `passing_score` tinyint UNSIGNED NOT NULL DEFAULT '70' COMMENT 'Pass threshold as a percentage (0–100)',
  `show_hints` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 = hint button visible, 0 = hidden',
  `shuffle_q` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = randomise question order per attempt',
  `shuffle_opts` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = randomise A/B/C/D order per attempt',
  `xp_reward` smallint UNSIGNED NOT NULL DEFAULT '50' COMMENT 'XP awarded when student passes for the first time',
  `status` enum('draft','published','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` int UNSIGNED DEFAULT NULL COMMENT 'FK to users.id — teacher who created this quiz',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One row per quiz. Linked to lessons via lesson_slug.';

--
-- Dumping data for table `sat_quizzes`
--

INSERT INTO `sat_quizzes` (`id`, `lesson_slug`, `title`, `instructions`, `section`, `domain`, `time_limit`, `passing_score`, `show_hints`, `shuffle_q`, `shuffle_opts`, `xp_reward`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'solving-linear-equations', 'Solving Linear Equations', 'Answer all questions. You can flag questions to review later.', 'math', 'algebra', 0, 60, 1, 0, 0, 50, 'published', NULL, '2026-02-23 17:41:05', '2026-02-28 20:17:23');

-- --------------------------------------------------------

--
-- Table structure for table `sat_quiz_answers`
--

CREATE TABLE `sat_quiz_answers` (
  `id` int UNSIGNED NOT NULL,
  `attempt_id` int UNSIGNED NOT NULL COMMENT 'FK → sat_quiz_attempts.id',
  `question_id` int UNSIGNED NOT NULL COMMENT 'FK → sat_quiz_questions.id',
  `user_id` int UNSIGNED NOT NULL COMMENT 'Denormalised for fast per-user queries',
  `quiz_id` int UNSIGNED NOT NULL COMMENT 'Denormalised for fast per-quiz queries',
  `user_answer` enum('a','b','c','d','skip') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'skip' COMMENT 'skip = unanswered / skipped',
  `correct_answer` enum('a','b','c','d') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Snapshot of correct answer at submission time',
  `is_correct` tinyint(1) NOT NULL DEFAULT '0',
  `time_spent` smallint UNSIGNED DEFAULT '0' COMMENT 'Seconds spent on this question',
  `hint_used` tinyint(1) NOT NULL DEFAULT '0',
  `flagged` tinyint(1) NOT NULL DEFAULT '0',
  `answered_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Per-question answers. Populated on submit. Powers review pages.';

-- --------------------------------------------------------

--
-- Table structure for table `sat_quiz_attempts`
--

CREATE TABLE `sat_quiz_attempts` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL COMMENT 'FK → users.id',
  `quiz_id` int UNSIGNED NOT NULL COMMENT 'FK → sat_quizzes.id',
  `status` enum('in_progress','completed','abandoned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_progress',
  `score` decimal(5,2) DEFAULT NULL COMMENT 'Score % 0–100. Set on completion.',
  `correct` smallint UNSIGNED DEFAULT '0' COMMENT 'Number of correct answers',
  `incorrect` smallint UNSIGNED DEFAULT '0',
  `skipped` smallint UNSIGNED DEFAULT '0',
  `total` smallint UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Total questions in this attempt',
  `time_spent` int UNSIGNED DEFAULT '0' COMMENT 'Seconds taken. Updated on completion.',
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `progress_json` json DEFAULT NULL COMMENT '{ "questionId": "b", … } — current answer state',
  `flagged_json` json DEFAULT NULL COMMENT '[42, 57, …] — flagged question IDs',
  `current_q` smallint UNSIGNED DEFAULT '1' COMMENT 'Last active question index — used for resume',
  `xp_awarded` smallint UNSIGNED NOT NULL DEFAULT '0',
  `attempt_number` tinyint UNSIGNED NOT NULL DEFAULT '1' COMMENT 'Increments on each retry',
  `passed` tinyint(1) DEFAULT NULL COMMENT 'NULL until completed, then 1/0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One row per student attempt. progress_json enables resume.';

--
-- Dumping data for table `sat_quiz_attempts`
--

INSERT INTO `sat_quiz_attempts` (`id`, `user_id`, `quiz_id`, `status`, `score`, `correct`, `incorrect`, `skipped`, `total`, `time_spent`, `started_at`, `completed_at`, `progress_json`, `flagged_json`, `current_q`, `xp_awarded`, `attempt_number`, `passed`, `created_at`, `updated_at`) VALUES
(19, 5, 1, 'abandoned', NULL, 0, 0, 0, 0, 0, '2026-02-23 20:07:32', NULL, NULL, NULL, 1, 0, 19, NULL, '2026-02-23 20:07:32', '2026-02-23 20:07:32'),
(20, 5, 1, 'abandoned', NULL, 0, 0, 0, 0, 0, '2026-02-23 20:07:32', NULL, NULL, NULL, 1, 0, 20, NULL, '2026-02-23 20:07:32', '2026-02-23 20:07:32'),
(21, 5, 1, 'abandoned', NULL, 0, 0, 0, 0, 0, '2026-02-23 20:07:32', NULL, NULL, NULL, 1, 0, 21, NULL, '2026-02-23 20:07:32', '2026-02-23 20:09:26'),
(22, 5, 1, 'abandoned', NULL, 0, 0, 0, 0, 0, '2026-02-23 20:09:26', NULL, NULL, NULL, 1, 0, 22, NULL, '2026-02-23 20:09:26', '2026-02-23 20:09:58'),
(23, 5, 1, 'abandoned', NULL, 0, 0, 0, 0, 0, '2026-02-23 20:09:58', NULL, NULL, NULL, 1, 0, 23, NULL, '2026-02-23 20:09:58', '2026-02-23 20:09:59'),
(24, 5, 1, 'abandoned', NULL, 0, 0, 0, 0, 0, '2026-02-23 20:09:59', NULL, NULL, NULL, 1, 0, 24, NULL, '2026-02-23 20:09:59', '2026-02-23 20:10:09'),
(25, 5, 1, 'abandoned', NULL, 0, 0, 0, 0, 0, '2026-02-23 20:10:09', NULL, NULL, NULL, 1, 0, 25, NULL, '2026-02-23 20:10:09', '2026-02-23 22:03:09'),
(152, 10, 1, 'in_progress', NULL, 0, 1, 0, 5, 0, '2026-03-03 10:05:11', NULL, '{\"answers\": [{\"key\": \"B\", \"q_id\": 1, \"is_correct\": false, \"time_spent\": 3, \"correct_key\": \"C\", \"explanation\": \"Subtract 6 from both sides: 2x = 8. Then divide by 2: x = 4.\", \"explanation_html\": \"<p>Subtract 6 from both sides: 2x = 8. Then divide by 2: x = 4.</p>\", \"explanation_images\": [\"/uploads/explanations/20260228_21fab4299f870c1ece6c.png\"]}], \"flagged\": []}', '[]', 2, 0, 1, NULL, '2026-03-03 10:05:11', '2026-03-03 10:05:38');

-- --------------------------------------------------------

--
-- Table structure for table `sat_quiz_progress`
--

CREATE TABLE `sat_quiz_progress` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `quiz_id` int UNSIGNED NOT NULL,
  `attempt_id` int UNSIGNED NOT NULL,
  `current_question` smallint UNSIGNED NOT NULL DEFAULT '0',
  `answered_questions` json DEFAULT NULL,
  `flagged_questions` json DEFAULT NULL,
  `progress_data` json DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sat_quiz_questions`
--

CREATE TABLE `sat_quiz_questions` (
  `id` int UNSIGNED NOT NULL,
  `quiz_id` int UNSIGNED NOT NULL COMMENT 'FK → sat_quizzes.id',
  `position` smallint UNSIGNED NOT NULL DEFAULT '1' COMMENT 'Display order within the quiz (1, 2, 3…)',
  `stem` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Question text. Supports LaTeX: wrap in $…$ or $$…$$',
  `type` enum('mcq','grid_in') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mcq',
  `option_a` text COLLATE utf8mb4_unicode_ci,
  `option_b` text COLLATE utf8mb4_unicode_ci,
  `option_c` text COLLATE utf8mb4_unicode_ci,
  `option_d` text COLLATE utf8mb4_unicode_ci,
  `correct_answer` enum('a','b','c','d') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'The correct option letter (lowercase)',
  `explanation` text COLLATE utf8mb4_unicode_ci COMMENT 'Step-by-step explanation revealed after answering',
  `explanation_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `explanation_image` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `hint` text COLLATE utf8mb4_unicode_ci COMMENT 'Partial hint shown when student clicks Hint button',
  `difficulty` enum('easy','medium','hard') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `domain` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SAT domain. e.g. Heart of Algebra',
  `skill` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Micro-skill. e.g. Solving linear equations for x',
  `points` tinyint UNSIGNED NOT NULL DEFAULT '1' COMMENT 'Points awarded for a correct answer',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One row per question. correct_answer never exposed to browser.';

--
-- Dumping data for table `sat_quiz_questions`
--

INSERT INTO `sat_quiz_questions` (`id`, `quiz_id`, `position`, `stem`, `type`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `explanation`, `explanation_html`, `explanation_image`, `hint`, `difficulty`, `domain`, `skill`, `points`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Solve for x: 2x + 6 = 14', 'mcq', 'x = 2', 'x = 3', 'x = 4', 'x = 10', 'c', 'Subtract 6 from both sides: 2x = 8. Then divide by 2: x = 4.', '<p>Subtract 6 from both sides: 2x = 8. Then divide by 2: x = 4.</p>', '/uploads/explanations/20260228_21fab4299f870c1ece6c.png', '', 'easy', NULL, NULL, 1, '2026-02-23 17:44:47', '2026-02-28 17:38:16'),
(2, 1, 2, 'Solve for x: 5x − 4 = 3x + 10', 'mcq', 'x = 3', 'x = 5', 'x = 7', 'x = 9', 'c', 'Move x terms to one side: 2x = 14. Divide by 2: x = 7.', '<p>Move x terms to one side: 2x = 14. Divide by 2: x = 7.</p>', '/uploads/explanations/20260228_05ec5089d39460bad672.png', '', 'medium', NULL, NULL, 1, '2026-02-23 17:44:47', '2026-02-28 20:10:35'),
(3, 1, 3, 'Solve for x: 3(x − 2) = 12', 'mcq', 'x = 2', 'x = 4', 'x = 6', 'x = 8', 'c', 'Distribute: 3x − 6 = 12. Add 6: 3x = 18. Divide by 3: x = 6.', '<p>Distribute: 3x − 6 = 12. Add 6: 3x = 18. Divide by 3: x = 6.</p>', '/uploads/explanations/20260228_5aa6e7d7451dfd7724bd.png', '', 'medium', NULL, NULL, 1, '2026-02-23 17:44:47', '2026-02-28 20:17:23'),
(4, 1, 4, 'Solve for x: x/4 + 3 = 7', 'mcq', 'x = 8', 'x = 12', 'x = 16', 'x = 28', 'c', 'Subtract 3: x/4 = 4. Multiply by 4: x = 16.', '<p>Subtract 3: x/4 = 4. Multiply by 4: x = 16.</p>', '', '', 'medium', NULL, NULL, 1, '2026-02-23 17:44:47', '2026-02-28 17:24:06'),
(5, 1, 5, 'If 4x + 2 = 3x + 9, what is the value of x?', 'mcq', 'x = 5', 'x = 6', 'x = 7', 'x = 11', 'c', 'Subtract 3x from both sides: x + 2 = 9. Subtract 2: x = 7.', '<p>Subtract 3x from both sides: x + 2 = 9. Subtract 2: x = 7.</p>', '/uploads/explanations/20260228_a692bed7d7652ae92482.png', '', 'hard', NULL, NULL, 1, '2026-02-23 17:44:47', '2026-02-28 20:16:40');

-- --------------------------------------------------------

--
-- Table structure for table `sat_quiz_question_stats`
--

CREATE TABLE `sat_quiz_question_stats` (
  `question_id` int UNSIGNED NOT NULL,
  `quiz_id` int UNSIGNED NOT NULL,
  `total_attempts` int UNSIGNED NOT NULL DEFAULT '0',
  `correct_count` int UNSIGNED NOT NULL DEFAULT '0',
  `hint_count` int UNSIGNED NOT NULL DEFAULT '0',
  `avg_time_seconds` decimal(8,2) DEFAULT NULL COMMENT 'Average seconds per question',
  `accuracy_rate` decimal(5,2) DEFAULT NULL COMMENT 'correct_count / total_attempts * 100',
  `last_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Aggregated per-question stats. Rebuilt after each submission.';

-- --------------------------------------------------------

--
-- Table structure for table `schedule_tasks`
--

CREATE TABLE `schedule_tasks` (
  `id` int UNSIGNED NOT NULL,
  `schedule_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `task_date` date NOT NULL,
  `task_type` enum('lesson','quiz','practice_test','review','spaced_review','rest') NOT NULL,
  `lesson_id` int UNSIGNED DEFAULT NULL,
  `quiz_id` int UNSIGNED DEFAULT NULL,
  `test_id` int UNSIGNED DEFAULT NULL,
  `micro_topic_id` smallint UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `estimated_mins` tinyint NOT NULL DEFAULT '20',
  `sort_order` tinyint NOT NULL DEFAULT '0',
  `is_completed` tinyint(1) NOT NULL DEFAULT '0',
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `payload` longtext,
  `last_activity` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `session_enrollments`
--

CREATE TABLE `session_enrollments` (
  `id` int NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `session_id` int NOT NULL,
  `attended` tinyint(1) DEFAULT '0',
  `enrolled_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `spaced_review_history`
--

CREATE TABLE `spaced_review_history` (
  `id` int UNSIGNED NOT NULL,
  `item_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `given_answer` varchar(50) DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `quality_rating` tinyint NOT NULL,
  `reviewed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `spaced_review_items`
--

CREATE TABLE `spaced_review_items` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `question_id` int UNSIGNED NOT NULL,
  `repetition` tinyint NOT NULL DEFAULT '0',
  `ease_factor` float NOT NULL DEFAULT '2.5',
  `interval_days` smallint NOT NULL DEFAULT '1',
  `next_review_date` date NOT NULL,
  `last_review_date` date DEFAULT NULL,
  `last_quality` tinyint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_performance`
--

CREATE TABLE `student_performance` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `quiz_id` int UNSIGNED NOT NULL,
  `total_attempts` int UNSIGNED NOT NULL DEFAULT '1',
  `best_score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `average_score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `improvement_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `mastery_level` enum('beginner','developing','proficient','advanced','expert') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'beginner',
  `last_attempt_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_profiles`
--

CREATE TABLE `student_profiles` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `test_date` date DEFAULT NULL,
  `target_score` smallint NOT NULL DEFAULT '1200',
  `current_score` smallint DEFAULT NULL,
  `predicted_score` smallint DEFAULT NULL,
  `predicted_range_low` smallint DEFAULT NULL,
  `predicted_range_high` smallint DEFAULT NULL,
  `weekly_study_hours` tinyint NOT NULL DEFAULT '10',
  `grade` tinyint DEFAULT NULL,
  `school` varchar(255) DEFAULT NULL,
  `onboarding_step` tinyint NOT NULL DEFAULT '0',
  `onboarding_complete` tinyint(1) NOT NULL DEFAULT '0',
  `xp_total` int UNSIGNED NOT NULL DEFAULT '0',
  `xp_this_week` int UNSIGNED NOT NULL DEFAULT '0',
  `streak_current` smallint NOT NULL DEFAULT '0',
  `streak_longest` smallint NOT NULL DEFAULT '0',
  `streak_last_date` date DEFAULT NULL,
  `league` enum('bronze','silver','gold','diamond') NOT NULL DEFAULT 'bronze',
  `league_rank` smallint DEFAULT NULL,
  `math_theta` float NOT NULL DEFAULT '0',
  `rw_theta` float NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `student_profiles`
--

INSERT INTO `student_profiles` (`id`, `user_id`, `test_date`, `target_score`, `current_score`, `predicted_score`, `predicted_range_low`, `predicted_range_high`, `weekly_study_hours`, `grade`, `school`, `onboarding_step`, `onboarding_complete`, `xp_total`, `xp_this_week`, `streak_current`, `streak_longest`, `streak_last_date`, `league`, `league_rank`, `math_theta`, `rw_theta`, `created_at`, `updated_at`) VALUES
(6, 7, NULL, 1200, NULL, NULL, NULL, NULL, 10, NULL, NULL, 1, 0, 0, 0, 0, 0, NULL, 'bronze', NULL, 0, 0, '2026-02-26 01:20:49', '2026-02-26 01:20:49'),
(23, 24, NULL, 1200, NULL, NULL, NULL, NULL, 10, NULL, NULL, 4, 1, 0, 0, 0, 0, NULL, 'bronze', NULL, 0.6, -1.8, '2026-03-07 14:15:28', '2026-03-07 14:16:04');

-- --------------------------------------------------------

--
-- Table structure for table `student_skill_profiles`
--

CREATE TABLE `student_skill_profiles` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `micro_topic_id` int NOT NULL,
  `theta` decimal(6,3) NOT NULL DEFAULT '0.000',
  `attempts` smallint UNSIGNED NOT NULL DEFAULT '0',
  `correct` smallint UNSIGNED NOT NULL DEFAULT '0',
  `accuracy_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `study_schedules`
--

CREATE TABLE `study_schedules` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `generated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `test_date` date DEFAULT NULL,
  `weekly_hours` tinyint NOT NULL DEFAULT '10',
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `study_streaks`
--

CREATE TABLE `study_streaks` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `current_streak` int UNSIGNED NOT NULL DEFAULT '0',
  `longest_streak` int UNSIGNED NOT NULL DEFAULT '0',
  `last_active` date DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` tinyint UNSIGNED NOT NULL,
  `slug` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(7) DEFAULT NULL,
  `sort_order` tinyint NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `slug`, `name`, `icon`, `color`, `sort_order`) VALUES
(1, 'math', 'Math', 'calculator', NULL, 1),
(2, 'reading-writing', 'Reading & Writing', 'book-open', NULL, 2);

-- --------------------------------------------------------

--
-- Table structure for table `tutoring_sessions`
--

CREATE TABLE `tutoring_sessions` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `zoom_link` varchar(500) DEFAULT NULL,
  `scheduled_at` datetime NOT NULL,
  `duration_mins` int NOT NULL DEFAULT '60',
  `max_students` int NOT NULL DEFAULT '30',
  `subject` varchar(50) DEFAULT 'general',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `uuid` char(36) NOT NULL DEFAULT (uuid()),
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `role` enum('student','admin','parent') NOT NULL DEFAULT 'student',
  `avatar_url` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_suspended` tinyint(1) NOT NULL DEFAULT '0',
  `email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `dark_mode` tinyint(1) NOT NULL DEFAULT '0',
  `sound_effects` tinyint(1) NOT NULL DEFAULT '1',
  `notification_prefs` json DEFAULT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'America/New_York',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `onboarding_complete` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `uuid`, `email`, `password_hash`, `first_name`, `last_name`, `role`, `avatar_url`, `is_active`, `is_suspended`, `email_verified`, `dark_mode`, `sound_effects`, `notification_prefs`, `timezone`, `last_login_at`, `created_at`, `updated_at`, `onboarding_complete`) VALUES
(7, '36d6ef74-9813-457d-9975-35e2f25a5342', 'abdulmaliktariqalfaraidi@gmail.com', '$2y$12$Di9x4VQD/j6gnH817QYp5ea8B86FPus41O4moA7wTGqG46UZEHJc6', 'Abdulmalik', 'Alfaraidi', 'student', NULL, 1, 0, 0, 0, 1, NULL, 'America/New_York', NULL, '2026-02-26 01:20:49', '2026-03-02 20:24:29', 1),
(24, '4bc0e5a7-99c0-4c75-a68e-ac97892b6b57', 'rashkoarnaudovv@gmail.com', '$2y$12$j86vkQMkJIGUR5Ct8yhaT.O0/5gtwW1CxtmW7Bjwsoju8N3uLei.O', 'Rashko', 'Arnaudov', 'student', NULL, 1, 0, 0, 0, 1, NULL, 'America/New_York', NULL, '2026-03-07 14:15:27', '2026-03-07 14:20:10', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_achievements`
--

CREATE TABLE `user_achievements` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `achievement_id` smallint UNSIGNED NOT NULL,
  `unlocked_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_achievements`
--

INSERT INTO `user_achievements` (`id`, `user_id`, `achievement_id`, `unlocked_at`) VALUES
(9, 24, 1, '2026-03-07 14:16:04');

-- --------------------------------------------------------

--
-- Table structure for table `user_lesson_progress`
--

CREATE TABLE `user_lesson_progress` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `lesson_link` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT '0',
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_lesson_progress`
--

INSERT INTO `user_lesson_progress` (`id`, `user_id`, `lesson_link`, `completed`, `completed_at`) VALUES
(1, 5, '/math/solving-linear-equations', 1, '2026-03-01 12:24:26');

-- --------------------------------------------------------

--
-- Table structure for table `user_xp`
--

CREATE TABLE `user_xp` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `xp` int NOT NULL DEFAULT '0',
  `level` int NOT NULL DEFAULT '1',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_xp`
--

INSERT INTO `user_xp` (`id`, `user_id`, `xp`, `level`, `updated_at`) VALUES
(2, 5, 60, 1, '2026-03-01 16:02:47');

-- --------------------------------------------------------

--
-- Table structure for table `video_progress`
--

CREATE TABLE `video_progress` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `lesson_id` int UNSIGNED NOT NULL,
  `seconds_watched` int UNSIGNED NOT NULL DEFAULT '0',
  `percent_watched` float NOT NULL DEFAULT '0',
  `completed` tinyint(1) NOT NULL DEFAULT '0',
  `last_position` int UNSIGNED NOT NULL DEFAULT '0',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_quiz_performance_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_quiz_performance_summary` (
`quiz_id` int unsigned
,`title` varchar(255)
,`lesson_slug` varchar(120)
,`section` varchar(60)
,`domain` varchar(100)
,`status` enum('draft','published','archived')
,`total_attempts` bigint
,`completed_attempts` bigint
,`avg_score` decimal(6,2)
,`best_score` decimal(5,2)
,`unique_students` bigint
,`question_count` bigint
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_student_quiz_history`
-- (See below for the actual view)
--
CREATE TABLE `v_student_quiz_history` (
`attempt_id` int unsigned
,`user_id` int unsigned
,`quiz_id` int unsigned
,`quiz_title` varchar(255)
,`lesson_slug` varchar(120)
,`passing_score` tinyint unsigned
,`xp_reward` smallint unsigned
,`status` enum('in_progress','completed','abandoned')
,`score` decimal(5,2)
,`correct` smallint unsigned
,`incorrect` smallint unsigned
,`skipped` smallint unsigned
,`total` smallint unsigned
,`time_spent` int unsigned
,`xp_awarded` smallint unsigned
,`attempt_number` tinyint unsigned
,`passed` tinyint(1)
,`started_at` datetime
,`completed_at` datetime
);

-- --------------------------------------------------------

--
-- Table structure for table `writing_submissions`
--

CREATE TABLE `writing_submissions` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `essay_text` longtext NOT NULL,
  `word_count` smallint DEFAULT NULL,
  `score_ideas` tinyint DEFAULT NULL,
  `score_organization` tinyint DEFAULT NULL,
  `score_style` tinyint DEFAULT NULL,
  `score_conventions` tinyint DEFAULT NULL,
  `score_overall` float DEFAULT NULL,
  `annotations_json` longtext,
  `improvement_tips` text,
  `ai_tokens_used` smallint DEFAULT NULL,
  `status` enum('pending','processing','complete','error') NOT NULL DEFAULT 'pending',
  `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `xp_events`
--

CREATE TABLE `xp_events` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `xp_earned` smallint NOT NULL,
  `reference_id` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `achievements`
--
ALTER TABLE `achievements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `admin_accounts`
--
ALTER TABLE `admin_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `ai_tutor_conversations`
--
ALTER TABLE `ai_tutor_conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_updated` (`updated_at`);

--
-- Indexes for table `ai_tutor_messages`
--
ALTER TABLE `ai_tutor_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversation` (`conversation_id`);

--
-- Indexes for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_token` (`token_hash`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `category_performance`
--
ALTER TABLE `category_performance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_category` (`user_id`,`category`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `college_matches`
--
ALTER TABLE `college_matches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `diagnostic_attempts`
--
ALTER TABLE `diagnostic_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user` (`user_id`);

--
-- Indexes for table `explanation_feedback`
--
ALTER TABLE `explanation_feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_question` (`user_id`,`question_id`),
  ADD KEY `idx_question` (`question_id`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `micro_topic_id` (`micro_topic_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_module` (`module_id`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_published` (`is_published`);

--
-- Indexes for table `lesson_completions`
--
ALTER TABLE `lesson_completions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_lesson` (`user_id`,`lesson_id`),
  ADD KEY `lesson_id` (`lesson_id`);

--
-- Indexes for table `micro_topics`
--
ALTER TABLE `micro_topics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_module` (`module_id`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_subject` (`subject_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_token_hash` (`token_hash`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `practice_tests`
--
ALTER TABLE `practice_tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `practice_test_answers`
--
ALTER TABLE `practice_test_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_attempt_q` (`attempt_id`,`question_id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `practice_test_attempts`
--
ALTER TABLE `practice_test_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `test_id` (`test_id`),
  ADD KEY `idx_user_test` (`user_id`,`test_id`),
  ADD KEY `idx_submitted` (`submitted_at`);

--
-- Indexes for table `practice_test_results`
--
ALTER TABLE `practice_test_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_subject` (`user_id`,`subject`);

--
-- Indexes for table `practice_test_sections`
--
ALTER TABLE `practice_test_sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `test_id` (`test_id`);

--
-- Indexes for table `practice_test_section_questions`
--
ALTER TABLE `practice_test_section_questions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sec_q` (`section_id`,`question_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `module_id` (`module_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_micro_topic` (`micro_topic_id`),
  ADD KEY `idx_difficulty` (`difficulty`),
  ADD KEY `idx_published` (`is_published`),
  ADD KEY `idx_ai_review` (`ai_review_status`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `module_id` (`module_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_lesson` (`lesson_id`),
  ADD KEY `idx_published` (`is_published`);

--
-- Indexes for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quiz_id` (`quiz_id`),
  ADD KEY `idx_user_quiz` (`user_id`,`quiz_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_submitted` (`submitted_at`);

--
-- Indexes for table `quiz_attempt_answers`
--
ALTER TABLE `quiz_attempt_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_attempt_question` (`attempt_id`,`question_id`),
  ADD KEY `idx_attempt` (`attempt_id`),
  ADD KEY `fk_qaa_sat_question` (`question_id`);

--
-- Indexes for table `quiz_progress`
--
ALTER TABLE `quiz_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `attempt_id` (`attempt_id`);

--
-- Indexes for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_quiz_question` (`quiz_id`,`question_id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `idx_quiz` (`quiz_id`);

--
-- Indexes for table `sat_quizzes`
--
ALTER TABLE `sat_quizzes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lesson_slug` (`lesson_slug`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_section` (`section`),
  ADD KEY `idx_domain` (`domain`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `sat_quiz_answers`
--
ALTER TABLE `sat_quiz_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attempt_id` (`attempt_id`),
  ADD KEY `idx_question_id` (`question_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_quiz_id` (`quiz_id`),
  ADD KEY `idx_is_correct` (`is_correct`),
  ADD KEY `idx_user_quiz` (`user_id`,`quiz_id`);

--
-- Indexes for table `sat_quiz_attempts`
--
ALTER TABLE `sat_quiz_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_quiz` (`user_id`,`quiz_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_quiz_id` (`quiz_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_started_at` (`started_at`),
  ADD KEY `idx_user_status` (`user_id`,`status`);

--
-- Indexes for table `sat_quiz_progress`
--
ALTER TABLE `sat_quiz_progress`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sat_quiz_questions`
--
ALTER TABLE `sat_quiz_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quiz_id` (`quiz_id`),
  ADD KEY `idx_position` (`quiz_id`,`position`),
  ADD KEY `idx_difficulty` (`difficulty`);

--
-- Indexes for table `sat_quiz_question_stats`
--
ALTER TABLE `sat_quiz_question_stats`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `idx_quiz_id` (`quiz_id`),
  ADD KEY `idx_accuracy_rate` (`accuracy_rate`);

--
-- Indexes for table `schedule_tasks`
--
ALTER TABLE `schedule_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `lesson_id` (`lesson_id`),
  ADD KEY `quiz_id` (`quiz_id`),
  ADD KEY `test_id` (`test_id`),
  ADD KEY `micro_topic_id` (`micro_topic_id`),
  ADD KEY `idx_user_date` (`user_id`,`task_date`),
  ADD KEY `idx_completed` (`is_completed`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_last` (`last_activity`);

--
-- Indexes for table `session_enrollments`
--
ALTER TABLE `session_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`user_id`,`session_id`);

--
-- Indexes for table `spaced_review_history`
--
ALTER TABLE `spaced_review_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `idx_user_date` (`user_id`,`reviewed_at`);

--
-- Indexes for table `spaced_review_items`
--
ALTER TABLE `spaced_review_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_question` (`user_id`,`question_id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `idx_user_next` (`user_id`,`next_review_date`);

--
-- Indexes for table `student_performance`
--
ALTER TABLE `student_performance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_quiz` (`user_id`,`quiz_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_quiz_id` (`quiz_id`);

--
-- Indexes for table `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_league` (`league`),
  ADD KEY `idx_xp` (`xp_total` DESC);

--
-- Indexes for table `student_skill_profiles`
--
ALTER TABLE `student_skill_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_user_topic` (`user_id`,`micro_topic_id`),
  ADD KEY `idx_topic` (`micro_topic_id`);

--
-- Indexes for table `study_schedules`
--
ALTER TABLE `study_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `study_streaks`
--
ALTER TABLE `study_streaks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `tutoring_sessions`
--
ALTER TABLE `tutoring_sessions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `user_achievements`
--
ALTER TABLE `user_achievements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_ach` (`user_id`,`achievement_id`),
  ADD KEY `achievement_id` (`achievement_id`);

--
-- Indexes for table `user_lesson_progress`
--
ALTER TABLE `user_lesson_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_lesson` (`user_id`,`lesson_link`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `user_xp`
--
ALTER TABLE `user_xp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user` (`user_id`);

--
-- Indexes for table `video_progress`
--
ALTER TABLE `video_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_lesson` (`user_id`,`lesson_id`),
  ADD KEY `lesson_id` (`lesson_id`);

--
-- Indexes for table `writing_submissions`
--
ALTER TABLE `writing_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `xp_events`
--
ALTER TABLE `xp_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_date` (`user_id`,`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `achievements`
--
ALTER TABLE `achievements`
  MODIFY `id` smallint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_accounts`
--
ALTER TABLE `admin_accounts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ai_tutor_conversations`
--
ALTER TABLE `ai_tutor_conversations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ai_tutor_messages`
--
ALTER TABLE `ai_tutor_messages`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `category_performance`
--
ALTER TABLE `category_performance`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `college_matches`
--
ALTER TABLE `college_matches`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `diagnostic_attempts`
--
ALTER TABLE `diagnostic_attempts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `explanation_feedback`
--
ALTER TABLE `explanation_feedback`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lesson_completions`
--
ALTER TABLE `lesson_completions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `micro_topics`
--
ALTER TABLE `micro_topics`
  MODIFY `id` smallint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` smallint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `practice_tests`
--
ALTER TABLE `practice_tests`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `practice_test_answers`
--
ALTER TABLE `practice_test_answers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `practice_test_attempts`
--
ALTER TABLE `practice_test_attempts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `practice_test_results`
--
ALTER TABLE `practice_test_results`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `practice_test_sections`
--
ALTER TABLE `practice_test_sections`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `practice_test_section_questions`
--
ALTER TABLE `practice_test_section_questions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_attempt_answers`
--
ALTER TABLE `quiz_attempt_answers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT for table `quiz_progress`
--
ALTER TABLE `quiz_progress`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sat_quizzes`
--
ALTER TABLE `sat_quizzes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sat_quiz_answers`
--
ALTER TABLE `sat_quiz_answers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sat_quiz_attempts`
--
ALTER TABLE `sat_quiz_attempts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=153;

--
-- AUTO_INCREMENT for table `sat_quiz_progress`
--
ALTER TABLE `sat_quiz_progress`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sat_quiz_questions`
--
ALTER TABLE `sat_quiz_questions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `schedule_tasks`
--
ALTER TABLE `schedule_tasks`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `session_enrollments`
--
ALTER TABLE `session_enrollments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `spaced_review_history`
--
ALTER TABLE `spaced_review_history`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `spaced_review_items`
--
ALTER TABLE `spaced_review_items`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_performance`
--
ALTER TABLE `student_performance`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_profiles`
--
ALTER TABLE `student_profiles`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `student_skill_profiles`
--
ALTER TABLE `student_skill_profiles`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `study_schedules`
--
ALTER TABLE `study_schedules`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `study_streaks`
--
ALTER TABLE `study_streaks`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` tinyint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tutoring_sessions`
--
ALTER TABLE `tutoring_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `user_achievements`
--
ALTER TABLE `user_achievements`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `user_lesson_progress`
--
ALTER TABLE `user_lesson_progress`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_xp`
--
ALTER TABLE `user_xp`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `video_progress`
--
ALTER TABLE `video_progress`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `writing_submissions`
--
ALTER TABLE `writing_submissions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `xp_events`
--
ALTER TABLE `xp_events`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

-- --------------------------------------------------------

--
-- Structure for view `v_quiz_performance_summary`
--
DROP TABLE IF EXISTS `v_quiz_performance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`sfbdegrhor3p4`@`localhost` SQL SECURITY DEFINER VIEW `v_quiz_performance_summary`  AS SELECT `q`.`id` AS `quiz_id`, `q`.`title` AS `title`, `q`.`lesson_slug` AS `lesson_slug`, `q`.`section` AS `section`, `q`.`domain` AS `domain`, `q`.`status` AS `status`, count(distinct `a`.`id`) AS `total_attempts`, count(distinct (case when (`a`.`status` = 'completed') then `a`.`id` end)) AS `completed_attempts`, round(avg((case when (`a`.`status` = 'completed') then `a`.`score` end)),2) AS `avg_score`, round(max((case when (`a`.`status` = 'completed') then `a`.`score` end)),2) AS `best_score`, count(distinct `a`.`user_id`) AS `unique_students`, count(distinct `qq`.`id`) AS `question_count` FROM ((`sat_quizzes` `q` left join `sat_quiz_attempts` `a` on((`a`.`quiz_id` = `q`.`id`))) left join `sat_quiz_questions` `qq` on((`qq`.`quiz_id` = `q`.`id`))) GROUP BY `q`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_student_quiz_history`
--
DROP TABLE IF EXISTS `v_student_quiz_history`;

CREATE ALGORITHM=UNDEFINED DEFINER=`sfbdegrhor3p4`@`localhost` SQL SECURITY DEFINER VIEW `v_student_quiz_history`  AS SELECT `a`.`id` AS `attempt_id`, `a`.`user_id` AS `user_id`, `a`.`quiz_id` AS `quiz_id`, `q`.`title` AS `quiz_title`, `q`.`lesson_slug` AS `lesson_slug`, `q`.`passing_score` AS `passing_score`, `q`.`xp_reward` AS `xp_reward`, `a`.`status` AS `status`, `a`.`score` AS `score`, `a`.`correct` AS `correct`, `a`.`incorrect` AS `incorrect`, `a`.`skipped` AS `skipped`, `a`.`total` AS `total`, `a`.`time_spent` AS `time_spent`, `a`.`xp_awarded` AS `xp_awarded`, `a`.`attempt_number` AS `attempt_number`, `a`.`passed` AS `passed`, `a`.`started_at` AS `started_at`, `a`.`completed_at` AS `completed_at` FROM (`sat_quiz_attempts` `a` join `sat_quizzes` `q` on((`q`.`id` = `a`.`quiz_id`))) ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_accounts`
--
ALTER TABLE `admin_accounts`
  ADD CONSTRAINT `admin_accounts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ai_tutor_conversations`
--
ALTER TABLE `ai_tutor_conversations`
  ADD CONSTRAINT `ai_tutor_conversations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ai_tutor_conversations_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ai_tutor_messages`
--
ALTER TABLE `ai_tutor_messages`
  ADD CONSTRAINT `ai_tutor_messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `ai_tutor_conversations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD CONSTRAINT `auth_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lessons`
--
ALTER TABLE `lessons`
  ADD CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lessons_ibfk_2` FOREIGN KEY (`micro_topic_id`) REFERENCES `micro_topics` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `lessons_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lesson_completions`
--
ALTER TABLE `lesson_completions`
  ADD CONSTRAINT `lesson_completions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lesson_completions_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `micro_topics`
--
ALTER TABLE `micro_topics`
  ADD CONSTRAINT `micro_topics_ibfk_1` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `modules`
--
ALTER TABLE `modules`
  ADD CONSTRAINT `modules_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `practice_tests`
--
ALTER TABLE `practice_tests`
  ADD CONSTRAINT `practice_tests_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `practice_test_answers`
--
ALTER TABLE `practice_test_answers`
  ADD CONSTRAINT `practice_test_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `practice_test_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `practice_test_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `practice_test_answers_ibfk_3` FOREIGN KEY (`section_id`) REFERENCES `practice_test_sections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `practice_test_attempts`
--
ALTER TABLE `practice_test_attempts`
  ADD CONSTRAINT `practice_test_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `practice_test_attempts_ibfk_2` FOREIGN KEY (`test_id`) REFERENCES `practice_tests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `practice_test_sections`
--
ALTER TABLE `practice_test_sections`
  ADD CONSTRAINT `practice_test_sections_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `practice_tests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `practice_test_section_questions`
--
ALTER TABLE `practice_test_section_questions`
  ADD CONSTRAINT `practice_test_section_questions_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `practice_test_sections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `practice_test_section_questions_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`micro_topic_id`) REFERENCES `micro_topics` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `questions_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `questions_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `questions_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `quizzes_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `quizzes_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `quizzes_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD CONSTRAINT `quiz_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_attempts_ibfk_2` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_attempt_answers`
--
ALTER TABLE `quiz_attempt_answers`
  ADD CONSTRAINT `fk_qaa_sat_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `sat_quiz_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_qaa_sat_question` FOREIGN KEY (`question_id`) REFERENCES `sat_quiz_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_progress`
--
ALTER TABLE `quiz_progress`
  ADD CONSTRAINT `quiz_progress_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD CONSTRAINT `quiz_questions_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_questions_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sat_quiz_answers`
--
ALTER TABLE `sat_quiz_answers`
  ADD CONSTRAINT `fk_answers_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `sat_quiz_attempts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sat_quiz_questions`
--
ALTER TABLE `sat_quiz_questions`
  ADD CONSTRAINT `fk_questions_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `sat_quizzes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sat_quiz_question_stats`
--
ALTER TABLE `sat_quiz_question_stats`
  ADD CONSTRAINT `fk_qstats_question` FOREIGN KEY (`question_id`) REFERENCES `sat_quiz_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schedule_tasks`
--
ALTER TABLE `schedule_tasks`
  ADD CONSTRAINT `schedule_tasks_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `study_schedules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_tasks_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_tasks_ibfk_3` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `schedule_tasks_ibfk_4` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `schedule_tasks_ibfk_5` FOREIGN KEY (`test_id`) REFERENCES `practice_tests` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `schedule_tasks_ibfk_6` FOREIGN KEY (`micro_topic_id`) REFERENCES `micro_topics` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `spaced_review_history`
--
ALTER TABLE `spaced_review_history`
  ADD CONSTRAINT `spaced_review_history_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `spaced_review_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `spaced_review_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `spaced_review_items`
--
ALTER TABLE `spaced_review_items`
  ADD CONSTRAINT `spaced_review_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `spaced_review_items_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD CONSTRAINT `student_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_skill_profiles`
--
ALTER TABLE `student_skill_profiles`
  ADD CONSTRAINT `student_skill_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `study_schedules`
--
ALTER TABLE `study_schedules`
  ADD CONSTRAINT `study_schedules_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_achievements`
--
ALTER TABLE `user_achievements`
  ADD CONSTRAINT `user_achievements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_achievements_ibfk_2` FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `video_progress`
--
ALTER TABLE `video_progress`
  ADD CONSTRAINT `video_progress_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `video_progress_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `writing_submissions`
--
ALTER TABLE `writing_submissions`
  ADD CONSTRAINT `writing_submissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `xp_events`
--
ALTER TABLE `xp_events`
  ADD CONSTRAINT `xp_events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
