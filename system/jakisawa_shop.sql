-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 22, 2026 at 05:44 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `jakisawa_shop`
--

DELIMITER $$
--
-- Procedures
--
CREATE  PROCEDURE `create_order` (IN `p_user_id` INT, IN `p_shipping_address` TEXT, IN `p_billing_address` TEXT, IN `p_payment_method` VARCHAR(50), IN `p_notes` TEXT, OUT `p_order_number` VARCHAR(20), OUT `p_order_id` INT)   BEGIN

    DECLARE v_order_total DECIMAL(15,2);

    DECLARE v_subtotal DECIMAL(15,2);

    DECLARE v_customer_name VARCHAR(100);

    DECLARE v_customer_email VARCHAR(100);

    DECLARE v_customer_phone VARCHAR(20);

    

    

    SELECT full_name, email, phone 

    INTO v_customer_name, v_customer_email, v_customer_phone

    FROM users WHERE id = p_user_id;

    

    

    SELECT SUM(p.unit_price * c.quantity) INTO v_subtotal

    FROM cart c

    JOIN remedies p ON c.product_id = p.id

    WHERE c.user_id = p_user_id OR c.session_id = CONCAT('user_', p_user_id);

    

    IF v_subtotal IS NULL THEN

        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cart is empty';

    END IF;

    

    

    SET v_order_total = v_subtotal + 200.00 + ROUND(v_subtotal * 0.16, 2);

    

    

    SET p_order_number = CONCAT('ORD', DATE_FORMAT(NOW(), '%Y%m%d'), 

        LPAD(FLOOR(RAND() * 10000), 4, '0'));

    

    START TRANSACTION;

    

    

    INSERT INTO orders (

        order_number, user_id, customer_name, customer_email, customer_phone,

        shipping_address, billing_address, payment_method,

        subtotal, shipping_cost, tax_amount, total_amount, notes

    ) VALUES (

        p_order_number, p_user_id, v_customer_name, v_customer_email, v_customer_phone,

        p_shipping_address, p_billing_address, p_payment_method,

        v_subtotal, 200.00, ROUND(v_subtotal * 0.16, 2), v_order_total, p_notes

    );

    

    SET p_order_id = LAST_INSERT_ID();

    

    

    INSERT INTO order_items (

        order_id, product_id, product_name, product_sku,

        unit_price, quantity, total_price

    )

    SELECT 

        p_order_id, p.id, p.name, p.sku,

        p.unit_price, c.quantity,

        ROUND(p.unit_price * c.quantity, 2)

    FROM cart c

    JOIN remedies p ON c.product_id = p.id

    WHERE c.user_id = p_user_id OR c.session_id = CONCAT('user_', p_user_id);

    

    

    UPDATE remedies p

    JOIN cart c ON p.id = c.product_id

    SET p.stock_quantity = p.stock_quantity - c.quantity,

        p.updated_at = NOW()

    WHERE c.user_id = p_user_id OR c.session_id = CONCAT('user_', p_user_id);

    

    

    DELETE FROM cart 

    WHERE user_id = p_user_id OR session_id = CONCAT('user_', p_user_id);

    

    

    INSERT INTO audit_log (action, table_name, record_id, user_id)

    VALUES ('order_created', 'orders', p_order_id, p_user_id);

    

    COMMIT;

    

    SELECT p_order_id, p_order_number, v_order_total;

END$$

--
-- Functions
--
CREATE  FUNCTION `getPendingOrdersCount` () RETURNS INT(11) READS SQL DATA BEGIN

    DECLARE pending_count INT;

    

    SELECT COUNT(*) INTO pending_count 

    FROM orders 

    WHERE order_status = 'pending' 

    AND payment_status = 'pending';

    

    RETURN pending_count;

