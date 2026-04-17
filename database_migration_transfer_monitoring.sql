-- Database Migration for Transfer Monitoring
-- NixiEpp Module v1.1.0
-- Date: 2026-04-17

-- Create transfer monitoring table
CREATE TABLE IF NOT EXISTS `service_domain_transfer` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `domain_name` VARCHAR(255) NOT NULL,
    `service_id` INT(11) UNSIGNED DEFAULT NULL,
    `status` ENUM(
        'pending',
        'checking',
        'completed',
        'failed',
        'transferring_out',
        'transferred_out',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',
    `transfer_direction` ENUM('in', 'out') NOT NULL DEFAULT 'in',
    `transfer_status` VARCHAR(50) DEFAULT NULL COMMENT 'EPP transfer status',
    `result_code` INT(11) DEFAULT NULL COMMENT 'EPP result code',
    `failure_reason` TEXT DEFAULT NULL,
    `auth_code` VARCHAR(255) DEFAULT NULL COMMENT 'Encrypted auth code',
    `transfer_initiated_at` DATETIME DEFAULT NULL,
    `last_checked_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `failed_at` DATETIME DEFAULT NULL,
    `transferred_out_at` DATETIME DEFAULT NULL,
    `check_count` INT(11) NOT NULL DEFAULT 0 COMMENT 'Number of status checks performed',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_domain_name` (`domain_name`),
    KEY `idx_status` (`status`),
    KEY `idx_transfer_direction` (`transfer_direction`),
    KEY `idx_transfer_initiated` (`transfer_initiated_at`),
    KEY `idx_last_checked` (`last_checked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Domain transfer monitoring table';

-- Add index for pending transfers query
CREATE INDEX `idx_pending_transfers` ON `service_domain_transfer` (`status`, `transfer_initiated_at`);

-- Add index for transfers older than X days
CREATE INDEX `idx_old_transfers` ON `service_domain_transfer` (`status`, `transfer_initiated_at`, `last_checked_at`);
