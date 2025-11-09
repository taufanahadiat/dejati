-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Oct 03, 2025 at 12:17 PM
-- Server version: 9.1.0
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dejati`
--

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `table_number` varchar(10) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `total_amount` int DEFAULT NULL,
  `paid_amount` int DEFAULT NULL,
  `change_amount` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `table_number`, `payment_method`, `total_amount`, `paid_amount`, `change_amount`, `created_at`) VALUES
(4, '8', 'cash', 78000, 80000, 2000, '2025-08-24 22:53:15'),
(3, '7', 'cash', 66000, 80000, 14000, '2025-08-24 22:17:24'),
(6, '4', 'cash', 47000, 60000, 13000, '2025-08-24 23:10:32'),
(7, '1', 'cash', 80000, 80000, 0, '2025-08-24 23:21:15'),
(8, '12', 'credit_card', 257000, 257000, 0, '2025-08-27 20:02:04');

-- --------------------------------------------------------

--
-- Table structure for table `order_carwash`
--

DROP TABLE IF EXISTS `order_carwash`;
CREATE TABLE IF NOT EXISTS `order_carwash` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_tr` int DEFAULT NULL,
  `id_prod` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `qty` int DEFAULT NULL,
  `unit_price` int DEFAULT NULL,
  `total` int DEFAULT NULL,
  `nopol` varchar(50) DEFAULT NULL,
  `service` varchar(100) DEFAULT NULL,
  `ukuran` varchar(50) DEFAULT NULL,
  `vacuum` enum('yes','no') DEFAULT NULL,
  `profit_pegawai` int DEFAULT '0',
  `profit_management` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `order_id` (`id_tr`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `order_carwash`
--

INSERT INTO `order_carwash` (`id`, `id_tr`, `id_prod`, `item_name`, `qty`, `unit_price`, `total`, `nopol`, `service`, `ukuran`, `vacuum`, `profit_pegawai`, `profit_management`) VALUES
(1, 4, '4', 'Cuci Mobil Hidrolik', 1, 55000, 55000, 'BM 7287 HJ', 'Budi', 'Mobil Besar', 'yes', 0, 0),
(2, 6, '2', 'Cuci Motor', 1, 25000, 25000, 'F 2222 NI', 'Heru', 'Mobil Besar', 'yes', 0, 0),
(3, 7, '6', 'Promo Hidrolik Dejati Car Wash ', 1, 45000, 45000, 'BM 2782 NB', 'Budi', 'Mobil Besar', '', 13500, 31500),
(4, 8, '5', 'Salon Mobil', 1, 70000, 70000, 'As 1605 FF', 'Heru', 'Mobil Sedang', '', 21000, 49000),
(5, 8, '2', 'Cuci Motor', 1, 20000, 20000, 'F 2222 NI', 'Budi', 'Motor', '', 6000, 14000);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_tr` int DEFAULT NULL,
  `id_prod` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `item_price` int DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `total` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`id_tr`)
) ENGINE=MyISAM AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `id_tr`, `id_prod`, `item_name`, `item_price`, `quantity`, `total`) VALUES
(7, 4, '35', 'Dejati Signature (Hot)', 23000, 1, 23000),
(5, 3, '11', 'French Fries', 22000, 2, 44000),
(6, 3, '19', 'Mie Goreng Dejati', 22000, 1, 22000),
(9, 6, '23', 'Dimsum', 22000, 1, 22000),
(10, 7, '14', 'Spaghetti Bolognese', 35000, 1, 35000),
(11, 8, '12', 'Paket Chicken Katsu', 35000, 1, 35000),
(12, 8, '17', 'Ricebowl Chicken (Chicken Teriyaki)', 35000, 3, 105000),
(13, 8, '25', 'Zuppa Soup', 27000, 1, 27000);

-- --------------------------------------------------------

--
-- Table structure for table `tb_category`
--

DROP TABLE IF EXISTS `tb_category`;
CREATE TABLE IF NOT EXISTS `tb_category` (
  `id_cat` int NOT NULL AUTO_INCREMENT,
  `name_cat` varchar(200) NOT NULL,
  `icon` varchar(50) NOT NULL,
  PRIMARY KEY (`id_cat`)
) ENGINE=MyISAM AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tb_category`
--

INSERT INTO `tb_category` (`id_cat`, `name_cat`, `icon`) VALUES
(1, 'Coffee', 'coffee'),
(2, 'Meals', 'yoshoku'),
(3, 'Mie & Pasta', 'ramen_dining'),
(4, 'Rice Bowl', 'washoku'),
(5, 'Ala Carte', 'fastfood'),
(6, 'Snacks', 'cookie'),
(7, 'Tea Based', 'local_florist'),
(8, 'Manual Brew', 'coffee_maker'),
(9, 'DeJati\'s Tropical', 'local_bar'),
(11, 'Aneka Juice', 'blender'),
(12, 'Milk Based', 'grocery');

-- --------------------------------------------------------

--
-- Table structure for table `tb_datacafe`
--

DROP TABLE IF EXISTS `tb_datacafe`;
CREATE TABLE IF NOT EXISTS `tb_datacafe` (
  `id_prod` int NOT NULL AUTO_INCREMENT,
  `nama_prod` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `id_cat` int DEFAULT NULL,
  `variant` tinyint(1) NOT NULL,
  `nama_var` varchar(100) DEFAULT NULL,
  `biaya_var` varchar(200) DEFAULT NULL,
  `biaya` int DEFAULT NULL,
  `foto` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `updated_at` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int NOT NULL,
  PRIMARY KEY (`id_prod`)
) ENGINE=MyISAM AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tb_datacafe`
--

INSERT INTO `tb_datacafe` (`id_prod`, `nama_prod`, `id_cat`, `variant`, `nama_var`, `biaya_var`, `biaya`, `foto`, `updated_at`, `updated_by`) VALUES
(12, 'Paket Chicken Katsu', 2, 0, NULL, NULL, 35000, '12_paket_chicken_katsu.jpg', '2025-08-15 20:28:01', 1),
(11, 'French Fries', 6, 0, NULL, NULL, 22000, '11_french_fries.jpg', '2025-08-14 23:25:25', 1),
(10, 'Paket Se\'i Sapi', 2, 0, NULL, NULL, 40000, '10_paket_se_i_sapi.jpg', '2025-08-14 21:47:20', 1),
(9, 'Paket Ayam Geprek', 2, 1, 'Hemat;Komplit', '27000;30000', NULL, '9_ayam_geprek.jpg', '2025-08-15 21:59:57', 1),
(13, 'Spaghetti Aglio Olio', 3, 0, NULL, NULL, 32000, '13_spaghetti_aglio_olio.jpg', '2025-08-15 20:40:24', 1),
(14, 'Spaghetti Bolognese', 3, 0, NULL, NULL, 35000, '14_spaghetti_bolognese.jpg', '2025-08-15 20:43:38', 1),
(15, 'Spagehtti Carbonara', 3, 0, NULL, NULL, 37000, '15_spagehtti_carbonara.jpg', '2025-08-15 20:44:37', 1),
(16, 'Rice Bowl Beef', 4, 1, 'Beef Teriyaki;Beef Blackpepper', '40000;40000', NULL, '16_rice_bowl_beef.jpeg', '2025-08-17 20:19:45', 1),
(17, 'Ricebowl Chicken', 4, 1, 'Chicken Asam Manis;Chicken Teriyaki', '35000;35000', NULL, '17_ricebowl_chicken.jpeg', '2025-08-17 15:07:27', 1),
(18, 'Paket Iga Bakar', 2, 0, NULL, NULL, 50000, '18_paket_iga_bakar.jpeg', '2025-08-17 15:08:59', 1),
(19, 'Mie Goreng Dejati', 3, 0, NULL, NULL, 22000, '19_mie_goreng_dejati.jpeg', '2025-08-17 15:11:41', 1),
(20, 'Nasi Goreng', 2, 1, 'Spesial;Kampung', '25000;20000', NULL, '20_nasi_goreng.jpeg', '2025-08-17 15:13:25', 1),
(21, 'Paket Ayam Kampung', 2, 1, 'Hemat;Komplit', '37000;40000', NULL, '21_paket_ayam_kampung.jpeg', '2025-08-17 15:15:02', 1),
(22, 'Tahu Walik', 6, 0, NULL, NULL, 20000, '22_tahu_walik.jpeg', '2025-08-17 15:16:09', 1),
(23, 'Dimsum', 6, 0, NULL, NULL, 22000, '23_dimsum.jpeg', '2025-08-17 15:18:27', 1),
(24, 'Pempek', 6, 0, NULL, NULL, 25000, '24_pempek.jpeg', '2025-08-17 15:19:17', 1),
(25, 'Zuppa Soup', 6, 0, NULL, NULL, 27000, '25_zuppa_soup.jpeg', '2025-08-17 15:20:03', 1),
(26, 'Onion Ring', 6, 0, NULL, NULL, 15000, '26_onion_ring.jpeg', '2025-08-17 15:32:33', 0),
(27, 'Onion Ring', 6, 0, NULL, NULL, 15000, '27_onion_ring.jpeg', '2025-08-17 15:34:13', 1),
(28, 'Mix Snack ', 6, 1, '1 (asin);2 (manis)', '35000;35000', NULL, '28_mix_snack.jpeg', '2025-08-17 15:36:31', 1),
(29, 'Paket Ayam Bakar', 2, 1, 'Hemat;Komplit', '27000;30000', NULL, '29_paket_ayam_bakar.jpeg', '2025-08-17 15:37:20', 1),
(30, 'Mie Godog', 3, 0, NULL, NULL, 25000, '30_mie_godog.jpeg', '2025-08-17 15:39:16', 1),
(31, 'Vietnam Drip', 8, 0, NULL, NULL, 25000, '31_vietnam_drip.jpeg', '2025-08-17 15:41:22', 1),
(33, 'Latte Original', 1, 1, 'Hot;Cold', '22000;23000', NULL, '33_latte_original.jpeg', '2025-08-17 16:13:49', 1),
(34, 'Cappucino', 1, 1, 'Hot;Cold', '22000;23000', NULL, '34_cappucino.jpeg', '2025-08-17 16:15:58', 1),
(35, 'Dejati Signature', 1, 1, 'Hot;Ice', '23000;25000', NULL, '35_dejati_signature.jpeg', '2025-08-17 16:18:25', 1),
(36, 'Americano', 1, 1, 'Hot;Ice', '20000;22000', NULL, '36_americano.jpeg', '2025-08-17 16:21:04', 1),
(37, 'Coffee Mocktail', 1, 0, NULL, NULL, 30000, '37_coffee_mocktail.jpeg', '2025-08-17 16:21:38', 1),
(38, 'Caramel Macchiato ', 1, 0, NULL, NULL, 25000, '38_caramel_macchiato.jpeg', '2025-08-17 16:22:20', 1),
(39, 'Mint Latte', 1, 1, 'Avocado;Red Velvet;Matcha', '25000;25000;25000', NULL, '39_mint_latte.jpeg', '2025-08-17 16:23:33', 1),
(40, 'Yogurt Punch ', 9, 0, NULL, NULL, 30000, '40_yogurt_punch.jpeg', '2025-08-17 16:35:11', 0),
(41, 'Affogato ', 1, 0, NULL, NULL, 25000, '41_affogato.jpeg', '2025-08-17 16:38:23', 1),
(42, 'Sewindu', 12, 0, NULL, NULL, 30000, '42_sewindu.jpeg', '2025-08-17 16:38:58', 1),
(43, 'Royal Milk Tea', 12, 0, NULL, NULL, 30000, '43_royal_milk_tea.jpeg', '2025-08-17 16:40:02', 1),
(44, 'Milkshake ', 12, 1, 'Vanilla;Caramel;Hazelnut;Chocolate;Taro', '27000;27000;27000;27000;27000', NULL, '44_milkshake.jpeg', '2025-08-17 16:43:34', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tb_datacarwash`
--

DROP TABLE IF EXISTS `tb_datacarwash`;
CREATE TABLE IF NOT EXISTS `tb_datacarwash` (
  `id_produk` int NOT NULL AUTO_INCREMENT,
  `produk` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `biaya` int NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  PRIMARY KEY (`id_produk`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `tb_datacarwash`
--

INSERT INTO `tb_datacarwash` (`id_produk`, `produk`, `biaya`, `updated_at`, `updated_by`) VALUES
(1, 'Promo Cuci Mobil', 30000, '2024-07-19 07:44:01', 6),
(2, 'Cuci Motor', 20000, '2024-06-29 17:04:35', 6),
(4, 'Cuci Mobil Hidrolik', 50000, '2024-06-29 17:03:26', 6),
(5, 'Salon Mobil', 70000, '2024-06-29 17:06:28', 6),
(6, 'Promo Hidrolik Dejati Car Wash ', 45000, '2024-07-19 07:48:02', NULL),
(7, 'Dejati car wash vacum ', 35000, '2024-07-19 07:48:38', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tb_pegawai`
--

DROP TABLE IF EXISTS `tb_pegawai`;
CREATE TABLE IF NOT EXISTS `tb_pegawai` (
  `kode_pegawai` char(10) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `nama_pegawai` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `telepon` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`kode_pegawai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_pegawai`
--

INSERT INTO `tb_pegawai` (`kode_pegawai`, `nama_pegawai`, `telepon`, `created_at`) VALUES
('PGW-0005', 'Asep', '000', '2024-07-19 07:50:20'),
('PGW-0006', 'Budi', '000', '2024-07-19 07:50:31'),
('PGW-0007', 'Dika', '000', '2024-07-19 07:50:39');

-- --------------------------------------------------------

--
-- Table structure for table `tb_user`
--

DROP TABLE IF EXISTS `tb_user`;
CREATE TABLE IF NOT EXISTS `tb_user` (
  `id_user` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `nama_user` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `level` enum('Administrator','Kasir','Karyawan') NOT NULL,
  `status` enum('Aktif','Tidak Aktif') NOT NULL,
  `last_logged_in` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_user`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `tb_user`
--

INSERT INTO `tb_user` (`id_user`, `username`, `nama_user`, `password`, `level`, `status`, `last_logged_in`, `ip_address`, `created_at`) VALUES
(1, 'admin', 'Admin Dejati', '$2y$10$/4rfsIP6KB0iZis9H4pLr.P5v1OixyiWXg/4oi1a7d.8yaWM71pni', 'Administrator', 'Aktif', '2025-10-03 19:17:12', '::1', '2024-06-29 16:51:33'),
(2, 'kasir', 'Kasir', '$2y$10$/4rfsIP6KB0iZis9H4pLr.P5v1OixyiWXg/4oi1a7d.8yaWM71pni', 'Kasir', 'Aktif', NULL, NULL, '2024-06-29 16:51:33'),
(3, 'jati35', 'Dejati Karyawan', '$2y$10$/4rfsIP6KB0iZis9H4pLr.P5v1OixyiWXg/4oi1a7d.8yaWM71pni', 'Karyawan', 'Aktif', NULL, NULL, '2024-06-29 16:51:33'),
(6, 'user', 'User', '$2y$10$/4rfsIP6KB0iZis9H4pLr.P5v1OixyiWXg/4oi1a7d.8yaWM71pni', 'Kasir', 'Aktif', NULL, NULL, '2024-06-29 16:51:33'),
(7, 'arta', 'arta', '$2y$10$bOIAOr4wgrmwR1AsSLrU9OHEhNLyfKvXA2KVBTYezXr1r1NPDqSLG', 'Kasir', 'Aktif', '2024-07-19 14:56:51', '192.168.0.102', '2024-07-19 07:40:47'),
(4, 'aca', 'aca', '$2y$10$2e/VmKPz25duTvIJVlMQTejDYs.zZ1i/g1aciWbOgoSTNyvGaSRT6', 'Kasir', 'Aktif', NULL, NULL, '2024-07-19 07:41:35'),
(5, 'libia', 'libia', '$2y$10$5S4HNJ7VHfAoMtJMr4o5m.TazteZIBXdN5pz1UDn/QYPOuognnbDW', 'Kasir', 'Aktif', NULL, NULL, '2024-07-19 07:42:29');
COMMIT;


CREATE TABLE IF NOT EXISTS `tb_utility` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_stock` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `item_number` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tb_stock` (`id`, `name`, `item_number`) VALUES
(1, 'Soto', 2),
(2, 'Iga', 8);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
