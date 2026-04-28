-- ============================================================
--  Boutique Hotel Management System (BHMS)
--  Database Schema — MySQL
--  CS251 Software Engineering 1 | Capital University
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `bhms` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `bhms`;

-- ============================================================
-- 1. GUESTS
-- ============================================================
CREATE TABLE `guests` (
  `guest_id`       INT          NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(100) NOT NULL,
  `email`          VARCHAR(150) NOT NULL,
  `phone`          VARCHAR(20)  DEFAULT NULL,
  `vip_status`     TINYINT(1)   NOT NULL DEFAULT 0,
  `loyalty_points` INT          NOT NULL DEFAULT 0,
  `loyalty_tier`   ENUM('Bronze','Silver','Gold','Platinum') NOT NULL DEFAULT 'Bronze',
  `blacklisted`    TINYINT(1)   NOT NULL DEFAULT 0,
  `blacklist_reason` TEXT       DEFAULT NULL,
  `anonymized`     TINYINT(1)   NOT NULL DEFAULT 0,
  `dob`            DATE         DEFAULT NULL,
  `anniversary`    DATE         DEFAULT NULL,
  `nationality`    VARCHAR(60)  DEFAULT NULL,
  `passport_no`    VARCHAR(50)  DEFAULT NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`guest_id`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 2. ROOM TYPES
-- ============================================================
CREATE TABLE `room_types` (
  `type_id`     INT           NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(50)   NOT NULL,
  `capacity`    INT           NOT NULL DEFAULT 2,
  `base_price`  DECIMAL(10,2) NOT NULL,
  `description` TEXT          DEFAULT NULL,
  `amenities`   TEXT          DEFAULT NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3. ROOMS
-- ============================================================
CREATE TABLE `rooms` (
  `room_id`     INT         NOT NULL AUTO_INCREMENT,
  `type_id`     INT         NOT NULL,
  `room_number` VARCHAR(10) NOT NULL,
  `floor`       INT         NOT NULL,
  `status`      ENUM('Clean','Dirty','Occupied','InCleaning','Inspecting','Ready','OutOfOrder')
                            NOT NULL DEFAULT 'Ready',
  `digital_key` VARCHAR(255) DEFAULT NULL,
  `notes`       TEXT        DEFAULT NULL,
  `updated_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`room_id`),
  UNIQUE KEY `uq_room_number` (`room_number`),
  CONSTRAINT `fk_rooms_type` FOREIGN KEY (`type_id`) REFERENCES `room_types` (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. STAFF
-- ============================================================
CREATE TABLE `staff` (
  `staff_id`      INT          NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100) NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('Receptionist','Housekeeper','Supervisor','Manager','Accountant','Admin','NightAuditor')
                               NOT NULL DEFAULT 'Receptionist',
  `active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login`    DATETIME     DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`staff_id`),
  UNIQUE KEY `uq_staff_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 5. RESERVATIONS
-- ============================================================
CREATE TABLE `reservations` (
  `reservation_id`  INT    NOT NULL AUTO_INCREMENT,
  `guest_id`        INT    NOT NULL,
  `room_id`         INT    DEFAULT NULL,
  `check_in_date`   DATE   NOT NULL,
  `check_out_date`  DATE   NOT NULL,
  `adults`          INT    NOT NULL DEFAULT 1,
  `children`        INT    NOT NULL DEFAULT 0,
  `status`          ENUM('Inquiry','Confirmed','CheckedIn','CheckedOut',
                         'Cancelled','NoShow','FolioClosed')
                           NOT NULL DEFAULT 'Inquiry',
  `special_request` TEXT   DEFAULT NULL,
  `deposit_amount`  DECIMAL(10,2) DEFAULT 0.00,
  `created_by`      INT    DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`reservation_id`),
  CONSTRAINT `fk_res_guest`   FOREIGN KEY (`guest_id`)    REFERENCES `guests` (`guest_id`),
  CONSTRAINT `fk_res_room`    FOREIGN KEY (`room_id`)     REFERENCES `rooms`  (`room_id`),
  CONSTRAINT `fk_res_created` FOREIGN KEY (`created_by`)  REFERENCES `staff`  (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 6. RESERVATION STATE LOG (Multi-State Workflow)
-- ============================================================
CREATE TABLE `reservation_state_log` (
  `log_id`         INT          NOT NULL AUTO_INCREMENT,
  `reservation_id` INT          NOT NULL,
  `from_state`     VARCHAR(30)  DEFAULT NULL,
  `to_state`       VARCHAR(30)  NOT NULL,
  `changed_by`     INT          DEFAULT NULL,
  `changed_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `note`           TEXT         DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  CONSTRAINT `fk_statelog_res`   FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`),
  CONSTRAINT `fk_statelog_staff` FOREIGN KEY (`changed_by`)     REFERENCES `staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 7. FOLIOS
-- ============================================================
CREATE TABLE `folios` (
  `folio_id`       INT           NOT NULL AUTO_INCREMENT,
  `reservation_id` INT           NOT NULL,
  `total_amount`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency`       CHAR(3)       NOT NULL DEFAULT 'EGP',
  `deposit_paid`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status`         ENUM('Open','PartiallyPaid','Closed') NOT NULL DEFAULT 'Open',
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at`      DATETIME      DEFAULT NULL,
  PRIMARY KEY (`folio_id`),
  UNIQUE KEY `uq_folio_reservation` (`reservation_id`),
  CONSTRAINT `fk_folio_res` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 8. FOLIO CHARGES
-- ============================================================
CREATE TABLE `folio_charges` (
  `charge_id`   INT           NOT NULL AUTO_INCREMENT,
  `folio_id`    INT           NOT NULL,
  `service_id`  INT           DEFAULT NULL,
  `description` VARCHAR(255)  NOT NULL,
  `amount`      DECIMAL(10,2) NOT NULL,
  `charge_type` ENUM('Room','Service','Tax','Penalty','Deposit','Minibar','Other')
                              NOT NULL DEFAULT 'Service',
  `charged_by`  INT           DEFAULT NULL,
  `charged_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`charge_id`),
  CONSTRAINT `fk_charge_folio`   FOREIGN KEY (`folio_id`)   REFERENCES `folios`   (`folio_id`),
  CONSTRAINT `fk_charge_staff`   FOREIGN KEY (`charged_by`) REFERENCES `staff`    (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 9. PAYMENTS
-- ============================================================
CREATE TABLE `payments` (
  `payment_id`     INT           NOT NULL AUTO_INCREMENT,
  `folio_id`       INT           NOT NULL,
  `amount`         DECIMAL(10,2) NOT NULL,
  `foreign_amount` DECIMAL(10,2) DEFAULT NULL,
  `exchange_rate`  DECIMAL(8,4)  DEFAULT NULL,
  `currency`       CHAR(3)       NOT NULL DEFAULT 'EGP',
  `method`         ENUM('Cash','CreditCard','ForeignCash','Split','BankTransfer')
                                 NOT NULL DEFAULT 'Cash',
  `status`         ENUM('Pending','Completed','Refunded','Failed')
                                 NOT NULL DEFAULT 'Pending',
  `processed_by`   INT           DEFAULT NULL,
  `paid_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  CONSTRAINT `fk_pay_folio` FOREIGN KEY (`folio_id`)     REFERENCES `folios` (`folio_id`),
  CONSTRAINT `fk_pay_staff` FOREIGN KEY (`processed_by`) REFERENCES `staff`  (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 10. SERVICES (Spa, Cafe, Minibar, Tours)
-- ============================================================
CREATE TABLE `services` (
  `service_id`       INT           NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(100)  NOT NULL,
  `category`         ENUM('Spa','Cafe','Minibar','Tour','Laundry','Other')
                                   NOT NULL DEFAULT 'Other',
  `price`            DECIMAL(10,2) NOT NULL,
  `cancellation_hrs` INT           NOT NULL DEFAULT 24,
  `penalty_pct`      DECIMAL(5,2)  NOT NULL DEFAULT 50.00,
  `active`           TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 11. GUEST PREFERENCES
-- ============================================================
CREATE TABLE `preferences` (
  `pref_id`    INT          NOT NULL AUTO_INCREMENT,
  `guest_id`   INT          NOT NULL,
  `type`       VARCHAR(50)  NOT NULL,
  `value`      VARCHAR(255) NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pref_id`),
  CONSTRAINT `fk_pref_guest` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`guest_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 12. HOUSEKEEPING TASKS
-- ============================================================
CREATE TABLE `hk_tasks` (
  `task_id`      INT      NOT NULL AUTO_INCREMENT,
  `room_id`      INT      NOT NULL,
  `assigned_to`  INT      DEFAULT NULL,
  `type`         ENUM('Cleaning','Inspection','Turndown','DeepClean','Special')
                          NOT NULL DEFAULT 'Cleaning',
  `status`       ENUM('Pending','InProgress','Done','Skipped')
                          NOT NULL DEFAULT 'Pending',
  `priority`     TINYINT  NOT NULL DEFAULT 2 COMMENT '1=Low,2=Med,3=High,4=Critical',
  `notes`        TEXT     DEFAULT NULL,
  `score`        TINYINT  DEFAULT NULL COMMENT 'Inspection score 1-10',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`task_id`),
  CONSTRAINT `fk_hk_room`  FOREIGN KEY (`room_id`)     REFERENCES `rooms` (`room_id`),
  CONSTRAINT `fk_hk_staff` FOREIGN KEY (`assigned_to`) REFERENCES `staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 13. MAINTENANCE REQUESTS
-- ============================================================
CREATE TABLE `maintenance_requests` (
  `request_id`  INT     NOT NULL AUTO_INCREMENT,
  `room_id`     INT     NOT NULL,
  `reported_by` INT     DEFAULT NULL,
  `assigned_to` INT     DEFAULT NULL,
  `issue`       TEXT    NOT NULL,
  `priority`    ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
  `status`      ENUM('Open','InProgress','Resolved','Escalated','OutOfOrder')
                        NOT NULL DEFAULT 'Open',
  `resolution`  TEXT    DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  CONSTRAINT `fk_maint_room`     FOREIGN KEY (`room_id`)     REFERENCES `rooms` (`room_id`),
  CONSTRAINT `fk_maint_reporter` FOREIGN KEY (`reported_by`) REFERENCES `staff` (`staff_id`),
  CONSTRAINT `fk_maint_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 14. AUDIT LOGS (Immutable)
-- ============================================================
CREATE TABLE `audit_logs` (
  `log_id`      INT          NOT NULL AUTO_INCREMENT,
  `staff_id`    INT          DEFAULT NULL,
  `action`      VARCHAR(255) NOT NULL,
  `table_name`  VARCHAR(50)  DEFAULT NULL,
  `record_id`   INT          DEFAULT NULL,
  `old_value`   TEXT         DEFAULT NULL,
  `new_value`   TEXT         DEFAULT NULL,
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `timestamp`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  CONSTRAINT `fk_audit_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 15. NIGHT AUDITS
-- ============================================================
CREATE TABLE `night_audits` (
  `audit_id`      INT          NOT NULL AUTO_INCREMENT,
  `run_by`        INT          NOT NULL,
  `audit_date`    DATE         NOT NULL,
  `is_simulation` TINYINT(1)   NOT NULL DEFAULT 1,
  `status`        VARCHAR(50)  NOT NULL DEFAULT 'Pending',
  `total_revenue` DECIMAL(12,2) DEFAULT NULL,
  `errors_found`  INT          NOT NULL DEFAULT 0,
  `report_data`   LONGTEXT     DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`audit_id`),
  CONSTRAINT `fk_nightaudit_staff` FOREIGN KEY (`run_by`) REFERENCES `staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 16. FEEDBACK
-- ============================================================
CREATE TABLE `feedback` (
  `feedback_id`    INT      NOT NULL AUTO_INCREMENT,
  `guest_id`       INT      NOT NULL,
  `reservation_id` INT      NOT NULL,
  `rating`         TINYINT  NOT NULL COMMENT '1-5 stars',
  `comment`        TEXT     DEFAULT NULL,
  `submitted_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`feedback_id`),
  CONSTRAINT `fk_fb_guest` FOREIGN KEY (`guest_id`)       REFERENCES `guests`       (`guest_id`),
  CONSTRAINT `fk_fb_res`   FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 17. INVENTORY
-- ============================================================
CREATE TABLE `inventory` (
  `item_id`    INT          NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `category`   VARCHAR(50)  DEFAULT NULL,
  `quantity`   INT          NOT NULL DEFAULT 0,
  `threshold`  INT          NOT NULL DEFAULT 10,
  `unit`       VARCHAR(20)  NOT NULL DEFAULT 'pcs',
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 18. LOST AND FOUND
-- ============================================================
CREATE TABLE `lost_found` (
  `item_id`    INT          NOT NULL AUTO_INCREMENT,
  `room_id`    INT          DEFAULT NULL,
  `guest_id`   INT          DEFAULT NULL,
  `found_by`   INT          DEFAULT NULL,
  `description` TEXT        NOT NULL,
  `status`     ENUM('Found','Claimed','Discarded') NOT NULL DEFAULT 'Found',
  `found_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `returned_at` DATETIME    DEFAULT NULL,
  PRIMARY KEY (`item_id`),
  CONSTRAINT `fk_lf_room`  FOREIGN KEY (`room_id`)  REFERENCES `rooms`  (`room_id`),
  CONSTRAINT `fk_lf_guest` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`guest_id`),
  CONSTRAINT `fk_lf_staff` FOREIGN KEY (`found_by`) REFERENCES `staff`  (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 19. PRICING RULES
-- ============================================================
CREATE TABLE `pricing_rules` (
  `rule_id`    INT           NOT NULL AUTO_INCREMENT,
  `type_id`    INT           DEFAULT NULL COMMENT 'NULL = applies to all room types',
  `rule_name`  VARCHAR(100)  NOT NULL,
  `strategy`   ENUM('Standard','Weekend','VIP','Occupancy','Seasonal') NOT NULL DEFAULT 'Standard',
  `adjustment` DECIMAL(5,2)  NOT NULL DEFAULT 0.00 COMMENT 'percentage e.g. 25.00 = +25%',
  `start_date` DATE          DEFAULT NULL,
  `end_date`   DATE          DEFAULT NULL,
  `active`     TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`rule_id`),
  CONSTRAINT `fk_price_type` FOREIGN KEY (`type_id`) REFERENCES `room_types` (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SEED DATA — Default Admin + Roles
-- ============================================================
INSERT INTO `staff` (`name`, `email`, `password_hash`, `role`) VALUES
('System Admin',   'admin@bhms.com',       '$2y$10$examplehashADMIN',       'Admin'),
('John Manager',   'manager@bhms.com',     '$2y$10$examplehashMANAGER',     'Manager'),
('Sara Reception', 'reception@bhms.com',   '$2y$10$examplehashRECEPTION',   'Receptionist'),
('Omar HK',        'housekeeper@bhms.com', '$2y$10$examplehashHOUSEKEEPER', 'Housekeeper'),
('Alex Auditor',   'auditor@bhms.com',     '$2y$10$examplehashAUDITOR',     'NightAuditor');

INSERT INTO `room_types` (`name`, `capacity`, `base_price`, `description`) VALUES
('Standard Single', 1, 500.00,  'Comfortable single room with all basic amenities'),
('Standard Double', 2, 800.00,  'Spacious double room with twin or double bed'),
('Deluxe Double',   2, 1200.00, 'Deluxe room with premium furnishings and city view'),
('Junior Suite',    3, 2000.00, 'Suite with separate living area'),
('Presidential',    4, 5000.00, 'Top floor presidential suite with butler service');

INSERT INTO `rooms` (`type_id`, `room_number`, `floor`, `status`) VALUES
(1, '101', 1, 'Ready'), (1, '102', 1, 'Ready'), (1, '103', 1, 'Ready'),
(2, '201', 2, 'Ready'), (2, '202', 2, 'Ready'), (2, '203', 2, 'Ready'),
(3, '301', 3, 'Ready'), (3, '302', 3, 'Ready'),
(4, '401', 4, 'Ready'),
(5, '501', 5, 'Ready');

INSERT INTO `services` (`name`, `category`, `price`, `cancellation_hrs`, `penalty_pct`) VALUES
('Swedish Massage',  'Spa',     250.00, 24, 50.00),
('Room Service',     'Cafe',     80.00,  2, 100.00),
('Mini Bar Package', 'Minibar', 150.00,  0, 100.00),
('Airport Transfer', 'Other',   200.00, 12,  50.00),
('Late Checkout',    'Other',   100.00,  4,  0.00),
('Early Checkin',    'Other',   100.00,  4,  0.00);

INSERT INTO `inventory` (`name`, `category`, `quantity`, `threshold`, `unit`) VALUES
('Bath Towels',    'Linen',      200, 50, 'pcs'),
('Hand Towels',    'Linen',      300, 80, 'pcs'),
('Bed Sheets',     'Linen',      100, 30, 'sets'),
('Shampoo Bottles','Consumable', 500, 100,'pcs'),
('Soap Bars',      'Consumable', 500, 100,'pcs'),
('Coffee Sachets', 'Minibar',    300, 60, 'pcs'),
('Water Bottles',  'Minibar',    400, 80, 'pcs');

INSERT INTO `pricing_rules` (`rule_name`, `strategy`, `adjustment`) VALUES
('Standard Rate',      'Standard',  0.00),
('Weekend Premium',    'Weekend',   25.00),
('VIP Discount',       'VIP',      -15.00),
('High Occupancy',     'Occupancy', 20.00),
('Low Season',         'Seasonal', -10.00);

COMMIT;

-- ============================================================
-- INDEXES for performance
-- ============================================================
ALTER TABLE `reservations`  ADD INDEX `idx_res_guest`  (`guest_id`);
ALTER TABLE `reservations`  ADD INDEX `idx_res_status` (`status`);
ALTER TABLE `reservations`  ADD INDEX `idx_res_dates`  (`check_in_date`, `check_out_date`);
ALTER TABLE `hk_tasks`      ADD INDEX `idx_hk_room`    (`room_id`);
ALTER TABLE `hk_tasks`      ADD INDEX `idx_hk_status`  (`status`);
ALTER TABLE `audit_logs`    ADD INDEX `idx_audit_time` (`timestamp`);
ALTER TABLE `folio_charges` ADD INDEX `idx_charge_folio` (`folio_id`);
ALTER TABLE `payments`      ADD INDEX `idx_pay_folio`  (`folio_id`);

-- ============================================================
-- VIEW: Active Reservations Summary
-- ============================================================
CREATE VIEW `v_active_reservations` AS
SELECT
  r.reservation_id,
  g.name        AS guest_name,
  g.email       AS guest_email,
  g.vip_status,
  rm.room_number,
  rt.name       AS room_type,
  r.check_in_date,
  r.check_out_date,
  DATEDIFF(r.check_out_date, r.check_in_date) AS nights,
  r.status,
  f.total_amount,
  f.status      AS folio_status
FROM reservations r
JOIN guests    g  ON r.guest_id = g.guest_id
LEFT JOIN rooms     rm ON r.room_id  = rm.room_id
LEFT JOIN room_types rt ON rm.type_id = rt.type_id
LEFT JOIN folios    f  ON r.reservation_id = f.reservation_id
WHERE r.status IN ('Confirmed','CheckedIn');

-- ============================================================
-- VIEW: Room Status Dashboard
-- ============================================================
CREATE VIEW `v_room_status` AS
SELECT
  rm.room_id,
  rm.room_number,
  rm.floor,
  rm.status,
  rt.name  AS room_type,
  rt.base_price,
  COALESCE(g.name, 'Vacant') AS current_guest
FROM rooms rm
JOIN room_types rt ON rm.type_id = rt.type_id
LEFT JOIN reservations r ON rm.room_id = r.room_id AND r.status = 'CheckedIn'
LEFT JOIN guests g ON r.guest_id = g.guest_id;

-- ============================================================
-- VIEW: Staff Task Load
-- ============================================================
CREATE VIEW `v_staff_tasks` AS
SELECT
  s.staff_id,
  s.name,
  s.role,
  COUNT(h.task_id)                                    AS total_tasks,
  SUM(h.status = 'Pending')                           AS pending,
  SUM(h.status = 'InProgress')                        AS in_progress,
  SUM(h.status = 'Done')                              AS done,
  ROUND(AVG(h.score),1)                               AS avg_score
FROM staff s
LEFT JOIN hk_tasks h ON s.staff_id = h.assigned_to
GROUP BY s.staff_id;

