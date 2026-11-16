-- Distribuidora Demo · Sistema de pedidos por zona (Comercio Integral)
-- Importar UNA vez en una base de datos vacía (phpMyAdmin → Importar).
-- Usuarios: demo (administrador), rocio, marcos y lidia (vendedores). Contraseña en demo-pedidos-contrasena.txt.

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
DROP TABLE IF EXISTS `analytics_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `analytics_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('page_view','click_order','product_view','product_add','order_created','whatsapp_open','contact_click') COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant_id` int unsigned DEFAULT NULL,
  `visitor_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `path` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referrer` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `analytics_type_created` (`type`,`created_at`),
  KEY `analytics_variant` (`variant_id`,`type`),
  CONSTRAINT `analytics_variant_fk` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `analytics_events` WRITE;
/*!40000 ALTER TABLE `analytics_events` DISABLE KEYS */;
INSERT INTO `analytics_events` VALUES (1,'order_created',NULL,NULL,NULL,NULL,'{\"items\":1}','2026-11-09 01:20:36'),(2,'order_created',NULL,NULL,NULL,NULL,'{\"items\":2}','2026-11-09 01:20:37'),(3,'order_created',NULL,NULL,NULL,NULL,'{\"items\":1}','2026-11-09 01:20:37'),(4,'order_created',NULL,NULL,NULL,NULL,'{\"items\":1}','2026-11-09 01:20:37'),(5,'order_created',NULL,NULL,NULL,NULL,'{\"items\":2}','2026-11-09 01:20:37'),(6,'order_created',NULL,NULL,NULL,NULL,'{\"items\":1}','2026-11-09 01:20:37'),(7,'order_created',NULL,NULL,NULL,NULL,'{\"items\":1}','2026-11-09 01:20:37'),(8,'page_view',NULL,'b1448419-f7c9-4ad6-bf05-cb15a72e221b','/',NULL,NULL,'2026-11-09 01:21:05');
/*!40000 ALTER TABLE `analytics_events` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_id` int unsigned DEFAULT NULL,
  `action` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `before_data` longtext COLLATE utf8mb4_unicode_ci,
  `after_data` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_entity` (`entity`,`entity_id`,`created_at`),
  KEY `audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `business_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `business_contacts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_id` int unsigned DEFAULT NULL,
  `role_label` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contacts_zone_fk` (`zone_id`),
  CONSTRAINT `contacts_zone_fk` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `business_contacts` WRITE;
