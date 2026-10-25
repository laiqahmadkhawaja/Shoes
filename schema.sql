-- Shoes Shop Management System Database Schema
-- Compatible with MySQL 5.7+ / 8.0+

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `expenses`;
DROP TABLE IF EXISTS `stock_adjustment_items`;
DROP TABLE IF EXISTS `stock_adjustments`;
DROP TABLE IF EXISTS `return_exchange_new_items`;
DROP TABLE IF EXISTS `return_items`;
DROP TABLE IF EXISTS `returns_exchanges`;
DROP TABLE IF EXISTS `customer_payments`;
DROP TABLE IF EXISTS `supplier_payments`;
DROP TABLE IF EXISTS `purchase_items`;
DROP TABLE IF EXISTS `purchases`;
DROP TABLE IF EXISTS `sale_payments`;
DROP TABLE IF EXISTS `sale_items`;
DROP TABLE IF EXISTS `sales`;
DROP TABLE IF EXISTS `cash_registers`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `product_variants`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `colors`;
DROP TABLE IF EXISTS `sizes`;
DROP TABLE IF EXISTS `brands`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `settings`;

-- Settings Table
CREATE TABLE `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) UNIQUE NOT NULL,
  `setting_value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users Table
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'manager', 'cashier', 'inventory', 'accountant') NOT NULL DEFAULT 'cashier',
  `phone` VARCHAR(20) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `name_ur` VARCHAR(100) NULL,
  `slug` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Brands
CREATE TABLE `brands` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sizes
CREATE TABLE `sizes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `size_system` VARCHAR(20) NOT NULL DEFAULT 'EU', -- EU, UK, US, Kids
  `size_number` VARCHAR(20) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Colors
CREATE TABLE `colors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `name_ur` VARCHAR(50) NULL,
  `hex_code` VARCHAR(20) NOT NULL DEFAULT '#000000'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products (Shoe Models)
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `article_code` VARCHAR(50) UNIQUE NOT NULL, -- e.g. ART-101
  `name` VARCHAR(150) NOT NULL,
  `category_id` INT NULL,
  `brand_id` INT NULL,
  `gender` ENUM('men', 'women', 'kids', 'unisex') NOT NULL DEFAULT 'men',
  `description` TEXT NULL,
  `image` VARCHAR(255) NULL,
  `base_purchase_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `base_selling_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `wholesale_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `min_selling_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `rack_location` VARCHAR(50) NULL,
  `shelf_location` VARCHAR(50) NULL,
  `box_number` VARCHAR(50) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`brand_id`) REFERENCES `brands`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Variants (Size-Color Combinations)
CREATE TABLE `product_variants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `size_id` INT NOT NULL,
  `color_id` INT NOT NULL,
  `sku` VARCHAR(100) UNIQUE NULL,
  `barcode` VARCHAR(100) UNIQUE NOT NULL,
  `purchase_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock_qty` INT NOT NULL DEFAULT 0,
  `damaged_qty` INT NOT NULL DEFAULT 0,
  `alert_qty` INT NOT NULL DEFAULT 2,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`size_id`) REFERENCES `sizes`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`color_id`) REFERENCES `colors`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suppliers
CREATE TABLE `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `company` VARCHAR(150) NULL,
  `phone` VARCHAR(30) NULL,
  `email` VARCHAR(100) NULL,
  `address` TEXT NULL,
  `opening_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `current_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customers (Khata / Account)
CREATE TABLE `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `email` VARCHAR(100) NULL,
  `address` TEXT NULL,
  `preferred_size` VARCHAR(20) NULL,
  `credit_limit` DECIMAL(12,2) NOT NULL DEFAULT 50000.00,
  `opening_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `current_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily Cash Registers
CREATE TABLE `cash_registers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `opening_time` DATETIME NOT NULL,
  `closing_time` DATETIME NULL,
  `opening_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `closing_amount` DECIMAL(10,2) NULL,
  `cash_sales` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cash_in` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cash_out` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `expected_amount` DECIMAL(10,2) NULL,
  `difference` DECIMAL(10,2) NULL,
  `notes` TEXT NULL,
  `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open',
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sales Invoices
CREATE TABLE `sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no` VARCHAR(50) UNIQUE NOT NULL,
  `user_id` INT NOT NULL,
  `customer_id` INT NULL,
  `register_id` INT NULL,
  `sale_date` DATETIME NOT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `grand_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `change_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` ENUM('paid', 'partial', 'due') NOT NULL DEFAULT 'paid',
  `payment_method` ENUM('cash', 'card', 'bank', 'jazzcash', 'easypaisa', 'credit', 'split') NOT NULL DEFAULT 'cash',
  `notes` TEXT NULL,
  `status` ENUM('completed', 'hold', 'cancelled', 'refunded') NOT NULL DEFAULT 'completed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`register_id`) REFERENCES `cash_registers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sale Items
