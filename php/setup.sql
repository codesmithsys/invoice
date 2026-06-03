CREATE DATABASE IF NOT EXISTS `invoice` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `invoice`;

CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_number` VARCHAR(50) NOT NULL,
    `date_of_issue` DATE NOT NULL,
    `due_date` DATE DEFAULT NULL,
    `from_name` VARCHAR(255) NOT NULL DEFAULT '',
    `from_address` VARCHAR(500) NOT NULL DEFAULT '',
    `from_email` VARCHAR(255) NOT NULL DEFAULT '',
    `from_vat_no` VARCHAR(100) NOT NULL DEFAULT '',
    `from_account_number` VARCHAR(100) NOT NULL DEFAULT '',
    `from_swift_bic` VARCHAR(100) NOT NULL DEFAULT '',
    `to_name` VARCHAR(255) NOT NULL DEFAULT '',
    `to_address` VARCHAR(500) NOT NULL DEFAULT '',
    `to_email` VARCHAR(255) NOT NULL DEFAULT '',
    `to_vat_no` VARCHAR(100) NOT NULL DEFAULT '',
    `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
    `net_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `gross_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('draft','sent','paid','overdue') NOT NULL DEFAULT 'draft',
    `notes` TEXT,
    `payment_method` VARCHAR(255) NOT NULL DEFAULT '',
    `logo_path` VARCHAR(500) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `invoice_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `description` VARCHAR(500) NOT NULL,
    `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `sort_order` INT NOT NULL DEFAULT 0,
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
