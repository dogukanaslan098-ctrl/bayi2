-- ============================================================
--  Sepet Tablosu Migrasyonu
--  SQL sekmesinde çalıştır
-- ============================================================

CREATE TABLE IF NOT EXISTS `carts` (
  `id`              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  `dealer_id`       INT UNSIGNED    NOT NULL,
  `product_id`      INT UNSIGNED    NOT NULL COMMENT 'WooCommerce ID',
  `sku`             VARCHAR(100)    NOT NULL,
  `name`            VARCHAR(300)    NOT NULL,
  `quantity`        INT UNSIGNED    NOT NULL DEFAULT 1,
  `unit_price`      DECIMAL(10,2)   NOT NULL COMMENT 'Normal liste fiyatı',
  `added_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`dealer_id`) REFERENCES `dealers`(`id`) ON DELETE CASCADE,
  INDEX `idx_dealer` (`dealer_id`),
  UNIQUE KEY `unique_cart_item` (`dealer_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
