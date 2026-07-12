-- MySQL dump 10.13  Distrib 8.0.43, for Linux (x86_64)
--
-- Host: localhost    Database: dejati
-- ------------------------------------------------------
-- Server version	8.0.43

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `tb_category`
--

DROP TABLE IF EXISTS `tb_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tb_category` (
  `id_cat` int NOT NULL AUTO_INCREMENT,
  `name_cat` varchar(200) NOT NULL,
  `icon` varchar(50) NOT NULL,
  PRIMARY KEY (`id_cat`)
) ENGINE=MyISAM AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb_category`
--

LOCK TABLES `tb_category` WRITE;
/*!40000 ALTER TABLE `tb_category` DISABLE KEYS */;
INSERT INTO `tb_category` VALUES (1,'Coffee','coffee'),(2,'Meals','yoshoku'),(3,'Mie & Pasta','ramen_dining'),(4,'Rice Bowl','washoku'),(5,'Ala Carte','fastfood'),(6,'Snacks','cookie'),(7,'Tea Based','local_florist'),(8,'Manual Brew','coffee_maker'),(9,'DeJati\'s Tropical','local_bar'),(11,'Aneka Juice','blender'),(12,'Milk Based','grocery');
/*!40000 ALTER TABLE `tb_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb_datacafe`
--

DROP TABLE IF EXISTS `tb_datacafe`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tb_datacafe` (
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
) ENGINE=MyISAM AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb_datacafe`
--

LOCK TABLES `tb_datacafe` WRITE;
/*!40000 ALTER TABLE `tb_datacafe` DISABLE KEYS */;
INSERT INTO `tb_datacafe` VALUES (12,'Paket Chicken Katsu',2,0,NULL,NULL,35000,'12_paket_chicken_katsu.jpg','2025-08-15 20:28:01',1),(11,'French Fries',6,0,NULL,NULL,22000,'11_french_fries.jpg','2025-08-14 23:25:25',1),(10,'Paket Se\'i Sapi',2,0,NULL,NULL,40000,'10_paket_se_i_sapi.jpg','2025-08-14 21:47:20',1),(9,'Paket Ayam Geprek',2,1,'Hemat;Komplit','27000;30000',NULL,'9_ayam_geprek.jpg','2025-08-15 21:59:57',1),(13,'Spaghetti Aglio Olio',3,0,NULL,NULL,32000,'13_spaghetti_aglio_olio.jpg','2025-08-15 20:40:24',1),(14,'Spaghetti Bolognese',3,0,NULL,NULL,35000,'14_spaghetti_bolognese.jpg','2025-08-15 20:43:38',1),(15,'Spagehtti Carbonara',3,0,NULL,NULL,37000,'15_spagehtti_carbonara.jpg','2025-08-15 20:44:37',1),(16,'Rice Bowl Beef',4,1,'Beef Teriyaki;Beef Blackpepper','40000;40000',NULL,'16_rice_bowl_beef.jpeg','2025-08-17 20:19:45',1),(17,'Ricebowl Chicken',4,1,'Chicken Asam Manis;Chicken Teriyaki','35000;35000',NULL,'17_ricebowl_chicken.jpeg','2025-08-17 15:07:27',1),(18,'Paket Iga Bakar',2,0,NULL,NULL,50000,'18_paket_iga_bakar.jpeg','2025-08-17 15:08:59',1),(19,'Mie Goreng Dejati',3,0,NULL,NULL,22000,'19_mie_goreng_dejati.jpeg','2025-08-17 15:11:41',1),(20,'Nasi Goreng',2,1,'Spesial;Kampung','25000;20000',NULL,'20_nasi_goreng.jpeg','2025-08-17 15:13:25',1),(21,'Paket Ayam Kampung',2,1,'Hemat;Komplit','37000;40000',NULL,'21_paket_ayam_kampung.jpeg','2025-08-17 15:15:02',1),(22,'Tahu Walik',6,0,NULL,NULL,20000,'22_tahu_walik.jpeg','2025-08-17 15:16:09',1),(23,'Dimsum',6,0,NULL,NULL,22000,'23_dimsum.jpeg','2025-08-17 15:18:27',1),(24,'Pempek',6,0,NULL,NULL,25000,'24_pempek.jpeg','2025-08-17 15:19:17',1),(25,'Zuppa Soup',6,0,NULL,NULL,27000,'25_zuppa_soup.jpeg','2025-08-17 15:20:03',1),(26,'Onion Ring',6,0,NULL,NULL,15000,'26_onion_ring.jpeg','2025-08-17 15:32:33',0),(27,'Onion Ring',6,0,NULL,NULL,15000,'27_onion_ring.jpeg','2025-08-17 15:34:13',1),(28,'Mix Snack ',6,1,'1 (asin);2 (manis)','35000;35000',NULL,'28_mix_snack.jpeg','2025-08-17 15:36:31',1),(29,'Paket Ayam Bakar',2,1,'Hemat;Komplit','27000;30000',NULL,'29_paket_ayam_bakar.jpeg','2025-08-17 15:37:20',1),(30,'Mie Godog',3,0,NULL,NULL,25000,'30_mie_godog.jpeg','2025-08-17 15:39:16',1),(31,'Vietnam Drip',8,0,NULL,NULL,25000,'31_vietnam_drip.jpeg','2025-08-17 15:41:22',1),(33,'Latte Original',1,1,'Hot;Cold','22000;23000',NULL,'33_latte_original.jpeg','2025-08-17 16:13:49',1),(34,'Cappucino',1,1,'Hot;Cold','22000;23000',NULL,'34_cappucino.jpeg','2026-07-06 22:13:15',1),(35,'Dejati Signature',1,1,'Hot;Ice','23000;25000',NULL,'35_dejati_signature.jpeg','2026-07-05 08:09:09',1),(36,'Americano',1,1,'Hot;Ice','20000;22000',NULL,'36_americano.jpeg','2026-07-06 22:13:15',1),(37,'Coffee Mocktail',1,0,NULL,NULL,30000,'37_coffee_mocktail.jpeg','2025-08-17 16:21:38',1),(38,'Caramel Macchiato ',1,0,NULL,NULL,25000,'38_caramel_macchiato.jpeg','2025-08-17 16:22:20',1),(39,'Mint Latte',1,1,'Avocado;Red Velvet;Matcha','25000;25000;25000',NULL,'39_mint_latte.jpeg','2025-08-17 16:23:33',1),(40,'Yogurt Punch ',9,0,NULL,NULL,30000,'40_yogurt_punch.jpeg','2025-08-17 16:35:11',0),(41,'Affogato',1,0,NULL,NULL,26000,'41_affogato.jpeg','2026-07-06 22:13:15',1),(42,'Sewindu',12,0,NULL,NULL,30000,'42_sewindu.jpeg','2025-08-17 16:38:58',1),(43,'Royal Milk Tea',12,0,NULL,NULL,30000,'43_royal_milk_tea.jpeg','2025-08-17 16:40:02',1),(44,'Milkshake ',12,1,'Vanilla;Caramel;Hazelnut;Chocolate;Taro','27000;27000;27000;27000;27000',NULL,'44_milkshake.jpeg','2025-08-17 16:43:34',1),(75,'Ketoprak Jati',2,1,'Ketoprak Pakai Telor;Ketoprak Cabe 1','18000;22000',NULL,'','2026-07-05 11:20:03',1);
/*!40000 ALTER TABLE `tb_datacafe` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb_datacarwash`
--

DROP TABLE IF EXISTS `tb_datacarwash`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tb_datacarwash` (
  `id_produk` int NOT NULL AUTO_INCREMENT,
  `produk` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `biaya` int NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  PRIMARY KEY (`id_produk`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb_datacarwash`
--

LOCK TABLES `tb_datacarwash` WRITE;
/*!40000 ALTER TABLE `tb_datacarwash` DISABLE KEYS */;
INSERT INTO `tb_datacarwash` VALUES (1,'Promo Cuci Mobil',30000,'2024-07-19 07:44:01',6),(2,'Cuci Motor',20000,'2024-06-29 17:04:35',6),(4,'Cuci Mobil Hidrolik',60000,'2026-07-05 01:28:41',1),(5,'Salon Mobil',70000,'2024-06-29 17:06:28',6),(6,'Promo Hidrolik Dejati Car Wash ',45000,'2024-07-19 07:48:02',NULL),(7,'Dejati car wash vacum',35000,'2026-07-08 06:20:48',1);
/*!40000 ALTER TABLE `tb_datacarwash` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-12 17:10:43
