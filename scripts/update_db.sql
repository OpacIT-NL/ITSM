SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE TABLE IF NOT EXISTS `itsm_am_configurationtemplates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `active` int(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `itsm_am_connectiontypes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `reverse_name` varchar(100) DEFAULT NULL,
  `active` int(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `itsm_am_configurations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customerid` int(11) NOT NULL,
  `templateid` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `active` int(1) NOT NULL DEFAULT 1,
  `createdby` int(11) DEFAULT NULL,
  `createdat` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedat` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `customerid` (`customerid`),
  KEY `templateid` (`templateid`),
  KEY `createdby` (`createdby`),
  CONSTRAINT `itsm_am_configurations_ibfk_1` FOREIGN KEY (`customerid`) REFERENCES `itsm_ob_customers` (`id`),
  CONSTRAINT `itsm_am_configurations_ibfk_2` FOREIGN KEY (`templateid`) REFERENCES `itsm_am_configurationtemplates` (`id`),
  CONSTRAINT `itsm_am_configurations_ibfk_3` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `itsm_am_configurationassets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `configurationid` int(11) NOT NULL,
  `assetid` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `configuration_asset_unique` (`configurationid`,`assetid`),
  KEY `assetid` (`assetid`),
  CONSTRAINT `itsm_am_configurationassets_ibfk_1` FOREIGN KEY (`configurationid`) REFERENCES `itsm_am_configurations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `itsm_am_configurationassets_ibfk_2` FOREIGN KEY (`assetid`) REFERENCES `itsm_am_assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `itsm_am_assetconnections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `configurationid` int(11) NOT NULL,
  `sourceassetid` int(11) NOT NULL,
  `targetassetid` int(11) NOT NULL,
  `connectiontypeid` int(11) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `createdby` int(11) DEFAULT NULL,
  `createdat` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_connection_unique` (`configurationid`,`sourceassetid`,`targetassetid`,`connectiontypeid`),
  KEY `sourceassetid` (`sourceassetid`),
  KEY `targetassetid` (`targetassetid`),
  KEY `connectiontypeid` (`connectiontypeid`),
  KEY `createdby` (`createdby`),
  CONSTRAINT `itsm_am_assetconnections_ibfk_1` FOREIGN KEY (`configurationid`) REFERENCES `itsm_am_configurations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `itsm_am_assetconnections_ibfk_2` FOREIGN KEY (`sourceassetid`) REFERENCES `itsm_am_assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `itsm_am_assetconnections_ibfk_3` FOREIGN KEY (`targetassetid`) REFERENCES `itsm_am_assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `itsm_am_assetconnections_ibfk_4` FOREIGN KEY (`connectiontypeid`) REFERENCES `itsm_am_connectiontypes` (`id`),
  CONSTRAINT `itsm_am_assetconnections_ibfk_5` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `itsm_api_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `token_prefix` varchar(32) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `operatorid` int(11) NOT NULL,
  `createdby` int(11) DEFAULT NULL,
  `createdat` datetime NOT NULL DEFAULT current_timestamp(),
  `lastusedat` datetime DEFAULT NULL,
  `expiresat` datetime DEFAULT NULL,
  `active` int(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `token_prefix` (`token_prefix`),
  KEY `operatorid` (`operatorid`),
  KEY `createdby` (`createdby`),
  KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `itsm_cm_changeactivitycomments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `changeactivityid` int(11) NOT NULL,
  `operatorid` int(11) DEFAULT NULL,
  `commenttext` longtext NOT NULL,
  `internalonly` int(1) NOT NULL DEFAULT 1,
  `createdat` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `changeactivityid` (`changeactivityid`),
  KEY `operatorid` (`operatorid`),
  CONSTRAINT `itsm_cm_changeactivitycomments_ibfk_1` FOREIGN KEY (`changeactivityid`) REFERENCES `itsm_cm_changeactivities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `itsm_cm_changeactivitycomments_ibfk_2` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
