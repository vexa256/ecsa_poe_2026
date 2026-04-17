-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 17, 2026 at 12:53 AM
-- Server version: 8.0.45-0ubuntu0.24.04.1
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `poe_2026`
--

-- --------------------------------------------------------

--
-- Table structure for table `aggregated_submissions`
--

CREATE TABLE `aggregated_submissions` (
  `id` bigint UNSIGNED NOT NULL,
  `client_uuid` char(36) NOT NULL,
  `idempotency_key` char(64) DEFAULT NULL,
  `reference_data_version` varchar(40) NOT NULL,
  `server_received_at` datetime DEFAULT NULL,
  `country_code` varchar(10) NOT NULL,
  `province_code` varchar(30) DEFAULT NULL,
  `pheoc_code` varchar(30) DEFAULT NULL,
  `district_code` varchar(30) NOT NULL,
  `poe_code` varchar(40) NOT NULL,
  `submitted_by_user_id` bigint UNSIGNED NOT NULL,
  `period_start` datetime NOT NULL,
  `period_end` datetime NOT NULL,
  `total_screened` int UNSIGNED NOT NULL DEFAULT '0',
  `total_male` int UNSIGNED NOT NULL DEFAULT '0',
  `total_female` int UNSIGNED NOT NULL DEFAULT '0',
  `total_other` int UNSIGNED NOT NULL DEFAULT '0',
  `total_unknown_gender` int UNSIGNED NOT NULL DEFAULT '0',
  `total_symptomatic` int UNSIGNED NOT NULL DEFAULT '0',
  `total_asymptomatic` int UNSIGNED NOT NULL DEFAULT '0',
  `notes` varchar(255) DEFAULT NULL,
  `device_id` varchar(80) NOT NULL,
  `app_version` varchar(40) DEFAULT NULL,
  `platform` enum('ANDROID','IOS','WEB') NOT NULL DEFAULT 'ANDROID',
  `record_version` int UNSIGNED NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `sync_status` enum('UNSYNCED','SYNCED','FAILED') NOT NULL DEFAULT 'UNSYNCED',
  `synced_at` datetime DEFAULT NULL,
  `sync_attempt_count` int UNSIGNED NOT NULL DEFAULT '0',
  `last_sync_error` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alerts`
--

CREATE TABLE `alerts` (
  `id` bigint UNSIGNED NOT NULL,
  `client_uuid` char(36) NOT NULL,
  `idempotency_key` char(64) DEFAULT NULL,
  `reference_data_version` varchar(40) NOT NULL,
  `server_received_at` datetime DEFAULT NULL,
  `country_code` varchar(10) NOT NULL,
  `province_code` varchar(30) DEFAULT NULL,
  `pheoc_code` varchar(30) DEFAULT NULL,
  `district_code` varchar(30) NOT NULL,
  `poe_code` varchar(40) NOT NULL,
  `secondary_screening_id` bigint UNSIGNED NOT NULL,
  `generated_from` enum('RULE_BASED','OFFICER') NOT NULL DEFAULT 'RULE_BASED',
  `risk_level` enum('LOW','MEDIUM','HIGH','CRITICAL') NOT NULL DEFAULT 'HIGH',
  `alert_code` varchar(80) NOT NULL,
  `alert_title` varchar(150) NOT NULL,
  `alert_details` varchar(500) DEFAULT NULL,
  `routed_to_level` enum('DISTRICT','PHEOC','NATIONAL') NOT NULL DEFAULT 'DISTRICT',
  `ihr_tier` varchar(40) DEFAULT NULL COMMENT 'TIER_1_ALWAYS_NOTIFIABLE | TIER_2_ANNEX2 | NULL — derived from IHR escalation rules',
  `status` enum('OPEN','ACKNOWLEDGED','CLOSED') NOT NULL DEFAULT 'OPEN',
  `acknowledged_by_user_id` bigint UNSIGNED DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `device_id` varchar(80) NOT NULL,
  `app_version` varchar(40) DEFAULT NULL,
  `platform` enum('ANDROID','IOS','WEB') NOT NULL DEFAULT 'ANDROID',
  `record_version` int UNSIGNED NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `sync_status` enum('UNSYNCED','SYNCED','FAILED') NOT NULL DEFAULT 'UNSYNCED',
  `synced_at` datetime DEFAULT NULL,
  `sync_attempt_count` int UNSIGNED NOT NULL DEFAULT '0',
  `last_sync_error` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_03_23_162334_create_personal_access_tokens_table', 2);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint UNSIGNED NOT NULL,
  `client_uuid` char(36) NOT NULL,
  `idempotency_key` char(64) DEFAULT NULL,
  `reference_data_version` varchar(40) NOT NULL,
  `server_received_at` datetime DEFAULT NULL,
  `country_code` varchar(10) NOT NULL,
  `province_code` varchar(30) DEFAULT NULL,
  `pheoc_code` varchar(30) DEFAULT NULL,
  `district_code` varchar(30) NOT NULL,
  `poe_code` varchar(40) NOT NULL,
  `primary_screening_id` bigint UNSIGNED NOT NULL,
  `created_by_user_id` bigint UNSIGNED NOT NULL,
  `notification_type` enum('SECONDARY_REFERRAL','ALERT') NOT NULL DEFAULT 'SECONDARY_REFERRAL',
  `status` enum('OPEN','IN_PROGRESS','CLOSED') NOT NULL DEFAULT 'OPEN',
  `priority` enum('NORMAL','HIGH','CRITICAL') NOT NULL DEFAULT 'NORMAL',
  `reason_code` varchar(80) NOT NULL,
  `reason_text` varchar(255) DEFAULT NULL,
  `assigned_role_key` varchar(60) NOT NULL,
  `assigned_user_id` bigint UNSIGNED DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `device_id` varchar(80) NOT NULL,
  `app_version` varchar(40) DEFAULT NULL,
  `platform` enum('ANDROID','IOS','WEB') NOT NULL DEFAULT 'ANDROID',
  `record_version` int UNSIGNED NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `sync_status` enum('UNSYNCED','SYNCED','FAILED') NOT NULL DEFAULT 'UNSYNCED',
  `synced_at` datetime DEFAULT NULL,
  `sync_attempt_count` int UNSIGNED NOT NULL DEFAULT '0',
  `last_sync_error` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `client_uuid`, `idempotency_key`, `reference_data_version`, `server_received_at`, `country_code`, `province_code`, `pheoc_code`, `district_code`, `poe_code`, `primary_screening_id`, `created_by_user_id`, `notification_type`, `status`, `priority`, `reason_code`, `reason_text`, `assigned_role_key`, `assigned_user_id`, `opened_at`, `closed_at`, `device_id`, `app_version`, `platform`, `record_version`, `deleted_at`, `sync_status`, `synced_at`, `sync_attempt_count`, `last_sync_error`, `created_at`, `updated_at`) VALUES
(17, 'd04fb571-4ab4-4aa5-9290-4667713ca3a5', NULL, 'rda-2026-02-01', '2026-04-15 10:37:06', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 65, 2, 'SECONDARY_REFERRAL', 'CLOSED', 'NORMAL', 'PRIMARY_SYMPTOMS_DETECTED', 'Symptoms present. Gender: MALE. Temp: Not measured. Priority: NORMAL. Traveler: Timothy Ayebare. POE: UG-JIN-JIN-JIN-001. District: Jinja District. PHEOC: Jinja RPHEOC. Officer: Ayebare Timothy.', 'POE_SECONDARY', 2, '2026-04-15 13:03:52', '2026-04-16 22:22:03', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'WEB', 1, NULL, 'SYNCED', '2026-04-15 10:37:06', 0, NULL, '2026-04-15 10:37:06', '2026-04-16 22:22:03'),
(18, '7037c9f4-f463-461a-8795-0f05215fb7a5', NULL, 'rda-2026-02-01', '2026-04-15 10:38:35', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 72, 2, 'SECONDARY_REFERRAL', 'CLOSED', 'NORMAL', 'PRIMARY_SYMPTOMS_DETECTED', 'Symptoms present. Gender: MALE. Temp: Not measured. Priority: NORMAL. Traveler: [Not captured]. POE: UG-JIN-JIN-JIN-001. District: Jinja District. PHEOC: Jinja RPHEOC. Officer: Ayebare Timothy.', 'POE_SECONDARY', 2, '2026-04-15 10:39:03', '2026-04-15 20:41:34', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'WEB', 1, NULL, 'SYNCED', '2026-04-15 10:38:35', 0, NULL, '2026-04-15 10:38:35', '2026-04-15 20:41:34'),
(19, '1fd32b54-b083-4373-bd07-1f353fdd84a8', NULL, 'rda-2026-02-01', '2026-04-15 21:13:36', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 74, 2, 'SECONDARY_REFERRAL', 'CLOSED', 'NORMAL', 'PRIMARY_SYMPTOMS_DETECTED', 'Symptoms present. Gender: MALE. Temp: Not measured. Priority: NORMAL. Traveler: Ayebare Timothy. POE: UG-JIN-JIN-JIN-001. District: Jinja District. PHEOC: Jinja RPHEOC. Officer: Ayebare Timothy.', 'POE_SECONDARY', 2, '2026-04-15 21:13:52', '2026-04-15 21:16:24', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, NULL, 'SYNCED', '2026-04-15 21:13:36', 0, NULL, '2026-04-15 21:13:36', '2026-04-15 21:16:24'),
(20, '5005fe71-6fd9-416f-b75f-62a4497f72ce', NULL, 'rda-2026-02-01', '2026-04-15 21:17:59', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 75, 2, 'SECONDARY_REFERRAL', 'CLOSED', 'HIGH', 'PRIMARY_SYMPTOMS_DETECTED', 'Symptoms present. Gender: MALE. Temp: 38.0°C. Priority: HIGH. Traveler: bib andrew. POE: UG-JIN-JIN-JIN-001. District: Jinja District. PHEOC: Jinja RPHEOC. Officer: Ayebare Timothy.', 'POE_SECONDARY', 2, '2026-04-15 21:18:11', '2026-04-15 21:19:57', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, NULL, 'SYNCED', '2026-04-15 21:17:59', 0, NULL, '2026-04-15 21:17:59', '2026-04-15 21:19:57'),
(21, 'baf6f0af-49cf-4797-b05a-bcc8682ffcb9', NULL, 'rda-2026-02-01', '2026-04-15 21:37:34', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 77, 2, 'SECONDARY_REFERRAL', 'IN_PROGRESS', 'HIGH', 'PRIMARY_SYMPTOMS_DETECTED', 'Symptoms present. Gender: MALE. Temp: 38.0°C. Priority: HIGH. Traveler: BOB ANDREW. POE: UG-JIN-JIN-JIN-001. District: Jinja District. PHEOC: Jinja RPHEOC. Officer: Ayebare Timothy.', 'POE_SECONDARY', 2, '2026-04-15 21:37:41', NULL, 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, NULL, 'SYNCED', '2026-04-15 21:37:34', 0, NULL, '2026-04-15 21:37:34', '2026-04-15 21:37:41'),
(22, '77ee1680-e905-4b70-b22e-1858868a3917', NULL, 'rda-2026-02-01', '2026-04-15 22:17:39', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 81, 2, 'SECONDARY_REFERRAL', 'CLOSED', 'NORMAL', 'PRIMARY_SYMPTOMS_DETECTED', 'Symptoms present. Gender: MALE. Temp: 34.0°C. Priority: NORMAL. Traveler: this asdfdfd. POE: UG-JIN-JIN-JIN-001. District: Jinja District. PHEOC: Jinja RPHEOC. Officer: Ayebare Timothy.', 'POE_SECONDARY', 2, '2026-04-15 22:18:23', '2026-04-15 22:20:14', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, NULL, 'SYNCED', '2026-04-15 22:17:39', 0, NULL, '2026-04-15 22:17:39', '2026-04-15 22:20:14'),
(23, '61b02789-be81-44ff-9e21-58584f5e0620', NULL, 'rda-2026-02-01', '2026-04-16 08:22:42', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 84, 2, 'SECONDARY_REFERRAL', 'CLOSED', 'CRITICAL', 'PRIMARY_SYMPTOMS_DETECTED', 'Symptoms present. Gender: FEMALE. Temp: 40.0°C. Priority: CRITICAL. Traveler: Moreen. POE: UG-JIN-JIN-JIN-001. District: Jinja District. PHEOC: Jinja RPHEOC. Officer: Ayebare Timothy.', 'POE_SECONDARY', 2, '2026-04-16 08:23:56', '2026-04-16 08:27:15', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, NULL, 'SYNCED', '2026-04-16 08:22:42', 0, NULL, '2026-04-16 08:22:42', '2026-04-16 08:27:15'),
(24, '424ecd57-994a-4da5-9918-6effc6966287', NULL, 'rda-2026-02-01', '2026-04-16 08:35:00', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 86, 2, 'SECONDARY_REFERRAL', 'IN_PROGRESS', 'HIGH', 'PRIMARY_SYMPTOMS_DETECTED', 'Symptoms present. Gender: FEMALE. Temp: 38.0°C. Priority: HIGH. Traveler: PHILIP. POE: UG-JIN-JIN-JIN-001. District: Jinja District. PHEOC: Jinja RPHEOC. Officer: Ayebare Timothy.', 'POE_SECONDARY', 2, '2026-04-16 08:35:11', NULL, 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, NULL, 'SYNCED', '2026-04-16 08:35:00', 0, NULL, '2026-04-16 08:35:00', '2026-04-16 08:35:11');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint UNSIGNED NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `primary_screenings`
--

CREATE TABLE `primary_screenings` (
  `id` bigint UNSIGNED NOT NULL,
  `client_uuid` char(36) NOT NULL,
  `idempotency_key` char(64) DEFAULT NULL,
  `reference_data_version` varchar(40) NOT NULL,
  `server_received_at` datetime DEFAULT NULL,
  `country_code` varchar(10) NOT NULL,
  `province_code` varchar(30) DEFAULT NULL,
  `pheoc_code` varchar(30) DEFAULT NULL,
  `district_code` varchar(30) NOT NULL,
  `poe_code` varchar(40) NOT NULL,
  `captured_by_user_id` bigint UNSIGNED NOT NULL,
  `gender` enum('MALE','FEMALE','OTHER','UNKNOWN') NOT NULL,
  `traveler_direction` enum('ENTRY','EXIT','TRANSIT') DEFAULT NULL COMMENT 'IHR direction of travel at POE — NULL = not captured (pre-migration records)',
  `traveler_full_name` varchar(150) DEFAULT NULL,
  `temperature_value` decimal(5,2) DEFAULT NULL,
  `temperature_unit` enum('C','F') DEFAULT NULL,
  `symptoms_present` tinyint(1) NOT NULL,
  `captured_at` datetime NOT NULL,
  `captured_timezone` varchar(64) DEFAULT NULL,
  `device_id` varchar(80) NOT NULL,
  `app_version` varchar(40) DEFAULT NULL,
  `platform` enum('ANDROID','IOS','WEB') NOT NULL DEFAULT 'ANDROID',
  `referral_created` tinyint(1) NOT NULL DEFAULT '0',
  `record_version` int UNSIGNED NOT NULL DEFAULT '1',
  `record_status` enum('COMPLETED','VOIDED') NOT NULL DEFAULT 'COMPLETED',
  `void_reason` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `sync_status` enum('UNSYNCED','SYNCED','FAILED') NOT NULL DEFAULT 'UNSYNCED',
  `synced_at` datetime DEFAULT NULL,
  `sync_attempt_count` int UNSIGNED NOT NULL DEFAULT '0',
  `last_sync_error` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `primary_screenings`
--

INSERT INTO `primary_screenings` (`id`, `client_uuid`, `idempotency_key`, `reference_data_version`, `server_received_at`, `country_code`, `province_code`, `pheoc_code`, `district_code`, `poe_code`, `captured_by_user_id`, `gender`, `traveler_direction`, `traveler_full_name`, `temperature_value`, `temperature_unit`, `symptoms_present`, `captured_at`, `captured_timezone`, `device_id`, `app_version`, `platform`, `referral_created`, `record_version`, `record_status`, `void_reason`, `deleted_at`, `sync_status`, `synced_at`, `sync_attempt_count`, `last_sync_error`, `created_at`, `updated_at`) VALUES
(1, '3a225088-0203-4290-9f1e-406654ae5c88', NULL, 'rda-2026-02-01', '2026-03-24 20:23:59', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-24 20:23:58', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 1, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 20:23:59', 1, NULL, '2026-03-24 20:23:58', '2026-03-24 20:23:59'),
(2, '44219376-0890-4a50-8f45-1d124136012c', NULL, 'rda-2026-02-01', '2026-03-24 20:24:13', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-24 20:24:12', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 1, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 20:24:13', 1, NULL, '2026-03-24 20:24:12', '2026-03-24 20:24:13'),
(3, '799513ed-2a3d-4e9a-b330-36867495dd1c', NULL, 'rda-2026-02-01', '2026-03-24 21:21:26', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'FEMALE', NULL, NULL, NULL, NULL, 0, '2026-03-24 20:23:09', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 116, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 21:21:26', 60, NULL, '2026-03-24 20:23:09', '2026-03-24 21:21:26'),
(4, '80963886-4d85-4a1f-92f8-8f2ba71e1d4b', NULL, 'rda-2026-02-01', '2026-03-24 21:21:26', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-24 20:59:06', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 92, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 21:21:26', 47, NULL, '2026-03-24 20:59:06', '2026-03-24 21:21:26'),
(5, '654540e4-56b0-4613-9fb4-9dec77c0f581', NULL, 'rda-2026-02-01', '2026-03-24 21:21:33', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-24 21:21:31', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 21:21:33', 1, NULL, '2026-03-24 21:21:31', '2026-03-24 21:21:33'),
(6, '1dd4f9b0-0439-4f7e-849f-7104feab8607', NULL, 'rda-2026-02-01', '2026-03-24 21:21:45', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-24 21:21:43', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 21:21:45', 1, NULL, '2026-03-24 21:21:43', '2026-03-24 21:21:45'),
(7, '590ec6a6-5089-42f5-b903-fcf6919549c0', NULL, 'rda-2026-02-01', '2026-03-24 21:23:50', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-24 21:23:16', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 8, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 21:23:50', 7, NULL, '2026-03-24 21:23:16', '2026-03-24 21:23:50'),
(8, '6c108d96-590f-4911-b066-413c1aceae75', NULL, 'rda-2026-02-01', '2026-03-24 21:23:51', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-24 21:23:26', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 6, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 21:23:51', 5, NULL, '2026-03-24 21:23:26', '2026-03-24 21:23:51'),
(9, '46db5154-c8c3-4f47-98de-8bae56cccdba', NULL, 'rda-2026-02-01', '2026-03-24 21:23:51', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, 32.00, 'C', 0, '2026-03-24 21:23:34', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 4, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 21:23:51', 3, NULL, '2026-03-24 21:23:34', '2026-03-24 21:23:51'),
(10, 'a0f55518-f9f3-44e6-8ade-7ff2e72484a5', NULL, 'rda-2026-02-01', '2026-03-24 21:24:03', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-24 21:24:02', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 21:24:03', 1, NULL, '2026-03-24 21:24:02', '2026-03-24 21:24:03'),
(11, 'd0426643-d3a7-49f5-8ad4-75df073ace71', NULL, 'rda-2026-02-01', '2026-03-24 21:24:29', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-24 21:24:28', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 21:24:29', 1, NULL, '2026-03-24 21:24:28', '2026-03-24 21:24:29'),
(12, 'ccdb0d17-73b9-45a5-bc8e-e4356627ee8e', NULL, 'rda-2026-02-01', '2026-03-24 21:24:49', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-24 21:24:47', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 21:24:49', 1, NULL, '2026-03-24 21:24:47', '2026-03-24 21:24:49'),
(13, '0f5ff0d6-9ccf-422e-b30b-1abdeb008b7a', NULL, 'rda-2026-02-01', '2026-03-24 21:24:57', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-24 21:24:55', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 21:24:57', 1, NULL, '2026-03-24 21:24:55', '2026-03-24 21:24:57'),
(14, 'f8ef728a-79b3-4e01-baba-961902296c00', NULL, 'rda-2026-02-01', '2026-03-24 21:25:06', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 21:25:05', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 3, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 21:25:06', 1, NULL, '2026-03-24 21:25:05', '2026-03-24 21:25:06'),
(15, 'ab737462-8a75-4a93-a5ec-67ab1fe87043', NULL, 'rda-2026-02-01', '2026-03-24 21:34:44', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 21:34:42', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 3, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 21:34:44', 1, NULL, '2026-03-24 21:34:42', '2026-03-24 21:34:44'),
(16, '178238c1-71a8-4cec-a930-1ba4c7de43b2', NULL, 'rda-2026-02-01', '2026-03-24 21:41:36', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 21:41:35', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 3, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 21:41:36', 1, NULL, '2026-03-24 21:41:35', '2026-03-24 21:41:36'),
(17, '9091e974-245a-488d-ab7f-edec2db7e53d', NULL, 'rda-2026-02-01', '2026-03-24 21:42:34', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 21:42:33', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 3, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 21:42:34', 1, NULL, '2026-03-24 21:42:33', '2026-03-24 21:42:34'),
(18, '69cd814e-506f-4bd2-b466-5bea077cf259', NULL, 'rda-2026-02-01', '2026-03-24 22:01:51', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'FEMALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 22:01:49', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 3, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 22:01:51', 1, NULL, '2026-03-24 22:01:49', '2026-03-24 22:01:51'),
(19, 'c5abce73-bbe1-4a90-8f8b-9385d9def58a', NULL, 'rda-2026-02-01', '2026-03-24 22:02:10', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 22:02:09', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 3, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 22:02:10', 1, NULL, '2026-03-24 22:02:09', '2026-03-24 22:02:10'),
(20, 'c0d49249-d505-4d20-82c6-70ed8c1f20be', NULL, 'rda-2026-02-01', '2026-03-24 22:07:32', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 22:07:31', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 3, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 22:07:32', 1, NULL, '2026-03-24 22:07:31', '2026-03-24 22:07:32'),
(21, 'c686a09c-48d4-43d1-bc0a-e85d146facf9', NULL, 'rda-2026-02-01', '2026-03-24 22:11:08', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, 34.00, 'C', 1, '2026-03-24 22:11:06', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 3, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 22:11:08', 1, NULL, '2026-03-24 22:11:06', '2026-03-24 22:11:08'),
(22, '9bfb42d6-a11f-470e-995c-1ea6eb33e531', NULL, 'rda-2026-02-01', '2026-03-24 22:16:26', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 22:16:11', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 5, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 22:16:26', 3, NULL, '2026-03-24 22:16:11', '2026-03-24 22:16:26'),
(23, '3c5e863e-9afd-4ea6-bce8-4dac48a24857', NULL, 'rda-2026-02-01', '2026-03-24 23:49:00', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 23:38:04', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 23:49:00', 1, NULL, '2026-03-24 23:38:04', '2026-03-24 23:49:00'),
(24, 'b7376805-4b40-4c54-9943-84567e779426', NULL, 'rda-2026-02-01', '2026-03-24 23:49:00', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 23:38:18', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 23:49:00', 1, NULL, '2026-03-24 23:38:18', '2026-03-24 23:49:00'),
(25, 'db140acc-282d-4820-aed6-54e50d1f1186', NULL, 'rda-2026-02-01', '2026-03-24 23:49:01', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 23:38:21', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 23:49:01', 1, NULL, '2026-03-24 23:38:21', '2026-03-24 23:49:01'),
(26, '22ac482c-9a24-48d0-86cc-b38cf7acf843', NULL, 'rda-2026-02-01', '2026-03-24 23:49:01', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 23:38:22', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 23:49:01', 1, NULL, '2026-03-24 23:38:22', '2026-03-24 23:49:01'),
(27, '244a1a9b-7ebb-4fe7-8b1b-c3b6ac0f2a32', NULL, 'rda-2026-02-01', '2026-03-24 23:49:02', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 23:38:22', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 23:49:02', 1, NULL, '2026-03-24 23:38:22', '2026-03-24 23:49:02'),
(28, '87833921-336e-4cf3-99df-db264f3d7340', NULL, 'rda-2026-02-01', '2026-03-24 23:49:02', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 23:38:22', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 23:49:02', 1, NULL, '2026-03-24 23:38:22', '2026-03-24 23:49:02'),
(29, '9a1f7398-2a4f-4b8d-9199-33bd28eae28a', NULL, 'rda-2026-02-01', '2026-03-24 23:49:02', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 23:38:22', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 23:49:02', 1, NULL, '2026-03-24 23:38:22', '2026-03-24 23:49:02'),
(30, 'd40eeca6-edca-4f01-ab9d-9f5877919f89', NULL, 'rda-2026-02-01', '2026-03-24 23:49:05', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 23:38:22', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 23:49:05', 1, NULL, '2026-03-24 23:38:22', '2026-03-24 23:49:05'),
(31, 'e4c710c8-1c74-4aae-bb53-d15fc75b6ba4', NULL, 'rda-2026-02-01', '2026-03-24 23:49:05', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 23:38:22', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 23:49:05', 1, NULL, '2026-03-24 23:38:22', '2026-03-24 23:49:05'),
(32, '04bb5b9f-11eb-40bd-91b6-f42b736d4283', NULL, 'rda-2026-02-01', '2026-03-24 23:49:06', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 23:38:23', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 23:49:06', 1, NULL, '2026-03-24 23:38:23', '2026-03-24 23:49:06'),
(33, '295aec48-fb68-46d8-815e-a99abbc29a42', NULL, 'rda-2026-02-01', '2026-03-24 23:49:06', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 23:38:23', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 23:49:06', 1, NULL, '2026-03-24 23:38:23', '2026-03-24 23:49:06'),
(34, '3a580a08-383e-4176-8e84-9712bb36b286', NULL, 'rda-2026-02-01', '2026-03-24 23:49:06', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 23:38:23', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 23:49:06', 1, NULL, '2026-03-24 23:38:23', '2026-03-24 23:49:06'),
(35, '44060594-9d1e-455e-b881-580cb47263bf', NULL, 'rda-2026-02-01', '2026-03-24 23:49:07', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 23:38:23', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 23:49:07', 1, NULL, '2026-03-24 23:38:23', '2026-03-24 23:49:07'),
(36, 'c31827fc-3b2c-49f1-b452-7353a2e7d41d', NULL, 'rda-2026-02-01', '2026-03-24 23:49:07', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 23:38:23', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 23:49:07', 1, NULL, '2026-03-24 23:38:23', '2026-03-24 23:49:07'),
(37, '41fddf7d-376b-42cf-b193-bfbec617632e', NULL, 'rda-2026-02-01', '2026-03-24 23:49:25', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 23:49:25', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 3, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 23:49:25', 1, NULL, '2026-03-24 23:49:25', '2026-03-24 23:49:25'),
(38, 'dd8a4a17-21b6-4873-9d8a-db23324fb0cd', NULL, 'rda-2026-02-01', '2026-03-24 23:49:43', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-24 23:49:43', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 3, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 23:49:43', 1, NULL, '2026-03-24 23:49:43', '2026-03-24 23:49:43'),
(39, 'ee26a3d2-7477-4844-bfa9-2c107a02e419', NULL, 'rda-2026-02-01', '2026-03-24 23:49:51', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-24 23:49:50', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 23:49:51', 1, NULL, '2026-03-24 23:49:50', '2026-03-24 23:49:51'),
(40, '361da742-a02b-42a6-a5d2-2eb7a951c81d', NULL, 'rda-2026-02-01', '2026-03-24 23:50:01', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-24 23:50:00', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-24 23:50:01', 1, NULL, '2026-03-24 23:50:00', '2026-03-24 23:50:01'),
(41, '3602c092-e07b-4d9e-94ac-65bc91810e85', NULL, 'rda-2026-02-01', '2026-03-25 00:03:42', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-25 00:03:41', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-25 00:03:42', 1, NULL, '2026-03-25 00:03:41', '2026-03-25 00:03:42'),
(42, 'd4e74e75-9fa5-4b92-8839-915f66398107', NULL, 'rda-2026-02-01', '2026-03-25 00:03:49', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-25 00:03:48', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-25 00:03:49', 1, NULL, '2026-03-25 00:03:48', '2026-03-25 00:03:49'),
(43, '22acd8c7-e80b-4295-8297-20f1edafc1f6', NULL, 'rda-2026-02-01', '2026-03-25 00:04:59', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-25 00:04:05', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 14, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-25 00:04:59', 13, NULL, '2026-03-25 00:04:05', '2026-03-25 00:04:59'),
(44, '9a77db80-f971-408d-a78c-7cc9b003a7ef', NULL, 'rda-2026-02-01', '2026-03-25 00:05:00', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-25 00:04:12', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 12, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-25 00:05:00', 11, NULL, '2026-03-25 00:04:12', '2026-03-25 00:05:00'),
(45, 'f21a3ce9-d53d-4700-9ef6-7b5b34600878', NULL, 'rda-2026-02-01', '2026-03-25 00:05:00', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-25 00:04:16', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 10, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-25 00:05:00', 9, NULL, '2026-03-25 00:04:16', '2026-03-25 00:05:00'),
(46, '4b3f08bc-6c78-4463-a1d3-d70caedeffef', NULL, 'rda-2026-02-01', '2026-03-25 00:05:01', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-25 00:04:21', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 8, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-25 00:05:01', 7, NULL, '2026-03-25 00:04:21', '2026-03-25 00:05:01'),
(47, '90a33584-296a-48ab-92c7-be67969c8f88', NULL, 'rda-2026-02-01', '2026-03-25 00:05:01', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-25 00:04:25', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 6, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-25 00:05:01', 5, NULL, '2026-03-25 00:04:25', '2026-03-25 00:05:01'),
(48, '7c7db9f4-1bc5-4467-ad43-fe890cc6d68d', NULL, 'rda-2026-02-01', '2026-03-25 00:05:01', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-25 00:04:30', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 6, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-25 00:05:01', 5, NULL, '2026-03-25 00:04:30', '2026-03-25 00:05:01'),
(49, '3761f95a-aae8-4c07-a65f-1120ba2f21f2', NULL, 'rda-2026-02-01', '2026-03-25 00:05:02', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-25 00:04:34', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 4, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-25 00:05:02', 3, NULL, '2026-03-25 00:04:34', '2026-03-25 00:05:02'),
(50, '2b9f8065-bf2c-42e5-9f5f-c1ef9bcbd74d', NULL, 'rda-2026-02-01', '2026-03-25 00:05:02', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-25 00:04:39', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 4, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-25 00:05:02', 3, NULL, '2026-03-25 00:04:39', '2026-03-25 00:05:02'),
(51, '9bce8567-6c6a-4d51-a3bb-deef86d24316', NULL, 'rda-2026-02-01', '2026-03-25 00:05:03', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-25 00:04:42', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-25 00:05:03', 1, NULL, '2026-03-25 00:04:42', '2026-03-25 00:05:03'),
(52, '6990e507-12b9-4f39-b86f-9288c4167167', NULL, 'rda-2026-02-01', '2026-03-26 12:22:57', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-03-26 12:22:56', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-26 12:22:57', 0, NULL, '2026-03-26 12:22:57', '2026-03-26 12:22:57'),
(53, '2eb1c3bc-89a8-49b6-af7f-e05a73aaee30', NULL, 'rda-2026-02-01', '2026-03-26 12:23:10', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-26 12:23:09', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-26 12:23:10', 0, NULL, '2026-03-26 12:23:10', '2026-03-26 12:23:10'),
(54, '71e810ff-fc8e-4cc2-a88f-555a393948c9', NULL, 'rda-2026-02-01', '2026-03-26 12:24:42', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-26 12:24:41', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-26 12:24:42', 0, NULL, '2026-03-26 12:24:42', '2026-03-26 12:24:42'),
(55, '95a5fe43-c063-4e1b-9b0e-739f9bb41f77', NULL, 'rda-2026-02-01', '2026-03-26 12:25:52', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-26 12:24:57', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 10, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-26 12:25:52', 0, NULL, '2026-03-26 12:25:52', '2026-03-26 12:25:52'),
(56, 'cc19c934-26c0-4b13-85ea-9c24fa07b7cd', NULL, 'rda-2026-02-01', '2026-03-26 12:25:53', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-26 12:25:14', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 9, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-26 12:25:53', 0, NULL, '2026-03-26 12:25:53', '2026-03-26 12:25:53'),
(57, '560f7aa7-0700-4907-942e-b3109d78a169', NULL, 'rda-2026-02-01', '2026-03-26 12:33:40', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, 'GHGHHGGH', 36.70, 'C', 1, '2026-03-26 12:33:39', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-26 12:33:40', 0, NULL, '2026-03-26 12:33:40', '2026-03-26 12:33:40'),
(58, 'bdb40b63-9e72-4b10-885b-2c48c3881035', NULL, 'rda-2026-02-01', '2026-03-26 12:36:23', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, 37.00, 'C', 0, '2026-03-26 12:36:23', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-26 12:36:23', 0, NULL, '2026-03-26 12:36:23', '2026-03-26 12:36:23'),
(59, '73384f88-d00a-4e4a-a15a-eeef7924da7a', NULL, 'rda-2026-02-01', '2026-03-26 12:37:46', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, '3443433434', 37.00, 'C', 1, '2026-03-26 12:37:45', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-26 12:37:46', 0, NULL, '2026-03-26 12:37:46', '2026-03-26 12:37:46'),
(60, 'a42c0f06-9755-4e7b-b9bd-75b996af19d3', NULL, 'rda-2026-02-01', '2026-03-26 12:38:02', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, '344343', 38.00, 'C', 1, '2026-03-26 12:38:01', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-26 12:38:02', 0, NULL, '2026-03-26 12:38:02', '2026-03-26 12:38:02'),
(61, 'e006ea0d-a926-4eb3-9dc1-0fca39041c7f', NULL, 'rda-2026-02-01', '2026-03-26 12:38:42', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-26 12:38:42', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-26 12:38:42', 0, NULL, '2026-03-26 12:38:42', '2026-03-26 12:38:42'),
(62, 'ddd5e5e1-6f7b-4aca-b687-65f7a34bab88', NULL, 'rda-2026-02-01', '2026-03-26 12:38:49', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-03-26 12:38:48', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-26 12:38:49', 0, NULL, '2026-03-26 12:38:49', '2026-03-26 12:38:49'),
(63, '041d7157-b429-4881-8826-28ecaa976f64', NULL, 'rda-2026-02-01', '2026-03-26 12:51:57', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, NULL, 35.00, 'C', 0, '2026-03-26 12:51:57', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-26 12:51:57', 0, NULL, '2026-03-26 12:51:57', '2026-03-26 12:51:57'),
(64, 'f51b5ecc-f28e-4fc2-86fc-3ec52e1d0b64', NULL, 'rda-2026-02-01', '2026-03-26 20:52:47', 'UG', 'Kabale RPHEOC', 'Kabale RPHEOC', 'Kisoro District', 'Bunagana', 3, 'MALE', NULL, 'allen', 37.00, 'C', 1, '2026-03-26 20:52:46', 'Africa/Dar_es_Salaam', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-03-26 20:52:47', 0, NULL, '2026-03-26 20:52:47', '2026-03-26 20:52:47'),
(65, 'e2d506ab-fc78-4c3f-90af-5385542ca4d9', NULL, 'rda-2026-02-01', '2026-04-15 10:37:06', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, 'Timothy Ayebare', NULL, NULL, 1, '2026-04-15 10:37:06', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'WEB', 1, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-15 10:37:06', 0, NULL, '2026-04-15 10:37:06', '2026-04-15 10:37:06'),
(66, 'c127e537-237d-4a30-a350-87ccfd344df6', NULL, 'rda-2026-02-01', '2026-04-15 10:37:14', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-04-15 10:37:14', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'WEB', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-15 10:37:14', 0, NULL, '2026-04-15 10:37:14', '2026-04-15 10:37:14'),
(67, '95263820-0756-4edb-aa6a-d5e3e6b4ca83', NULL, 'rda-2026-02-01', '2026-04-15 10:37:18', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-04-15 10:37:18', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'WEB', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-15 10:37:18', 0, NULL, '2026-04-15 10:37:18', '2026-04-15 10:37:18'),
(68, 'f726a080-0acd-4172-9dbf-8bcc477b9576', NULL, 'rda-2026-02-01', '2026-04-15 10:37:22', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-04-15 10:37:21', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'WEB', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-15 10:37:22', 0, NULL, '2026-04-15 10:37:22', '2026-04-15 10:37:22'),
(69, '8eb353e7-57c3-48cc-8162-b2e0a9f90050', NULL, 'rda-2026-02-01', '2026-04-15 10:37:32', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-04-15 10:37:32', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'WEB', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-15 10:37:32', 0, NULL, '2026-04-15 10:37:32', '2026-04-15 10:37:32'),
(70, '9ac319f4-7701-4502-97f5-d33e71b1eae9', NULL, 'rda-2026-02-01', '2026-04-15 10:37:37', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-04-15 10:37:37', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'WEB', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-15 10:37:37', 0, NULL, '2026-04-15 10:37:37', '2026-04-15 10:37:37'),
(71, '37f1aaf9-0c8f-422d-b146-34373af3e3a7', NULL, 'rda-2026-02-01', '2026-04-15 10:37:42', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, NULL, NULL, NULL, 0, '2026-04-15 10:37:42', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'WEB', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-15 10:37:42', 0, NULL, '2026-04-15 10:37:42', '2026-04-15 10:37:42'),
(72, '322e9310-4d77-4d12-a735-50d569b4287a', NULL, 'rda-2026-02-01', '2026-04-15 10:38:35', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, NULL, NULL, NULL, 1, '2026-04-15 10:38:35', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'WEB', 1, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-15 10:38:35', 0, NULL, '2026-04-15 10:38:35', '2026-04-15 10:38:35'),
(73, 'c5e3fa5d-e154-4a43-9e3e-0b4d0db29688', NULL, 'rda-2026-02-01', '2026-04-15 21:13:13', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, NULL, 38.00, 'C', 0, '2026-04-15 21:13:12', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-15 21:13:13', 0, NULL, '2026-04-15 21:13:13', '2026-04-15 21:13:13'),
(74, '0f1933cf-1f93-4ce5-9d63-2a2baea819f3', NULL, 'rda-2026-02-01', '2026-04-15 21:13:36', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, 'Ayebare Timothy', NULL, NULL, 1, '2026-04-15 21:13:35', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-15 21:13:36', 0, NULL, '2026-04-15 21:13:36', '2026-04-15 21:13:36'),
(75, '99cf15b4-f5f1-42d1-b6f6-f08baf7959ba', NULL, 'rda-2026-02-01', '2026-04-15 21:17:59', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, 'bib andrew', 38.00, 'C', 1, '2026-04-15 21:17:59', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-15 21:17:59', 0, NULL, '2026-04-15 21:17:59', '2026-04-15 21:17:59'),
(76, 'abff0c19-c032-4b87-b893-0543f5897632', NULL, 'rda-2026-02-01', '2026-04-15 21:37:14', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, NULL, 37.00, 'C', 0, '2026-04-15 21:37:13', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-15 21:37:14', 0, NULL, '2026-04-15 21:37:14', '2026-04-15 21:37:14'),
(77, '882ab364-8a36-492a-805a-04a99a78abe8', NULL, 'rda-2026-02-01', '2026-04-15 21:37:34', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, 'BOB ANDREW', 38.00, 'C', 1, '2026-04-15 21:37:34', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-15 21:37:34', 0, NULL, '2026-04-15 21:37:34', '2026-04-15 21:37:34'),
(78, '6e034898-df42-435f-ac31-cff2f27a1fe5', NULL, 'rda-2026-02-01', '2026-04-15 22:17:12', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, NULL, 37.00, 'C', 0, '2026-04-15 22:17:12', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-15 22:17:12', 0, NULL, '2026-04-15 22:17:12', '2026-04-15 22:17:12'),
(79, '1f23b02d-e9b1-48f5-b395-b5bf3d4da14a', NULL, 'rda-2026-02-01', '2026-04-15 22:17:19', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, NULL, 38.00, 'C', 0, '2026-04-15 22:17:19', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-15 22:17:19', 0, NULL, '2026-04-15 22:17:19', '2026-04-15 22:17:19'),
(80, 'e29f9c99-1989-42a3-bf89-07cf406d64c4', NULL, 'rda-2026-02-01', '2026-04-15 22:17:26', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, NULL, 39.00, 'C', 0, '2026-04-15 22:17:26', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-15 22:17:26', 0, NULL, '2026-04-15 22:17:26', '2026-04-15 22:17:26'),
(81, '62ae1e97-d1ca-4acd-9e68-6f34bb2ca410', NULL, 'rda-2026-02-01', '2026-04-15 22:17:39', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, 'this asdfdfd', 34.00, 'C', 1, '2026-04-15 22:17:39', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-15 22:17:39', 0, NULL, '2026-04-15 22:17:39', '2026-04-15 22:17:39'),
(82, 'ee38868f-a827-48c3-8a79-62e5f6a03324', NULL, 'rda-2026-02-01', '2026-04-16 08:22:10', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, NULL, 37.00, 'C', 0, '2026-04-16 08:22:10', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-16 08:22:10', 0, NULL, '2026-04-16 08:22:10', '2026-04-16 08:22:10'),
(83, '8f0ea651-1931-4677-b869-6e348a6d8502', NULL, 'rda-2026-02-01', '2026-04-16 08:22:23', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'FEMALE', NULL, NULL, 37.00, 'C', 0, '2026-04-16 08:22:22', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-16 08:22:23', 0, NULL, '2026-04-16 08:22:23', '2026-04-16 08:22:23'),
(84, '1e9c1ddc-bab3-41a7-bdf3-5b334301ee1a', NULL, 'rda-2026-02-01', '2026-04-16 08:22:42', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'FEMALE', NULL, 'Moreen', 40.00, 'C', 1, '2026-04-16 08:22:42', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-16 08:22:42', 0, NULL, '2026-04-16 08:22:42', '2026-04-16 08:22:42'),
(85, '1fa6de7f-317e-4825-baeb-fdec1c66945b', NULL, 'rda-2026-02-01', '2026-04-16 08:34:45', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, NULL, 38.00, 'C', 0, '2026-04-16 08:34:45', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-16 08:34:45', 0, NULL, '2026-04-16 08:34:45', '2026-04-16 08:34:45'),
(86, 'c55e76ec-1e82-4d2e-a7a8-b047bd0c6738', NULL, 'rda-2026-02-01', '2026-04-16 08:35:00', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'FEMALE', NULL, 'PHILIP', 38.00, 'C', 1, '2026-04-16 08:35:00', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 1, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-16 08:35:00', 0, NULL, '2026-04-16 08:35:00', '2026-04-16 08:35:00'),
(87, '4d88e26a-ea7c-4879-9ef1-f839483a9f76', NULL, 'rda-2026-02-01', '2026-04-16 22:10:07', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, NULL, 34.00, 'C', 0, '2026-04-16 22:10:07', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-16 22:10:07', 0, NULL, '2026-04-16 22:10:07', '2026-04-16 22:10:07'),
(88, 'e32a8baa-8168-4397-bdc0-4642639ff7ef', NULL, 'rda-2026-02-01', '2026-04-16 22:10:25', 'UG', 'Jinja RPHEOC', 'Jinja RPHEOC', 'Jinja District', 'UG-JIN-JIN-JIN-001', 2, 'MALE', NULL, NULL, 34.00, 'C', 0, '2026-04-16 22:10:24', 'Africa/Sao_Tome', 'ECSA-MN52AM0B-FZ0X9I', '0.0.1', 'ANDROID', 0, 2, 'COMPLETED', NULL, NULL, 'SYNCED', '2026-04-16 22:10:25', 0, NULL, '2026-04-16 22:10:25', '2026-04-16 22:10:25');

-- --------------------------------------------------------

--
-- Table structure for table `secondary_actions`
--

CREATE TABLE `secondary_actions` (
  `id` bigint UNSIGNED NOT NULL,
  `secondary_screening_id` bigint UNSIGNED NOT NULL,
  `action_code` varchar(80) NOT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT '1',
  `details` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `secondary_actions`
--

INSERT INTO `secondary_actions` (`id`, `secondary_screening_id`, `action_code`, `is_done`, `details`) VALUES
(11, 7, 'PPE_USED', 1, NULL),
(12, 7, 'SEPARATE_INTERVIEW_ROOM', 1, NULL),
(13, 7, 'ISOLATED', 1, NULL),
(14, 7, 'MASK_GIVEN', 1, NULL),
(15, 7, 'REFERRED_CLINIC', 1, NULL),
(16, 8, 'ISOLATED', 1, NULL),
(17, 8, 'PPE_USED', 1, NULL),
(18, 9, 'SEPARATE_INTERVIEW_ROOM', 1, NULL),
(19, 9, 'MASK_GIVEN', 1, NULL),
(20, 9, 'REFERRED_HOSPITAL', 1, NULL),
(49, 11, 'REFERRED_CLINIC', 1, NULL),
(50, 11, 'ISOLATED', 1, NULL),
(51, 11, 'PPE_USED', 1, NULL),
(56, 12, 'ALERT_ISSUED', 1, NULL),
(57, 12, 'ISOLATED', 1, NULL),
(58, 12, 'REFERRED_HOSPITAL', 1, NULL),
(63, 6, 'REFERRED_CLINIC', 1, NULL),
(64, 6, 'ALLOWED_CONTINUE', 1, NULL),
(65, 6, 'SEPARATE_INTERVIEW_ROOM', 1, NULL),
(66, 6, 'REFERRED_HOSPITAL', 1, NULL),
(67, 6, 'QUARANTINE_RECOMMENDED', 1, NULL),
(68, 6, 'SAMPLE_COLLECTED', 1, NULL),
(69, 6, 'PPE_USED', 1, NULL),
(72, 10, 'PPE_USED', 1, NULL),
(73, 10, 'ISOLATED', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `secondary_exposures`
--

CREATE TABLE `secondary_exposures` (
  `id` bigint UNSIGNED NOT NULL,
  `secondary_screening_id` bigint UNSIGNED NOT NULL,
  `exposure_code` varchar(80) NOT NULL,
  `response` enum('YES','NO','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN',
  `details` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `secondary_exposures`
--

INSERT INTO `secondary_exposures` (`id`, `secondary_screening_id`, `exposure_code`, `response`, `details`) VALUES
(36, 7, 'LAB_EXPOSURE', 'YES', NULL),
(37, 7, 'SICK_PERSON_CONTACT', 'UNKNOWN', NULL),
(38, 7, 'KNOWN_CASE_CONTACT', 'YES', NULL),
(39, 7, 'MASS_GATHERING', 'YES', NULL),
(40, 7, 'FUNERAL_BURIAL', 'YES', NULL),
(46, 8, 'KNOWN_CASE_CONTACT', 'YES', NULL),
(47, 8, 'FUNERAL_BURIAL', 'YES', NULL),
(48, 8, 'MASS_GATHERING', 'YES', NULL),
(49, 8, 'LAB_EXPOSURE', 'YES', NULL),
(50, 8, 'SICK_PERSON_CONTACT', 'YES', NULL),
(56, 9, 'KNOWN_CASE_CONTACT', 'YES', NULL),
(57, 9, 'MASS_GATHERING', 'YES', NULL),
(58, 9, 'LAB_EXPOSURE', 'YES', NULL),
(59, 9, 'FUNERAL_BURIAL', 'YES', NULL),
(60, 9, 'SICK_PERSON_CONTACT', 'UNKNOWN', NULL),
(116, 11, 'MASS_GATHERING', 'YES', NULL),
(117, 11, 'FUNERAL_BURIAL', 'YES', NULL),
(118, 11, 'LAB_EXPOSURE', 'YES', NULL),
(119, 11, 'KNOWN_CASE_CONTACT', 'UNKNOWN', NULL),
(120, 11, 'SICK_PERSON_CONTACT', 'UNKNOWN', NULL),
(131, 12, 'SICK_PERSON_CONTACT', 'UNKNOWN', NULL),
(132, 12, 'FUNERAL_BURIAL', 'UNKNOWN', NULL),
(133, 12, 'KNOWN_CASE_CONTACT', 'UNKNOWN', NULL),
(134, 12, 'MASS_GATHERING', 'UNKNOWN', NULL),
(135, 12, 'LAB_EXPOSURE', 'UNKNOWN', NULL),
(146, 6, 'MASS_GATHERING', 'YES', NULL),
(147, 6, 'LAB_EXPOSURE', 'YES', NULL),
(148, 6, 'KNOWN_CASE_CONTACT', 'YES', NULL),
(149, 6, 'FUNERAL_BURIAL', 'YES', NULL),
(150, 6, 'SICK_PERSON_CONTACT', 'YES', NULL),
(156, 10, 'MASS_GATHERING', 'YES', NULL),
(157, 10, 'SICK_PERSON_CONTACT', 'UNKNOWN', NULL),
(158, 10, 'KNOWN_CASE_CONTACT', 'UNKNOWN', NULL),
(159, 10, 'FUNERAL_BURIAL', 'YES', NULL),
(160, 10, 'LAB_EXPOSURE', 'YES', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `secondary_samples`
--

CREATE TABLE `secondary_samples` (
  `id` bigint UNSIGNED NOT NULL,
  `secondary_screening_id` bigint UNSIGNED NOT NULL,
  `sample_collected` tinyint(1) NOT NULL DEFAULT '0',
  `sample_type` varchar(80) DEFAULT NULL,
  `sample_identifier` varchar(120) DEFAULT NULL,
  `lab_destination` varchar(150) DEFAULT NULL,
  `collected_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `secondary_screenings`
--

CREATE TABLE `secondary_screenings` (
  `id` bigint UNSIGNED NOT NULL,
  `client_uuid` char(36) NOT NULL,
  `idempotency_key` char(64) DEFAULT NULL,
  `reference_data_version` varchar(40) NOT NULL,
  `server_received_at` datetime DEFAULT NULL,
  `country_code` varchar(10) NOT NULL,
  `province_code` varchar(30) DEFAULT NULL,
  `pheoc_code` varchar(30) DEFAULT NULL,
  `district_code` varchar(30) NOT NULL,
  `poe_code` varchar(40) NOT NULL,
  `primary_screening_id` bigint UNSIGNED NOT NULL,
  `notification_id` bigint UNSIGNED N