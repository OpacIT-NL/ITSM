SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE TABLE `itsm_core_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `settingkey` varchar(100) NOT NULL,
  `settingvalue` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settingkey` (`settingkey`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

ALTER TABLE `itsm_ob_customers`
  ADD COLUMN `defaultlanguage` varchar(10) DEFAULT NULL AFTER `primaryphone`;

ALTER TABLE `itsm_ob_operators`
  ADD COLUMN `preferredlanguage` varchar(10) DEFAULT NULL AFTER `isadmin`;

ALTER TABLE `itsm_ob_persons`
  ADD COLUMN `preferredlanguage` varchar(10) DEFAULT NULL AFTER `allowssp`;

INSERT INTO `itsm_core_settings` (`settingkey`, `settingvalue`)
VALUES ('default_language', 'nl_NL');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
