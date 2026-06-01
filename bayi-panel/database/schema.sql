-- ============================================================
--  B2B Bayi Panel — Veritabanı Şeması
--  MySQL 5.7+ / MariaDB 10.3+
--  Kullanım: cPanel > phpMyAdmin > SQL sekmesi > çalıştır
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ── Bayiler ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dealers` (
  `id`               INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  `wc_customer_id`   INT UNSIGNED    DEFAULT NULL COMMENT 'WooCommerce customer ID',
  `company_name`     VARCHAR(200)    NOT NULL,
  `contact_name`     VARCHAR(100)    NOT NULL,
  `email`            VARCHAR(150)    NOT NULL UNIQUE,
  `phone`            VARCHAR(20)     NOT NULL,
  `password_hash`    VARCHAR(255)    NOT NULL,
  `tax_office`       VARCHAR(100)    DEFAULT NULL,
  `tax_number`       VARCHAR(20)     DEFAULT NULL,
  `tckn`             VARCHAR(11)     DEFAULT NULL,
  `address`          TEXT            DEFAULT NULL,
  `city`             VARCHAR(50)     DEFAULT NULL,
  `district`         VARCHAR(50)     DEFAULT NULL,
  `postal_code`      VARCHAR(10)     DEFAULT NULL,
  `level`            ENUM('bronz','silver','gold','platinum') NOT NULL DEFAULT 'bronz',
  `discount_type`    ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `discount_value`   DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `status`           ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
  `reset_token`      VARCHAR(64)     DEFAULT NULL,
  `reset_expires`    DATETIME        DEFAULT NULL,
  `last_login`       DATETIME        DEFAULT NULL,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email`  (`email`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Ürün Cache (WC'den senkronize edilir) ───────────────────
CREATE TABLE IF NOT EXISTS `product_cache` (
  `id`          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  `wc_id`       INT UNSIGNED  NOT NULL UNIQUE,
  `sku`         VARCHAR(100)  NOT NULL,
  `barcode`     VARCHAR(50)   DEFAULT NULL,
  `name`        VARCHAR(300)  NOT NULL,
  `price`       DECIMAL(10,2) NOT NULL,
  `stock_qty`   INT           NOT NULL DEFAULT 0,
  `image_url`   TEXT          DEFAULT NULL,
  `category`    VARCHAR(100)  DEFAULT NULL,
  `synced_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_sku`     (`sku`),
  INDEX `idx_barcode` (`barcode`),
  FULLTEXT INDEX `ft_name` (`name`, `sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Siparişler ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `orders` (
  `id`             INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  `order_number`   VARCHAR(20)   NOT NULL UNIQUE,
  `wc_order_id`    INT UNSIGNED  DEFAULT NULL COMMENT 'WooCommerce sipariş ID',
  `dealer_id`      INT UNSIGNED  NOT NULL,
  `status`         ENUM('pending','processing','shipped','delivered','cancelled')
                   NOT NULL DEFAULT 'pending',
  `payment_method` ENUM('card','eft') NOT NULL,
  `payment_status` ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
  `subtotal`       DECIMAL(12,2) NOT NULL,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total`          DECIMAL(12,2) NOT NULL,
  `cargo_company`  VARCHAR(50)   DEFAULT NULL,
  `cargo_code`     VARCHAR(50)   DEFAULT NULL,
  `notes`          TEXT          DEFAULT NULL,
  `invoice_path`   VARCHAR(255)  DEFAULT NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`dealer_id`) REFERENCES `dealers`(`id`) ON DELETE RESTRICT,
  INDEX `idx_dealer`  (`dealer_id`),
  INDEX `idx_status`  (`status`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Sipariş Kalemleri ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  `order_id`     INT UNSIGNED  NOT NULL,
  `product_id`   INT UNSIGNED  NOT NULL,
  `sku`          VARCHAR(100)  NOT NULL,
  `name`         VARCHAR(300)  NOT NULL,
  `quantity`     INT UNSIGNED  NOT NULL,
  `unit_price`   DECIMAL(10,2) NOT NULL COMMENT 'Normal liste fiyatı',
  `dealer_price` DECIMAL(10,2) NOT NULL COMMENT 'İndirimli bayi fiyatı',
  `line_total`   DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  INDEX `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Kampanyalar ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `campaigns` (
  `id`             INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  `name`           VARCHAR(200)  NOT NULL,
  `discount_type`  ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `discount_value` DECIMAL(10,2) NOT NULL,
  `target`         ENUM('all','bronz','silver','gold','platinum') NOT NULL DEFAULT 'all',
  `starts_at`      DATETIME      NOT NULL,
  `ends_at`        DATETIME      NOT NULL,
  `is_active`      TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Bildirimler ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `dealer_id`  INT UNSIGNED NOT NULL,
  `type`       VARCHAR(50)  NOT NULL COMMENT 'order_created|order_shipped|payment_received|campaign',
  `title`      VARCHAR(200) NOT NULL,
  `body`       TEXT         DEFAULT NULL,
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`dealer_id`) REFERENCES `dealers`(`id`) ON DELETE CASCADE,
  INDEX `idx_dealer_read` (`dealer_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Stok Kilitleri (eş zamanlı sipariş çakışma önleme) ──────
CREATE TABLE IF NOT EXISTS `stock_locks` (
  `product_id`  INT UNSIGNED NOT NULL,
  `locked_qty`  INT UNSIGNED NOT NULL DEFAULT 0,
  `locked_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Başvuru Formları ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dealer_applications` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_name` VARCHAR(200) NOT NULL,
  `contact_name` VARCHAR(100) NOT NULL,
  `email`        VARCHAR(150) NOT NULL,
  `phone`        VARCHAR(20)  NOT NULL,
  `tax_number`   VARCHAR(20)  DEFAULT NULL,
  `message`      TEXT         DEFAULT NULL,
  `status`       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
