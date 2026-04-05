SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE TABLE `itsm_am_assets` (
  `id` int(11) NOT NULL,
  `objectid` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `startdate` varchar(255) DEFAULT NULL,
  `enddate` varchar(255) DEFAULT NULL,
  `price` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL,
  `active` int(1) NOT NULL,
  `archived` int(1) NOT NULL,
  `customfield1` varchar(255) DEFAULT NULL,
  `customfield2` varchar(255) DEFAULT NULL,
  `customfield3` varchar(255) DEFAULT NULL,
  `customfield4` varchar(255) DEFAULT NULL,
  `customfield5` varchar(255) DEFAULT NULL,
  `customfield6` varchar(255) DEFAULT NULL,
  `customfield7` varchar(255) DEFAULT NULL,
  `customfield8` varchar(255) DEFAULT NULL,
  `customfield9` varchar(255) DEFAULT NULL,
  `customfield10` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_am_fields` (
  `id` int(11) NOT NULL,
  `type` int(11) NOT NULL,
  `field` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_am_types` (
  `id` int(11) NOT NULL,
  `type` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
ALTER TABLE `itsm_am_assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `objectid` (`objectid`);

ALTER TABLE `itsm_am_fields`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_am_types`
  ADD PRIMARY KEY (`id`);
  ALTER TABLE `itsm_am_assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_am_fields`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_am_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
  
  ALTER TABLE `itsm_am_fields` CHANGE `name` `name` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL;
  CREATE TRIGGER `after_insert_assettype` AFTER INSERT ON `itsm_am_types` FOR EACH ROW BEGIN DECLARE i INT DEFAULT 1; WHILE i <= 10 DO INSERT INTO itsm_am_fields (`type`, `field`, `name`) VALUES (NEW.id, CONCAT('customfield', i), NULL); SET i = i + 1; END WHILE; END
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