/*!40000 ALTER TABLE `business_contacts` DISABLE KEYS */;
INSERT INTO `business_contacts` VALUES (1,'Rocío Vargas','70000000',1,'Pedidos Zona Norte',1,1,1,'2026-11-09 01:20:34','2026-11-09 01:20:35',NULL),(2,'Marcos Rojas','70000000',2,'Pedidos Zona Sur',0,2,1,'2026-11-09 01:20:34','2026-11-09 01:20:35',NULL),(3,'Lidia Paz','70000000',3,'Pedidos Zona Este',0,3,1,'2026-11-09 01:20:34','2026-11-09 01:20:35',NULL);
/*!40000 ALTER TABLE `business_contacts` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `content_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `content_sections` (
  `key` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `draft` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `published` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `published_at` datetime DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `content_sections` WRITE;
/*!40000 ALTER TABLE `content_sections` DISABLE KEYS */;
INSERT INTO `content_sections` VALUES ('benefits','{\"title\":\"Lo que nos importa\",\"items\":[{\"icon\":\"egg\",\"title\":\"Huevos frescos\",\"text\":\"Ofrecemos seis categorías, de Jumbo (doble yema) a quinta, para que elijas el tamaño que necesitas.\"},{\"icon\":\"star\",\"title\":\"Excelente calidad\",\"text\":\"Te indicamos la categoría de cada maple para que sepas exactamente qué recibes.\"},{\"icon\":\"leaf\",\"title\":\"Producción responsable\",\"text\":\"Es nuestro compromiso de trabajo. Pregúntanos lo que quieras saber sobre la granja.\"},{\"icon\":\"home\",\"title\":\"De nuestra granja a tu mesa\",\"text\":\"Pides directamente a la granja y te atendemos por WhatsApp.\"},{\"icon\":\"shield\",\"title\":\"Calidad y confianza\",\"text\":\"Precios publicados con fecha y cada pedido confirmado personalmente por nuestro equipo.\"}]}','{\"title\":\"Lo que nos importa\",\"items\":[{\"icon\":\"egg\",\"title\":\"Huevos frescos\",\"text\":\"Ofrecemos seis categorías, de Jumbo (doble yema) a quinta, para que elijas el tamaño que necesitas.\"},{\"icon\":\"star\",\"title\":\"Excelente calidad\",\"text\":\"Te indicamos la categoría de cada maple para que sepas exactamente qué recibes.\"},{\"icon\":\"leaf\",\"title\":\"Producción responsable\",\"text\":\"Es nuestro compromiso de trabajo. Pregúntanos lo que quieras saber sobre la granja.\"},{\"icon\":\"home\",\"title\":\"De nuestra granja a tu mesa\",\"text\":\"Pides directamente a la granja y te atendemos por WhatsApp.\"},{\"icon\":\"shield\",\"title\":\"Calidad y confianza\",\"text\":\"Precios publicados con fecha y cada pedido confirmado personalmente por nuestro equipo.\"}]}','2026-11-09 01:20:34',NULL,'2026-11-09 01:20:34'),('brand','{\"title\":\"Distribuidora Demo\",\"text\":\"Somos Distribuidora Demo, de Santa Cruz, Bolivia. Atendemos pedidos de huevos por maple y por paquete en las zonas Norte, Sur y Este, con atención directa por WhatsApp.\"}','{\"title\":\"Distribuidora Demo\",\"text\":\"Somos Distribuidora Demo, de Santa Cruz, Bolivia. Atendemos pedidos de huevos por maple y por paquete en las zonas Norte, Sur y Este, con atención directa por WhatsApp.\"}','2026-11-09 01:20:34',NULL,'2026-11-09 01:20:35'),('contacts','{\"title\":\"Contactos por zona\",\"text\":\"Los pedidos llegan a nuestro número principal. También puedes escribir al contacto de apoyo de tu zona.\"}','{\"title\":\"Contactos por zona\",\"text\":\"Los pedidos llegan a nuestro número principal. También puedes escribir al contacto de apoyo de tu zona.\"}','2026-11-09 01:20:34',NULL,'2026-11-09 01:20:34'),('faq','{\"title\":\"Preguntas frecuentes\",\"items\":[{\"q\":\"¿Qué presentaciones tienen?\",\"a\":\"Vendemos por maple de 30 huevos y por paquete de 10 maples (300 huevos), en categorías de Jumbo a quinta.\"},{\"q\":\"¿Cómo hago un pedido?\",\"a\":\"Agrega los productos a tu pedido, completa tus datos y envíalo. Se guarda con un número y se abre WhatsApp con el detalle listo para enviar.\"},{\"q\":\"¿Cómo confirmo la disponibilidad?\",\"a\":\"Nuestro equipo revisa cada pedido y te confirma por WhatsApp la disponibilidad, el precio final y la entrega.\"},{\"q\":\"¿En qué zonas atienden?\",\"a\":\"Tenemos contactos para la Zona Norte, la Zona Sur y la Zona Este. Si estás en otra zona, escríbenos y te decimos si podemos llegar.\"},{\"q\":\"¿Cómo se coordina el pago y la entrega?\",\"a\":\"Se coordinan por WhatsApp al confirmar tu pedido.\"}]}','{\"title\":\"Preguntas frecuentes\",\"items\":[{\"q\":\"¿Qué presentaciones tienen?\",\"a\":\"Vendemos por maple de 30 huevos y por paquete de 10 maples (300 huevos), en categorías de Jumbo a quinta.\"},{\"q\":\"¿Cómo hago un pedido?\",\"a\":\"Agrega los productos a tu pedido, completa tus datos y envíalo. Se guarda con un número y se abre WhatsApp con el detalle listo para enviar.\"},{\"q\":\"¿Cómo confirmo la disponibilidad?\",\"a\":\"Nuestro equipo revisa cada pedido y te confirma por WhatsApp la disponibilidad, el precio final y la entrega.\"},{\"q\":\"¿En qué zonas atienden?\",\"a\":\"Tenemos contactos para la Zona Norte, la Zona Sur y la Zona Este. Si estás en otra zona, escríbenos y te decimos si podemos llegar.\"},{\"q\":\"¿Cómo se coordina el pago y la entrega?\",\"a\":\"Se coordinan por WhatsApp al confirmar tu pedido.\"}]}','2026-11-09 01:20:34',NULL,'2026-11-09 01:20:34'),('hero','{\"eyebrow\":\"Huevos frescos • Calidad • Confianza\",\"title\":\"Huevos frescos, de la granja a tu mesa\",\"subtitle\":\"Elige por maple o por paquete, arma tu pedido en un minuto y confírmalo por WhatsApp con nuestro equipo.\",\"primary_cta\":\"Ver precios de hoy\",\"secondary_cta\":\"Hacer pedido\"}','{\"eyebrow\":\"Huevos frescos • Calidad • Confianza\",\"title\":\"Huevos frescos, de la granja a tu mesa\",\"subtitle\":\"Elige por maple o por paquete, arma tu pedido en un minuto y confírmalo por WhatsApp con nuestro equipo.\",\"primary_cta\":\"Ver precios de hoy\",\"secondary_cta\":\"Hacer pedido\"}','2026-11-09 01:20:34',NULL,'2026-11-09 01:20:35'),('order','{\"notice\":\"Tu pedido se guarda con un número y lo envías por WhatsApp. La confirmación final de precio, disponibilidad y entrega la realiza nuestro equipo.\"}','{\"notice\":\"Tu pedido se guarda con un número y lo envías por WhatsApp. La confirmación final de precio, disponibilidad y entrega la realiza nuestro equipo.\"}','2026-11-09 01:20:34',NULL,'2026-11-09 01:20:34');
/*!40000 ALTER TABLE `content_sections` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_id` int unsigned DEFAULT NULL,
  `address` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `reference` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_phone` (`phone`),
  KEY `customers_name` (`name`),
  KEY `customers_zone_fk` (`zone_id`),
  CONSTRAINT `customers_zone_fk` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'Tienda Doña Elena','71110001',2,'Av. Santos Dumont, 3er anillo','Frente al mercado',NULL,0,'2026-11-09 01:20:36','2026-11-09 01:20:36',NULL),(2,'Panadería El Trigal','71110002',1,'Calle Beni 450','Portón azul',NULL,0,'2026-11-09 01:20:37','2026-11-09 01:20:37',NULL),(3,'Restaurante La Casona','71110003',3,'Av. Virgen de Cotoca, 4to anillo','',NULL,0,'2026-11-09 01:20:37','2026-11-09 01:20:37',NULL),(4,'Minimarket San José','71110004',2,'Barrio Lazareto, calle 5','Esquina con farmacia',NULL,0,'2026-11-09 01:20:37','2026-11-09 01:20:37',NULL),(5,'Juan Pérez','71110005',1,'Equipetrol, calle 9','',NULL,0,'2026-11-09 01:20:37','2026-11-09 01:20:37',NULL),(6,'Pensión Doña Rosa','71110006',3,'Plan 3000, UV 12','Casa verde',NULL,0,'2026-11-09 01:20:37','2026-11-09 01:20:37',NULL),(7,'Heladería Polar','71110007',NULL,'Centro, calle Junín','',NULL,0,'2026-11-09 01:20:37','2026-11-09 01:20:37',NULL);
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `inventory_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_status` (
  `variant_id` int unsigned NOT NULL,
  `status` enum('disponible','poco_stock','agotado','por_confirmar') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'por_confirmar',
  `quantity` int unsigned DEFAULT NULL,
  `low_threshold` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`variant_id`),
  CONSTRAINT `inventory_variant_fk` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `inventory_status` WRITE;
/*!40000 ALTER TABLE `inventory_status` DISABLE KEYS */;
INSERT INTO `inventory_status` VALUES (1,'por_confirmar',NULL,NULL,NULL,'2026-11-09 01:20:34'),(2,'por_confirmar',NULL,NULL,NULL,'2026-11-09 01:20:34'),(3,'por_confirmar',NULL,NULL,NULL,'2026-11-09 01:20:34'),(4,'por_confirmar',NULL,NULL,NULL,'2026-11-09 01:20:34'),(5,'por_confirmar',NULL,NULL,NULL,'2026-11-09 01:20:34'),(6,'por_confirmar',NULL,NULL,NULL,'2026-11-09 01:20:34'),(7,'por_confirmar',NULL,NULL,NULL,'2026-11-09 01:20:34'),(8,'por_confirmar',NULL,NULL,NULL,'2026-11-09 01:20:34'),(9,'por_confirmar',NULL,NULL,NULL,'2026-11-09 01:20:34'),(10,'por_confirmar',NULL,NULL,NULL,'2026-11-09 01:20:34'),(11,'por_confirmar',NULL,NULL,NULL,'2026-11-09 01:20:34'),(12,'por_confirmar',NULL,NULL,NULL,'2026-11-09 01:20:34');
/*!40000 ALTER TABLE `inventory_status` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `order_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `type` enum('created','status','note','assigned','whatsapp','edited') COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_value` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_value` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `actor_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `events_order` (`order_id`,`created_at`),
  KEY `events_actor_fk` (`actor_id`),
  CONSTRAINT `events_actor_fk` FOREIGN KEY (`actor_id`) REFERENCES `profiles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `events_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `order_events` WRITE;
/*!40000 ALTER TABLE `order_events` DISABLE KEYS */;
INSERT INTO `order_events` VALUES (1,1,'created',NULL,'nuevo',NULL,NULL,'2026-11-06 00:20:36'),(2,1,'status','nuevo','confirmado',NULL,3,'2026-11-06 00:20:36'),(3,1,'status','confirmado','entregado','Cobrado',3,'2026-11-06 00:20:36'),(4,2,'created',NULL,'nuevo',NULL,NULL,'2026-11-06 23:20:37'),(5,2,'status','nuevo','confirmado',NULL,2,'2026-11-06 23:20:37'),(6,2,'status','confirmado','entregado','Fiado (cobrar después)',2,'2026-11-06 23:20:37'),(7,3,'created',NULL,'nuevo',NULL,NULL,'2026-11-07 20:20:37'),(8,3,'status','nuevo','confirmado',NULL,4,'2026-11-07 20:20:37'),(9,3,'status','confirmado','entregado','Cobrado',4,'2026-11-07 20:20:37'),(10,4,'created',NULL,'nuevo',NULL,NULL,'2026-11-07 20:20:37'),(11,4,'status','nuevo','confirmado',NULL,3,'2026-11-07 20:20:37'),(12,5,'created',NULL,'nuevo',NULL,NULL,'2026-11-08 23:20:37'),(13,5,'status','nuevo','confirmado',NULL,2,'2026-11-08 23:20:37'),(14,6,'created',NULL,'nuevo',NULL,NULL,'2026-11-09 00:20:37'),(15,7,'created',NULL,'nuevo',NULL,NULL,'2026-11-08 19:20:37');
/*!40000 ALTER TABLE `order_events` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `variant_id` int unsigned NOT NULL,
  `product_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `presentation` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `units` int unsigned NOT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `quantity` int unsigned NOT NULL,
  `line_total` decimal(12,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `items_order` (`order_id`),
  KEY `items_variant` (`variant_id`),
  CONSTRAINT `items_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `items_variant_fk` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,2,'Segunda','maple',30,27.00,4,108.00,'2026-11-09 01:20:36'),(2,2,6,'Primera','paquete',300,270.00,1,270.00,'2026-11-09 01:20:37'),(3,2,11,'Jumbo','maple',30,32.00,2,64.00,'2026-11-09 01:20:37'),(4,3,3,'Tercera','maple',30,25.00,6,150.00,'2026-11-09 01:20:37'),(5,4,7,'Segunda','paquete',300,250.00,1,250.00,'2026-11-09 01:20:37'),(6,5,11,'Jumbo','maple',30,32.00,1,32.00,'2026-11-09 01:20:37'),(7,5,2,'Segunda','maple',30,27.00,1,27.00,'2026-11-09 01:20:37'),(8,6,4,'Cuarta','maple',30,23.00,5,115.00,'2026-11-09 01:20:37'),(9,7,1,'Primera','maple',30,29.00,3,87.00,'2026-11-09 01:20:37');
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `public_token` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int unsigned NOT NULL,
  `status` enum('nuevo','pendiente_whatsapp','confirmado','preparando','listo','entregado','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nuevo',
  `customer_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_phone` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_id` int unsigned DEFAULT NULL,
  `zone_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `reference` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `notes` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `has_unpriced_items` tinyint(1) NOT NULL DEFAULT '0',
  `contact_id` int unsigned DEFAULT NULL,
  `assigned_to` int unsigned DEFAULT NULL,
  `whatsapp_opened_at` datetime DEFAULT NULL,
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'web',
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_code` (`code`),
  UNIQUE KEY `orders_token` (`public_token`),
  KEY `orders_created` (`created_at`),
  KEY `orders_status` (`status`,`created_at`),
  KEY `orders_customer` (`customer_id`),
  KEY `orders_zone` (`zone_id`),
  KEY `orders_contact_fk` (`contact_id`),
  KEY `orders_assigned_fk` (`assigned_to`),
  CONSTRAINT `orders_assigned_fk` FOREIGN KEY (`assigned_to`) REFERENCES `profiles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_contact_fk` FOREIGN KEY (`contact_id`) REFERENCES `business_contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `orders_zone_fk` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,'ACS-261108-0001','2b64df53ef96fc0a87f85069ec6547ae',1,'entregado','Tienda Doña Elena','71110001',2,'Zona Sur','Av. Santos Dumont, 3er anillo','Frente al mercado','',108.00,108.00,0,2,3,NULL,'web',0,'2026-11-06 00:20:36','2026-11-09 01:20:37'),(2,'ACS-261108-0002','c65917d875b842517ab00d163402ea79',2,'entregado','Panadería El Trigal','71110002',1,'Zona Norte','Calle Beni 450','Portón azul','',334.00,334.00,0,1,2,NULL,'web',0,'2026-11-06 23:20:37','2026-11-09 01:20:37'),(3,'ACS-261108-0003','728b6eeaf933536b59ac19aebf7961e2',3,'entregado','Restaurante La Casona','71110003',3,'Zona Este','Av. Virgen de Cotoca, 4to anillo','','',150.00,150.00,0,3,4,NULL,'web',0,'2026-11-07 20:20:37','2026-11-09 01:20:37'),(4,'ACS-261108-0004','e4d49d237fbad1a5c645448dcafe99a3',4,'confirmado','Minimarket San José','71110004',2,'Zona Sur','Barrio Lazareto, calle 5','Esquina con farmacia','',250.00,250.00,0,2,3,NULL,'web',0,'2026-11-07 20:20:37','2026-11-09 01:20:37'),(5,'ACS-261108-0005','bca8e9e7c267c79dd164ed162471f905',5,'confirmado','Juan Pérez','71110005',1,'Zona Norte','Equipetrol, calle 9','','',59.00,59.00,0,1,2,NULL,'web',0,'2026-11-08 23:20:37','2026-11-09 01:20:37'),(6,'ACS-261108-0006','0d00a502ffbb8b8544c6beefeabef3ef',6,'nuevo','Pensión Doña Rosa','71110006',3,'Zona Este','Plan 3000, UV 12','Casa verde','',115.00,115.00,0,3,4,NULL,'web',0,'2026-11-09 00:20:37','2026-11-09 01:20:37'),(7,'ACS-261108-0007','3b065ee92faf5066754d63f5e38f1ecf',7,'nuevo','Heladería Polar','71110007',NULL,'','Centro, calle Junín','','',87.00,87.00,0,1,2,NULL,'web',0,'2026-11-08 19:20:37','2026-11-09 01:20:37');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `partner_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `partner_sales` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `seller_id` int unsigned NOT NULL,
  `product_id` int unsigned NOT NULL,
  `presentation` enum('maple','paquete') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int unsigned NOT NULL,
  `maples` int unsigned NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `paid` tinyint(1) NOT NULL DEFAULT '1',
  `from_central` tinyint(1) NOT NULL DEFAULT '0',
  `customer_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `note` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `order_id` int unsigned DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_seller` (`seller_id`,`created_at`),
  KEY `sales_created` (`created_at`),
  KEY `sales_product_fk` (`product_id`),
  KEY `sales_created_fk` (`created_by`),
  KEY `sales_order` (`order_id`),
  CONSTRAINT `sales_created_fk` FOREIGN KEY (`created_by`) REFERENCES `profiles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `sales_seller_fk` FOREIGN KEY (`seller_id`) REFERENCES `profiles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `partner_sales` WRITE;
/*!40000 ALTER TABLE `partner_sales` DISABLE KEYS */;
INSERT INTO `partner_sales` VALUES (1,3,2,'maple',4,4,27.00,108.00,1,0,'Tienda Doña Elena','Pedido ACS-261108-0001',1,3,'2026-11-06 00:20:36',NULL),(2,2,1,'paquete',1,10,270.00,270.00,0,1,'Panadería El Trigal','Pedido ACS-261108-0002',2,2,'2026-11-06 23:20:37',NULL),(3,2,6,'maple',2,2,32.00,64.00,0,1,'Panadería El Trigal','Pedido ACS-261108-0002',2,2,'2026-11-06 23:20:37',NULL),(4,4,3,'maple',6,6,25.00,150.00,1,0,'Restaurante La Casona','Pedido ACS-261108-0003',3,4,'2026-11-07 20:20:37',NULL);
/*!40000 ALTER TABLE `partner_sales` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `price_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `price_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `publication_id` int unsigned NOT NULL,
  `variant_id` int unsigned NOT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `availability` enum('disponible','poco_stock','agotado','por_confirmar') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disponible',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `history_pub_variant` (`publication_id`,`variant_id`),
  KEY `history_variant` (`variant_id`),
  CONSTRAINT `history_pub_fk` FOREIGN KEY (`publication_id`) REFERENCES `price_publications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `history_variant_fk` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `price_history` WRITE;
/*!40000 ALTER TABLE `price_history` DISABLE KEYS */;
INSERT INTO `price_history` VALUES (1,1,1,29.00,'disponible',NULL,'2026-11-09 01:20:34','2026-11-09 01:20:34'),(2,1,2,27.00,'disponible',NULL,'2026-11-09 01:20:34','2026-11-09 01:20:34'),(3,1,3,25.00,'disponible',NULL,'2026-11-09 01:20:34','2026-11-09 01:20:34'),(4,1,4,23.00,'disponible',NULL,'2026-11-09 01:20:34','2026-11-09 01:20:34'),(5,1,5,20.00,'disponible',NULL,'2026-11-09 01:20:34','2026-11-09 01:20:34'),(6,1,6,270.00,'disponible',NULL,'2026-11-09 01:20:34','2026-11-09 01:20:36'),(7,1,7,250.00,'disponible',NULL,'2026-11-09 01:20:34','2026-11-09 01:20:34'),(8,1,8,230.00,'disponible',NULL,'2026-11-09 01:20:34','2026-11-09 01:20:34'),(9,1,9,210.00,'disponible',NULL,'2026-11-09 01:20:34','2026-11-09 01:20:34'),(10,1,10,180.00,'disponible',NULL,'2026-11-09 01:20:34','2026-11-09 01:20:36'),(11,1,11,32.00,'disponible',NULL,'2026-11-09 01:20:36','2026-11-09 01:20:36'),(12,1,12,300.00,'disponible',NULL,'2026-11-09 01:20:36','2026-11-09 01:20:36');
/*!40000 ALTER TABLE `price_history` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `price_publications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `price_publications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `status` enum('draft','published','discarded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `effective_from` datetime NOT NULL,
  `note` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_reference` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `published_by` int unsigned DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pubs_status` (`status`,`effective_from`),
  KEY `pubs_created_fk` (`created_by`),
  KEY `pubs_published_fk` (`published_by`),
  CONSTRAINT `pubs_created_fk` FOREIGN KEY (`created_by`) REFERENCES `profiles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pubs_published_fk` FOREIGN KEY (`published_by`) REFERENCES `profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `price_publications` WRITE;
/*!40000 ALTER TABLE `price_publications` DISABLE KEYS */;
INSERT INTO `price_publications` VALUES (1,'published','2026-11-08 23:20:36','Lista de demostración',0,NULL,1,'2026-11-09 01:20:36','2026-11-09 01:20:34','2026-11-09 01:20:36');
/*!40000 ALTER TABLE `price_publications` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `product_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_variants` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `presentation` enum('maple','paquete') COLLATE utf8mb4_unicode_ci NOT NULL,
  `units` int unsigned NOT NULL,
  `label` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `variants_product_pres` (`product_id`,`presentation`),
  CONSTRAINT `variants_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `product_variants` WRITE;
/*!40000 ALTER TABLE `product_variants` DISABLE KEYS */;
INSERT INTO `product_variants` VALUES (1,1,'maple',30,'Maple · 30 huevos',1,1,'2026-11-09 01:20:34','2026-11-09 01:20:34'),(2,2,'maple',30,'Maple · 30 huevos',1,1,'2026-11-09 01:20:34','2026-11-09 01:20:34'),(3,3,'maple',30,'Maple · 30 huevos',1,1,'2026-11-09 01:20:34','2026-11-09 01:20:34'),(4,4,'maple',30,'Maple · 30 huevos',1,1,'2026-11-09 01:20:34','2026-11-09 01:20:34'),(5,5,'maple',30,'Maple · 30 huevos',1,1,'2026-11-09 01:20:34','2026-11-09 01:20:34'),(6,1,'paquete',300,'Paquete · 10 maples (300 huevos)',2,1,'2026-11-09 01:20:34','2026-11-09 01:20:34'),(7,2,'paquete',300,'Paquete · 10 maples (300 huevos)',2,1,'2026-11-09 01:20:34','2026-11-09 01:20:34'),(8,3,'paquete',300,'Paquete · 10 maples (300 huevos)',2,1,'2026-11-09 01:20:34','2026-11-09 01:20:34'),(9,4,'paquete',300,'Paquete · 10 maples (300 huevos)',2,1,'2026-11-09 01:20:34','2026-11-09 01:20:34'),(10,5,'paquete',300,'Paquete · 10 maples (300 huevos)',2,1,'2026-11-09 01:20:34','2026-11-09 01:20:34'),(11,6,'maple',30,'Maple · 30 huevos',1,1,'2026-11-09 01:20:34','2026-11-09 01:20:34'),(12,6,'paquete',300,'Paquete · 10 maples (300 huevos)',2,1,'2026-11-09 01:20:34','2026-11-09 01:20:34');
/*!40000 ALTER TABLE `product_variants` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `grade` enum('jumbo','primera','segunda','tercera','cuarta','quinta') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(240) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `badge` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `status` enum('draft','active','inactive','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_slug` (`slug`),
  KEY `products_status_sort` (`status`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'primera','Primera','primera','Huevo grande, ideal para venta.',NULL,NULL,2,'active','2026-11-09 01:20:34','2026-11-09 01:20:34',NULL),(2,'segunda','Segunda','segunda','','La más pedida',NULL,3,'active','2026-11-09 01:20:34','2026-11-09 01:20:34',NULL),(3,'tercera','Tercera','tercera','',NULL,NULL,4,'active','2026-11-09 01:20:34','2026-11-09 01:20:34',NULL),(4,'cuarta','Cuarta','cuarta','',NULL,NULL,5,'active','2026-11-09 01:20:34','2026-11-09 01:20:34',NULL),(5,'quinta','Quinta','quinta','',NULL,NULL,6,'active','2026-11-09 01:20:34','2026-11-09 01:20:34',NULL),(6,'jumbo','Jumbo','jumbo','Más de 75 g, doble yema.','Doble yema',NULL,0,'active','2026-11-09 01:20:34','2026-11-09 01:20:34',NULL);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `profiles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_id` int unsigned DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `session_version` int unsigned NOT NULL DEFAULT '1',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `profiles_email` (`email`),
  UNIQUE KEY `profiles_username` (`username`),
  UNIQUE KEY `profiles_phone` (`phone`),
  KEY `profiles_role_fk` (`role_id`),
  KEY `profiles_zone_fk` (`zone_id`),
  CONSTRAINT `profiles_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `profiles_zone_fk` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `profiles` WRITE;
/*!40000 ALTER TABLE `profiles` DISABLE KEYS */;
INSERT INTO `profiles` VALUES (1,NULL,'demo','70000000','Administrador Demo','admin',NULL,'$argon2id$v=19$m=65536,t=4,p=1$meO7RCW5LwXiGMzC9BEu7g$GxGCEdryfmcSyBQXnYcvVGOgR+5vvhLOnsYUXrqBQyQ',1,1,'2026-11-09 01:21:31','2026-11-09 01:20:36','2026-11-09 01:21:31',NULL),(2,NULL,'rocio','70000001','Rocío Vargas','socio',1,'$2y$12$L2hMdP.uSzH/1IVYlwb53.YGSFndB/zwIXLYGvJW/qIe14swURoS2',1,1,NULL,'2026-11-09 01:20:36','2026-11-09 01:20:36',NULL),(3,NULL,'marcos','70000002','Marcos Rojas','socio',2,'$argon2id$v=19$m=65536,t=4,p=1$ZS4pUjeYtF5uiFfLicMEqA$0aOftEZegkXynug12eYHUC2GQDtoHvQggADPaeHM15k',1,1,'2026-11-09 01:21:48','2026-11-09 01:20:36','2026-11-09 01:21:48',NULL),(4,NULL,'lidia','70000003','Lidia Paz','socio',3,'$2y$12$L2hMdP.uSzH/1IVYlwb53.YGSFndB/zwIXLYGvJW/qIe14swURoS2',1,1,NULL,'2026-11-09 01:20:36','2026-11-09 01:20:36',NULL);
/*!40000 ALTER TABLE `profiles` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rate_limits` (
  `rkey` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `window_start` datetime NOT NULL,
  `hits` int unsigned NOT NULL,
  PRIMARY KEY (`rkey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rate_limits` WRITE;
/*!40000 ALTER TABLE `rate_limits` DISABLE KEYS */;
INSERT INTO `rate_limits` VALUES ('ev:ec56f7c7ed0e81b38cbc3229e8a5c428','2026-11-09 01:21:05',1),('login-email:demo','2026-11-09 01:21:25',1),('login-email:marcos','2026-11-09 01:21:45',1),('login-ip:ec56f7c7ed0e81b38cbc3229e8a5c428','2026-11-09 01:21:25',2);
/*!40000 ALTER TABLE `rate_limits` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES ('admin','Administrador','Controla usuarios, precios, productos, contenido y configuración.'),('operador','Operador','Cambia estados de pedidos y la disponibilidad de productos.'),('socio','Socio','Registra ingresos de stock, retiros y ventas de su zona, y ve el resumen del negocio.'),('ventas','Ventas','Administra pedidos y clientes.');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `settlements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settlements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `seller_id` int unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `note` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `settle_seller` (`seller_id`,`created_at`),
  KEY `settle_created_fk` (`created_by`),
  CONSTRAINT `settle_created_fk` FOREIGN KEY (`created_by`) REFERENCES `profiles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `settle_seller_fk` FOREIGN KEY (`seller_id`) REFERENCES `profiles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `settlements` WRITE;
/*!40000 ALTER TABLE `settlements` DISABLE KEYS */;
INSERT INTO `settlements` VALUES (1,3,100.00,'Entregó en efectivo',1,'2026-11-08 01:20:38',NULL);
/*!40000 ALTER TABLE `settlements` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `site_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `site_settings` (
  `key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `site_settings` WRITE;
/*!40000 ALTER TABLE `site_settings` DISABLE KEYS */;
INSERT INTO `site_settings` VALUES ('business_name','\"Distribuidora Demo\"',NULL,'2026-11-09 01:20:35'),('city','\"Santa Cruz, Bolivia\"',NULL,'2026-11-09 01:20:34'),('hero_image_path','null',NULL,'2026-11-09 01:20:34'),('inventory_quantities_enabled','false',NULL,'2026-11-09 01:20:34'),('legal_text','\"Precios en bolivianos (Bs). La disponibilidad, el precio final y la entrega se confirman por WhatsApp con la empresa.\"',NULL,'2026-11-09 01:20:34'),('logo_path','null',NULL,'2026-11-09 01:20:34'),('max_users','10',NULL,'2026-11-09 01:20:35'),('package_discount','20',NULL,'2026-11-09 01:20:34'),('price_stale_after_hours','87600',NULL,'2026-11-09 01:20:35'),('schema_version','4',NULL,'2026-11-09 01:20:35'),('slogan','\"Pedidos directos al vendedor de tu zona\"',NULL,'2026-11-09 01:20:35'),('social','{\"instagram\":\"\",\"tiktok\":\"\",\"facebook\":\"\"}',NULL,'2026-11-09 01:20:35'),('stock_auto','true',NULL,'2026-11-09 01:20:34'),('stock_location','\"Almacén central\"',NULL,'2026-11-09 01:20:35'),('stock_low_maples','30',NULL,'2026-11-09 01:20:34'),('tagline','\"Huevos frescos • Precios del día • Entrega por zona\"',NULL,'2026-11-09 01:20:35'),('zone_routing_enabled','true',NULL,'2026-11-09 01:20:35');
/*!40000 ALTER TABLE `site_settings` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_movements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `holder_id` int unsigned DEFAULT NULL,
  `maples` int NOT NULL,
  `type` enum('ingreso','retiro','devolucion','venta','ajuste') COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` char(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_id` int unsigned DEFAULT NULL,
  `note` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `stock_product_holder` (`product_id`,`holder_id`),
  KEY `stock_created` (`created_at`),
  KEY `stock_batch` (`batch`),
  KEY `stock_holder_fk` (`holder_id`),
  KEY `stock_created_fk` (`created_by`),
  CONSTRAINT `stock_created_fk` FOREIGN KEY (`created_by`) REFERENCES `profiles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_holder_fk` FOREIGN KEY (`holder_id`) REFERENCES `profiles` (`id`),
  CONSTRAINT `stock_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `stock_movements` WRITE;
/*!40000 ALTER TABLE `stock_movements` DISABLE KEYS */;
INSERT INTO `stock_movements` VALUES (1,6,NULL,220,'ingreso',NULL,NULL,'Llegada de la granja',1,'2026-11-09 01:20:36'),(2,1,NULL,220,'ingreso',NULL,NULL,'Llegada de la granja',1,'2026-11-09 01:20:36'),(3,2,NULL,220,'ingreso',NULL,NULL,'Llegada de la granja',1,'2026-11-09 01:20:36'),(4,3,NULL,220,'ingreso',NULL,NULL,'Llegada de la granja',1,'2026-11-09 01:20:36'),(5,4,NULL,220,'ingreso',NULL,NULL,'Llegada de la granja',1,'2026-11-09 01:20:36'),(6,5,NULL,22,'ingreso',NULL,NULL,'Llegada de la granja',1,'2026-11-09 01:20:36'),(7,2,NULL,-30,'retiro',NULL,NULL,'',1,'2026-11-09 01:20:36'),(8,2,2,30,'retiro',NULL,NULL,'',1,'2026-11-09 01:20:36'),(9,3,NULL,-20,'retiro',NULL,NULL,'',1,'2026-11-09 01:20:36'),(10,3,2,20,'retiro',NULL,NULL,'',1,'2026-11-09 01:20:36'),(11,2,NULL,-30,'retiro',NULL,NULL,'',1,'2026-11-09 01:20:36'),(12,2,3,30,'retiro',NULL,NULL,'',1,'2026-11-09 01:20:36'),(13,3,NULL,-20,'retiro',NULL,NULL,'',1,'2026-11-09 01:20:36'),(14,3,3,20,'retiro',NULL,NULL,'',1,'2026-11-09 01:20:36'),(15,2,NULL,-30,'retiro',NULL,NULL,'',1,'2026-11-09 01:20:36'),(16,2,4,30,'retiro',NULL,NULL,'',1,'2026-11-09 01:20:36'),(17,3,NULL,-20,'retiro',NULL,NULL,'',1,'2026-11-09 01:20:36'),(18,3,4,20,'retiro',NULL,NULL,'',1,'2026-11-09 01:20:36'),(19,2,3,-4,'venta',NULL,1,'Pedido ACS-261108-0001',3,'2026-11-06 00:20:36'),(20,1,NULL,-10,'venta',NULL,2,'Pedido ACS-261108-0002',2,'2026-11-06 23:20:37'),(21,6,NULL,-2,'venta',NULL,3,'Pedido ACS-261108-0002',2,'2026-11-06 23:20:37'),(22,3,4,-6,'venta',NULL,4,'Pedido ACS-261108-0003',4,'2026-11-07 20:20:37');
/*!40000 ALTER TABLE `stock_movements` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `zones` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `zones_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `zones` WRITE;
/*!40000 ALTER TABLE `zones` DISABLE KEYS */;
INSERT INTO `zones` VALUES (1,'norte','Zona Norte',1,1,'2026-11-09 01:20:34','2026-11-09 01:20:34',NULL),(2,'sur','Zona Sur',2,1,'2026-11-09 01:20:34','2026-11-09 01:20:34',NULL),(3,'este','Zona Este',3,1,'2026-11-09 01:20:34','2026-11-09 01:20:34',NULL);
/*!40000 ALTER TABLE `zones` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

