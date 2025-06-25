-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 25, 2025 at 01:54 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `marketplace_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

DROP TABLE IF EXISTS `cart_items`;
CREATE TABLE IF NOT EXISTS `cart_items` (
  `cart_item_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`cart_item_id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `cart_items`
--

INSERT INTO `cart_items` (`cart_item_id`, `user_id`, `product_id`, `quantity`, `created_at`, `updated_at`) VALUES
(7, 9, 21, 2, '2025-06-13 07:39:06', '2025-06-13 07:39:06'),
(8, 11, 19, 5, '2025-06-13 07:41:42', '2025-06-13 07:41:42'),
(9, 9, 16, 3, '2025-06-13 07:55:22', '2025-06-13 07:55:22');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `name`) VALUES
(1, 'Electronics'),
(2, 'Apparel'),
(3, 'Home Goods'),
(4, 'Books');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `order_id` int NOT NULL AUTO_INCREMENT,
  `buyer_id` int NOT NULL,
  `order_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `total_amount` decimal(10,2) NOT NULL,
  `shipping_address` text NOT NULL,
  `payment_status` enum('pending','paid','refunded') NOT NULL DEFAULT 'pending',
  `order_status` enum('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `status` varchar(50) DEFAULT 'pending',
  `user_id` int NOT NULL,
  PRIMARY KEY (`order_id`),
  KEY `buyer_id` (`buyer_id`)
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `buyer_id`, `order_date`, `total_amount`, `shipping_address`, `payment_status`, `order_status`, `status`, `user_id`) VALUES
(11, 0, '2025-06-12 23:32:51', 0.00, '', 'pending', '', 'pending', 9),
(12, 0, '2025-06-12 23:42:11', 0.00, '', 'pending', '', 'pending', 11),
(13, 0, '2025-06-12 23:56:45', 0.00, '', 'pending', '', 'pending', 7),
(14, 0, '2025-06-24 17:49:09', 0.00, '', 'pending', 'pending', 'pending', 7);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE IF NOT EXISTS `order_items` (
  `order_item_id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `price_at_purchase` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`order_item_id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `quantity`, `unit_price`, `price_at_purchase`) VALUES
