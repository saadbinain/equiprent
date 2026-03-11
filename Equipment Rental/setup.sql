-- ============================================================
--  Equipment Rental Management System
--  Database  : comshop
--  Created   : 2026-03-12
-- ============================================================

CREATE DATABASE IF NOT EXISTS `comshop`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `comshop`;

-- ------------------------------------------------------------
--  Drop tables in FK-safe order
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `rentals`;
DROP TABLE IF EXISTS `equipment`;

-- ------------------------------------------------------------
--  Equipment
-- ------------------------------------------------------------
CREATE TABLE `equipment` (
    `id`                 INT            NOT NULL AUTO_INCREMENT,
    `name`               VARCHAR(100)   NOT NULL,
    `description`        TEXT,
    `category`           VARCHAR(50)    NOT NULL DEFAULT 'General',
    `daily_rate`         DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    `total_quantity`     INT            NOT NULL DEFAULT 1,
    `available_quantity` INT            NOT NULL DEFAULT 1,
    `created_at`         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Rentals
-- ------------------------------------------------------------
CREATE TABLE `rentals` (
    `id`                   INT           NOT NULL AUTO_INCREMENT,
    `equipment_id`         INT           NOT NULL,
    `customer_name`        VARCHAR(100)  NOT NULL,
    `customer_phone`       VARCHAR(20)   DEFAULT NULL,
    `customer_address`     TEXT          DEFAULT NULL,
    `quantity`             INT           NOT NULL DEFAULT 1,
    `daily_rate`           DECIMAL(10,2) NOT NULL,
    `rent_date`            DATE          NOT NULL,
    `expected_return_date` DATE          NOT NULL,
    `actual_return_date`   DATE          DEFAULT NULL,
    `total_days`           INT           DEFAULT NULL,
    `total_amount`         DECIMAL(10,2) DEFAULT NULL,
    `status`               ENUM('rented','returned') NOT NULL DEFAULT 'rented',
    `notes`                TEXT          DEFAULT NULL,
    `created_at`           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_rental_equipment`
        FOREIGN KEY (`equipment_id`) REFERENCES `equipment`(`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Sample Equipment Data
-- ------------------------------------------------------------
INSERT INTO `equipment`
    (`name`, `description`, `category`, `daily_rate`, `total_quantity`, `available_quantity`)
VALUES
    ('Mini Excavator',       'Mini excavator for small to medium digging jobs',         'Heavy Equipment',  5000.00,  2,  2),
    ('Concrete Mixer 350L',  'Electric concrete mixer with 350-litre capacity',         'Mixing Equipment', 1500.00,  3,  3),
    ('Generator 5KVA',       'Portable diesel generator with 5 KVA output',             'Power Equipment',  2500.00,  5,  5),
    ('Scaffolding Set',      'Complete steel scaffolding set, up to 6 m height',        'Construction',      800.00, 10, 10),
    ('Plate Compactor',      'Vibratory plate compactor for soil and asphalt',          'Compaction',       1200.00,  4,  4),
    ('Electric Jackhammer',  'Electric jackhammer with assorted drill bits',            'Breaking',          900.00,  6,  6),
    ('Diesel Water Pump 3"', 'Diesel water pump with 3-inch outlet',                    'Pumping',           700.00,  8,  8),
    ('Boom Lift 40 ft',      '40-foot articulated boom lift for elevated work',         'Lifting',          8000.00,  1,  1),
    ('Angle Grinder 9"',     '9-inch angle grinder with cutting and grinding discs',    'Grinding',          400.00, 10, 10),
    ('Air Compressor 50L',   'Electric air compressor, 50-litre tank, 8-bar pressure', 'Pneumatic',         600.00,  6,  6);
