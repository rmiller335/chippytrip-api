/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `airlines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `airlines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `icao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `iata` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `call_sign` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `types` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `airlines_icao_unique` (`icao`),
  KEY `airlines_iata_index` (`iata`),
  KEY `airlines_call_sign_index` (`call_sign`),
  KEY `airlines_country_code_index` (`country_code`),
  KEY `airlines_status_index` (`status`),
  CONSTRAINT `airlines_country_code_foreign` FOREIGN KEY (`country_code`) REFERENCES `countries` (`iso2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `airports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `airports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `icao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `iata` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` double NOT NULL,
  `longitude` double NOT NULL,
  `timezone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `elevation` int DEFAULT NULL,
  `wiki_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `flights_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alternatives` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `airports_icao_unique` (`icao`),
  KEY `airports_iata_index` (`iata`),
  KEY `airports_country_code_foreign` (`country_code`),
  CONSTRAINT `airports_country_code_foreign` FOREIGN KEY (`country_code`) REFERENCES `countries` (`iso2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `iso2` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `iso3` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dominion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `countries_iso2_unique` (`iso2`),
  UNIQUE KEY `countries_iso3_unique` (`iso3`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_related_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_related_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inbound_email_id` bigint unsigned NOT NULL,
  `record_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_related_records_inbound_email_id_foreign` (`inbound_email_id`),
  KEY `email_related_records_record_type_record_id_index` (`record_type`,`record_id`),
  CONSTRAINT `email_related_records_inbound_email_id_foreign` FOREIGN KEY (`inbound_email_id`) REFERENCES `inbound_emails` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `errors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `errorable_id` bigint unsigned NOT NULL,
  `errorable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `context` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `errors_errorable_type_errorable_id_index` (`errorable_type`,`errorable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `family_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `family_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `family_member_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auto_add` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `family_members_user_id_family_member_id_unique` (`user_id`,`family_member_id`),
  KEY `family_members_family_member_id_foreign` (`family_member_id`),
  CONSTRAINT `family_members_family_member_id_foreign` FOREIGN KEY (`family_member_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `family_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flights` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `airline_icao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `flight_no` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `flight` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `origin_icao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination_icao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `departure_date` date NOT NULL,
  `departure_dt` datetime DEFAULT NULL,
  `arrival_dt` datetime DEFAULT NULL,
  `duration` int unsigned DEFAULT NULL,
  `equipment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meal_service` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_seats` int unsigned DEFAULT NULL,
  `business_seats` int unsigned DEFAULT NULL,
  `coach_seats` int unsigned DEFAULT NULL,
  `alert_start` date NOT NULL,
  `alert_end` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flights_airline_icao_index` (`airline_icao`),
  KEY `flights_flight_no_index` (`flight_no`),
  KEY `flights_origin_icao_index` (`origin_icao`),
  KEY `flights_destination_icao_index` (`destination_icao`),
  KEY `flights_flight_index` (`flight`),
  KEY `flights_departure_date_index` (`departure_date`),
  KEY `flights_alert_start_index` (`alert_start`),
  KEY `flights_alert_end_index` (`alert_end`),
  CONSTRAINT `flights_airline_icao_foreign` FOREIGN KEY (`airline_icao`) REFERENCES `airlines` (`icao`),
  CONSTRAINT `flights_destination_icao_foreign` FOREIGN KEY (`destination_icao`) REFERENCES `airports` (`icao`),
  CONSTRAINT `flights_origin_icao_foreign` FOREIGN KEY (`origin_icao`) REFERENCES `airports` (`icao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inbound_emails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inbound_emails` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `message_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text_body` longtext COLLATE utf8mb4_unicode_ci,
  `html_body` longtext COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inbound_emails_user_id_foreign` (`user_id`),
  KEY `inbound_emails_message_id_index` (`message_id`),
  CONSTRAINT `inbound_emails_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `listeners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `listeners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `watch_id` bigint unsigned NOT NULL,
  `travelers` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `listeners_user_id_index` (`user_id`),
  KEY `listeners_watch_id_index` (`watch_id`),
  CONSTRAINT `listeners_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `listeners_watch_id_foreign` FOREIGN KEY (`watch_id`) REFERENCES `watches` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`),
  CONSTRAINT `password_reset_tokens_email_foreign` FOREIGN KEY (`email`) REFERENCES `users` (`email`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  UNIQUE KEY `personal_access_tokens_device_unique` (`tokenable_type`,`tokenable_id`,`name`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`),
  CONSTRAINT `sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_channels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `channel` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `identifier` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `credentials` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_channels_user_id_channel_identifier_unique` (`user_id`,`channel`,`identifier`),
  KEY `user_channels_channel_index` (`channel`),
  CONSTRAINT `user_channels_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subscription_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_subscription_type_index` (`subscription_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `watch_callbacks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `watch_callbacks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alert_id` bigint unsigned NOT NULL COMMENT 'FlightAware alert ID that triggered this callback',
  `watch_id` bigint unsigned DEFAULT NULL,
  `event_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Event type: filed, offblock, departure, arrival, onblock, diverted, cancelled, ...',
  `event_dt` datetime DEFAULT NULL,
  `summary` longtext COLLATE utf8mb4_unicode_ci,
  `short_description` longtext COLLATE utf8mb4_unicode_ci,
  `long_description` text COLLATE utf8mb4_unicode_ci COMMENT 'Full human-readable description of the event',
  `fa_flight_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'FlightAware unique flight identifier, e.g. UAL123-1234567890-airline-0123',
  `ident` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Flight identifier / callsign, e.g. UAL123',
  `ident_icao` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ICAO operator + flight number, e.g. UAL123',
  `ident_iata` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IATA operator + flight number, e.g. UA123',
  `registration` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Aircraft tail/registration number, e.g. N12345',
  `atc_ident` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ATC identifier used for the flight',
  `inbound_fa_flight_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'fa_flight_id of the inbound (previous) leg',
  `aircraft_type` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ICAO (or IATA) aircraft type code, e.g. B738',
  `origin` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Origin airport ICAO/IATA/LID code',
  `origin_icao` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin_iata` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Destination airport ICAO/IATA/LID code',
  `destination_icao` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_iata` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route` text COLLATE utf8mb4_unicode_ci COMMENT 'Filed route string',
  `route_distance` int unsigned DEFAULT NULL COMMENT 'Filed route distance in nautical miles',
  `filed_ete` int unsigned DEFAULT NULL COMMENT 'Filed estimated time en-route in seconds',
  `filed_altitude` int unsigned DEFAULT NULL COMMENT 'Filed cruising altitude in hundreds of feet (FL)',
  `filed_airspeed_kts` int unsigned DEFAULT NULL COMMENT 'Filed true airspeed in knots',
  `gate_origin` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gate_destination` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `terminal_origin` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `terminal_destination` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `baggage_claim` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Operator/airline ICAO code',
  `operator_icao` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator_iata` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `flight_number` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scheduled_out` timestamp NULL DEFAULT NULL COMMENT 'Scheduled gate departure',
  `estimated_out` timestamp NULL DEFAULT NULL COMMENT 'Estimated gate departure',
  `actual_out` timestamp NULL DEFAULT NULL COMMENT 'Actual gate departure',
  `scheduled_off` timestamp NULL DEFAULT NULL COMMENT 'Scheduled runway departure',
  `estimated_off` timestamp NULL DEFAULT NULL COMMENT 'Estimated runway departure',
  `actual_off` timestamp NULL DEFAULT NULL COMMENT 'Actual runway departure',
  `scheduled_on` timestamp NULL DEFAULT NULL COMMENT 'Scheduled runway arrival',
  `estimated_on` timestamp NULL DEFAULT NULL COMMENT 'Estimated runway arrival',
  `actual_on` timestamp NULL DEFAULT NULL COMMENT 'Actual runway arrival',
  `scheduled_in` timestamp NULL DEFAULT NULL COMMENT 'Scheduled gate arrival',
  `estimated_in` timestamp NULL DEFAULT NULL COMMENT 'Estimated gate arrival',
  `actual_in` timestamp NULL DEFAULT NULL COMMENT 'Actual gate arrival',
  `position_only` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'True if this is a position-only (no filed plan) flight',
  `blocked` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'True if the flight is blocked from public display',
  `cancelled` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'True if the flight has been cancelled',
  `diverted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'True if the flight has been diverted',
  `flight_error` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Error string returned by FlightAware if flight data is unavailable',
  `raw_payload` json NOT NULL COMMENT 'Complete raw JSON payload received from FlightAware',
  `source_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP address FlightAware POSTed from',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `watch_callbacks_notification_id_unique` (`notification_id`),
  KEY `watch_callbacks_alert_id_index` (`alert_id`),
  KEY `watch_callbacks_event_code_index` (`event_code`),
  KEY `watch_callbacks_fa_flight_id_index` (`fa_flight_id`),
  KEY `watch_callbacks_ident_index` (`ident`),
  KEY `watch_callbacks_registration_index` (`registration`),
  KEY `watch_callbacks_origin_index` (`origin`),
  KEY `watch_callbacks_destination_index` (`destination`),
  KEY `watch_callbacks_watch_id_foreign` (`watch_id`),
  CONSTRAINT `watch_callbacks_watch_id_foreign` FOREIGN KEY (`watch_id`) REFERENCES `watches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `watches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `watches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `flight_id` bigint unsigned NOT NULL,
  `subscription_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `enabled_at` timestamp NULL DEFAULT NULL,
  `secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `watches_flight_id_index` (`flight_id`),
  KEY `watches_subscription_id_index` (`subscription_id`),
  KEY `watches_enabled_index` (`enabled`),
  CONSTRAINT `watches_flight_id_foreign` FOREIGN KEY (`flight_id`) REFERENCES `flights` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `watches_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `watches_notifications` (
  `watch_id` bigint unsigned NOT NULL,
  `notification_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`watch_id`,`notification_id`),
  KEY `watches_notifications_notification_id_foreign` (`notification_id`),
  CONSTRAINT `watches_notifications_notification_id_foreign` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `watches_notifications_watch_id_foreign` FOREIGN KEY (`watch_id`) REFERENCES `watches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_04_15_191003_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_04_16_151630_flights',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_04_16_152024_watches',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_04_16_152646_listeners',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_04_16_154212_airports',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_04_16_183103_airlines',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_04_22_170211_create_audits_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_04_22_172325_create_countries',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_04_23_180406_add_departure_arrival_to_listeners',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_04_24_130137_full_dt_on_flights',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_04_30_155945_create_watch_callbacks',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_05_05_185817_user_channels',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_05_12_153935_add_secret_to_watches',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_05_13_155226_add_alert_window_to_flights',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_05_14_142820_add_enabled_to_watches',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_05_18_162637_allow_nulls_in_watch_secret',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_05_27_180426_nullable_flight_columns',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_05_28_154432_add_columns_to_flights',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_06_03_143206_create_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_06_15_163631_create_watches_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_06_16_123615_expand_callback_description_columns',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_06_24_120000_add_missing_columns_to_airports',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_08_03_171133_add_identifier_to_user_channels',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_08_26_165043_add_notification_id_to_watch_callbacks',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_09_01_145432_add_event_dt_to_watch_callbacks',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_09_09_142733_create_inbound_emails_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_09_10_142911_create_email_related_records_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_09_11_135807_create_errors_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_09_11_175406_add_display_name_to_airports',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_09_23_120000_fix_watches_notifications_notification_id_foreign',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_09_23_120001_add_index_to_email_related_records',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_09_23_120002_fix_user_channels_user_id_foreign',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_09_23_130000_add_subscription_type_to_users',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_09_23_130001_create_family_members_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2026_09_23_140000_make_users_password_nullable',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2026_09_23_150000_add_password_reset_tokens_email_foreign',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2026_09_23_150001_add_sessions_user_id_foreign',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2026_09_23_160000_add_name_to_family_members',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2026_09_24_120000_add_watch_id_to_watch_callbacks',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2026_09_24_170000_unique_token_per_device',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2026_09_24_180000_drop_audits_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2026_09_25_120000_add_enabled_at_to_watches',16);