CREATE TABLE `sale_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `variant_id` INT NOT NULL,
  `article_code` VARCHAR(50) NOT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `size_number` VARCHAR(20) NOT NULL,
  `color_name` VARCHAR(50) NOT NULL,
  `unit_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `quantity` INT NOT NULL DEFAULT 1,
  `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sale Payments (For Split & Multiple payments)
CREATE TABLE `sale_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT NOT NULL,
  `customer_id` INT NULL,
  `payment_method` VARCHAR(30) NOT NULL, -- cash, card, jazzcash, easypaisa, bank, credit
  `amount` DECIMAL(12,2) NOT NULL,
  `reference_no` VARCHAR(100) NULL,
  `payment_date` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchases (Supplier Invoices)
CREATE TABLE `purchases` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `po_number` VARCHAR(50) UNIQUE NOT NULL,
  `supplier_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `purchase_date` DATE NOT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `freight_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `other_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `grand_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `balance_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` ENUM('paid', 'partial', 'unpaid') NOT NULL DEFAULT 'paid',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchase Items (Size-Color Assortment)
CREATE TABLE `purchase_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `variant_id` INT NOT NULL,
  `unit_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `quantity` INT NOT NULL DEFAULT 1,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Supplier Payments (Supplier Ledger)
CREATE TABLE `supplier_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_id` INT NOT NULL,
  `purchase_id` INT NULL,
  `payment_method` VARCHAR(30) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `reference_no` VARCHAR(100) NULL,
  `payment_date` DATE NOT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`),
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Payments (Khata Settlement)
CREATE TABLE `customer_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT NOT NULL,
  `sale_id` INT NULL,
  `payment_method` VARCHAR(30) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `reference_no` VARCHAR(100) NULL,
  `payment_date` DATE NOT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`),
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Returns and Exchanges
CREATE TABLE `returns_exchanges` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `return_no` VARCHAR(50) UNIQUE NOT NULL,
  `sale_id` INT NOT NULL,
  `customer_id` INT NULL,
  `user_id` INT NOT NULL,
  `type` ENUM('return', 'exchange') NOT NULL DEFAULT 'return',
  `return_date` DATETIME NOT NULL,
  `returned_items_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `new_items_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `difference_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00, -- Positive if customer owes, negative if refund
  `refund_method` VARCHAR(30) NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Returned Items
CREATE TABLE `return_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `return_id` INT NOT NULL,
  `original_sale_item_id` INT NULL,
  `variant_id` INT NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `refund_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `condition_status` ENUM('resalable', 'defective_damaged') NOT NULL DEFAULT 'resalable',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`return_id`) REFERENCES `returns_exchanges`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exchange New Items Picked
CREATE TABLE `return_exchange_new_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `return_id` INT NOT NULL,
  `new_variant_id` INT NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`return_id`) REFERENCES `returns_exchanges`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`new_variant_id`) REFERENCES `product_variants`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock Adjustments
CREATE TABLE `stock_adjustments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `adjustment_no` VARCHAR(50) UNIQUE NOT NULL,
  `user_id` INT NOT NULL,
  `adjustment_date` DATETIME NOT NULL,
  `type` ENUM('in', 'out', 'physical_audit', 'damaged', 'transfer') NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock Adjustment Items
CREATE TABLE `stock_adjustment_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `adjustment_id` INT NOT NULL,
  `variant_id` INT NOT NULL,
  `old_qty` INT NOT NULL,
  `new_qty` INT NOT NULL,
  `diff_qty` INT NOT NULL,
  `unit_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`adjustment_id`) REFERENCES `stock_adjustments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Expenses
CREATE TABLE `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `expense_date` DATE NOT NULL,
  `category` VARCHAR(100) NOT NULL, -- Rent, Electricity, Salaries, Transport, Tea/Refreshment, Maintenance, Misc
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(30) NOT NULL DEFAULT 'cash',
  `description` TEXT NULL,
  `user_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Logs
CREATE TABLE `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(100) NOT NULL,
  `record_id` INT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Seed Default Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('shop_name', 'StepStyle Footwear & Shoes'),
