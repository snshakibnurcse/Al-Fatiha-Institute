-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: afidb
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `accounts_transactions`
--

DROP TABLE IF EXISTS `accounts_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounts_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_no` varchar(30) NOT NULL,
  `type` enum('Income','Expense') NOT NULL,
  `category` varchar(100) NOT NULL,
  `title` varchar(200) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `transaction_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT 'Cash',
  `reference_no` varchar(100) DEFAULT NULL,
  `voucher_file` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_no` (`transaction_no`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `accounts_transactions_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounts_transactions`
--

LOCK TABLES `accounts_transactions` WRITE;
/*!40000 ALTER TABLE `accounts_transactions` DISABLE KEYS */;
INSERT INTO `accounts_transactions` VALUES (1,'TXN-2025-001','Income','Student Tuition & Fees','Monthly Tuition Fees Collection',145000.00,'2026-10-09','bKash / Bank','REF-OCT-FEES',NULL,'Total student monthly tuition fee received for current term.',4,'2026-10-09 18:09:31'),(2,'TXN-2025-002','Income','Admission Form & Registration','New Session Admission Form Sales',35000.00,'2026-10-09','Cash','ADM-FORM-101',NULL,'Collected from 35 new applicants.',4,'2026-10-09 18:09:31'),(3,'TXN-2025-003','Income','Donations & Grants','Islamic Trust Education Grant',100000.00,'2026-09-09','Bank Transfer','TRF-GRANT-99',NULL,'Special grant for Cambridge Quran & Tajweed lab.',4,'2026-10-09 18:09:31'),(4,'TXN-2025-004','Expense','Staff Salaries & Honorarium','Faculty & Staff Monthly Payroll',85000.00,'2026-10-09','Bank Transfer','PAY-2025-10',NULL,'Monthly salary disbursement for teachers and support staff.',4,'2026-10-09 18:09:31'),(5,'TXN-2025-005','Expense','Electricity & Utilities','Electricity & Internet Bill Payment',14200.00,'2026-10-09','bKash','BILL-ELEC-402',NULL,'PDB monthly power bill & high-speed fiber internet.',4,'2026-10-09 18:09:31'),(6,'TXN-2025-006','Expense','Office Stationery & Supplies','Cambridge Early Years Workbooks & Printing',18500.00,'2026-09-09','Cash','VOUCH-MEMO-88',NULL,'Purchased 200 sets of phonics workbooks and printing paper.',4,'2026-10-09 18:09:31'),(7,'TXN-2025-007','Expense','Building Rent & Maintenance','Campus Maintenance & AC Service',12000.00,'2026-08-09','Cash','MAINT-V-001',NULL,'Servicing air conditioners in classrooms and electrical repair.',4,'2026-10-09 18:09:31'),(8,'TXN-INC-202610-003','Income','Student Tuition & Fees','Tuition Fee - Abdullah Al-Mamun (Year One, Roll: 1)',2000.00,'2026-10-09','Cash','INV-202610-001 / 1076',NULL,'Auto-synced income from Paid Fee Invoice #INV-202610-001',4,'2026-10-09 18:41:24'),(9,'TXN-INC-202610-027','Income','Student Tuition & Fees','Tuition Fee - Sumaiya Jannat (KG (Kindergarten), Roll: 2)',1700.00,'2026-10-12','bKash Online','INV-202610-111 / BKASH-TXN-1234',NULL,'Auto-synced income from Paid Fee Invoice #INV-202610-111',1,'2026-10-12 08:24:52'),(10,'TXN-INC-202610-044','Income','Book & Uniform Sales','5 Pcs Exercise Book (খাতা) - Rayhan Chowdhury (Play Group, Roll: 1)',250.00,'2026-10-12','Cash','INV-BK-202610-656 / CASH-REC-01',NULL,'Auto-synced income from Paid Invoice #INV-BK-202610-656 (5 Pcs Exercise Book (খাতা))',NULL,'2026-10-12 10:46:58'),(11,'TXN-INC-202610-045','Income','Book & Uniform Sales','Official School Diary 2026 - Rayhan Chowdhury (Play Group, Roll: 1)',150.00,'2026-10-12','bKash / Mobile Banking','INV-DRY-202610-679 / BKASH-TRX-998877',NULL,'Auto-synced income from Paid Invoice #INV-DRY-202610-679 (Official School Diary 2026)',NULL,'2026-10-12 10:46:58'),(12,'TXN-INC-202610-008','Income','Student Tuition & Fees','Monthly Tuition Fee - October 2026 - Ayesha Siddiqa (Play Group, Roll: 2)',1700.00,'2026-10-25','Cheque','INV-202610-102',NULL,'Auto-synced income from Paid Invoice #INV-202610-102 (Monthly Tuition Fee - October 2026)',1,'2026-10-25 04:43:16');
/*!40000 ALTER TABLE `accounts_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admissions`
--

DROP TABLE IF EXISTS `admissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_no` varchar(30) NOT NULL,
  `child_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `dob` date NOT NULL,
  `target_class_id` int(11) NOT NULL,
  `father_name` varchar(100) NOT NULL,
  `mother_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text NOT NULL,
  `previous_school` varchar(150) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `birth_reg_or_passport` varchar(100) DEFAULT NULL,
  `age_jan_1` varchar(50) DEFAULT NULL,
  `blood_group` varchar(10) DEFAULT NULL,
  `nationality` varchar(80) DEFAULT 'Bangladeshi',
  `religion` varchar(80) DEFAULT 'Islam',
  `permanent_address` text DEFAULT NULL,
  `father_occupation` varchar(100) DEFAULT NULL,
  `father_organization` varchar(150) DEFAULT NULL,
  `father_location` varchar(150) DEFAULT NULL,
  `father_nid_passport` varchar(100) DEFAULT NULL,
  `father_present_address` text DEFAULT NULL,
  `mother_occupation` varchar(100) DEFAULT NULL,
  `mother_organization` varchar(150) DEFAULT NULL,
  `mother_location` varchar(150) DEFAULT NULL,
  `mother_phone` varchar(40) DEFAULT NULL,
  `mother_nid_passport` varchar(100) DEFAULT NULL,
  `mother_present_address` text DEFAULT NULL,
  `guardian_is_other` tinyint(1) DEFAULT 0,
  `other_guardian_name` varchar(150) DEFAULT NULL,
  `other_guardian_relationship` varchar(80) DEFAULT NULL,
  `other_guardian_phone` varchar(40) DEFAULT NULL,
  `other_guardian_email` varchar(100) DEFAULT NULL,
  `other_guardian_present_address` text DEFAULT NULL,
  `other_guardian_permanent_address` text DEFAULT NULL,
  `emergency_name` varchar(150) DEFAULT NULL,
  `emergency_relationship` varchar(80) DEFAULT NULL,
  `emergency_phone` varchar(40) DEFAULT NULL,
  `has_allergies` varchar(10) DEFAULT 'No',
  `allergies_list` text DEFAULT NULL,
  `regular_medications` text DEFAULT NULL,
  `has_medical_conditions` varchar(10) DEFAULT 'No',
  `medical_conditions_details` text DEFAULT NULL,
  `preferred_hospital` varchar(190) DEFAULT NULL,
  `transportation_type` varchar(50) DEFAULT 'Own Transport',
  `pickup_point` varchar(190) DEFAULT NULL,
  `dropoff_point` varchar(190) DEFAULT NULL,
  `consent_agreed` tinyint(1) DEFAULT 1,
  `documents_submitted` text DEFAULT NULL,
  `form_data` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_no` (`application_no`),
  KEY `target_class_id` (`target_class_id`),
  CONSTRAINT `admissions_ibfk_1` FOREIGN KEY (`target_class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admissions`
--

LOCK TABLES `admissions` WRITE;
/*!40000 ALTER TABLE `admissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `admissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('Present','Absent','Late','Excused') NOT NULL,
  `remarks` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
INSERT INTO `attendance` VALUES (1,1,4,'2026-10-09','Present','On time');
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_name` varchar(50) NOT NULL,
  `age_group` varchar(50) NOT NULL,
  `section` varchar(10) DEFAULT 'A',
  `room_no` varchar(20) DEFAULT '101',
  `tuition_fee` decimal(10,2) NOT NULL DEFAULT 3500.00,
  `admission_fee` decimal(10,2) NOT NULL DEFAULT 8000.00,
  `book_kit_fee` decimal(10,2) NOT NULL DEFAULT 3500.00,
  `primary_teacher_id` int(11) DEFAULT NULL,
  `backup_teacher_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
INSERT INTO `classes` VALUES (1,'Play Group','2.5 - 3.5 Years','A','101',1700.00,8000.00,3500.00,NULL,NULL),(2,'Nursery','3.5 - 4.5 Years','A','102',1700.00,8000.00,3500.00,NULL,NULL),(3,'KG (Kindergarten)','4.5 - 5.5 Years','A','103',1700.00,8000.00,3500.00,NULL,NULL),(4,'Class One','5.5 - 6.5 Years','A','201',2000.00,8000.00,3500.00,1,NULL),(5,'Class Two','6.5 - 7.5 Years','A','202',2000.00,8000.00,3500.00,NULL,NULL),(6,'Class Three','7-8 Years','A','301',3500.00,12000.00,4500.00,2,NULL);
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fees`
--

DROP TABLE IF EXISTS `fees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(30) NOT NULL,
  `student_id` int(11) NOT NULL,
  `fee_type` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `due_date` date NOT NULL,
  `fee_month` varchar(7) DEFAULT NULL,
  `status` enum('Unpaid','Paid','Partial') DEFAULT 'Unpaid',
  `paid_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `student_id` (`student_id`),
  KEY `fee_month` (`fee_month`),
  CONSTRAINT `fees_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fees`
--

LOCK TABLES `fees` WRITE;
/*!40000 ALTER TABLE `fees` DISABLE KEYS */;
INSERT INTO `fees` VALUES (1,'INV-2025-101',1,'Monthly Tuition Fee - October',4500.00,'2025-10-10','2025-10','Paid','2025-10-05','bKash Online',NULL,NULL),(2,'INV-2026-832',1,'Monthly fees november',1700.00,'2026-10-19','2026-10','Paid','2026-10-10','Cash / Admin',NULL,NULL),(3,'INV-202610-001',1,'Monthly Tuition Fee - October 2026',2000.00,'2026-10-10','2026-10','Paid','2026-10-09','Cash',NULL,NULL),(4,'INV-202609-101',2,'Monthly Tuition Fee - September 2026',1700.00,'2026-09-10','2026-09','Paid','2026-09-08','Cash',NULL,NULL),(5,'INV-202610-101',2,'Monthly Tuition Fee - October 2026',1700.00,'2026-10-10','2026-10','Paid','2026-10-06','bKash Online',NULL,NULL),(6,'INV-202611-101',2,'Monthly Tuition Fee - November 2026',1700.00,'2026-11-10','2026-11','Paid','2026-10-10','Cash',NULL,NULL),(7,'INV-202609-102',3,'Monthly Tuition Fee - September 2026',1700.00,'2026-09-10','2026-09','Paid','2026-09-08','Cash',NULL,NULL),(8,'INV-202610-102',3,'Monthly Tuition Fee - October 2026',1700.00,'2026-10-10','2026-10','Paid','2026-10-25','Cheque','',NULL),(9,'INV-202609-103',4,'Monthly Tuition Fee - September 2026',1700.00,'2026-09-10','2026-09','Paid','2026-09-08','Cash',NULL,NULL),(10,'INV-202610-103',4,'Monthly Tuition Fee - October 2026',1700.00,'2026-10-10','2026-10','Paid','2026-10-06','bKash Online',NULL,NULL),(11,'INV-202611-103',4,'Monthly Tuition Fee - November 2026',1700.00,'2026-11-10','2026-11','Paid','2026-10-10','Cash',NULL,NULL),(12,'INV-202609-104',5,'Monthly Tuition Fee - September 2026',1700.00,'2026-09-10','2026-09','Paid','2026-09-08','Cash',NULL,NULL),(13,'INV-202610-104',5,'Monthly Tuition Fee - October 2026',1700.00,'2026-10-10','2026-10','Unpaid',NULL,NULL,NULL,NULL),(14,'INV-202609-105',6,'Monthly Tuition Fee - September 2026',1700.00,'2026-09-10','2026-09','Paid','2026-09-08','Cash',NULL,NULL),(15,'INV-202610-105',6,'Monthly Tuition Fee - October 2026',1700.00,'2026-10-10','2026-10','Paid','2026-10-06','bKash Online',NULL,NULL),(16,'INV-202609-106',7,'Monthly Tuition Fee - September 2026',1700.00,'2026-09-10','2026-09','Paid','2026-09-08','Cash',NULL,NULL),(17,'INV-202610-106',7,'Monthly Tuition Fee - October 2026',1700.00,'2026-10-10','2026-10','Unpaid',NULL,NULL,NULL,NULL),(18,'INV-202609-107',8,'Monthly Tuition Fee - September 2026',1700.00,'2026-09-10','2026-09','Paid','2026-09-08','Cash',NULL,NULL),(19,'INV-202610-107',8,'Monthly Tuition Fee - October 2026',1700.00,'2026-10-10','2026-10','Paid','2026-10-06','bKash Online',NULL,NULL),(20,'INV-202609-108',9,'Monthly Tuition Fee - September 2026',1700.00,'2026-09-10','2026-09','Paid','2026-09-08','Cash',NULL,NULL),(21,'INV-202610-108',9,'Monthly Tuition Fee - October 2026',1700.00,'2026-10-10','2026-10','Unpaid',NULL,NULL,NULL,NULL),(22,'INV-202609-109',10,'Monthly Tuition Fee - September 2026',1700.00,'2026-09-10','2026-09','Paid','2026-09-08','Cash',NULL,NULL),(23,'INV-202610-109',10,'Monthly Tuition Fee - October 2026',1700.00,'2026-10-10','2026-10','Paid','2026-10-06','bKash Online',NULL,NULL),(24,'INV-202609-110',11,'Monthly Tuition Fee - September 2026',1700.00,'2026-09-10','2026-09','Paid','2026-09-08','Cash',NULL,NULL),(25,'INV-202610-110',11,'Monthly Tuition Fee - October 2026',1700.00,'2026-10-10','2026-10','Unpaid',NULL,NULL,NULL,NULL),(26,'INV-202609-111',12,'Monthly Tuition Fee - September 2026',1700.00,'2026-09-10','2026-09','Paid','2026-09-08','Cash',NULL,NULL),(27,'INV-202610-111',12,'Monthly Tuition Fee - October 2026',1700.00,'2026-10-10','2026-10','Paid','2026-10-12','bKash Online',NULL,NULL),(28,'INV-202609-112',13,'Monthly Tuition Fee - September 2026',1700.00,'2026-09-10','2026-09','Paid','2026-09-08','Cash',NULL,NULL),(29,'INV-202610-112',13,'Monthly Tuition Fee - October 2026',1700.00,'2026-10-10','2026-10','Unpaid',NULL,NULL,NULL,NULL),(30,'INV-202609-113',14,'Monthly Tuition Fee - September 2026',1700.00,'2026-09-10','2026-09','Paid','2026-09-08','Cash',NULL,NULL),(31,'INV-202610-113',14,'Monthly Tuition Fee - October 2026',1700.00,'2026-10-10','2026-10','Paid','2026-10-06','bKash Online',NULL,NULL),(32,'INV-202609-114',15,'Monthly Tuition Fee - September 2026',1700.00,'2026-09-10','2026-09','Paid','2026-09-08','Cash',NULL,NULL),(33,'INV-202610-114',15,'Monthly Tuition Fee - October 2026',1700.00,'2026-10-10','2026-10','Unpaid',NULL,NULL,NULL,NULL),(34,'INV-202609-115',1,'Monthly Tuition Fee - September 2026',2000.00,'2026-09-10','2026-09','Paid','2026-09-08','Cash',NULL,NULL),(35,'INV-202610-115',1,'Monthly Tuition Fee - October 2026',2000.00,'2026-10-10','2026-10','Paid','2026-10-06','bKash Online',NULL,NULL),(36,'INV-202609-116',16,'Monthly Tuition Fee - September 2026',2000.00,'2026-09-10','2026-09','Paid','2026-09-08','Cash',NULL,NULL),(37,'INV-202610-116',16,'Monthly Tuition Fee - October 2026',2000.00,'2026-10-10','2026-10','Paid','2026-10-06','bKash Online',NULL,NULL),(38,'INV-202609-117',17,'Monthly Tuition Fee - September 2026',2000.00,'2026-09-10','2026-09','Paid','2026-09-08','Cash',NULL,NULL),(39,'INV-202610-117',17,'Monthly Tuition Fee - October 2026',2000.00,'2026-10-10','2026-10','Unpaid',NULL,NULL,NULL,NULL),(40,'INV-202609-118',18,'Monthly Tuition Fee - September 2026',2000.00,'2026-09-10','2026-09','Paid','2026-09-08','Cash',NULL,NULL),(41,'INV-202610-118',18,'Monthly Tuition Fee - October 2026',2000.00,'2026-10-10','2026-10','Paid','2026-10-06','bKash Online',NULL,NULL),(42,'INV-202609-119',19,'Monthly Tuition Fee - September 2026',2000.00,'2026-09-10','2026-09','Paid','2026-09-08','Cash',NULL,NULL),(43,'INV-202610-119',19,'Monthly Tuition Fee - October 2026',2000.00,'2026-10-10','2026-10','Unpaid',NULL,NULL,NULL,NULL),(44,'INV-BK-202610-656',2,'5 Pcs Exercise Book (খাতা)',250.00,'2026-10-12','2026-10','Paid','2026-10-12','Cash','CASH-REC-01','Received 5 line khata by student'),(45,'INV-DRY-202610-679',2,'Official School Diary 2026',150.00,'2026-10-19','2026-10','Paid','2026-10-12','bKash / Mobile Banking','BKASH-TRX-998877','Student diary copy'),(46,'INV-202611-003',3,'Monthly Tuition Fee - November 2026',1700.00,'2026-11-10',NULL,'Unpaid',NULL,NULL,NULL,NULL),(47,'INV-202611-005',5,'Monthly Tuition Fee - November 2026',1700.00,'2026-11-10',NULL,'Unpaid',NULL,NULL,NULL,NULL),(48,'INV-202611-006',6,'Monthly Tuition Fee - November 2026',1700.00,'2026-11-10',NULL,'Unpaid',NULL,NULL,NULL,NULL),(49,'INV-202611-007',7,'Monthly Tuition Fee - November 2026',1700.00,'2026-11-10',NULL,'Unpaid',NULL,NULL,NULL,NULL),(50,'INV-202611-008',8,'Monthly Tuition Fee - November 2026',1700.00,'2026-11-10',NULL,'Unpaid',NULL,NULL,NULL,NULL),(51,'INV-202611-009',9,'Monthly Tuition Fee - November 2026',1700.00,'2026-11-10',NULL,'Unpaid',NULL,NULL,NULL,NULL),(52,'INV-202611-010',10,'Monthly Tuition Fee - November 2026',1700.00,'2026-11-10',NULL,'Unpaid',NULL,NULL,NULL,NULL),(53,'INV-202611-011',11,'Monthly Tuition Fee - November 2026',1700.00,'2026-11-10',NULL,'Unpaid',NULL,NULL,NULL,NULL),(54,'INV-202611-012',12,'Monthly Tuition Fee - November 2026',1700.00,'2026-11-10',NULL,'Unpaid',NULL,NULL,NULL,NULL),(55,'INV-202611-013',13,'Monthly Tuition Fee - November 2026',1700.00,'2026-11-10',NULL,'Unpaid',NULL,NULL,NULL,NULL),(56,'INV-202611-014',14,'Monthly Tuition Fee - November 2026',1700.00,'2026-11-10',NULL,'Unpaid',NULL,NULL,NULL,NULL),(57,'INV-202611-015',15,'Monthly Tuition Fee - November 2026',1700.00,'2026-11-10',NULL,'Unpaid',NULL,NULL,NULL,NULL),(58,'INV-202611-001',1,'Monthly Tuition Fee - November 2026',2000.00,'2026-11-10',NULL,'Unpaid',NULL,NULL,NULL,NULL),(59,'INV-202611-016',16,'Monthly Tuition Fee - November 2026',2000.00,'2026-11-10',NULL,'Unpaid',NULL,NULL,NULL,NULL),(60,'INV-202611-017',17,'Monthly Tuition Fee - November 2026',2000.00,'2026-11-10',NULL,'Unpaid',NULL,NULL,NULL,NULL),(61,'INV-202611-018',18,'Monthly Tuition Fee - November 2026',2000.00,'2026-11-10',NULL,'Unpaid',NULL,NULL,NULL,NULL),(62,'INV-202611-019',19,'Monthly Tuition Fee - November 2026',2000.00,'2026-11-10',NULL,'Unpaid',NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `fees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inquiries`
--

DROP TABLE IF EXISTS `inquiries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inquiries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `status` enum('Unread','Read') DEFAULT 'Unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inquiries`
--

LOCK TABLES `inquiries` WRITE;
/*!40000 ALTER TABLE `inquiries` DISABLE KEYS */;
/*!40000 ALTER TABLE `inquiries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notices`
--

DROP TABLE IF EXISTS `notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'General',
  `content` text NOT NULL,
  `target` enum('all','parent','teacher') DEFAULT 'all',
  `published_date` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notices`
--

LOCK TABLES `notices` WRITE;
/*!40000 ALTER TABLE `notices` DISABLE KEYS */;
INSERT INTO `notices` VALUES (1,'Admission Open for Session 2026','Admission','Admissions are now open for Play Group, Nursery, KG, Year 1 and Year 2 for the academic session 2026. Limited seats available.','all','2026-10-09'),(2,'Annual Quran Recitation Competition','Event','The Annual Quran Recitation & Tajweed Competition for students will be held on Thursday next week.','all','2026-10-09'),(3,'Test Notice','Admission','This is test notice','all','2026-10-09');
/*!40000 ALTER TABLE `notices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `results`
--

DROP TABLE IF EXISTS `results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `exam_term` enum('First Term','Mid Term','Final Exam') NOT NULL,
  `marks_obtained` decimal(5,2) NOT NULL,
  `max_marks` decimal(5,2) DEFAULT 100.00,
  `teacher_remarks` varchar(255) DEFAULT 'Good performance',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `results_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `results`
--

LOCK TABLES `results` WRITE;
/*!40000 ALTER TABLE `results` DISABLE KEYS */;
INSERT INTO `results` VALUES (1,1,4,'Mid Term',92.50,100.00,'Outstanding English reading & phonics performance','2026-10-09 14:45:46'),(2,1,7,'Mid Term',98.00,100.00,'Excellent recitation of Surah Al-Fatiha & Duas','2026-10-09 14:45:46');
/*!40000 ALTER TABLE `results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `dob` date NOT NULL,
  `class_id` int(11) NOT NULL,
  `roll_no` int(11) NOT NULL,
  `parent_user_id` int(11) DEFAULT NULL,
  `father_name` varchar(100) NOT NULL,
  `mother_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `status` enum('Active','Graduated','Suspended') DEFAULT 'Active',
  `admission_date` date DEFAULT NULL,
  `blood_group` varchar(10) DEFAULT NULL,
  `religion` varchar(50) DEFAULT 'Islam',
  `nationality` varchar(50) DEFAULT 'Bangladeshi',
  `birth_reg_no` varchar(100) DEFAULT NULL,
  `permanent_address` text DEFAULT NULL,
  `father_occupation` varchar(100) DEFAULT NULL,
  `father_phone` varchar(40) DEFAULT NULL,
  `father_nid` varchar(100) DEFAULT NULL,
  `mother_occupation` varchar(100) DEFAULT NULL,
  `mother_phone` varchar(40) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(40) DEFAULT NULL,
  `emergency_contact_relation` varchar(50) DEFAULT NULL,
  `previous_school` varchar(150) DEFAULT NULL,
  `medical_notes` text DEFAULT NULL,
  `admission_session` varchar(20) DEFAULT '2027',
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_id` (`student_id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (1,'AFI-C1-001','Abdullah','Al-Mamun','Male','2019-05-12',4,1,3,'Mohammad Tariqul Islam','Fatima Khatun','+880 1822-222222','Block-C, Main Road, Cox\'s Bazar','Active','2025-01-10',NULL,'Islam','Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2027'),(2,'AFI-PG-001','Rayhan','Chowdhury','Male','2023-04-12',1,1,3,'Rashid Chowdhury','Farhana Yasmin','01819111222','Kolatoli Road, Cox\'s Bazar','Active','2026-01-01',NULL,'Islam','Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2027'),(3,'AFI-PG-002','Ayesha','Siddiqa','Female','2023-02-18',1,2,NULL,'Kamal Uddin','Nasima Begum','01819333444','Tekpara, Cox\'s Bazar','Active','2026-01-01',NULL,'Islam','Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2027'),(4,'AFI-PG-003','Umar','Farooq','Male','2023-06-25',1,3,NULL,'Farooq Ahmed','Rehana Akhtar','01819555666','Bazar Ghata, Cox\'s Bazar','Active','2026-01-01',NULL,'Islam','Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2027'),(5,'AFI-PG-004','Maryam','Akhtar','Female','2023-01-30',1,4,NULL,'Akhtar Hossain','Salma Khatun','01819777888','Laldhighir Par, Cox\'s Bazar','Active','2026-01-01',NULL,'Islam','Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2027'),(6,'AFI-PG-005','Zayd','Al-Hasan','Male','2023-05-15',1,5,NULL,'Hasan Mahmud','Jannatul Ferdous','01819999000','Rumaliarchhara, Cox\'s Bazar','Active','2026-01-01',NULL,'Islam','Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2027'),(7,'AFI-NUR-001','Fatima','Zahra','Female','2022-03-10',2,1,NULL,'Ziaur Rahman','Tahsina Begum','01711222333','Tarabaniarchhara, Cox\'s Bazar','Active','2026-01-01',NULL,'Islam','Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2027'),(8,'AFI-NUR-002','Ibrahim','Khalil','Male','2022-07-22',2,2,NULL,'Khalilur Rahman','Rokeya Sultana','01711444555','Hashemia Madrasah Road, Cox\'s Bazar','Active','2026-01-01',NULL,'Islam','Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2027'),(9,'AFI-NUR-003','Hafsa','Binte Ali','Female','2022-09-05',2,3,NULL,'Ali Ashraf','Halima Sadia','01711666777','Jhilongja, Cox\'s Bazar','Active','2026-01-01',NULL,'Islam','Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2027'),(10,'AFI-NUR-004','Hamza','Bin Tariq','Male','2022-01-14',2,4,NULL,'Tariqul Islam','Maksuda Parveen','01711888999','Boro Bazar, Cox\'s Bazar','Active','2026-01-01',NULL,'Islam','Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2027'),(11,'AFI-KG-001','Tahsin','Ahmed','Male','2021-04-18',3,1,NULL,'Ahmed Kabir','Shahana Khanam','01822111222','Paharika, Cox\'s Bazar','Active','2026-01-01',NULL,'Islam','Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2027'),(12,'AFI-KG-002','Sumaiya','Jannat','Female','2021-08-09',3,2,NULL,'Jalal Uddin','Rumana Afroze','01822333444','Samiti Para, Cox\'s Bazar','Active','2026-01-01',NULL,'Islam','Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2027'),(13,'AFI-KG-003','Bilal','Hossain','Male','2021-02-27',3,3,NULL,'Amir Hossain','Dilruba Begum','01822555666','Hospital Road, Cox\'s Bazar','Active','2026-01-01',NULL,'Islam','Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2027'),(14,'AFI-KG-004','Khadija','Rahman','Female','2021-11-03',3,4,NULL,'Mizanur Rahman','Sharmin Akter','01822777888','Kasturi Ghat, Cox\'s Bazar','Active','2026-01-01',NULL,'Islam','Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2027'),(15,'AFI-KG-005','Anas','Mahmud','Male','2021-06-21',3,5,NULL,'Mahmudul Hasan','Nusrat Jahan','01822999000','Airport Road, Cox\'s Bazar','Active','2026-01-01',NULL,'Islam','Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2027'),(16,'AFI-C1-002','Nabila','Islam','Female','2020-05-19',4,2,NULL,'Nurul Islam','Rasheda Parveen','01911222333','Kalatali Bye-pass, Cox\'s Bazar','Active','2026-01-01',NULL,'Islam','Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2027'),(17,'AFI-C1-003','Yusuf','Karim','Male','2020-09-12',4,3,NULL,'Rezaul Karim','Shirin Akter','01911444555','Tekpara, Cox\'s Bazar','Active','2026-01-01',NULL,'Islam','Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2027'),(18,'AFI-C2-001','Samiul','Haque','Male','2019-02-14',5,1,NULL,'Enamul Haque','Marufa Begum','01611222333','Rumaliarchhara, Cox\'s Bazar','Active','2026-01-01',NULL,'Islam','Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2027'),(19,'AFI-C2-002','Tasnim','Sultana','Female','2019-07-28',5,2,NULL,'Monirul Islam','Ferdousi Begum','01611444555','Jhawtala, Cox\'s Bazar','Active','2026-01-01',NULL,'Islam','Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2027');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `total_marks` int(11) DEFAULT 100,
  `pass_marks` int(11) DEFAULT 40,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subjects`
--

LOCK TABLES `subjects` WRITE;
/*!40000 ALTER TABLE `subjects` DISABLE KEYS */;
INSERT INTO `subjects` VALUES (1,'ENG-P1','Cambridge Literacy & Phonics',1,1,100,40,NULL),(2,'ARA-P1','Arabic Vocabulary & Tajweed',1,1,100,40,NULL),(3,'MAT-P1','Early Numeracy & Shapes',1,1,100,40,NULL),(4,'ENG-Y1','Cambridge Primary English',4,1,100,40,NULL),(5,'MAT-Y1','Cambridge Primary Mathematics',4,1,100,40,NULL),(6,'SCI-Y1','Cambridge Primary Science',4,1,100,40,NULL),(7,'ISL-Y1','Islamic Studies & Hifz',4,1,100,40,NULL),(8,'ENG-Y3','Cambridge English Literature',6,2,100,40,'Cambridge Primary Stage 3 English reading, writing and grammar.'),(9,'SCI-Y3','Cambridge Science Stage 3',6,1,100,40,'Stage 3 biology, physical forces, and scientific inquiry.'),(10,'ARA-Y3','Quranic Arabic & Tajweed Y3',6,1,100,50,'Makhraj, tajweed rules and Surah recitation.');
/*!40000 ALTER TABLE `subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_attendance`
--

DROP TABLE IF EXISTS `teacher_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('Present','Absent','Late','Excused') NOT NULL,
  `remarks` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `teacher_attendance_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_attendance`
--

LOCK TABLES `teacher_attendance` WRITE;
/*!40000 ALTER TABLE `teacher_attendance` DISABLE KEYS */;
/*!40000 ALTER TABLE `teacher_attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teachers`
--

DROP TABLE IF EXISTS `teachers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teachers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `teacher_code` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `qualification` varchar(150) NOT NULL,
  `specialty` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `joining_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teacher_code` (`teacher_code`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teachers`
--

LOCK TABLES `teachers` WRITE;
/*!40000 ALTER TABLE `teachers` DISABLE KEYS */;
INSERT INTO `teachers` VALUES (1,2,'TCH-2025-01','Ustadh Ahmad Rahman','M.A. in Arabic & Islamic Studies (Al-Azhar), B.Ed','Arabic, Tajweed & Islamic Studies','teacher@alfatihainstitute.edu.bd','+880 1811-111111','2025-01-01'),(2,5,'TCH-2026-71','Ummay Safa','BA in English','English','safa@al-fatihacxb.com','018111111','2026-10-10');
/*!40000 ALTER TABLE `teachers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `timetables`
--

DROP TABLE IF EXISTS `timetables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timetables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `day_of_week` enum('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `period_name` varchar(50) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `teacher_ids` varchar(255) DEFAULT NULL,
  `room_no` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `timetables_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timetables`
--

LOCK TABLES `timetables` WRITE;
/*!40000 ALTER TABLE `timetables` DISABLE KEYS */;
INSERT INTO `timetables` VALUES (1,4,'Sunday','Period 1 (Morning Quran)','08:30:00','09:15:00',7,'1','201','2026-10-10 06:38:59'),(2,4,'Sunday','Period 2 (Cambridge Literacy)','09:15:00','10:00:00',4,'1','201','2026-10-10 06:38:59'),(3,4,'Sunday','Period 3 (Math & Numeracy)','10:15:00','11:00:00',5,'1','201','2026-10-10 06:38:59'),(4,4,'Monday','Period 1 (Arabic & Tajweed)','08:30:00','09:15:00',7,'1','201','2026-10-10 06:38:59'),(5,4,'Monday','Period 2 (Science Lab)','09:15:00','10:00:00',6,'1','201','2026-10-10 06:38:59'),(6,4,'Tuesday','Period 1 (Cambridge Literacy)','08:30:00','09:15:00',4,'1','201','2026-10-10 06:38:59'),(7,4,'Wednesday','Period 1 (Islamic Ethics & Hifz)','08:30:00','09:15:00',7,'1','201','2026-10-10 06:38:59'),(8,4,'Thursday','Period 1 (Recitation & Art)','08:30:00','09:15:00',7,'1','201','2026-10-10 06:38:59');
/*!40000 ALTER TABLE `timetables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'parent',
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$1B3wfE.m6zc0SNwnlgAlietMwxNyCvVZ8bt9.moOXcX9YqzTgJrsa','admin@alfatihainstitute.edu.bd','admin','Principal Admin','+880 1800-000000','active','2026-10-09 14:45:46'),(2,'teacher','$2y$10$BxQgkJpn3Tm8idGRDRwqH.ZvAEv6KTXSi8UPY0yI/LSPgt8ksNs02','teacher@alfatihainstitute.edu.bd','teacher','Ustadh Ahmad Rahman','+880 1811-111111','active','2026-10-09 14:45:46'),(3,'parent','$2y$10$aEcFVT1CHIzh1QLcxk//Eur3BegdVOzVZuVICOMFnGuEj8DbUwUZm','parent@alfatihainstitute.edu.bd','parent','Mohammad Tariqul Islam','+880 1822-222222','active','2026-10-09 14:45:46'),(4,'accountant','$2y$10$vBEwfmkOiuNsnUqi7hNuRu7HTr.z5HAhscBKwuCmeiLLS7bzELHa.','accountant@alfatihainstitute.edu.bd','accountant','Shahidul Alam (Chief Accountant)','+880 1833-333333','active','2026-10-09 18:09:31'),(5,'safa','$2y$10$wiv6nRsq3kZByS/8E6OnAu2xESbwzIaX6TntsE0IxhX1/bcIaZYhW','safa@al-fatihacxb.com','teacher','Ummay Safa','018111111','active','2026-10-10 06:43:30');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-05 14:07:28
