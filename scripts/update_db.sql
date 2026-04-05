SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE TABLE `itsm_im_incidents` (
  `id` int(11) NOT NULL,
  `incidentnumber` varchar(10) NOT NULL,
  `incidenttype` varchar(32) NOT NULL,
  `majorincidentid` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `customerid` int(11) NOT NULL,
  `personid` int(11) NOT NULL,
  `personemail` varchar(255) DEFAULT NULL,
  `personphone` varchar(15) DEFAULT NULL,
  `categoryid` int(11) NOT NULL,
  `subcategoryid` int(11) DEFAULT NULL,
  `assetid` int(11) DEFAULT NULL,
  `operatorgroupid` int(11) DEFAULT NULL,
  `operatorid` int(11) DEFAULT NULL,
  `statusid` int(11) NOT NULL,
  `createdby` int(11) NOT NULL,
  `createdat` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedat` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_im_incidentcomments` (
  `id` int(11) NOT NULL,
  `incidentid` int(11) NOT NULL,
  `operatorid` int(11) NOT NULL,
  `commenttext` longtext NOT NULL,
  `internalonly` int(1) NOT NULL DEFAULT 0,
  `createdat` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

ALTER TABLE `itsm_im_incidents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `incidentnumber` (`incidentnumber`),
  ADD KEY `majorincidentid` (`majorincidentid`),
  ADD KEY `statusid` (`statusid`),
  ADD KEY `operatorid` (`operatorid`),
  ADD KEY `operatorgroupid` (`operatorgroupid`),
  ADD KEY `customerid` (`customerid`),
  ADD KEY `personid` (`personid`);

ALTER TABLE `itsm_im_incidentcomments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incidentid` (`incidentid`),
  ADD KEY `operatorid` (`operatorid`);

ALTER TABLE `itsm_im_incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_im_incidentcomments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_im_incidents`
  ADD CONSTRAINT `itsm_im_incidents_ibfk_0` FOREIGN KEY (`majorincidentid`) REFERENCES `itsm_im_incidents` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `itsm_im_incidents_ibfk_1` FOREIGN KEY (`customerid`) REFERENCES `itsm_ob_customers` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_2` FOREIGN KEY (`personid`) REFERENCES `itsm_ob_persons` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_3` FOREIGN KEY (`categoryid`) REFERENCES `itsm_core_category` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_4` FOREIGN KEY (`subcategoryid`) REFERENCES `itsm_core_subcategory` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_5` FOREIGN KEY (`assetid`) REFERENCES `itsm_am_assets` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_6` FOREIGN KEY (`operatorgroupid`) REFERENCES `itsm_ob_operatorgroups` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_7` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_8` FOREIGN KEY (`statusid`) REFERENCES `itsm_core_status` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_9` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`);

ALTER TABLE `itsm_im_incidentcomments`
  ADD CONSTRAINT `itsm_im_incidentcomments_ibfk_1` FOREIGN KEY (`incidentid`) REFERENCES `itsm_im_incidents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `itsm_im_incidentcomments_ibfk_2` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
