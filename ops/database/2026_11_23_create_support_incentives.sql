-- Support incentive calendar and daily approvals.
-- Apply manually after deploying the application; domain tables are managed in MySQL.

CREATE TABLE `support_holidays` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `holiday_date` date NOT NULL,
  `name` varchar(160) NOT NULL,
  `category` varchar(32) NOT NULL,
  `created_by` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_support_holidays_date` (`holiday_date`),
  KEY `idx_support_holidays_category` (`category`),
  CONSTRAINT `fk_support_holidays_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `support_incentives` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `support_user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `work_date` date NOT NULL,
  `category` varchar(32) NOT NULL,
  `holiday_name` varchar(160) DEFAULT NULL,
  `rate` int unsigned NOT NULL,
  `reporting_id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `verified_by` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `decision_note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_support_incentive_user_date` (`support_user_id`, `work_date`),
  KEY `idx_support_incentive_date_status` (`work_date`, `status`),
  KEY `idx_support_incentive_reporting` (`reporting_id`),
  CONSTRAINT `fk_support_incentives_support_user` FOREIGN KEY (`support_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_support_incentives_reporting` FOREIGN KEY (`reporting_id`) REFERENCES `reportings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_support_incentives_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