(7, 11, 21, 1, 0.00, 59990.00),
(8, 12, 19, 3, 0.00, 106990.00),
(9, 13, 10, 10, 0.00, 28990.00),
(10, 14, 21, 1, 0.00, 59990.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
CREATE TABLE IF NOT EXISTS `products` (
  `product_id` int NOT NULL AUTO_INCREMENT,
  `seller_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `category_id` int NOT NULL,
  `stock_quantity` int NOT NULL,
  `image_url` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','sold_out') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  KEY `seller_id` (`seller_id`),
  KEY `category_id` (`category_id`)
) ENGINE=MyISAM AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `seller_id`, `name`, `description`, `price`, `category_id`, `stock_quantity`, `image_url`, `status`, `created_at`, `updated_at`) VALUES
(7, 7, 'iPhone 16e', 'iPhone 16e is powered by the A18 chip. Shoot super-high-resolution photos with the 48MP Fusion camera. And with supersized battery life, you have more time to text, browse, and more.', 39990.00, 1, 3265, 'uploads/products/684b40688858d_ip16e.jpg', 'active', '2025-06-12 21:02:32', '2025-06-12 21:02:32'),
(8, 7, 'iPhone 16 Pro', 'iPhone 16 Pro. Featuring a stunning titanium design. Camera Control. 4K 120 fps Dolby Vision. And the A18 Pro chip.', 60990.00, 1, 1215, 'uploads/products/684b40ddbd162_iPhone16Pro.jpg', 'active', '2025-06-12 21:04:29', '2025-06-12 21:04:29'),
(9, 7, 'iPhone 15 Pro Max', 'iPhone 15 Pro Max. Forged in titanium and featuring the groundbreaking A17 Pro chip, a customizable Action button, and the most powerful iPhone camera system ever', 94990.00, 1, 2331, 'uploads/products/684b41f85546e_iPhone15ProMax.jpg', 'active', '2025-06-12 21:09:12', '2025-06-12 21:09:12'),
(10, 7, 'iPhone 13', 'A superbright display in a durable design. Hollywood-worthy video shooting made easy. A lightning-fast chip. And a big boost in battery life you’ll notice every day.', 28990.00, 1, 6522, 'uploads/products/684b9f397f37d_ip13.jpg', 'active', '2025-06-13 03:47:05', '2025-06-13 07:56:45'),
(11, 7, 'iPad Pro', 'Data plan required. 5G is available in select markets and through select carriers. Speeds vary based on site conditions and carrier. For details on 5G support, contact your carrier and see apple.com/ipad/cellular. Wi-Fi 6E available in countries and regions where supported', 94990.00, 1, 5465, 'uploads/products/684b9fb06d75d_ipadpro.jpg', 'active', '2025-06-13 03:49:04', '2025-06-13 03:49:04'),
(12, 7, 'Nike Zoom Vomero 5', 'Carve out a new lane for yourself in the Zoom Vomero 5. A richly layered design pairs airy textiles with synthetic leather and plastic accents to create a complex upper that\'s easy to style. And check out the insole, which celebrates iconic running coach and Nike co-founder Bill Bowerman.', 8895.00, 2, 1353, 'uploads/products/684ba11d41dd6_nikezoom.jpg', 'active', '2025-06-13 03:55:09', '2025-06-13 03:55:09'),
(13, 7, 'Air Jordan 1 Low OG', 'The Air Jordan 1 Low OG remakes the classic sneaker with new colours and textures. Premium materials and accents give fresh expression to an all-time favourite.', 7895.00, 2, 5665, 'uploads/products/684ba17bdffbb_airjordan1low.jpg', 'active', '2025-06-13 03:56:43', '2025-06-13 03:56:43'),
(14, 7, 'Nike Pegasus 41 \"Jakob Ingebrigtsen\"', 'Responsive cushioning in the Pegasus provides an energised ride for everyday road running. Experience lighter-weight energy return with dual Air Zoom units and a ReactX foam midsole. Plus, improved engineered mesh on the upper decreases weight and increases breathability. This version focuses on what matters most for Jakob Ingebrigtsen: finishing first.', 7895.00, 2, 8462, 'uploads/products/684ba1ca99a0a_pegasus.jpg', 'active', '2025-06-13 03:58:02', '2025-06-13 03:58:02'),
(15, 7, 'NIKE P-6000 PRM', 'A mash-up of Pegasus sneakers past, the Nike P-6000 takes early \'00s running style to modern heights. Combining sporty lines with leather, it\'s the perfect mix of head-turning looks and comfort. Plus, its foam cushioning adds a lifted, athletics-inspired stance and unbelievable cushioning.', 7295.00, 2, 4465, 'uploads/products/684ba2126d624_nikep6000.jpg', 'active', '2025-06-13 03:59:14', '2025-06-13 03:59:14'),
(16, 7, 'Nike LD-1000', 'Originally released in 1977, the LD-1000\'s innovative, dramatically flared heel was created to support long-distance runners. A fan favourite, now you can get your hands on one of Nike\'s most famous innovations too.', 6295.00, 2, 542211, 'uploads/products/684ba2707a56b_nikeld.jpg', 'active', '2025-06-13 04:00:48', '2025-06-13 04:00:48'),
(17, 7, '2022 Apple TV 4K (3rd generation)', 'Apple TV 4K lets you watch shows and movies in stunning 4K Dolby Vision and HDR10+. Get theater-like Spatial Audio with Dolby Atmos that immerses you in sound. Use it as a home hub to connect and control smart home accessories.', 9490.00, 1, 6515, 'uploads/products/684ba31411a37_2022.jpg', 'active', '2025-06-13 04:03:32', '2025-06-13 04:03:32'),
(18, 7, 'Nike Alphafly 3', 'Fine-tuned for marathon speed, the Alphafly 3 helps push you beyond what you thought possible. Three innovative technologies power your run: a double dose of Air Zoom units helps launch you into your next step; a full-length carbon-fibre plate helps propel you forwards with ease; and a heel-to-toe ZoomX foam midsole helps keep you fresh from start to 26.2. Time to leave your old personal records in the dust.', 14495.00, 2, 521, 'uploads/products/684ba3644ea81_alphafly.jpg', 'active', '2025-06-13 04:04:52', '2025-06-13 04:04:52'),
(19, 7, 'QA55S95DAGXXP SAMSUNG 55\" OLED 4K SMART TV', 'Product Overview\r\n\r\nOLED HDR Pro\r\nObject Tracking Sound+ with Dolby Atmos®\r\nMotion Xcelerator Turbo Pro (144Hz)\r\nOLED Glare Free Anti Reflection\r\nAttachable Slim One Connect Box', 106990.00, 3, 620, 'uploads/products/684ba40b5be8a_oled4k.jpg', 'active', '2025-06-13 04:07:39', '2025-06-13 07:42:11'),
(21, 7, 'LED-75C655 TCL 75`` GOOGLE QLED 4K TV', 'Product Overview\r\n\r\nUni body, Metallic, Slim, Two feet Stands\r\n4K Quantum Dot \r\nWCG up to 95% DCI-P3\r\nUp to 600 Nits Peak Brightness\r\nDolby Vision & HDR10+\r\nMEMC 60Hz \r\nDLG 120Hz', 59990.00, 3, 540, 'uploads/products/684ba5061f6fd_led.jpg', 'active', '2025-06-13 04:11:50', '2025-06-25 01:49:09');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
CREATE TABLE IF NOT EXISTS `reviews` (
  `review_id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `user_id` int NOT NULL,
  `rating` int NOT NULL,
  `comment` text,
  `review_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`review_id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`)
) ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(100) NOT NULL,
  `role` enum('buyer','seller','admin') NOT NULL DEFAULT 'buyer',
  `registration_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `email_notifications` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `address`, `contact_number`, `email`, `username`, `password`, `role`, `registration_date`, `email_notifications`) VALUES
(7, 'Froiland Anthony P. Gutierrez', 'P-2 San Pablo, Sison, Surigao del Norte', '09669531545', 'gfroilandanthony@gmail.com', 'froy', 'froilandqwe', '', '2025-06-12 20:54:32', 0),
(8, 'Froiland Gutierrez', 'P-2 San Pablo, Sison, Surigao del Norte', '32165498778', 'froiland@gmail.com', 'pooyland', '987654', '', '2025-06-12 20:58:26', 0),
(9, 'Mark Sitoy', 'Mat-i gwapo', '9394464', 'sitooooy@gmail.com', 'sitoy', '741852', '', '2025-06-12 20:59:08', 0),
(10, 'Mark James Cademia', 'Cayutan lugar ni Janglen', '8545218854', 'emjaycad@gmail.com', 'emjay', '963369', '', '2025-06-12 21:01:10', 0),
(11, 'sir pogi', 'audhsfad afddfa', '9985659', 'pogooo@gmail.com', 'pogi', '963852', '', '2025-06-13 07:40:41', 0);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
