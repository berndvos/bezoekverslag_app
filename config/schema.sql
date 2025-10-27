-- Adminer 4.8.1 MySQL 10.4.32-MariaDB dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `user_fullname` varchar(255) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `bezoekverslag`;
CREATE TABLE `bezoekverslag` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `klantnaam` varchar(255) NOT NULL,
  `projecttitel` varchar(255) DEFAULT NULL,
  `adres` varchar(255) DEFAULT NULL,
  `postcode` varchar(10) DEFAULT NULL,
  `plaats` varchar(255) DEFAULT NULL,
  `straatnaam` varchar(255) DEFAULT NULL,
  `huisnummer` varchar(10) DEFAULT NULL,
  `huisnummer_toevoeging` varchar(10) DEFAULT NULL,
  `kvk` varchar(50) DEFAULT NULL,
  `btw` varchar(50) DEFAULT NULL,
  `contact_naam` varchar(255) DEFAULT NULL,
  `contact_functie` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_tel` varchar(50) DEFAULT NULL,
  `situatie` text DEFAULT NULL,
  `doel` text DEFAULT NULL,
  `functioneel` text DEFAULT NULL,
  `uitbreiding` text DEFAULT NULL,
  `leverancier_ict` varchar(255) DEFAULT NULL,
  `leverancier_telecom` varchar(255) DEFAULT NULL,
  `leverancier_av` varchar(255) DEFAULT NULL,
  `gewenste_offertedatum` date DEFAULT NULL,
  `indicatief_budget` varchar(255) DEFAULT NULL,
  `wensen` text DEFAULT NULL,
  `beeldkwaliteitseisen` text DEFAULT NULL,
  `geluidseisen` text DEFAULT NULL,
  `bedieningseisen` text DEFAULT NULL,
  `beveiligingseisen` text DEFAULT NULL,
  `netwerkeisen` text DEFAULT NULL,
  `garantie` text DEFAULT NULL,
  `installatie_adres_afwijkend` varchar(3) DEFAULT 'Nee',
  `installatie_adres_straat` varchar(255) DEFAULT NULL,
  `installatie_adres_huisnummer` varchar(10) DEFAULT NULL,
  `installatie_adres_huisnummer_toevoeging` varchar(10) DEFAULT NULL,
  `installatie_adres_postcode` varchar(20) DEFAULT NULL,
  `installatie_adres_plaats` varchar(255) DEFAULT NULL,
  `cp_locatie_afwijkend` varchar(3) DEFAULT 'Nee',
  `cp_locatie_naam` varchar(255) DEFAULT NULL,
  `cp_locatie_functie` varchar(255) DEFAULT NULL,
  `cp_locatie_email` varchar(255) DEFAULT NULL,
  `cp_locatie_tel` varchar(50) DEFAULT NULL,
  `afvoer` varchar(3) DEFAULT NULL,
  `afvoer_omschrijving` text DEFAULT NULL,
  `installatiedatum` date DEFAULT NULL,
  `locatie_apparatuur` text DEFAULT NULL,
  `aantal_installaties` varchar(255) DEFAULT NULL,
  `parkeren` text DEFAULT NULL,
  `toegang` text DEFAULT NULL,
  `boortijden` text DEFAULT NULL,
  `opleverdatum` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `last_modified_at` datetime DEFAULT NULL,
  `last_modified_by` varchar(255) DEFAULT NULL,
  `pdf_version` int(11) DEFAULT 0,
  `pdf_path` varchar(255) DEFAULT NULL,
  `pdf_generated_at` datetime DEFAULT NULL,
  `pdf_up_to_date` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `bezoekverslag_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `client_access`;
CREATE TABLE `client_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bezoekverslag_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `can_edit` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_modified_by` varchar(255) DEFAULT NULL,
  `last_modified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`,`bezoekverslag_id`),
  KEY `bezoekverslag_id` (`bezoekverslag_id`),
  CONSTRAINT `client_access_ibfk_1` FOREIGN KEY (`bezoekverslag_id`) REFERENCES `bezoekverslag` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `foto`;
