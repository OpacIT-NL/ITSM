SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE TABLE `itsm_cm_changes` (
  `id` int(11) NOT NULL,
  `changenumber` varchar(10) NOT NULL,
  `requesttype` varchar(16) NOT NULL,
  `approvalstate` varchar(16) NOT NULL DEFAULT 'request',
  `changetype` varchar(32) NOT NULL,
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
  `coordinatorid` int(11) DEFAULT NULL,
  `statusid` int(11) DEFAULT NULL,
  `closed` int(1) NOT NULL DEFAULT 0,
  `createdby` int(11) NOT NULL,
  `createdat` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedat` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_cm_changecomments` (
  `id` int(11) NOT NULL,
  `changeid` int(11) NOT NULL,
  `operatorid` int(11) NOT NULL,
  `commenttext` longtext NOT NULL,
  `internalonly` int(1) NOT NULL DEFAULT 0,
  `createdat` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_cm_changeactivities` (
  `id` int(11) NOT NULL,
  `activitynumber` varchar(11) NOT NULL,
  `changeid` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `operatorgroupid` int(11) DEFAULT NULL,
  `operatorid` int(11) DEFAULT NULL,
  `statusid` int(11) DEFAULT NULL,
  `createdby` int(11) NOT NULL,
  `createdat` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedat` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(16) NOT NULL,
  `changerequesttype` varchar(16) NOT NULL,
  `categoryid` int(11) NOT NULL,
  `subcategoryid` int(11) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `commenttext` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_templateactivities` (
  `id` int(11) NOT NULL,
  `templateid` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `operatorgroupid` int(11) DEFAULT NULL,
  `operatorid` int(11) DEFAULT NULL,
  `statusid` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

ALTER TABLE `itsm_cm_changes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `changenumber` (`changenumber`),
  ADD KEY `customerid` (`customerid`),
  ADD KEY `personid` (`personid`),
  ADD KEY `categoryid` (`categoryid`),
  ADD KEY `subcategoryid` (`subcategoryid`),
  ADD KEY `assetid` (`assetid`),
  ADD KEY `operatorgroupid` (`operatorgroupid`),
  ADD KEY `operatorid` (`operatorid`),
  ADD KEY `coordinatorid` (`coordinatorid`),
  ADD KEY `statusid` (`statusid`),
  ADD KEY `createdby` (`createdby`);

ALTER TABLE `itsm_cm_changecomments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `changeid` (`changeid`),
  ADD KEY `operatorid` (`operatorid`);

ALTER TABLE `itsm_cm_changeactivities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `activitynumber` (`activitynumber`),
  ADD KEY `changeid` (`changeid`),
  ADD KEY `operatorgroupid` (`operatorgroupid`),
  ADD KEY `operatorid` (`operatorid`),
  ADD KEY `statusid` (`statusid`),
  ADD KEY `createdby` (`createdby`);

ALTER TABLE `itsm_core_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categoryid` (`categoryid`),
  ADD KEY `subcategoryid` (`subcategoryid`);

ALTER TABLE `itsm_core_templateactivities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `templateid` (`templateid`),
  ADD KEY `operatorgroupid` (`operatorgroupid`),
  ADD KEY `operatorid` (`operatorid`),
  ADD KEY `statusid` (`statusid`);

ALTER TABLE `itsm_cm_changes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_cm_changecomments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_cm_changeactivities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_templateactivities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_cm_changes`
  ADD CONSTRAINT `itsm_cm_changes_ibfk_1` FOREIGN KEY (`customerid`) REFERENCES `itsm_ob_customers` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_2` FOREIGN KEY (`personid`) REFERENCES `itsm_ob_persons` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_3` FOREIGN KEY (`categoryid`) REFERENCES `itsm_core_category` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_4` FOREIGN KEY (`subcategoryid`) REFERENCES `itsm_core_subcategory` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_5` FOREIGN KEY (`assetid`) REFERENCES `itsm_am_assets` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_6` FOREIGN KEY (`operatorgroupid`) REFERENCES `itsm_ob_operatorgroups` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_7` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_8` FOREIGN KEY (`statusid`) REFERENCES `itsm_core_status` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_9` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_10` FOREIGN KEY (`coordinatorid`) REFERENCES `itsm_ob_operators` (`id`);

ALTER TABLE `itsm_cm_changecomments`
  ADD CONSTRAINT `itsm_cm_changecomments_ibfk_1` FOREIGN KEY (`changeid`) REFERENCES `itsm_cm_changes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `itsm_cm_changecomments_ibfk_2` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`);

ALTER TABLE `itsm_cm_changeactivities`
  ADD CONSTRAINT `itsm_cm_changeactivities_ibfk_1` FOREIGN KEY (`changeid`) REFERENCES `itsm_cm_changes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `itsm_cm_changeactivities_ibfk_2` FOREIGN KEY (`operatorgroupid`) REFERENCES `itsm_ob_operatorgroups` (`id`),
  ADD CONSTRAINT `itsm_cm_changeactivities_ibfk_3` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`),
  ADD CONSTRAINT `itsm_cm_changeactivities_ibfk_4` FOREIGN KEY (`statusid`) REFERENCES `itsm_core_status` (`id`),
  ADD CONSTRAINT `itsm_cm_changeactivities_ibfk_5` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`);

ALTER TABLE `itsm_core_templates`
  ADD CONSTRAINT `itsm_core_templates_ibfk_1` FOREIGN KEY (`categoryid`) REFERENCES `itsm_core_category` (`id`),
  ADD CONSTRAINT `itsm_core_templates_ibfk_2` FOREIGN KEY (`subcategoryid`) REFERENCES `itsm_core_subcategory` (`id`);

ALTER TABLE `itsm_core_templateactivities`
  ADD CONSTRAINT `itsm_core_templateactivities_ibfk_1` FOREIGN KEY (`templateid`) REFERENCES `itsm_core_templates` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `itsm_core_templateactivities_ibfk_2` FOREIGN KEY (`operatorgroupid`) REFERENCES `itsm_ob_operatorgroups` (`id`),
  ADD CONSTRAINT `itsm_core_templateactivities_ibfk_3` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`),
  ADD CONSTRAINT `itsm_core_templateactivities_ibfk_4` FOREIGN KEY (`statusid`) REFERENCES `itsm_core_status` (`id`);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
