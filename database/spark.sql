-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: spark
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
-- Current Database: `spark`
--

/*!40000 DROP DATABASE IF EXISTS `spark`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `spark` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `spark`;

--
-- Table structure for table `afinidade_usuario_tag`
--

DROP TABLE IF EXISTS `afinidade_usuario_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `afinidade_usuario_tag` (
  `idusuario` int(11) NOT NULL,
  `idtag` int(11) NOT NULL,
  `pontuacao` int(11) DEFAULT 0,
  `atualizado_em` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`idusuario`,`idtag`),
  KEY `idtag` (`idtag`),
  CONSTRAINT `afinidade_usuario_tag_ibfk_1` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `afinidade_usuario_tag_ibfk_2` FOREIGN KEY (`idtag`) REFERENCES `tags` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `afinidade_usuario_tag`
--

LOCK TABLES `afinidade_usuario_tag` WRITE;
/*!40000 ALTER TABLE `afinidade_usuario_tag` DISABLE KEYS */;
INSERT INTO `afinidade_usuario_tag` VALUES (15,6,15,'2026-08-12 15:31:03'),(15,8,15,'2026-08-12 15:31:03'),(15,14,15,'2026-08-12 15:31:03'),(16,12,15,'2026-08-12 15:33:09'),(16,15,15,'2026-08-12 15:33:09'),(24,5,15,'2026-08-24 10:38:48'),(24,10,15,'2026-08-24 10:38:48'),(24,16,15,'2026-08-24 10:38:48'),(37,1,15,'2026-08-24 11:18:05'),(37,14,15,'2026-08-24 11:18:05'),(37,15,15,'2026-08-24 11:18:05'),(38,4,15,'2026-08-24 11:31:29'),(38,5,15,'2026-08-24 11:31:29'),(38,13,15,'2026-08-24 11:31:29'),(43,1,15,'2026-08-25 09:11:15'),(43,6,15,'2026-08-25 09:11:15'),(43,10,15,'2026-08-25 09:11:15'),(43,11,15,'2026-08-25 09:11:15'),(52,4,15,'2026-08-25 15:06:10'),(52,5,15,'2026-08-25 15:06:10'),(52,13,15,'2026-08-25 15:06:10'),(53,6,15,'2026-08-25 21:30:13'),(53,11,15,'2026-08-25 21:30:13'),(53,19,15,'2026-08-25 21:30:13');
/*!40000 ALTER TABLE `afinidade_usuario_tag` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comentarios`
--

DROP TABLE IF EXISTS `comentarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comentarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idusuario` int(11) DEFAULT NULL,
  `idmidias` int(11) DEFAULT NULL,
  `texto` varchar(1000) DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idusuario` (`idusuario`),
  KEY `idx_coment_midia` (`idmidias`,`criado_em`),
  CONSTRAINT `comentarios_ibfk_1` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `comentarios_ibfk_2` FOREIGN KEY (`idmidias`) REFERENCES `midias` (`id_midia`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comentarios`
--

LOCK TABLES `comentarios` WRITE;
/*!40000 ALTER TABLE `comentarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `comentarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conexoes`
--

DROP TABLE IF EXISTS `conexoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conexoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idusuario_a` int(11) NOT NULL,
  `idusuario_b` int(11) NOT NULL,
  `motivo` varchar(120) DEFAULT NULL,
  `idconversa` int(11) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_conexao_a` (`idusuario_a`,`criado_em`),
  KEY `idusuario_b` (`idusuario_b`),
  KEY `idconversa` (`idconversa`),
  CONSTRAINT `conexoes_ibfk_1` FOREIGN KEY (`idusuario_a`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `conexoes_ibfk_2` FOREIGN KEY (`idusuario_b`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `conexoes_ibfk_3` FOREIGN KEY (`idconversa`) REFERENCES `conversas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conexoes`
--

LOCK TABLES `conexoes` WRITE;
/*!40000 ALTER TABLE `conexoes` DISABLE KEYS */;
INSERT INTO `conexoes` VALUES (4,15,16,'Mesma área (Produção musical) e um momento que combina com o seu: só observando.',4,'2026-08-12 15:34:01'),(7,24,15,'Mesma área (Produção musical) e um momento que combina com o seu: aberto a colab.',7,'2026-08-24 10:39:57'),(11,38,15,'Mesma área (Produção musical) e um momento que combina com o seu: aberto a colab.',11,'2026-08-24 11:32:23'),(15,52,15,'Conexão direta pelo post',16,'2026-08-25 15:07:09');
/*!40000 ALTER TABLE `conexoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversa_participantes`
--

DROP TABLE IF EXISTS `conversa_participantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conversa_participantes` (
  `idconversa` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `entrou_em` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`idconversa`,`idusuario`),
  KEY `idusuario` (`idusuario`),
  CONSTRAINT `conversa_participantes_ibfk_1` FOREIGN KEY (`idconversa`) REFERENCES `conversas` (`id`),
  CONSTRAINT `conversa_participantes_ibfk_2` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversa_participantes`
--

LOCK TABLES `conversa_participantes` WRITE;
/*!40000 ALTER TABLE `conversa_participantes` DISABLE KEYS */;
INSERT INTO `conversa_participantes` VALUES (4,15,'2026-08-12 15:34:01'),(4,16,'2026-08-12 15:34:01'),(7,15,'2026-08-24 10:39:57'),(7,24,'2026-08-24 10:39:57'),(11,15,'2026-08-24 11:32:23'),(11,38,'2026-08-24 11:32:23'),(16,15,'2026-08-25 15:07:09'),(16,52,'2026-08-25 15:07:09');
/*!40000 ALTER TABLE `conversa_participantes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversas`
--

DROP TABLE IF EXISTS `conversas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conversas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('individual','grupo') NOT NULL DEFAULT 'individual',
  `nome` varchar(100) DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversas`
--

LOCK TABLES `conversas` WRITE;
/*!40000 ALTER TABLE `conversas` DISABLE KEYS */;
INSERT INTO `conversas` VALUES (4,'individual',NULL,'2026-08-12 15:34:01'),(7,'individual',NULL,'2026-08-24 10:39:57'),(11,'individual',NULL,'2026-08-24 11:32:23'),(16,'individual',NULL,'2026-08-25 15:07:09');
/*!40000 ALTER TABLE `conversas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `curtidas`
--

DROP TABLE IF EXISTS `curtidas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `curtidas` (
  `idusuario` int(11) NOT NULL,
  `idmidias` int(11) NOT NULL,
  `criado_em` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`idusuario`,`idmidias`),
  KEY `idx_curtidas_midia` (`idmidias`),
  CONSTRAINT `curtidas_ibfk_1` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `curtidas_ibfk_2` FOREIGN KEY (`idmidias`) REFERENCES `midias` (`id_midia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `curtidas`
--

LOCK TABLES `curtidas` WRITE;
/*!40000 ALTER TABLE `curtidas` DISABLE KEYS */;
INSERT INTO `curtidas` VALUES (15,10,'2026-08-12 15:35:12'),(24,10,'2026-08-24 10:40:45'),(38,10,'2026-08-24 11:43:43'),(43,10,'2026-08-25 09:13:20');
/*!40000 ALTER TABLE `curtidas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `forum_categorias`
--

DROP TABLE IF EXISTS `forum_categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forum_categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) DEFAULT NULL,
  `descricao` varchar(300) DEFAULT NULL,
  `icone` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `forum_categorias`
--

LOCK TABLES `forum_categorias` WRITE;
/*!40000 ALTER TABLE `forum_categorias` DISABLE KEYS */;
INSERT INTO `forum_categorias` VALUES (1,'Geral',NULL,NULL),(2,'Bloqueio Criativo','Como destravar quando nada sai','fa-brain'),(3,'Produção Musical','Samples, mixagem, composição e gêneros','fa-sliders'),(4,'Artes Visuais','Técnicas, materiais, paletas e processo','fa-brush'),(5,'Design & UI','Interfaces, tipografia e produto','fa-pen-nib'),(6,'Escrita & Roteiro','Narrativa, letra, poesia e argumento','fa-feather'),(7,'Colaborações','Procure parceiros e monte projetos','fa-handshake'),(8,'Feedback','Peça e dê crítica construtiva no trabalho','fa-comment-dots');
/*!40000 ALTER TABLE `forum_categorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `forum_respostas`
--

DROP TABLE IF EXISTS `forum_respostas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forum_respostas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idtopico` int(11) DEFAULT NULL,
  `idusuario` int(11) DEFAULT NULL,
  `texto` text DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idtopico` (`idtopico`),
  KEY `idusuario` (`idusuario`),
  CONSTRAINT `forum_respostas_ibfk_1` FOREIGN KEY (`idtopico`) REFERENCES `forum_topicos` (`id`),
  CONSTRAINT `forum_respostas_ibfk_2` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `forum_respostas`
--

LOCK TABLES `forum_respostas` WRITE;
/*!40000 ALTER TABLE `forum_respostas` DISABLE KEYS */;
/*!40000 ALTER TABLE `forum_respostas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `forum_topicos`
--

DROP TABLE IF EXISTS `forum_topicos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forum_topicos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idcategoria` int(11) DEFAULT NULL,
  `idusuario` int(11) DEFAULT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `texto` text DEFAULT NULL,
  `visitas` int(11) NOT NULL DEFAULT 0,
  `criado_em` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idcategoria` (`idcategoria`),
  KEY `idusuario` (`idusuario`),
  CONSTRAINT `forum_topicos_ibfk_1` FOREIGN KEY (`idcategoria`) REFERENCES `forum_categorias` (`id`),
  CONSTRAINT `forum_topicos_ibfk_2` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `forum_topicos`
--

LOCK TABLES `forum_topicos` WRITE;
/*!40000 ALTER TABLE `forum_topicos` DISABLE KEYS */;
/*!40000 ALTER TABLE `forum_topicos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `interacoes`
--

DROP TABLE IF EXISTS `interacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `interacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idusuario` int(11) DEFAULT NULL,
  `idmidias` int(11) DEFAULT NULL,
  `tipo_interacao` enum('visualizacao','curtida','comentario','compartilhamento','pulou') NOT NULL,
  `tempo_assistido_seg` int(11) DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idmidias` (`idmidias`),
  KEY `idx_interacoes_user` (`idusuario`,`criado_em`),
  CONSTRAINT `interacoes_ibfk_1` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `interacoes_ibfk_2` FOREIGN KEY (`idmidias`) REFERENCES `midias` (`id_midia`)
) ENGINE=InnoDB AUTO_INCREMENT=297 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `interacoes`
--

LOCK TABLES `interacoes` WRITE;
/*!40000 ALTER TABLE `interacoes` DISABLE KEYS */;
INSERT INTO `interacoes` VALUES (24,15,9,'compartilhamento',NULL,'2026-08-12 15:34:23'),(25,15,10,'compartilhamento',NULL,'2026-08-12 15:35:09'),(26,15,10,'curtida',NULL,'2026-08-12 15:35:12'),(27,15,10,'compartilhamento',NULL,'2026-08-12 15:35:15'),(50,24,10,'visualizacao',NULL,'2026-08-24 10:38:48'),(51,24,9,'visualizacao',NULL,'2026-08-24 10:38:48'),(52,24,10,'visualizacao',NULL,'2026-08-24 10:39:25'),(53,24,9,'visualizacao',NULL,'2026-08-24 10:39:25'),(54,24,10,'visualizacao',NULL,'2026-08-24 10:39:29'),(55,24,9,'visualizacao',NULL,'2026-08-24 10:39:29'),(56,24,10,'visualizacao',NULL,'2026-08-24 10:39:31'),(57,24,9,'visualizacao',NULL,'2026-08-24 10:39:31'),(58,24,10,'visualizacao',NULL,'2026-08-24 10:40:04'),(59,24,9,'visualizacao',NULL,'2026-08-24 10:40:04'),(60,24,10,'visualizacao',NULL,'2026-08-24 10:40:19'),(61,24,9,'visualizacao',NULL,'2026-08-24 10:40:19'),(62,24,10,'visualizacao',NULL,'2026-08-24 10:40:24'),(63,24,9,'visualizacao',NULL,'2026-08-24 10:40:24'),(64,24,10,'visualizacao',NULL,'2026-08-24 10:40:41'),(65,24,9,'visualizacao',NULL,'2026-08-24 10:40:41'),(66,24,10,'curtida',NULL,'2026-08-24 10:40:45'),(91,24,10,'visualizacao',NULL,'2026-08-24 11:09:56'),(92,24,9,'visualizacao',NULL,'2026-08-24 11:09:56'),(93,24,10,'visualizacao',NULL,'2026-08-24 11:10:16'),(94,24,9,'visualizacao',NULL,'2026-08-24 11:10:16'),(109,24,10,'visualizacao',NULL,'2026-08-24 11:16:15'),(110,24,9,'visualizacao',NULL,'2026-08-24 11:16:15'),(111,24,10,'visualizacao',NULL,'2026-08-24 11:16:20'),(112,24,9,'visualizacao',NULL,'2026-08-24 11:16:20'),(113,37,10,'visualizacao',NULL,'2026-08-24 11:18:10'),(114,37,9,'visualizacao',NULL,'2026-08-24 11:18:10'),(115,37,10,'visualizacao',NULL,'2026-08-24 11:18:42'),(116,37,9,'visualizacao',NULL,'2026-08-24 11:18:42'),(117,38,10,'visualizacao',NULL,'2026-08-24 11:31:29'),(118,38,9,'visualizacao',NULL,'2026-08-24 11:31:29'),(119,38,10,'visualizacao',NULL,'2026-08-24 11:32:14'),(120,38,9,'visualizacao',NULL,'2026-08-24 11:32:14'),(121,38,10,'visualizacao',NULL,'2026-08-24 11:43:27'),(122,38,9,'visualizacao',NULL,'2026-08-24 11:43:27'),(123,38,10,'visualizacao',NULL,'2026-08-24 11:43:34'),(124,38,9,'visualizacao',NULL,'2026-08-24 11:43:34'),(125,38,10,'visualizacao',NULL,'2026-08-24 11:43:38'),(126,38,9,'visualizacao',NULL,'2026-08-24 11:43:38'),(127,38,10,'visualizacao',NULL,'2026-08-24 11:43:40'),(128,38,9,'visualizacao',NULL,'2026-08-24 11:43:40'),(129,38,10,'curtida',NULL,'2026-08-24 11:43:43'),(144,43,10,'visualizacao',NULL,'2026-08-25 09:11:15'),(145,43,9,'visualizacao',NULL,'2026-08-25 09:11:15'),(146,43,10,'visualizacao',NULL,'2026-08-25 09:11:31'),(147,43,9,'visualizacao',NULL,'2026-08-25 09:11:31'),(148,43,10,'visualizacao',NULL,'2026-08-25 09:11:45'),(149,43,9,'visualizacao',NULL,'2026-08-25 09:11:45'),(150,43,10,'visualizacao',NULL,'2026-08-25 09:11:58'),(151,43,9,'visualizacao',NULL,'2026-08-25 09:11:58'),(152,43,10,'visualizacao',NULL,'2026-08-25 09:11:58'),(153,43,9,'visualizacao',NULL,'2026-08-25 09:11:58'),(154,43,10,'visualizacao',NULL,'2026-08-25 09:12:28'),(155,43,9,'visualizacao',NULL,'2026-08-25 09:12:28'),(156,43,10,'visualizacao',NULL,'2026-08-25 09:12:39'),(157,43,10,'visualizacao',NULL,'2026-08-25 09:12:43'),(158,43,9,'visualizacao',NULL,'2026-08-25 09:12:43'),(159,43,10,'visualizacao',NULL,'2026-08-25 09:13:07'),(160,43,9,'visualizacao',NULL,'2026-08-25 09:13:07'),(161,43,10,'curtida',NULL,'2026-08-25 09:13:18'),(162,43,10,'curtida',NULL,'2026-08-25 09:13:20'),(163,43,10,'visualizacao',NULL,'2026-08-25 12:20:25'),(164,43,9,'visualizacao',NULL,'2026-08-25 12:20:25'),(165,43,10,'visualizacao',NULL,'2026-08-25 12:20:43'),(166,43,9,'visualizacao',NULL,'2026-08-25 12:20:43'),(167,43,10,'visualizacao',NULL,'2026-08-25 12:20:45'),(168,43,9,'visualizacao',NULL,'2026-08-25 12:20:45'),(187,43,10,'visualizacao',NULL,'2026-08-25 14:37:15'),(188,43,9,'visualizacao',NULL,'2026-08-25 14:37:15'),(189,43,10,'visualizacao',NULL,'2026-08-25 14:37:32'),(190,43,9,'visualizacao',NULL,'2026-08-25 14:37:32'),(191,43,10,'visualizacao',NULL,'2026-08-25 14:37:34'),(192,43,9,'visualizacao',NULL,'2026-08-25 14:37:34'),(193,43,10,'visualizacao',NULL,'2026-08-25 14:37:38'),(194,43,9,'visualizacao',NULL,'2026-08-25 14:37:38'),(195,43,10,'visualizacao',NULL,'2026-08-25 14:38:08'),(196,43,9,'visualizacao',NULL,'2026-08-25 14:38:08'),(197,43,10,'visualizacao',NULL,'2026-08-25 14:41:31'),(198,43,9,'visualizacao',NULL,'2026-08-25 14:41:31'),(199,43,10,'visualizacao',NULL,'2026-08-25 14:41:49'),(200,43,9,'visualizacao',NULL,'2026-08-25 14:41:49'),(201,43,10,'visualizacao',NULL,'2026-08-25 14:41:58'),(202,43,9,'visualizacao',NULL,'2026-08-25 14:41:59'),(203,43,10,'visualizacao',NULL,'2026-08-25 14:42:00'),(204,43,9,'visualizacao',NULL,'2026-08-25 14:42:00'),(205,43,10,'visualizacao',NULL,'2026-08-25 14:49:53'),(206,43,9,'visualizacao',NULL,'2026-08-25 14:49:53'),(207,43,10,'visualizacao',NULL,'2026-08-25 14:50:13'),(208,43,9,'visualizacao',NULL,'2026-08-25 14:50:13'),(223,52,10,'visualizacao',NULL,'2026-08-25 15:06:10'),(224,52,9,'visualizacao',NULL,'2026-08-25 15:06:10'),(225,52,10,'visualizacao',NULL,'2026-08-25 15:06:40'),(226,52,9,'visualizacao',NULL,'2026-08-25 15:06:40'),(227,52,10,'visualizacao',NULL,'2026-08-25 15:07:03'),(228,52,9,'visualizacao',NULL,'2026-08-25 15:07:03'),(229,52,10,'visualizacao',NULL,'2026-08-25 15:09:33'),(230,52,9,'visualizacao',NULL,'2026-08-25 15:09:33'),(231,52,10,'visualizacao',NULL,'2026-08-25 15:10:51'),(232,52,9,'visualizacao',NULL,'2026-08-25 15:10:51'),(233,52,10,'visualizacao',NULL,'2026-08-25 15:11:24'),(234,52,9,'visualizacao',NULL,'2026-08-25 15:11:24'),(235,52,10,'visualizacao',NULL,'2026-08-25 21:27:10'),(236,52,9,'visualizacao',NULL,'2026-08-25 21:27:10'),(237,53,10,'visualizacao',NULL,'2026-08-25 21:30:14'),(238,53,9,'visualizacao',NULL,'2026-08-25 21:30:14'),(239,53,10,'visualizacao',NULL,'2026-08-25 21:30:34'),(240,53,9,'visualizacao',NULL,'2026-08-25 21:30:34');
/*!40000 ALTER TABLE `interacoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mensagens`
--

DROP TABLE IF EXISTS `mensagens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mensagens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idconversa` int(11) DEFAULT NULL,
  `idusuario_remetente` int(11) DEFAULT NULL,
  `texto` varchar(2000) DEFAULT NULL,
  `idmidia_anexo` int(11) DEFAULT NULL,
  `enviado_em` datetime DEFAULT current_timestamp(),
  `editado` tinyint(1) DEFAULT 0,
  `apagado` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idusuario_remetente` (`idusuario_remetente`),
  KEY `idmidia_anexo` (`idmidia_anexo`),
  KEY `idx_msg_conversa` (`idconversa`,`enviado_em`),
  CONSTRAINT `mensagens_ibfk_1` FOREIGN KEY (`idconversa`) REFERENCES `conversas` (`id`),
  CONSTRAINT `mensagens_ibfk_2` FOREIGN KEY (`idusuario_remetente`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `mensagens_ibfk_3` FOREIGN KEY (`idmidia_anexo`) REFERENCES `midias` (`id_midia`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mensagens`
--

LOCK TABLES `mensagens` WRITE;
/*!40000 ALTER TABLE `mensagens` DISABLE KEYS */;
INSERT INTO `mensagens` VALUES (10,4,15,'Oi! O Spark aproximou a gente aqui. Eu tô aberto a colab e vi que seu momento combina. Bora trocar uma ideia?',NULL,'2026-08-12 15:34:01',0,0),(17,7,24,'Oi! O Spark aproximou a gente aqui. Eu tô só observando e vi que seu momento combina. Bora trocar uma ideia?',NULL,'2026-08-24 10:39:57',0,0),(27,11,38,'Oi! O Spark aproximou a gente aqui. Eu tô só observando e vi que seu momento combina. Bora trocar uma ideia?',NULL,'2026-08-24 11:32:23',0,0),(37,16,52,'Oi! O Spark aproximou a gente aqui. Eu tô em fluxo e vi que seu momento combina. Bora trocar uma ideia?',NULL,'2026-08-25 15:07:09',0,0);
/*!40000 ALTER TABLE `mensagens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mensagens_status`
--

DROP TABLE IF EXISTS `mensagens_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mensagens_status` (
  `idmensagem` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `lida` tinyint(1) DEFAULT 0,
  `lida_em` datetime DEFAULT NULL,
  PRIMARY KEY (`idmensagem`,`idusuario`),
  KEY `idusuario` (`idusuario`),
  CONSTRAINT `mensagens_status_ibfk_1` FOREIGN KEY (`idmensagem`) REFERENCES `mensagens` (`id`),
  CONSTRAINT `mensagens_status_ibfk_2` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mensagens_status`
--

LOCK TABLES `mensagens_status` WRITE;
/*!40000 ALTER TABLE `mensagens_status` DISABLE KEYS */;
INSERT INTO `mensagens_status` VALUES (10,15,1,'2026-08-12 15:34:01'),(10,16,0,NULL),(17,15,0,NULL),(17,24,1,'2026-08-24 10:39:57'),(27,15,0,NULL),(27,38,1,'2026-08-24 11:32:23'),(31,43,0,NULL),(37,15,0,NULL),(37,52,1,'2026-08-25 15:07:09'),(38,52,0,NULL);
/*!40000 ALTER TABLE `mensagens_status` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `midia_tags`
--

DROP TABLE IF EXISTS `midia_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `midia_tags` (
  `idmidias` int(11) NOT NULL,
  `idtag` int(11) NOT NULL,
  PRIMARY KEY (`idmidias`,`idtag`),
  KEY `idtag` (`idtag`),
  CONSTRAINT `midia_tags_ibfk_1` FOREIGN KEY (`idmidias`) REFERENCES `midias` (`id_midia`),
  CONSTRAINT `midia_tags_ibfk_2` FOREIGN KEY (`idtag`) REFERENCES `tags` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `midia_tags`
--

LOCK TABLES `midia_tags` WRITE;
/*!40000 ALTER TABLE `midia_tags` DISABLE KEYS */;
/*!40000 ALTER TABLE `midia_tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `midias`
--

DROP TABLE IF EXISTS `midias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `midias` (
  `id_midia` int(11) NOT NULL AUTO_INCREMENT,
  `idusuario` int(11) DEFAULT NULL,
  `idTipos_midia` int(11) DEFAULT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `descricao` varchar(1000) DEFAULT NULL,
  `midia_url` varchar(500) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `tamanho_bytes` bigint(20) DEFAULT NULL,
  `duracao_segundos` int(11) DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_midia`),
  KEY `idTipos_midia` (`idTipos_midia`),
  KEY `idx_midias_autor_data` (`idusuario`,`criado_em`),
  KEY `idx_midias_data` (`criado_em`),
  CONSTRAINT `midias_ibfk_1` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `midias_ibfk_2` FOREIGN KEY (`idTipos_midia`) REFERENCES `tipos_midia` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `midias`
--

LOCK TABLES `midias` WRITE;
/*!40000 ALTER TABLE `midias` DISABLE KEYS */;
INSERT INTO `midias` VALUES (9,15,4,'','','midias_upload/audio/4ae08f28c88efc548a75bf6a4ee4fbb8.wav','audio/x-wav',25331624,NULL,'2026-08-12 15:34:23'),(10,15,1,'','','midias_upload/imagem/2d58b92a36605218c45e36faf61044cf.jpg','image/jpeg',860581,NULL,'2026-08-12 15:35:09');
/*!40000 ALTER TABLE `midias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `midias_em_pasta`
--

DROP TABLE IF EXISTS `midias_em_pasta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `midias_em_pasta` (
  `idPastas` int(11) NOT NULL,
  `idmidias` int(11) NOT NULL,
  `posicao` int(11) DEFAULT NULL,
  PRIMARY KEY (`idPastas`,`idmidias`),
  KEY `idmidias` (`idmidias`),
  CONSTRAINT `midias_em_pasta_ibfk_1` FOREIGN KEY (`idPastas`) REFERENCES `pastas` (`id`),
  CONSTRAINT `midias_em_pasta_ibfk_2` FOREIGN KEY (`idmidias`) REFERENCES `midias` (`id_midia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `midias_em_pasta`
--

LOCK TABLES `midias_em_pasta` WRITE;
/*!40000 ALTER TABLE `midias_em_pasta` DISABLE KEYS */;
INSERT INTO `midias_em_pasta` VALUES (1,10,0);
/*!40000 ALTER TABLE `midias_em_pasta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificacoes`
--

DROP TABLE IF EXISTS `notificacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notificacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idusuario` int(11) NOT NULL,
  `idator` int(11) NOT NULL,
  `tipo` enum('curtida','comentario','seguidor','mencao','resposta_forum','conexao') NOT NULL,
  `idmidias` int(11) DEFAULT NULL,
  `extra` varchar(255) DEFAULT NULL,
  `lida` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notif_dono` (`idusuario`,`lida`,`criado_em`),
  KEY `idator` (`idator`),
  KEY `idmidias` (`idmidias`),
  CONSTRAINT `notificacoes_ibfk_1` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `notificacoes_ibfk_2` FOREIGN KEY (`idator`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `notificacoes_ibfk_3` FOREIGN KEY (`idmidias`) REFERENCES `midias` (`id_midia`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificacoes`
--

LOCK TABLES `notificacoes` WRITE;
/*!40000 ALTER TABLE `notificacoes` DISABLE KEYS */;
INSERT INTO `notificacoes` VALUES (19,16,15,'conexao',NULL,'quer trocar uma ideia com você',0,'2026-08-12 15:34:01'),(32,15,24,'conexao',NULL,'quer trocar uma ideia com você',0,'2026-08-24 10:39:57'),(33,15,24,'curtida',10,NULL,0,'2026-08-24 10:40:45'),(52,15,38,'conexao',NULL,'quer trocar uma ideia com você',0,'2026-08-24 11:32:23'),(53,15,38,'curtida',10,NULL,0,'2026-08-24 11:43:43'),(60,15,43,'seguidor',NULL,NULL,0,'2026-08-25 09:13:12'),(61,15,43,'seguidor',NULL,NULL,0,'2026-08-25 09:13:14'),(62,15,43,'curtida',10,NULL,0,'2026-08-25 09:13:18'),(63,15,43,'curtida',10,NULL,0,'2026-08-25 09:13:20'),(76,15,52,'seguidor',NULL,NULL,0,'2026-08-25 15:07:05'),(77,15,52,'conexao',NULL,'quer trocar uma ideia com você',0,'2026-08-25 15:07:09');
/*!40000 ALTER TABLE `notificacoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pastas`
--

DROP TABLE IF EXISTS `pastas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pastas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idusuario` int(11) DEFAULT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `descricao` varchar(300) DEFAULT NULL,
  `privada` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idusuario` (`idusuario`),
  CONSTRAINT `pastas_ibfk_1` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pastas`
--

LOCK TABLES `pastas` WRITE;
/*!40000 ALTER TABLE `pastas` DISABLE KEYS */;
INSERT INTO `pastas` VALUES (1,15,'Salvos','Referências que eu guardei pra depois',0,'2026-08-12 15:35:15');
/*!40000 ALTER TABLE `pastas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seguidores`
--

DROP TABLE IF EXISTS `seguidores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seguidores` (
  `idseguidor` int(11) NOT NULL,
  `idseguido` int(11) NOT NULL,
  `criado_em` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`idseguidor`,`idseguido`),
  KEY `idx_seguidores_seguido` (`idseguido`),
  CONSTRAINT `seguidores_ibfk_1` FOREIGN KEY (`idseguidor`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `seguidores_ibfk_2` FOREIGN KEY (`idseguido`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seguidores`
--

LOCK TABLES `seguidores` WRITE;
/*!40000 ALTER TABLE `seguidores` DISABLE KEYS */;
INSERT INTO `seguidores` VALUES (43,15,'2026-08-25 09:13:14'),(52,15,'2026-08-25 15:07:05');
/*!40000 ALTER TABLE `seguidores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tags`
--

LOCK TABLES `tags` WRITE;
/*!40000 ALTER TABLE `tags` DISABLE KEYS */;
INSERT INTO `tags` VALUES (3,'beat'),(20,'bloqueio'),(13,'branding'),(17,'colab'),(5,'composicao'),(14,'fotografia'),(9,'ilustracao'),(6,'letra'),(2,'loop'),(4,'mixagem'),(7,'paleta'),(8,'pintura'),(16,'poesia'),(19,'processo'),(18,'referencia'),(15,'roteiro'),(1,'sample'),(10,'sketch'),(11,'tipografia'),(12,'ui');
/*!40000 ALTER TABLE `tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tipos_midia`
--

DROP TABLE IF EXISTS `tipos_midia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipos_midia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_midia` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipos_midia`
--

LOCK TABLES `tipos_midia` WRITE;
/*!40000 ALTER TABLE `tipos_midia` DISABLE KEYS */;
INSERT INTO `tipos_midia` VALUES (1,'imagem'),(2,'video'),(3,'documento'),(4,'audio');
/*!40000 ALTER TABLE `tipos_midia` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tipos_user`
--

DROP TABLE IF EXISTS `tipos_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipos_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome_tipo` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipos_user`
--

LOCK TABLES `tipos_user` WRITE;
/*!40000 ALTER TABLE `tipos_user` DISABLE KEYS */;
INSERT INTO `tipos_user` VALUES (1,'admin'),(2,'usuario');
/*!40000 ALTER TABLE `tipos_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `nome_usuario` varchar(100) NOT NULL,
  `nome_exibicao` varchar(100) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `bio` varchar(500) DEFAULT NULL,
  `area_criativa` enum('musica','visual','design','escrita','audiovisual','outro') NOT NULL DEFAULT 'outro',
  `ferramenta` varchar(80) DEFAULT NULL,
  `estado_criativo` enum('fluxo','bloqueado','buscando_referencia','aberto_colab','observando') NOT NULL DEFAULT 'observando',
  `estado_em` datetime DEFAULT NULL,
  `foto_perfil_url` varchar(500) DEFAULT NULL,
  `capa_url` varchar(500) DEFAULT NULL,
  `idtipos_user` int(11) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` datetime DEFAULT current_timestamp(),
  `ultimo_acesso` datetime DEFAULT NULL,
  `pais` varchar(100) DEFAULT NULL,
  `cidade` varchar(80) DEFAULT NULL,
  `plano` enum('free','pro') NOT NULL DEFAULT 'free',
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `nome_usuario` (`nome_usuario`),
  UNIQUE KEY `email` (`email`),
  KEY `idtipos_user` (`idtipos_user`),
  KEY `idx_user_estado` (`estado_criativo`,`estado_em`),
  KEY `idx_user_area` (`area_criativa`),
  CONSTRAINT `usuario_ibfk_1` FOREIGN KEY (`idtipos_user`) REFERENCES `tipos_user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES (1,'teste4',NULL,'marcelomdiniz09@gmail.com','$2y$10$/o/fnOtN1UuM1JWpe3o7vOLhRes.Ju8Nd3mMtUxhGplZNRFLbwTX2',NULL,'musica',NULL,'observando','2026-08-12 14:27:25',NULL,NULL,2,1,'2026-08-12 14:27:25','2026-08-12 15:06:00','Brasil',NULL,'free'),(15,'teste7','teste7','teste7@gmail.com','$2y$10$4EgK174bzl.dLgECTNPtrus4fjZI5WeKLAD2mx.GNY1q5Us1Ih0Oy',NULL,'musica','violao','aberto_colab','2026-08-12 15:31:13',NULL,NULL,2,1,'2026-08-12 15:31:03','2026-08-12 15:31:03','Brasil',NULL,'free'),(16,'123',NULL,'123@gmail.com','$2y$10$Hah/lQkOl0eykJUKKqcXEOqgWVxP3KJxJdLxGywGGpn8AtNdwdLyy',NULL,'musica',NULL,'observando','2026-08-12 15:33:09',NULL,NULL,2,1,'2026-08-12 15:33:09','2026-08-12 15:33:09','Brasil',NULL,'free'),(24,'teste15','teste15','teste15@gmail.com','$2y$10$A/bC1/uHVaMpEutR77mmb.KzBZFNxyF9fza9GIuwOxwAOaeDHefo6',NULL,'musica','violao','observando','2026-08-24 10:38:48',NULL,NULL,2,1,'2026-08-24 10:38:48','2026-08-24 11:16:15','Brasil',NULL,'free'),(37,'teste17','te','teste17@gmail.com','$2y$10$sscM2BpCrkDOcfr7tNrieOpfHYPid1nzgguXIzVihv5FNXrfnjO9e',NULL,'escrita','violao','observando','2026-08-24 11:18:05',NULL,NULL,2,1,'2026-08-24 11:18:05','2026-08-24 11:18:05','Brasil',NULL,'free'),(38,'teste088','teste088','teste088@gmail.com','$2y$10$OuEHYiQN3b4Q/K858wyb0egBqf2cseFGA1sy28Ndux1.MGTirnnv6',NULL,'musica','violao','aberto_colab','2026-08-24 11:44:03',NULL,NULL,2,1,'2026-08-24 11:31:29','2026-08-24 11:31:29','Brasil',NULL,'free'),(43,'teste88','teste88','teste88@gmail.com','$2y$10$R/maf89yrdDeQ4TMhahhp.QbhMyf7I.2eXhSsOSW0VxNnpH0MeZeq',NULL,'musica','violao','buscando_referencia','2026-08-25 14:38:40','profile_pics/0cdec2977ae3a0287af9fc790793594d.jpg',NULL,2,1,'2026-08-25 09:11:15','2026-08-25 14:49:53','Brasil',NULL,'free'),(52,'teste56','teste56','teste56@gmail.com','$2y$10$K/wwxDrhLkI2TH2Bns2B3u2dWUwNR3PtbaLwk9FQLVJytg02ujqAy',NULL,'design','violao','fluxo','2026-08-25 15:06:59',NULL,NULL,2,1,'2026-08-25 15:06:10','2026-08-25 21:27:10','Brasil',NULL,'free'),(53,'marcelo','M1077','marcelo@gmail.com','$2y$10$YMkaLkgW.cl/VKHZnAoHCeT9GZcMNY3UetUgpyLWyvJzkUxRuHSNu',NULL,'escrita','Escrita','observando','2026-08-25 21:30:13',NULL,NULL,2,1,'2026-08-25 21:30:13','2026-08-25 21:30:13','Brasil',NULL,'free');
/*!40000 ALTER TABLE `usuario` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-26  8:24:17
