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
  `type` int(11) NOT NULL,
  `startdate` varchar(255) DEFAULT NULL,
  `enddate` varchar(255) DEFAULT NULL,
  `price` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL,
  `active` int(1) NOT NULL,
  `archived` int(1) NOT NULL,
  `owner` int(11) DEFAULT NULL,
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
  `name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_am_types` (
  `id` int(11) NOT NULL,
  `type` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
DELIMITER $$
CREATE TRIGGER `after_delete_assettype` AFTER DELETE ON `itsm_am_types` FOR EACH ROW BEGIN
    DELETE FROM itsm_am_fields
    WHERE type = OLD.id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_insert_assettype` AFTER INSERT ON `itsm_am_types` FOR EACH ROW BEGIN
    DECLARE i INT DEFAULT 1;

    WHILE i <= 10 DO
        INSERT INTO itsm_am_fields (`type`, `field`, `name`)
        VALUES (NEW.id, CONCAT('customfield', i), NULL);

        SET i = i + 1;
    END WHILE;
END
$$
DELIMITER ;

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

CREATE TABLE `itsm_cm_changecomments` (
  `id` int(11) NOT NULL,
  `changeid` int(11) NOT NULL,
  `operatorid` int(11) DEFAULT NULL,
  `personid` int(11) DEFAULT NULL,
  `commenttext` longtext NOT NULL,
  `internalonly` int(1) NOT NULL DEFAULT 0,
  `createdat` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_cm_changes` (
  `id` int(11) NOT NULL,
  `changenumber` varchar(10) NOT NULL,
  `requesttype` varchar(16) NOT NULL,
  `approvalstate` varchar(16) NOT NULL DEFAULT 'request',
  `changetype` varchar(32) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `customerid` int(11) DEFAULT NULL,
  `personid` int(11) DEFAULT NULL,
  `personemail` varchar(255) DEFAULT NULL,
  `personphone` varchar(15) DEFAULT NULL,
  `categoryid` int(11) DEFAULT NULL,
  `subcategoryid` int(11) DEFAULT NULL,
  `assetid` int(11) DEFAULT NULL,
  `operatorgroupid` int(11) DEFAULT NULL,
  `operatorid` int(11) DEFAULT NULL,
  `coordinatorid` int(11) DEFAULT NULL,
  `statusid` int(11) DEFAULT NULL,
  `impactid` int(11) DEFAULT NULL,
  `urgencyid` int(11) DEFAULT NULL,
  `priorityid` int(11) DEFAULT NULL,
  `template_used` int(11) DEFAULT NULL,
  `closed` int(1) NOT NULL DEFAULT 0,
  `createdby` int(11) NOT NULL,
  `createdat` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedat` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_attachments` (
  `id` int(11) NOT NULL,
  `tasktype` varchar(32) NOT NULL,
  `taskid` int(11) NOT NULL,
  `commenttype` varchar(32) DEFAULT NULL,
  `commentid` int(11) DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `mimetype` varchar(255) DEFAULT NULL,
  `filesize` int(11) NOT NULL,
  `content` longblob NOT NULL,
  `uploadedby` int(11) DEFAULT NULL,
  `uploadedbyperson` int(11) DEFAULT NULL,
  `internalonly` int(1) NOT NULL DEFAULT 0,
  `createdat` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_category` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_form_presence` (
  `id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `tasktype` varchar(32) NOT NULL,
  `taskid` int(11) NOT NULL,
  `operatorid` int(11) NOT NULL,
  `operatorname` varchar(255) NOT NULL,
  `openedat` datetime NOT NULL DEFAULT current_timestamp(),
  `lastseen` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_form_saves` (
  `id` int(11) NOT NULL,
  `tasktype` varchar(32) NOT NULL,
  `taskid` int(11) NOT NULL,
  `savedby` int(11) NOT NULL,
  `lastsavedat` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_imap_imported` (
  `id` int(11) NOT NULL,
  `ruleid` int(11) NOT NULL,
  `folder` varchar(255) NOT NULL,
  `uid` int(11) NOT NULL,
  `messageid` varchar(255) DEFAULT NULL,
  `tasktype` varchar(32) NOT NULL,
  `taskid` int(11) NOT NULL,
  `importedat` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_imap_rules` (
  `id` int(11) NOT NULL,
  `folder` varchar(255) NOT NULL,
  `tasktype` varchar(32) NOT NULL,
  `categoryid` int(11) DEFAULT NULL,
  `subcategoryid` int(11) DEFAULT NULL,
  `fallback_customerid` int(11) DEFAULT NULL,
  `fallback_personid` int(11) DEFAULT NULL,
  `operatorgroupid` int(11) DEFAULT NULL,
  `active` int(1) NOT NULL DEFAULT 1,
  `createdby` int(11) NOT NULL,
  `createdat` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_impacts` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `sortorder` int(11) NOT NULL DEFAULT 0,
  `active` int(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_mailrules` (
  `id` int(11) NOT NULL,
  `tasktype` varchar(32) NOT NULL,
  `triggertype` varchar(32) NOT NULL DEFAULT 'statuschange',
  `fromstatusid` int(11) DEFAULT NULL,
  `tostatusid` int(11) DEFAULT NULL,
  `recipienttype` varchar(32) NOT NULL DEFAULT 'requester',
  `customrecipients` longtext DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `templatefile` varchar(255) NOT NULL,
  `active` int(1) NOT NULL DEFAULT 1,
  `createdby` int(11) NOT NULL,
  `createdat` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_news` (
  `id` int(11) NOT NULL,
  `newstype` varchar(32) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` longtext NOT NULL,
  `showssp` int(1) NOT NULL DEFAULT 0,
  `showoperatorhome` int(1) NOT NULL DEFAULT 0,
  `showlogin` int(1) NOT NULL DEFAULT 0,
  `createdby` int(11) NOT NULL,
  `createdat` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_priorities` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `sortorder` int(11) NOT NULL DEFAULT 0,
  `active` int(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_prioritymatrix` (
  `id` int(11) NOT NULL,
  `impactid` int(11) NOT NULL,
  `urgencyid` int(11) NOT NULL,
  `priorityid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_status` (
  `id` int(11) NOT NULL,
  `type` varchar(255) NOT NULL,
  `ready` int(1) NOT NULL,
  `closed` int(1) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_subcategory` (
  `id` int(11) NOT NULL,
  `parent` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_tasklinks` (
  `id` int(11) NOT NULL,
  `lefttype` varchar(32) NOT NULL,
  `leftid` int(11) NOT NULL,
  `relationtype` varchar(64) NOT NULL,
  `righttype` varchar(32) NOT NULL,
  `rightid` int(11) NOT NULL,
  `createdby` int(11) NOT NULL,
  `createdat` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_tasklogs` (
  `id` int(11) NOT NULL,
  `tasktype` varchar(32) NOT NULL,
  `taskid` int(11) NOT NULL,
  `actiontype` varchar(64) NOT NULL,
  `message` longtext NOT NULL,
  `oldvalue` varchar(255) DEFAULT NULL,
  `newvalue` varchar(255) DEFAULT NULL,
  `createdby` int(11) DEFAULT NULL,
  `createdat` timestamp NOT NULL DEFAULT current_timestamp()
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

CREATE TABLE `itsm_core_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(16) NOT NULL,
  `changerequesttype` varchar(16) NOT NULL,
  `persongroupid` int(11) DEFAULT NULL,
  `categoryid` int(11) NOT NULL,
  `subcategoryid` int(11) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `commenttext` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_urgencies` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `sortorder` int(11) NOT NULL DEFAULT 0,
  `active` int(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_em_events` (
  `id` int(11) NOT NULL,
  `eventnumber` varchar(20) NOT NULL,
  `categoryid` int(11) DEFAULT NULL,
  `subcategoryid` int(11) DEFAULT NULL,
  `assetid` int(11) DEFAULT NULL,
  `description` longtext NOT NULL,
  `incidentid` int(11) DEFAULT NULL,
  `acknowledged` int(1) NOT NULL DEFAULT 0,
  `closed` int(1) NOT NULL DEFAULT 0,
  `createdby` int(11) NOT NULL,
  `createdat` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_im_incidentcomments` (
  `id` int(11) NOT NULL,
  `incidentid` int(11) NOT NULL,
  `operatorid` int(11) DEFAULT NULL,
  `personid` int(11) DEFAULT NULL,
  `commenttext` longtext NOT NULL,
  `internalonly` int(1) NOT NULL DEFAULT 0,
  `createdat` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_im_incidents` (
  `id` int(11) NOT NULL,
  `incidentnumber` varchar(10) NOT NULL,
  `incidenttype` varchar(32) NOT NULL,
  `majorincidentid` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `customerid` int(11) DEFAULT NULL,
  `personid` int(11) DEFAULT NULL,
  `personemail` varchar(255) DEFAULT NULL,
  `personphone` varchar(15) DEFAULT NULL,
  `categoryid` int(11) DEFAULT NULL,
  `subcategoryid` int(11) DEFAULT NULL,
  `assetid` int(11) DEFAULT NULL,
  `operatorgroupid` int(11) DEFAULT NULL,
  `operatorid` int(11) DEFAULT NULL,
  `statusid` int(11) NOT NULL,
  `impactid` int(11) DEFAULT NULL,
  `urgencyid` int(11) DEFAULT NULL,
  `priorityid` int(11) DEFAULT NULL,
  `template_used` int(11) DEFAULT NULL,
  `createdby` int(11) NOT NULL,
  `createdat` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedat` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_km_items` (
  `id` int(11) NOT NULL,
  `parentid` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `publicaccess` int(1) NOT NULL DEFAULT 1,
  `createdby` int(11) NOT NULL,
  `createdat` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ob_buildings` (
  `id` int(11) NOT NULL,
  `customer` int(11) NOT NULL,
  `address` varchar(255) NOT NULL,
  `postalcode` varchar(255) NOT NULL,
  `city` varchar(255) NOT NULL,
  `idvp` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ob_customers` (
  `id` int(11) NOT NULL,
  `din` varchar(6) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `primarybuilding` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `postalcode` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `primaryemail` varchar(255) DEFAULT NULL,
  `primaryphone` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ob_operatorgroups` (
  `id` int(11) NOT NULL,
  `groupname` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ob_operators` (
  `id` int(11) NOT NULL,
  `lastname` varchar(255) NOT NULL,
  `firstname` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `allowlogin` int(1) NOT NULL,
  `firstlineincidents` int(1) NOT NULL,
  `secondlineincidents` int(1) NOT NULL,
  `reqforchange` int(1) NOT NULL,
  `simplechange` int(1) NOT NULL,
  `extchange` int(1) NOT NULL,
  `problems` int(1) NOT NULL,
  `operations` int(1) NOT NULL,
  `assets` int(1) NOT NULL,
  `persons` int(1) NOT NULL,
  `operators` int(1) NOT NULL,
  `buildings` int(1) NOT NULL,
  `customers` int(1) NOT NULL,
  `suppliers` int(1) NOT NULL,
  `groups` int(1) NOT NULL,
  `events` int(1) NOT NULL,
  `ubm` int(1) NOT NULL,
  `reporting` int(1) NOT NULL,
  `isadmin` int(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ob_opgrouplinks` (
  `id` int(11) NOT NULL,
  `groupid` int(11) NOT NULL,
  `operatorid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ob_persongrouplinks` (
  `id` int(11) NOT NULL,
  `person` int(11) NOT NULL,
  `persongroup` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ob_persongroups` (
  `id` int(11) NOT NULL,
  `groupname` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ob_persons` (
  `id` int(11) NOT NULL,
  `customerid` int(11) NOT NULL,
  `firstname` varchar(255) NOT NULL,
  `lastname` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `password` varchar(255) NOT NULL,
  `allowssp` int(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ob_suppliers` (
  `id` int(11) NOT NULL,
  `cin` varchar(6) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `postalcode` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `primaryemail` varchar(255) DEFAULT NULL,
  `primaryphone` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_pm_problemcomments` (
  `id` int(11) NOT NULL,
  `problemid` int(11) NOT NULL,
  `operatorid` int(11) NOT NULL,
  `commenttext` longtext NOT NULL,
  `internalonly` int(1) NOT NULL DEFAULT 0,
  `createdat` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_pm_problems` (
  `id` int(11) NOT NULL,
  `problemnumber` varchar(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` longtext NOT NULL,
  `customerid` int(11) DEFAULT NULL,
  `personid` int(11) DEFAULT NULL,
  `personemail` varchar(255) DEFAULT NULL,
  `personphone` varchar(255) DEFAULT NULL,
  `categoryid` int(11) DEFAULT NULL,
  `subcategoryid` int(11) DEFAULT NULL,
  `assetid` int(11) DEFAULT NULL,
  `operatorgroupid` int(11) DEFAULT NULL,
  `operatorid` int(11) DEFAULT NULL,
  `statusid` int(11) DEFAULT NULL,
  `impactid` int(11) DEFAULT NULL,
  `urgencyid` int(11) DEFAULT NULL,
  `priorityid` int(11) DEFAULT NULL,
  `template_used` int(11) DEFAULT NULL,
  `createdby` int(11) NOT NULL,
  `createdat` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_public_password_resets` (
  `id` int(11) NOT NULL,
  `personid` int(11) NOT NULL,
  `tokenhash` varchar(64) NOT NULL,
  `expiresat` datetime NOT NULL,
  `usedat` datetime DEFAULT NULL,
  `createdat` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ubm_items` (
  `id` int(11) NOT NULL,
  `parentid` int(11) DEFAULT NULL,
  `itemtype` varchar(32) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` longtext NOT NULL,
  `categoryid` int(11) DEFAULT NULL,
  `subcategoryid` int(11) DEFAULT NULL,
  `operatorgroupid` int(11) DEFAULT NULL,
  `operatorid` int(11) DEFAULT NULL,
  `statusid` int(11) DEFAULT NULL,
  `createdby` int(11) NOT NULL,
  `createdat` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


ALTER TABLE `itsm_am_assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `objectid` (`objectid`);

ALTER TABLE `itsm_am_fields`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_am_types`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_cm_changeactivities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `activitynumber` (`activitynumber`),
  ADD KEY `changeid` (`changeid`),
  ADD KEY `operatorgroupid` (`operatorgroupid`),
  ADD KEY `operatorid` (`operatorid`),
  ADD KEY `statusid` (`statusid`),
  ADD KEY `createdby` (`createdby`);

ALTER TABLE `itsm_cm_changecomments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `changeid` (`changeid`),
  ADD KEY `operatorid` (`operatorid`),
  ADD KEY `personid` (`personid`);

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
  ADD KEY `createdby` (`createdby`),
  ADD KEY `template_used` (`template_used`),
  ADD KEY `impactid` (`impactid`),
  ADD KEY `urgencyid` (`urgencyid`),
  ADD KEY `priorityid` (`priorityid`);

ALTER TABLE `itsm_core_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_lookup` (`tasktype`,`taskid`),
  ADD KEY `comment_lookup` (`commenttype`,`commentid`),
  ADD KEY `uploadedby` (`uploadedby`),
  ADD KEY `uploadedbyperson` (`uploadedbyperson`);

ALTER TABLE `itsm_core_category`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_core_form_presence`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `presence_token` (`token`),
  ADD KEY `presence_task` (`tasktype`,`taskid`,`lastseen`),
  ADD KEY `operatorid` (`operatorid`);

ALTER TABLE `itsm_core_form_saves`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `save_task` (`tasktype`,`taskid`),
  ADD KEY `savedby` (`savedby`);

ALTER TABLE `itsm_core_imap_imported`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `imap_message_uid` (`folder`,`uid`),
  ADD KEY `ruleid` (`ruleid`),
  ADD KEY `messageid` (`messageid`),
  ADD KEY `task_lookup` (`tasktype`,`taskid`);

ALTER TABLE `itsm_core_imap_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categoryid` (`categoryid`),
  ADD KEY `subcategoryid` (`subcategoryid`),
  ADD KEY `fallback_customerid` (`fallback_customerid`),
  ADD KEY `fallback_personid` (`fallback_personid`),
  ADD KEY `operatorgroupid` (`operatorgroupid`),
  ADD KEY `createdby` (`createdby`),
  ADD KEY `imap_rule_lookup` (`tasktype`,`folder`,`active`);

ALTER TABLE `itsm_core_impacts`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_core_mailrules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fromstatusid` (`fromstatusid`),
  ADD KEY `tostatusid` (`tostatusid`),
  ADD KEY `createdby` (`createdby`),
  ADD KEY `mailrule_lookup` (`tasktype`,`triggertype`,`fromstatusid`,`tostatusid`,`active`);

ALTER TABLE `itsm_core_news`
  ADD PRIMARY KEY (`id`),
  ADD KEY `createdby` (`createdby`);

ALTER TABLE `itsm_core_priorities`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_core_prioritymatrix`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `impact_urgency` (`impactid`,`urgencyid`),
  ADD KEY `urgencyid` (`urgencyid`),
  ADD KEY `priorityid` (`priorityid`);

ALTER TABLE `itsm_core_status`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_core_subcategory`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_core_tasklinks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `link_unique` (`lefttype`,`leftid`,`relationtype`,`righttype`,`rightid`),
  ADD KEY `createdby` (`createdby`);

ALTER TABLE `itsm_core_tasklogs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_lookup` (`tasktype`,`taskid`,`createdat`),
  ADD KEY `createdby` (`createdby`);

ALTER TABLE `itsm_core_templateactivities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `templateid` (`templateid`),
  ADD KEY `operatorgroupid` (`operatorgroupid`),
  ADD KEY `operatorid` (`operatorid`),
  ADD KEY `statusid` (`statusid`);

ALTER TABLE `itsm_core_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categoryid` (`categoryid`),
  ADD KEY `subcategoryid` (`subcategoryid`),
  ADD KEY `persongroupid` (`persongroupid`);

ALTER TABLE `itsm_core_urgencies`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_em_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `eventnumber` (`eventnumber`),
  ADD KEY `categoryid` (`categoryid`),
  ADD KEY `subcategoryid` (`subcategoryid`),
  ADD KEY `assetid` (`assetid`),
  ADD KEY `incidentid` (`incidentid`),
  ADD KEY `createdby` (`createdby`);

ALTER TABLE `itsm_im_incidentcomments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incidentid` (`incidentid`),
  ADD KEY `operatorid` (`operatorid`),
  ADD KEY `personid` (`personid`);

ALTER TABLE `itsm_im_incidents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `incidentnumber` (`incidentnumber`),
  ADD KEY `statusid` (`statusid`),
  ADD KEY `operatorid` (`operatorid`),
  ADD KEY `operatorgroupid` (`operatorgroupid`),
  ADD KEY `customerid` (`customerid`),
  ADD KEY `personid` (`personid`),
  ADD KEY `itsm_im_incidents_ibfk_3` (`categoryid`),
  ADD KEY `itsm_im_incidents_ibfk_4` (`subcategoryid`),
  ADD KEY `itsm_im_incidents_ibfk_5` (`assetid`),
  ADD KEY `itsm_im_incidents_ibfk_9` (`createdby`),
  ADD KEY `majorincidentid` (`majorincidentid`),
  ADD KEY `template_used` (`template_used`),
  ADD KEY `impactid` (`impactid`),
  ADD KEY `urgencyid` (`urgencyid`),
  ADD KEY `priorityid` (`priorityid`);

ALTER TABLE `itsm_km_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parentid` (`parentid`),
  ADD KEY `createdby` (`createdby`);

ALTER TABLE `itsm_ob_buildings`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_ob_customers`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_ob_operatorgroups`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_ob_operators`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_ob_opgrouplinks`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_ob_persongrouplinks`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_ob_persongroups`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_ob_persons`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_ob_suppliers`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_pm_problemcomments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `problemid` (`problemid`),
  ADD KEY `operatorid` (`operatorid`);

ALTER TABLE `itsm_pm_problems`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `problemnumber` (`problemnumber`),
  ADD KEY `customerid` (`customerid`),
  ADD KEY `personid` (`personid`),
  ADD KEY `categoryid` (`categoryid`),
  ADD KEY `subcategoryid` (`subcategoryid`),
  ADD KEY `assetid` (`assetid`),
  ADD KEY `operatorgroupid` (`operatorgroupid`),
  ADD KEY `operatorid` (`operatorid`),
  ADD KEY `statusid` (`statusid`),
  ADD KEY `createdby` (`createdby`),
  ADD KEY `template_used` (`template_used`),
  ADD KEY `impactid` (`impactid`),
  ADD KEY `urgencyid` (`urgencyid`),
  ADD KEY `priorityid` (`priorityid`);

ALTER TABLE `itsm_public_password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tokenhash` (`tokenhash`),
  ADD KEY `personid` (`personid`),
  ADD KEY `expiresat` (`expiresat`);

ALTER TABLE `itsm_ubm_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parentid` (`parentid`),
  ADD KEY `operatorgroupid` (`operatorgroupid`),
  ADD KEY `operatorid` (`operatorid`),
  ADD KEY `statusid` (`statusid`),
  ADD KEY `createdby` (`createdby`),
  ADD KEY `categoryid` (`categoryid`),
  ADD KEY `subcategoryid` (`subcategoryid`);


ALTER TABLE `itsm_am_assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_am_fields`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_am_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_cm_changeactivities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_cm_changecomments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_cm_changes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_category`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_form_presence`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_form_saves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_imap_imported`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_imap_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_impacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_mailrules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_priorities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_prioritymatrix`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_subcategory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_tasklinks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_tasklogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_templateactivities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_urgencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_em_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_im_incidentcomments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_im_incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_km_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_ob_buildings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_ob_customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_ob_operatorgroups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_ob_operators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_ob_opgrouplinks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_ob_persongrouplinks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_ob_persongroups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_ob_persons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_ob_suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_pm_problemcomments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_pm_problems`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_public_password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_ubm_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


ALTER TABLE `itsm_cm_changeactivities`
  ADD CONSTRAINT `itsm_cm_changeactivities_ibfk_1` FOREIGN KEY (`changeid`) REFERENCES `itsm_cm_changes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `itsm_cm_changeactivities_ibfk_2` FOREIGN KEY (`operatorgroupid`) REFERENCES `itsm_ob_operatorgroups` (`id`),
  ADD CONSTRAINT `itsm_cm_changeactivities_ibfk_3` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`),
  ADD CONSTRAINT `itsm_cm_changeactivities_ibfk_4` FOREIGN KEY (`statusid`) REFERENCES `itsm_core_status` (`id`),
  ADD CONSTRAINT `itsm_cm_changeactivities_ibfk_5` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`);

ALTER TABLE `itsm_cm_changecomments`
  ADD CONSTRAINT `itsm_cm_changecomments_ibfk_1` FOREIGN KEY (`changeid`) REFERENCES `itsm_cm_changes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `itsm_cm_changecomments_ibfk_2` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`),
  ADD CONSTRAINT `itsm_cm_changecomments_ibfk_3` FOREIGN KEY (`personid`) REFERENCES `itsm_ob_persons` (`id`);

ALTER TABLE `itsm_cm_changes`
  ADD CONSTRAINT `itsm_cm_changes_ibfk_1` FOREIGN KEY (`customerid`) REFERENCES `itsm_ob_customers` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_10` FOREIGN KEY (`coordinatorid`) REFERENCES `itsm_ob_operators` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_11` FOREIGN KEY (`template_used`) REFERENCES `itsm_core_templates` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_12` FOREIGN KEY (`impactid`) REFERENCES `itsm_core_impacts` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_13` FOREIGN KEY (`urgencyid`) REFERENCES `itsm_core_urgencies` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_14` FOREIGN KEY (`priorityid`) REFERENCES `itsm_core_priorities` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_2` FOREIGN KEY (`personid`) REFERENCES `itsm_ob_persons` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_3` FOREIGN KEY (`categoryid`) REFERENCES `itsm_core_category` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_4` FOREIGN KEY (`subcategoryid`) REFERENCES `itsm_core_subcategory` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_5` FOREIGN KEY (`assetid`) REFERENCES `itsm_am_assets` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_6` FOREIGN KEY (`operatorgroupid`) REFERENCES `itsm_ob_operatorgroups` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_7` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_8` FOREIGN KEY (`statusid`) REFERENCES `itsm_core_status` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_9` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`);

ALTER TABLE `itsm_core_attachments`
  ADD CONSTRAINT `itsm_core_attachments_ibfk_1` FOREIGN KEY (`uploadedby`) REFERENCES `itsm_ob_operators` (`id`),
  ADD CONSTRAINT `itsm_core_attachments_ibfk_2` FOREIGN KEY (`uploadedbyperson`) REFERENCES `itsm_ob_persons` (`id`);

ALTER TABLE `itsm_core_form_presence`
  ADD CONSTRAINT `itsm_core_form_presence_ibfk_1` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`) ON DELETE CASCADE;

ALTER TABLE `itsm_core_form_saves`
  ADD CONSTRAINT `itsm_core_form_saves_ibfk_1` FOREIGN KEY (`savedby`) REFERENCES `itsm_ob_operators` (`id`);

ALTER TABLE `itsm_core_imap_imported`
  ADD CONSTRAINT `itsm_core_imap_imported_ibfk_1` FOREIGN KEY (`ruleid`) REFERENCES `itsm_core_imap_rules` (`id`) ON DELETE CASCADE;

ALTER TABLE `itsm_core_imap_rules`
  ADD CONSTRAINT `itsm_core_imap_rules_ibfk_1` FOREIGN KEY (`categoryid`) REFERENCES `itsm_core_category` (`id`),
  ADD CONSTRAINT `itsm_core_imap_rules_ibfk_2` FOREIGN KEY (`subcategoryid`) REFERENCES `itsm_core_subcategory` (`id`),
  ADD CONSTRAINT `itsm_core_imap_rules_ibfk_3` FOREIGN KEY (`fallback_customerid`) REFERENCES `itsm_ob_customers` (`id`),
  ADD CONSTRAINT `itsm_core_imap_rules_ibfk_4` FOREIGN KEY (`fallback_personid`) REFERENCES `itsm_ob_persons` (`id`),
  ADD CONSTRAINT `itsm_core_imap_rules_ibfk_5` FOREIGN KEY (`operatorgroupid`) REFERENCES `itsm_ob_operatorgroups` (`id`),
  ADD CONSTRAINT `itsm_core_imap_rules_ibfk_6` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`);

ALTER TABLE `itsm_core_mailrules`
  ADD CONSTRAINT `itsm_core_mailrules_ibfk_1` FOREIGN KEY (`fromstatusid`) REFERENCES `itsm_core_status` (`id`),
  ADD CONSTRAINT `itsm_core_mailrules_ibfk_2` FOREIGN KEY (`tostatusid`) REFERENCES `itsm_core_status` (`id`),
  ADD CONSTRAINT `itsm_core_mailrules_ibfk_3` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`);

ALTER TABLE `itsm_core_news`
  ADD CONSTRAINT `itsm_core_news_ibfk_1` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`);

ALTER TABLE `itsm_core_prioritymatrix`
  ADD CONSTRAINT `itsm_core_prioritymatrix_ibfk_1` FOREIGN KEY (`impactid`) REFERENCES `itsm_core_impacts` (`id`),
  ADD CONSTRAINT `itsm_core_prioritymatrix_ibfk_2` FOREIGN KEY (`urgencyid`) REFERENCES `itsm_core_urgencies` (`id`),
  ADD CONSTRAINT `itsm_core_prioritymatrix_ibfk_3` FOREIGN KEY (`priorityid`) REFERENCES `itsm_core_priorities` (`id`);

ALTER TABLE `itsm_core_tasklinks`
  ADD CONSTRAINT `itsm_core_tasklinks_ibfk_1` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`);

ALTER TABLE `itsm_core_tasklogs`
  ADD CONSTRAINT `itsm_core_tasklogs_ibfk_1` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`);

ALTER TABLE `itsm_core_templateactivities`
  ADD CONSTRAINT `itsm_core_templateactivities_ibfk_1` FOREIGN KEY (`templateid`) REFERENCES `itsm_core_templates` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `itsm_core_templateactivities_ibfk_2` FOREIGN KEY (`operatorgroupid`) REFERENCES `itsm_ob_operatorgroups` (`id`),
  ADD CONSTRAINT `itsm_core_templateactivities_ibfk_3` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`),
  ADD CONSTRAINT `itsm_core_templateactivities_ibfk_4` FOREIGN KEY (`statusid`) REFERENCES `itsm_core_status` (`id`);

ALTER TABLE `itsm_core_templates`
  ADD CONSTRAINT `itsm_core_templates_ibfk_0` FOREIGN KEY (`persongroupid`) REFERENCES `itsm_ob_persongroups` (`id`),
  ADD CONSTRAINT `itsm_core_templates_ibfk_1` FOREIGN KEY (`categoryid`) REFERENCES `itsm_core_category` (`id`),
  ADD CONSTRAINT `itsm_core_templates_ibfk_2` FOREIGN KEY (`subcategoryid`) REFERENCES `itsm_core_subcategory` (`id`);

ALTER TABLE `itsm_em_events`
  ADD CONSTRAINT `itsm_em_events_ibfk_1` FOREIGN KEY (`categoryid`) REFERENCES `itsm_core_category` (`id`),
  ADD CONSTRAINT `itsm_em_events_ibfk_2` FOREIGN KEY (`subcategoryid`) REFERENCES `itsm_core_subcategory` (`id`),
  ADD CONSTRAINT `itsm_em_events_ibfk_3` FOREIGN KEY (`assetid`) REFERENCES `itsm_am_assets` (`id`),
  ADD CONSTRAINT `itsm_em_events_ibfk_4` FOREIGN KEY (`incidentid`) REFERENCES `itsm_im_incidents` (`id`),
  ADD CONSTRAINT `itsm_em_events_ibfk_5` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`);

ALTER TABLE `itsm_im_incidentcomments`
  ADD CONSTRAINT `itsm_im_incidentcomments_ibfk_1` FOREIGN KEY (`incidentid`) REFERENCES `itsm_im_incidents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `itsm_im_incidentcomments_ibfk_2` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`),
  ADD CONSTRAINT `itsm_im_incidentcomments_ibfk_3` FOREIGN KEY (`personid`) REFERENCES `itsm_ob_persons` (`id`);

ALTER TABLE `itsm_im_incidents`
  ADD CONSTRAINT `itsm_im_incidents_ibfk_0` FOREIGN KEY (`majorincidentid`) REFERENCES `itsm_im_incidents` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `itsm_im_incidents_ibfk_1` FOREIGN KEY (`customerid`) REFERENCES `itsm_ob_customers` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_10` FOREIGN KEY (`template_used`) REFERENCES `itsm_core_templates` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_11` FOREIGN KEY (`impactid`) REFERENCES `itsm_core_impacts` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_12` FOREIGN KEY (`urgencyid`) REFERENCES `itsm_core_urgencies` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_13` FOREIGN KEY (`priorityid`) REFERENCES `itsm_core_priorities` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_2` FOREIGN KEY (`personid`) REFERENCES `itsm_ob_persons` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_3` FOREIGN KEY (`categoryid`) REFERENCES `itsm_core_category` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_4` FOREIGN KEY (`subcategoryid`) REFERENCES `itsm_core_subcategory` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_5` FOREIGN KEY (`assetid`) REFERENCES `itsm_am_assets` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_6` FOREIGN KEY (`operatorgroupid`) REFERENCES `itsm_ob_operatorgroups` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_7` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_8` FOREIGN KEY (`statusid`) REFERENCES `itsm_core_status` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_9` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`);

ALTER TABLE `itsm_km_items`
  ADD CONSTRAINT `itsm_km_items_ibfk_1` FOREIGN KEY (`parentid`) REFERENCES `itsm_km_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `itsm_km_items_ibfk_2` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`);

ALTER TABLE `itsm_pm_problemcomments`
  ADD CONSTRAINT `itsm_pm_problemcomments_ibfk_1` FOREIGN KEY (`problemid`) REFERENCES `itsm_pm_problems` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `itsm_pm_problemcomments_ibfk_2` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`);

ALTER TABLE `itsm_pm_problems`
  ADD CONSTRAINT `itsm_pm_problems_ibfk_1` FOREIGN KEY (`customerid`) REFERENCES `itsm_ob_customers` (`id`),
  ADD CONSTRAINT `itsm_pm_problems_ibfk_10` FOREIGN KEY (`template_used`) REFERENCES `itsm_core_templates` (`id`),
  ADD CONSTRAINT `itsm_pm_problems_ibfk_11` FOREIGN KEY (`impactid`) REFERENCES `itsm_core_impacts` (`id`),
  ADD CONSTRAINT `itsm_pm_problems_ibfk_12` FOREIGN KEY (`urgencyid`) REFERENCES `itsm_core_urgencies` (`id`),
  ADD CONSTRAINT `itsm_pm_problems_ibfk_13` FOREIGN KEY (`priorityid`) REFERENCES `itsm_core_priorities` (`id`),
  ADD CONSTRAINT `itsm_pm_problems_ibfk_2` FOREIGN KEY (`personid`) REFERENCES `itsm_ob_persons` (`id`),
  ADD CONSTRAINT `itsm_pm_problems_ibfk_3` FOREIGN KEY (`categoryid`) REFERENCES `itsm_core_category` (`id`),
  ADD CONSTRAINT `itsm_pm_problems_ibfk_4` FOREIGN KEY (`subcategoryid`) REFERENCES `itsm_core_subcategory` (`id`),
  ADD CONSTRAINT `itsm_pm_problems_ibfk_5` FOREIGN KEY (`assetid`) REFERENCES `itsm_am_assets` (`id`),
  ADD CONSTRAINT `itsm_pm_problems_ibfk_6` FOREIGN KEY (`operatorgroupid`) REFERENCES `itsm_ob_operatorgroups` (`id`),
  ADD CONSTRAINT `itsm_pm_problems_ibfk_7` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`),
  ADD CONSTRAINT `itsm_pm_problems_ibfk_8` FOREIGN KEY (`statusid`) REFERENCES `itsm_core_status` (`id`),
  ADD CONSTRAINT `itsm_pm_problems_ibfk_9` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`);

ALTER TABLE `itsm_public_password_resets`
  ADD CONSTRAINT `itsm_public_password_resets_ibfk_1` FOREIGN KEY (`personid`) REFERENCES `itsm_ob_persons` (`id`) ON DELETE CASCADE;

ALTER TABLE `itsm_ubm_items`
  ADD CONSTRAINT `itsm_ubm_items_ibfk_1` FOREIGN KEY (`parentid`) REFERENCES `itsm_ubm_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `itsm_ubm_items_ibfk_2` FOREIGN KEY (`operatorgroupid`) REFERENCES `itsm_ob_operatorgroups` (`id`),
  ADD CONSTRAINT `itsm_ubm_items_ibfk_3` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`),
  ADD CONSTRAINT `itsm_ubm_items_ibfk_4` FOREIGN KEY (`statusid`) REFERENCES `itsm_core_status` (`id`),
  ADD CONSTRAINT `itsm_ubm_items_ibfk_5` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`),
  ADD CONSTRAINT `itsm_ubm_items_ibfk_6` FOREIGN KEY (`categoryid`) REFERENCES `itsm_core_category` (`id`),
  ADD CONSTRAINT `itsm_ubm_items_ibfk_7` FOREIGN KEY (`subcategoryid`) REFERENCES `itsm_core_subcategory` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