('shop_name_ur', ''),
('tagline', 'Premium Quality Shoes & Casual Footwear'),
('address', 'Shop #12, Anarkali Commercial Mall, Lahore, Pakistan'),
('phone', '+92 300 1234567'),
('email', 'info@stepstylefootwear.pk'),
('currency', 'PKR'),
('currency_symbol', 'Rs.'),
('tax_rate', '0.00'),
('return_policy_days', '7'),
('receipt_footer', 'Thank you for shopping with us! Shoes can be exchanged within 7 days in original condition with box and bill.'),
('receipt_footer_ur', ''),
('language', 'en'),
('print_type', 'thermal'); -- thermal or a4

-- Seed Default Admin & Cashier Users (password is 'admin123')
-- password_hash for 'admin123' generated with PASSWORD_BCRYPT
INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role`, `phone`) VALUES
('admin', '$2y$10$F6qs.88.OF0ALyex4HtDU..zpgnvYlDXX4xnqb02m0vXtaKq7fmm2', 'Muhammad Usman (Owner)', 'admin', '+923001234567'),
('manager', '$2y$10$F6qs.88.OF0ALyex4HtDU..zpgnvYlDXX4xnqb02m0vXtaKq7fmm2', 'Ali Raza (Store Manager)', 'manager', '+923019876543'),
('cashier', '$2y$10$F6qs.88.OF0ALyex4HtDU..zpgnvYlDXX4xnqb02m0vXtaKq7fmm2', 'Hamza Tariq (Cashier 1)', 'cashier', '+923214567890');

-- Seed Categories
INSERT INTO `categories` (`name`, `slug`) VALUES
('Formal Shoes', 'formal-shoes'),
('Casual & Sneakers', 'casual-sneakers'),
('Sports & Running', 'sports-running'),
('Sandals & Peshawari', 'sandals-peshawari'),
('Slippers & Chappals', 'slippers-chappals'),
('Boots & Loafers', 'boots-loafers'),
('School Shoes', 'school-shoes'),
('Kids Collection', 'kids-collection');

-- Seed Brands
INSERT INTO `brands` (`name`, `description`) VALUES
('Servis', 'Pakistani leading footwear brand'),
('Bata', 'Comfortable family footwear'),
('Ndure', 'Youth trendy casual and formal shoes'),
('Borjan', 'Fashionable leather and ladies footwear'),
('Urban Sole', 'Premium genuine leather shoes'),
('Hush Puppies', 'Ultra comfort formal and casual shoes'),
('JP Pierre', 'Luxury men formal footwear'),
('Nike', 'Performance sports & sneakers');

-- Seed Shoe Sizes (EU Standard, UK, Kids)
INSERT INTO `sizes` (`size_system`, `size_number`, `sort_order`) VALUES
('EU', '36', 1),
('EU', '37', 2),
('EU', '38', 3),
('EU', '39', 4),
('EU', '40', 5),
('EU', '41', 6),
('EU', '42', 7),
('EU', '43', 8),
('EU', '44', 9),
('EU', '45', 10),
('EU', '46', 11),
('Kids', '28', 12),
('Kids', '30', 13),
('Kids', '32', 14),
('Kids', '34', 15);

-- Seed Colors
INSERT INTO `colors` (`name`, `hex_code`) VALUES
('Black', '#111111'),
('Brown', '#5c3a21'),
('Tan', '#d2b48c'),
('Navy Blue', '#001f3f'),
('White', '#ffffff'),
('Grey', '#808080'),
('Burgundy / Maroon', '#800020');

-- Seed Demo Suppliers
INSERT INTO `suppliers` (`name`, `company`, `phone`, `email`, `address`, `opening_balance`, `current_balance`) VALUES
('Haji Tariq Shoes Wholesalers', 'Tariq Footwear Lahore', '+92 321 8889991', 'tariqshoes@gmail.com', 'Moti Bazaar, Lahore', 0.00, 15000.00),
('Apex Leather Mills & Soles', 'Apex Leather Works', '+92 300 4443322', 'sales@apexleather.pk', 'Industrial Area, Gujranwala', 0.00, 25000.00),
('Servis Distribution Hub', 'Servis Industries Ltd', '+92 311 2233445', 'info@servisdist.pk', 'Multan Road, Lahore', 0.00, 0.00);

-- Seed Demo Customers
INSERT INTO `customers` (`name`, `phone`, `email`, `address`, `preferred_size`, `credit_limit`, `opening_balance`, `current_balance`) VALUES
('Walk-in Customer', '03000000000', 'walkin@store.pk', 'Counter Sale', '42', 0.00, 0.00, 0.00),
('Chaudhry Nadeem', '+92 302 5551234', 'nadeem@gmail.com', 'Gulberg III, Lahore', '41', 30000.00, 0.00, 4500.00),
('Dr. Tariq Mahmood', '+92 333 4445566', 'tariq.doc@hotmail.com', 'DHA Phase 5, Lahore', '42', 50000.00, 0.00, 0.00),
('Malik Usman Advocate', '+92 312 9988776', 'usman.adv@yahoo.com', 'Model Town, Lahore', '43', 40000.00, 0.00, 12000.00);

-- Seed Demo Products & Variants (with realistic stock and barcodes)
-- Product 1: Classic Formal Shoe — ART-101 (from user example)
INSERT INTO `products` (`id`, `article_code`, `name`, `category_id`, `brand_id`, `gender`, `description`, `base_purchase_price`, `base_selling_price`, `wholesale_price`, `min_selling_price`, `rack_location`, `shelf_location`, `box_number`) VALUES
(1, 'ART-101', 'Classic Formal Derby Shoe', 1, 7, 'men', 'Handcrafted genuine cow leather formal shoe with durable sole and soft insole.', 2400.00, 3500.00, 3100.00, 3200.00, 'Rack A', 'Shelf 1', 'BOX-01');

-- ART-101 Variants (Black: Sizes 39, 40, 41, 42, 43, 44, 45, 46)
INSERT INTO `product_variants` (`product_id`, `size_id`, `color_id`, `sku`, `barcode`, `purchase_price`, `selling_price`, `stock_qty`, `alert_qty`) VALUES
(1, 4, 1, 'ART101-BLK-39', '890101039', 2400.00, 3500.00, 4, 2),
(1, 5, 1, 'ART101-BLK-40', '890101040', 2400.00, 3500.00, 8, 2),
(1, 6, 1, 'ART101-BLK-41', '890101041', 2400.00, 3500.00, 6, 2),
(1, 7, 1, 'ART101-BLK-42', '890101042', 2400.00, 3500.00, 3, 2),
(1, 8, 1, 'ART101-BLK-43', '890101043', 2400.00, 3500.00, 5, 2),
(1, 9, 1, 'ART101-BLK-44', '890101044', 2400.00, 3500.00, 2, 2),
(1, 10, 1, 'ART101-BLK-45', '890101045', 2400.00, 3500.00, 0, 2),
(1, 11, 1, 'ART101-BLK-46', '890101046', 2400.00, 3500.00, 1, 2),
-- ART-101 Brown Variants
(1, 5, 2, 'ART101-BRN-40', '890101240', 2400.00, 3500.00, 4, 2),
(1, 6, 2, 'ART101-BRN-41', '890101241', 2400.00, 3500.00, 7, 2),
(1, 7, 2, 'ART101-BRN-42', '890101242', 2400.00, 3500.00, 5, 2),
(1, 8, 2, 'ART101-BRN-43', '890101243', 2400.00, 3500.00, 3, 2);

-- Product 2: Air Comfort Running Sports Shoe — ART-202
INSERT INTO `products` (`id`, `article_code`, `name`, `category_id`, `brand_id`, `gender`, `description`, `base_purchase_price`, `base_selling_price`, `wholesale_price`, `min_selling_price`, `rack_location`, `shelf_location`, `box_number`) VALUES
(2, 'ART-202', 'Air Comfort Sports Running Shoe', 3, 8, 'men', 'Lightweight breathable mesh upper with responsive cushioning sole for jogging and daily walk.', 3200.00, 4800.00, 4300.00, 4500.00, 'Rack B', 'Shelf 2', 'BOX-08');

INSERT INTO `product_variants` (`product_id`, `size_id`, `color_id`, `sku`, `barcode`, `purchase_price`, `selling_price`, `stock_qty`, `alert_qty`) VALUES
(2, 5, 5, 'ART202-WHT-40', '890202540', 3200.00, 4800.00, 5, 2),
(2, 6, 5, 'ART202-WHT-41', '890202541', 3200.00, 4800.00, 9, 2),
(2, 7, 5, 'ART202-WHT-42', '890202542', 3200.00, 4800.00, 8, 2),
(2, 8, 5, 'ART202-WHT-43', '890202543', 3200.00, 4800.00, 4, 2),
(2, 6, 4, 'ART202-NVY-41', '890202441', 3200.00, 4800.00, 6, 2),
(2, 7, 4, 'ART202-NVY-42', '890202442', 3200.00, 4800.00, 7, 2);

-- Product 3: Pure Leather Peshawari Chappal — ART-303
INSERT INTO `products` (`id`, `article_code`, `name`, `category_id`, `brand_id`, `gender`, `description`, `base_purchase_price`, `base_selling_price`, `wholesale_price`, `min_selling_price`, `rack_location`, `shelf_location`, `box_number`) VALUES
(3, 'ART-303', 'Handcrafted Kaptaan Peshawari Chappal', 4, 5, 'men', 'Traditional pure leather Kaptaan design with high grade tyre sole.', 1900.00, 2800.00, 2500.00, 2600.00, 'Rack C', 'Shelf 1', 'BOX-15');

INSERT INTO `product_variants` (`product_id`, `size_id`, `color_id`, `sku`, `barcode`, `purchase_price`, `selling_price`, `stock_qty`, `alert_qty`) VALUES
(3, 5, 1, 'ART303-BLK-40', '890303140', 1900.00, 2800.00, 6, 2),
(3, 6, 1, 'ART303-BLK-41', '890303141', 1900.00, 2800.00, 10, 2),
(3, 7, 1, 'ART303-BLK-42', '890303142', 1900.00, 2800.00, 12, 2),
(3, 8, 1, 'ART303-BLK-43', '890303143', 1900.00, 2800.00, 5, 2),
(3, 6, 3, 'ART303-TAN-41', '890303341', 1900.00, 2800.00, 8, 2),
(3, 7, 3, 'ART303-TAN-42', '890303342', 1900.00, 2800.00, 6, 2);

-- Product 4: Ndure Youth Street Sneaker — ART-404
INSERT INTO `products` (`id`, `article_code`, `name`, `category_id`, `brand_id`, `gender`, `description`, `base_purchase_price`, `base_selling_price`, `wholesale_price`, `min_selling_price`, `rack_location`, `shelf_location`, `box_number`) VALUES
(4, 'ART-404', 'Youth Street Casual Sneaker', 2, 3, 'unisex', 'Trendy daily wear low-top sneaker with comfortable memory foam footbed.', 2100.00, 3200.00, 2800.00, 2900.00, 'Rack A', 'Shelf 3', 'BOX-22');

INSERT INTO `product_variants` (`product_id`, `size_id`, `color_id`, `sku`, `barcode`, `purchase_price`, `selling_price`, `stock_qty`, `alert_qty`) VALUES
(4, 5, 6, 'ART404-GRY-40', '890404640', 2100.00, 3200.00, 7, 2),
(4, 6, 6, 'ART404-GRY-41', '890404641', 2100.00, 3200.00, 8, 2),
(4, 7, 6, 'ART404-GRY-42', '890404642', 2100.00, 3200.00, 5, 2),
(4, 5, 1, 'ART404-BLK-40', '890404140', 2100.00, 3200.00, 4, 2),
(4, 6, 1, 'ART404-BLK-41', '890404141', 2100.00, 3200.00, 6, 2);

-- Product 5: Bata Durable School Shoes — ART-505
INSERT INTO `products` (`id`, `article_code`, `name`, `category_id`, `brand_id`, `gender`, `description`, `base_purchase_price`, `base_selling_price`, `wholesale_price`, `min_selling_price`, `rack_location`, `shelf_location`, `box_number`) VALUES
(5, 'ART-505', 'Bata Tough Kids School Shoes', 7, 2, 'kids', 'Scuff resistant tough leather school shoes with velcro strap.', 1200.00, 1850.00, 1600.00, 1700.00, 'Rack D', 'Shelf 1', 'BOX-30');

INSERT INTO `product_variants` (`product_id`, `size_id`, `color_id`, `sku`, `barcode`, `purchase_price`, `selling_price`, `stock_qty`, `alert_qty`) VALUES
(5, 12, 1, 'ART505-BLK-28', '890505128', 1200.00, 1850.00, 10, 3),
(5, 13, 1, 'ART505-BLK-30', '890505130', 1200.00, 1850.00, 12, 3),
(5, 14, 1, 'ART505-BLK-32', '890505132', 1200.00, 1850.00, 8, 3),
(5, 15, 1, 'ART505-BLK-34', '890505134', 1200.00, 1850.00, 7, 3);

-- Sample Cash Register (Open)
INSERT INTO `cash_registers` (`id`, `user_id`, `opening_time`, `opening_amount`, `cash_sales`, `cash_in`, `cash_out`, `status`) VALUES
(1, 1, NOW(), 5000.00, 14500.00, 0.00, 500.00, 'open');

-- Sample Sales
INSERT INTO `sales` (`id`, `invoice_no`, `user_id`, `customer_id`, `register_id`, `sale_date`, `subtotal`, `discount_amount`, `grand_total`, `paid_amount`, `change_amount`, `payment_status`, `payment_method`, `notes`) VALUES
(1, 'INV-20261025-001', 1, 1, 1, NOW() - INTERVAL 2 HOUR, 7000.00, 200.00, 6800.00, 7000.00, 200.00, 'paid', 'cash', 'Counter checkout'),
(2, 'INV-20261025-002', 1, 2, 1, NOW() - INTERVAL 1 HOUR, 4800.00, 0.00, 4800.00, 4800.00, 0.00, 'paid', 'jazzcash', 'Paid via JazzCash ref #98341'),
(3, 'INV-20261025-003', 1, 4, 1, NOW() - INTERVAL 30 MINUTE, 12000.00, 0.00, 12000.00, 0.00, 0.00, 'due', 'credit', 'Khata sale approved for Malik Usman');

-- Sample Sale Items
INSERT INTO `sale_items` (`sale_id`, `product_id`, `variant_id`, `article_code`, `product_name`, `size_number`, `color_name`, `unit_cost`, `unit_price`, `quantity`, `discount`, `total`) VALUES
(1, 1, 2, 'ART-101', 'Classic Formal Derby Shoe', '40', 'Black', 2400.00, 3500.00, 2, 200.00, 6800.00),
(2, 2, 14, 'ART-202', 'Air Comfort Sports Running Shoe', '41', 'White', 3200.00, 4800.00, 1, 0.00, 4800.00),
(3, 1, 4, 'ART-101', 'Classic Formal Derby Shoe', '42', 'Black', 2400.00, 3500.00, 2, 0.00, 7000.00),
(3, 2, 15, 'ART-202', 'Air Comfort Sports Running Shoe', '42', 'White', 3200.00, 4800.00, 1, 0.00, 4800.00),
(3, 5, 23, 'ART-505', 'Bata Tough Kids School Shoes', '32', 'Black', 1200.00, 1850.00, 1, 0.00, 1850.00);

-- Sample Payments
INSERT INTO `sale_payments` (`sale_id`, `customer_id`, `payment_method`, `amount`, `reference_no`, `payment_date`) VALUES
(1, 1, 'cash', 6800.00, 'CASH-001', NOW() - INTERVAL 2 HOUR),
(2, 2, 'jazzcash', 4800.00, 'JC-98341', NOW() - INTERVAL 1 HOUR);

-- Sample Expenses
INSERT INTO `expenses` (`expense_date`, `category`, `amount`, `payment_method`, `description`, `user_id`) VALUES
(CURDATE(), 'Tea & Refreshment', 450.00, 'cash', 'Daily staff tea and water bottles', 1),
(CURDATE(), 'Shop Electricity', 8500.00, 'bank', 'Monthly LESCO commercial shop bill', 1),
(CURDATE(), 'Shop Cleaning & Polishing', 1200.00, 'cash', 'Shoe display racks cleaning supplies', 1);

-- Sample Audit Log
INSERT INTO `audit_logs` (`user_id`, `action`, `module`, `record_id`, `details`, `ip_address`) VALUES
(1, 'INSTALL', 'SYSTEM', 1, 'Initial database tables created and seeded successfully', '127.0.0.1');