CREATE TABLE `foto` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ruimte_id` int(11) NOT NULL,
  `pad` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ruimte_id` (`ruimte_id`),
  CONSTRAINT `foto_ibfk_1` FOREIGN KEY (`ruimte_id`) REFERENCES `ruimte` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `project_bestanden`;
CREATE TABLE `project_bestanden` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `verslag_id` int(11) NOT NULL,
  `bestandsnaam` varchar(255) NOT NULL,
  `pad` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `verslag_id` (`verslag_id`),
  CONSTRAINT `project_bestanden_ibfk_1` FOREIGN KEY (`verslag_id`) REFERENCES `bezoekverslag` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `verslag_collaborators`;
CREATE TABLE `verslag_collaborators` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `verslag_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `granted_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `verslag_user_unique` (`verslag_id`,`user_id`),
  KEY `verslag_id` (`verslag_id`),
  KEY `user_id` (`user_id`),
  KEY `granted_by` (`granted_by`),
  CONSTRAINT `verslag_collaborators_ibfk_1` FOREIGN KEY (`verslag_id`) REFERENCES `bezoekverslag` (`id`) ON DELETE CASCADE,
  CONSTRAINT `verslag_collaborators_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `ruimte`;
CREATE TABLE `ruimte` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `verslag_id` int(11) NOT NULL,
  `naam` varchar(255) NOT NULL,
  `etage` varchar(255) DEFAULT NULL,
  `opmerkingen` text DEFAULT NULL,
  `aantal_aansluitingen` int(11) DEFAULT NULL,
  `type_aansluitingen` varchar(255) DEFAULT NULL,
  `huidig_scherm` varchar(255) DEFAULT NULL,
  `audio_aanwezig` varchar(3) DEFAULT NULL,
  `beeldkwaliteit` text DEFAULT NULL,
  `gewenst_scherm` varchar(255) DEFAULT NULL,
  `gewenst_aansluitingen` varchar(255) DEFAULT NULL,
  `presentatie_methode` varchar(50) DEFAULT NULL,
  `geluid_gewenst` varchar(3) DEFAULT NULL,
  `overige_wensen` text DEFAULT NULL,
  `kabeltraject_mogelijk` varchar(3) DEFAULT NULL,
  `beperkingen` text DEFAULT NULL,
  `ophanging` varchar(50) DEFAULT NULL,
  `montage_extra` text DEFAULT NULL,
  `stroom_voldoende` varchar(3) DEFAULT NULL,
  `stroom_extra` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `schema_version` int(11) NOT NULL DEFAULT 1,
  `lengte_ruimte` varchar(255) DEFAULT NULL,
  `breedte_ruimte` varchar(255) DEFAULT NULL,
  `hoogte_plafond` varchar(255) DEFAULT NULL,
  `type_plafond` varchar(255) DEFAULT NULL,
  `ruimte_boven_plafond` varchar(255) DEFAULT NULL,
  `huidige_situatie_v2` text DEFAULT NULL,
  `type_wand` varchar(255) DEFAULT NULL,
  `netwerk_aanwezig` varchar(3) DEFAULT NULL,
  `netwerk_extra` varchar(255) DEFAULT NULL,
  `netwerk_afstand` varchar(255) DEFAULT NULL,
  `stroom_aanwezig` varchar(3) DEFAULT NULL,
  `stroom_extra_v2` varchar(255) DEFAULT NULL,
  `stroom_afstand` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `verslag_id` (`verslag_id`),
  CONSTRAINT `ruimte_ibfk_1` FOREIGN KEY (`verslag_id`) REFERENCES `bezoekverslag` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','poweruser','accountmanager','viewer') NOT NULL DEFAULT 'viewer',
  `status` enum('pending','active','denied') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `two_factor_code` varchar(255) DEFAULT NULL,
  `two_factor_expires_at` datetime DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `force_password_change` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