END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(10) UNSIGNED DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(10) UNSIGNED NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` decimal(10,3) NOT NULL DEFAULT 1.000,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#2e7d32' COMMENT 'Category color in hex format',
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `low_stock_products`
-- (See below for the actual view)
--
CREATE TABLE `low_stock_products` (
`id` int(10) unsigned
,`name` varchar(200)
,`sku` varchar(20)
,`stock_quantity` int(11)
,`reorder_level` int(11)
,`category` varchar(100)
,`status` varchar(12)
);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_number` varchar(20) NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_email` varchar(100) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_alt_phone` varchar(20) DEFAULT NULL,
  `shipping_address` text NOT NULL,
  `shipping_city` varchar(100) DEFAULT NULL,
  `shipping_postal_code` varchar(20) DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `billing_city` varchar(100) DEFAULT NULL,
  `billing_postal_code` varchar(20) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(50) DEFAULT NULL,
  `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
  `order_status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `subtotal` decimal(15,2) NOT NULL,
  `shipping_cost` decimal(15,2) DEFAULT 200.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `shipping_fee` decimal(15,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `product_name` varchar(200) NOT NULL,
  `product_sku` varchar(20) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `quantity` decimal(10,0) NOT NULL,
  `total_price` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

--
-- Triggers `order_items`
--
DELIMITER $$
CREATE TRIGGER `trg_order_items_after_delete` AFTER DELETE ON `order_items` FOR EACH ROW BEGIN

    DECLARE v_supplier_id INT(10) UNSIGNED;

    DECLARE v_order_number VARCHAR(30);

    DECLARE v_raw_customer_id INT(10) UNSIGNED;

    DECLARE v_customer_id INT(10) UNSIGNED;

    SELECT r.supplier_id INTO v_supplier_id

    FROM remedies r

    WHERE r.id = OLD.product_id

    LIMIT 1;

    SELECT o.user_id, o.order_number INTO v_raw_customer_id, v_order_number

    FROM orders o

    WHERE o.id = OLD.order_id

    LIMIT 1;

    SELECT u.id INTO v_customer_id

    FROM users u

    WHERE u.id = v_raw_customer_id

    LIMIT 1;

    INSERT INTO stock_ledger (

        remedy_id, supplier_id, movement_type, qty_change, balance_after,

        unit_price, source_table, source_id, source_ref, customer_user_id,

        order_id, movement_at, notes

    )

    SELECT

        OLD.product_id,

        v_supplier_id,

        'order_adjustment',

        OLD.quantity,

        r.stock_quantity,

        OLD.unit_price,

        'order_items',

        OLD.id,

        v_order_number,

        v_customer_id,

        OLD.order_id,

        NOW(),

        'Order item removed / stock restored'

    FROM remedies r

    WHERE r.id = OLD.product_id;

END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_order_items_after_insert` AFTER INSERT ON `order_items` FOR EACH ROW BEGIN

    DECLARE v_supplier_id INT(10) UNSIGNED;

    DECLARE v_order_number VARCHAR(30);

    DECLARE v_raw_customer_id INT(10) UNSIGNED;

    DECLARE v_customer_id INT(10) UNSIGNED;

    SELECT r.supplier_id INTO v_supplier_id

    FROM remedies r

    WHERE r.id = NEW.product_id

    LIMIT 1;

    SELECT o.user_id, o.order_number INTO v_raw_customer_id, v_order_number

    FROM orders o

    WHERE o.id = NEW.order_id

    LIMIT 1;

    SELECT u.id INTO v_customer_id

    FROM users u

    WHERE u.id = v_raw_customer_id

    LIMIT 1;

    INSERT INTO stock_ledger (

        remedy_id, supplier_id, movement_type, qty_change, balance_after,

        unit_price, source_table, source_id, source_ref, customer_user_id,

        order_id, movement_at, notes

    )

    SELECT

        NEW.product_id,

        v_supplier_id,

        'order_sale',

        (0 - NEW.quantity),

        r.stock_quantity,

        NEW.unit_price,

        'order_items',

        NEW.id,

        v_order_number,

        v_customer_id,

        NEW.order_id,

        NOW(),

        'Customer order item created'

    FROM remedies r

    WHERE r.id = NEW.product_id;

END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_order_items_after_update` AFTER UPDATE ON `order_items` FOR EACH ROW BEGIN

    DECLARE v_diff DECIMAL(12,3);

    DECLARE v_supplier_id INT(10) UNSIGNED;

    DECLARE v_order_number VARCHAR(30);

    DECLARE v_raw_customer_id INT(10) UNSIGNED;

    DECLARE v_customer_id INT(10) UNSIGNED;

    SET v_diff = NEW.quantity - OLD.quantity;

    IF v_diff <> 0 THEN

        SELECT r.supplier_id INTO v_supplier_id

        FROM remedies r

        WHERE r.id = NEW.product_id

        LIMIT 1;

        SELECT o.user_id, o.order_number INTO v_raw_customer_id, v_order_number

        FROM orders o

        WHERE o.id = NEW.order_id

        LIMIT 1;

        SELECT u.id INTO v_customer_id

        FROM users u

        WHERE u.id = v_raw_customer_id

        LIMIT 1;

        INSERT INTO stock_ledger (

            remedy_id, supplier_id, movement_type, qty_change, balance_after,

            unit_price, source_table, source_id, source_ref, customer_user_id,

            order_id, movement_at, notes

        )

        SELECT

            NEW.product_id,

            v_supplier_id,

            'order_adjustment',

            (0 - v_diff),

            r.stock_quantity,

            NEW.unit_price,

            'order_items',

            NEW.id,

            v_order_number,

            v_customer_id,

            NEW.order_id,

            NOW(),

            CONCAT('Order quantity updated from ', OLD.quantity, ' to ', NEW.quantity)

        FROM remedies r

        WHERE r.id = NEW.product_id;

    END IF;

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `remedies`
--

CREATE TABLE `remedies` (
  `id` int(10) UNSIGNED NOT NULL,
  `sku` varchar(20) NOT NULL,
  `name` varchar(200) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `category_id` int(10) UNSIGNED DEFAULT 1,
  `supplier_id` int(10) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL COMMENT 'URL or path to the main product image',
  `ingredients` text DEFAULT NULL,
  `usage_instructions` text DEFAULT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `cost_price` decimal(15,2) DEFAULT NULL,
  `discount_price` decimal(15,2) DEFAULT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='keeps our products';

--
-- Dumping data for table `remedies`
--

-- --------------------------------------------------------

--
-- Table structure for table `remedy_seo_marketing`
--

CREATE TABLE `remedy_seo_marketing` (
  `id` int(11) NOT NULL,
  `remedy_id` int(11) NOT NULL,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_meta_description` text DEFAULT NULL,
  `seo_keywords` text DEFAULT NULL,
  `focus_keyword` varchar(255) DEFAULT NULL,
  `og_title` varchar(255) DEFAULT NULL,
  `og_description` text DEFAULT NULL,
  `canonical_url` varchar(255) DEFAULT NULL,
  `target_audience` varchar(255) DEFAULT NULL,
  `value_proposition` text DEFAULT NULL,
  `customer_pain_points` text DEFAULT NULL,
  `cta_text` varchar(255) DEFAULT NULL,
  `cta_link` varchar(255) DEFAULT NULL,
  `faq_q1` varchar(255) DEFAULT NULL,
  `faq_a1` text DEFAULT NULL,
  `faq_q2` varchar(255) DEFAULT NULL,
  `faq_a2` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `remedy_seo_marketing`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `sales_report`
-- (See below for the actual view)
--
CREATE TABLE `sales_report` (
`sale_date` date
,`order_count` bigint(21)
,`total_revenue` decimal(37,2)
,`avg_order_value` decimal(19,6)
,`unique_customers` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `stock_ledger`
--

CREATE TABLE `stock_ledger` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `remedy_id` int(10) UNSIGNED NOT NULL,
  `supplier_id` int(10) UNSIGNED DEFAULT NULL,
  `movement_type` enum('opening','stock_in','order_sale','order_adjustment','return_in','return_out','manual_adjustment') NOT NULL,
  `qty_change` decimal(12,3) NOT NULL COMMENT 'positive=increase, negative=decrease',
  `balance_after` decimal(12,3) DEFAULT NULL,
  `unit_cost` decimal(15,2) DEFAULT NULL,
  `unit_price` decimal(15,2) DEFAULT NULL,
  `source_table` varchar(50) DEFAULT NULL,
  `source_id` bigint(20) UNSIGNED DEFAULT NULL,
  `source_ref` varchar(100) DEFAULT NULL,
  `customer_user_id` int(10) UNSIGNED DEFAULT NULL,
  `order_id` int(10) UNSIGNED DEFAULT NULL,
  `acted_by` int(10) UNSIGNED DEFAULT NULL,
  `movement_at` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_ledger`
--

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

-- --------------------------------------------------------

--
-- Table structure for table `supplier_stock_receipts`
--

CREATE TABLE `supplier_stock_receipts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `receipt_number` varchar(40) NOT NULL,
  `supplier_id` int(10) UNSIGNED NOT NULL,
  `received_by` int(10) UNSIGNED DEFAULT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reference_no` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier_stock_receipt_items`
--

CREATE TABLE `supplier_stock_receipt_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `receipt_id` bigint(20) UNSIGNED NOT NULL,
  `remedy_id` int(10) UNSIGNED NOT NULL,
  `qty_received` decimal(12,3) NOT NULL,
  `unit_cost` decimal(15,2) DEFAULT NULL,
  `batch_no` varchar(80) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `line_notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `supplier_stock_receipt_items`
--
DELIMITER $$
CREATE TRIGGER `trg_ssri_after_insert` AFTER INSERT ON `supplier_stock_receipt_items` FOR EACH ROW BEGIN

    DECLARE v_supplier_id INT(10) UNSIGNED;

    SELECT supplier_id INTO v_supplier_id

    FROM supplier_stock_receipts

    WHERE id = NEW.receipt_id

    LIMIT 1;

    UPDATE remedies

    SET stock_quantity = stock_quantity + NEW.qty_received,

        updated_at = NOW()

    WHERE id = NEW.remedy_id;

    INSERT INTO stock_ledger (

        remedy_id, supplier_id, movement_type, qty_change, balance_after,

        unit_cost, source_table, source_id, source_ref, movement_at, notes

    )

    SELECT

        NEW.remedy_id,

        v_supplier_id,

        'stock_in',

        NEW.qty_received,

        r.stock_quantity,

        NEW.unit_cost,

        'supplier_stock_receipt_items',

        NEW.id,

        CONCAT('RECEIPT#', s.receipt_number),

        NOW(),

        COALESCE(NEW.line_notes, 'Supplier restock')

    FROM remedies r

    JOIN supplier_stock_receipts s ON s.id = NEW.receipt_id

    WHERE r.id = NEW.remedy_id;

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `username` varchar(255) NOT NULL,
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','staff','customer') DEFAULT 'customer',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '1 = active, 0 = inactive',
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_super_admin` tinyint(1) DEFAULT 0,
  `cannot_delete` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `verification_token` varchar(100) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `failed_attempts` int(11) DEFAULT 0,
  `lock_until` datetime DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `registration_source` varchar(20) NOT NULL DEFAULT 'self' COMMENT 'self, admin, portal, api, imported',
  `registration_ip` varchar(45) DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `failed_login_attempts` int(3) DEFAULT 0,
  `lockout_until` timestamp NULL DEFAULT NULL,
  `last_failed_login` timestamp NULL DEFAULT NULL,
  `last_successful_login` timestamp NULL DEFAULT NULL,
  `last_active` datetime DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_customer_subsequent_orders`
-- (See below for the actual view)
--
CREATE TABLE `vw_customer_subsequent_orders` (
`user_id` int(10) unsigned
,`full_name` varchar(100)
,`email` varchar(100)
,`orders_count` bigint(21)
,`first_order_at` timestamp
,`last_order_at` timestamp
,`lifetime_value` decimal(37,2)
,`paid_value` decimal(37,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_supplier_stock_tracking`
-- (See below for the actual view)
--
CREATE TABLE `vw_supplier_stock_tracking` (
`supplier_id` int(10) unsigned
,`supplier_name` varchar(200)
,`remedy_id` int(10) unsigned
,`remedy_name` varchar(200)
,`sku` varchar(20)
,`total_stocked_in` decimal(34,3)
,`total_sold` decimal(34,3)
,`net_adjustments` decimal(34,3)
,`current_stock` int(11)
,`last_restock_at` datetime
);

-- --------------------------------------------------------

--
-- Structure for view `low_stock_products`
--
DROP TABLE IF EXISTS `low_stock_products`;

CREATE ALGORITHM=UNDEFINED  SQL SECURITY DEFINER VIEW `low_stock_products`  AS SELECT `p`.`id` AS `id`, `p`.`name` AS `name`, `p`.`sku` AS `sku`, `p`.`stock_quantity` AS `stock_quantity`, `p`.`reorder_level` AS `reorder_level`, `c`.`name` AS `category`, CASE WHEN `p`.`stock_quantity` = 0 THEN 'Out of Stock' WHEN `p`.`stock_quantity` <= `p`.`reorder_level` THEN 'Low' ELSE 'Adequate' END AS `status` FROM (`remedies` `p` join `categories` `c` on(`p`.`category_id` = `c`.`id`)) WHERE `p`.`is_active` = 1 AND `p`.`stock_quantity` <= `p`.`reorder_level` ORDER BY `p`.`stock_quantity` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `sales_report`
--
DROP TABLE IF EXISTS `sales_report`;

CREATE ALGORITHM=UNDEFINED  SQL SECURITY DEFINER VIEW `sales_report`  AS SELECT cast(`o`.`created_at` as date) AS `sale_date`, count(0) AS `order_count`, sum(`o`.`total_amount`) AS `total_revenue`, avg(`o`.`total_amount`) AS `avg_order_value`, count(distinct `o`.`user_id`) AS `unique_customers` FROM `orders` AS `o` WHERE `o`.`order_status` <> 'cancelled' GROUP BY cast(`o`.`created_at` as date) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_customer_subsequent_orders`
--
DROP TABLE IF EXISTS `vw_customer_subsequent_orders`;

CREATE ALGORITHM=UNDEFINED  SQL SECURITY DEFINER VIEW `vw_customer_subsequent_orders`  AS SELECT `o`.`user_id` AS `user_id`, `u`.`full_name` AS `full_name`, `u`.`email` AS `email`, count(distinct `o`.`id`) AS `orders_count`, min(`o`.`created_at`) AS `first_order_at`, max(`o`.`created_at`) AS `last_order_at`, coalesce(sum(`o`.`total_amount`),0) AS `lifetime_value`, coalesce(sum(case when `o`.`payment_status` = 'paid' then `o`.`total_amount` else 0 end),0) AS `paid_value` FROM (`orders` `o` left join `users` `u` on(`u`.`id` = `o`.`user_id`)) WHERE `o`.`user_id` is not null GROUP BY `o`.`user_id`, `u`.`full_name`, `u`.`email` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_supplier_stock_tracking`
--
DROP TABLE IF EXISTS `vw_supplier_stock_tracking`;

CREATE ALGORITHM=UNDEFINED  SQL SECURITY DEFINER VIEW `vw_supplier_stock_tracking`  AS SELECT `s`.`id` AS `supplier_id`, `s`.`name` AS `supplier_name`, `r`.`id` AS `remedy_id`, `r`.`name` AS `remedy_name`, `r`.`sku` AS `sku`, coalesce(sum(case when `sl`.`movement_type` = 'stock_in' then `sl`.`qty_change` else 0 end),0) AS `total_stocked_in`, coalesce(sum(case when `sl`.`movement_type` = 'order_sale' then abs(`sl`.`qty_change`) else 0 end),0) AS `total_sold`, coalesce(sum(case when `sl`.`movement_type` in ('order_adjustment','manual_adjustment','return_in','return_out') then `sl`.`qty_change` else 0 end),0) AS `net_adjustments`, `r`.`stock_quantity` AS `current_stock`, max(case when `sl`.`movement_type` = 'stock_in' then `sl`.`movement_at` end) AS `last_restock_at` FROM ((`remedies` `r` left join `suppliers` `s` on(`s`.`id` = `r`.`supplier_id`)) left join `stock_ledger` `sl` on(`sl`.`remedy_id` = `r`.`id`)) GROUP BY `s`.`id`, `s`.`name`, `r`.`id`, `r`.`name`, `r`.`sku`, `r`.`stock_quantity` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_action` (`action`,`created_at`),
  ADD KEY `idx_table_record` (`table_name`,`record_id`),
  ADD KEY `idx_audit_created_at` (`created_at`),
  ADD KEY `idx_audit_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_audit_table_created` (`table_name`,`created_at`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_cart_item` (`session_id`,`product_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_cart_session` (`session_id`),
  ADD KEY `idx_cart_user` (`user_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_user_orders` (`user_id`,`created_at`),
  ADD KEY `idx_status` (`order_status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items` (`order_id`),
  ADD KEY `idx_product_sales` (`product_id`,`created_at`);

--
-- Indexes for table `remedies`
--
ALTER TABLE `remedies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `idx_sku` (`sku`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_active` (`is_active`);
ALTER TABLE `remedies` ADD FULLTEXT KEY `idx_search` (`name`,`description`);

--
-- Indexes for table `remedy_seo_marketing`
--
ALTER TABLE `remedy_seo_marketing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `remedy_id` (`remedy_id`),
  ADD KEY `idx_remedy_id` (`remedy_id`);

--
-- Indexes for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stock_ledger_remedy` (`remedy_id`),
  ADD KEY `idx_stock_ledger_supplier` (`supplier_id`),
  ADD KEY `idx_stock_ledger_order` (`order_id`),
  ADD KEY `idx_stock_ledger_customer` (`customer_user_id`),
  ADD KEY `idx_stock_ledger_type_time` (`movement_type`,`movement_at`),
  ADD KEY `idx_stock_ledger_source` (`source_table`,`source_id`),
  ADD KEY `fk_stock_ledger_actor` (`acted_by`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `supplier_stock_receipts`
--
ALTER TABLE `supplier_stock_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_supplier_stock_receipts_receipt_number` (`receipt_number`),
  ADD KEY `idx_supplier_stock_receipts_supplier` (`supplier_id`),
  ADD KEY `idx_supplier_stock_receipts_received_at` (`received_at`),
  ADD KEY `fk_ssr_received_by` (`received_by`);

--
-- Indexes for table `supplier_stock_receipt_items`
--
ALTER TABLE `supplier_stock_receipt_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ssri_receipt` (`receipt_id`),
  ADD KEY `idx_ssri_remedy` (`remedy_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_users_active` (`is_active`),
  ADD KEY `idx_approved_by` (`approved_by`),
  ADD KEY `idx_approval_status` (`approval_status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=191;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `remedies`
--
ALTER TABLE `remedies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `remedy_seo_marketing`
--
ALTER TABLE `remedy_seo_marketing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=269;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3542;

--
-- AUTO_INCREMENT for table `supplier_stock_receipts`
--
ALTER TABLE `supplier_stock_receipts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplier_stock_receipt_items`
--
ALTER TABLE `supplier_stock_receipt_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `remedies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `remedies` (`id`);

--
-- Constraints for table `remedies`
--
ALTER TABLE `remedies`
  ADD CONSTRAINT `fk_remedies_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `remedies_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `remedies_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  ADD CONSTRAINT `fk_stock_ledger_actor` FOREIGN KEY (`acted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_stock_ledger_customer` FOREIGN KEY (`customer_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_stock_ledger_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_stock_ledger_remedy` FOREIGN KEY (`remedy_id`) REFERENCES `remedies` (`id`),
  ADD CONSTRAINT `fk_stock_ledger_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `supplier_stock_receipts`
--
ALTER TABLE `supplier_stock_receipts`
  ADD CONSTRAINT `fk_ssr_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ssr_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `supplier_stock_receipt_items`
--
ALTER TABLE `supplier_stock_receipt_items`
  ADD CONSTRAINT `fk_ssri_receipt` FOREIGN KEY (`receipt_id`) REFERENCES `supplier_stock_receipts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ssri_remedy` FOREIGN KEY (`remedy_id`) REFERENCES `remedies` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
