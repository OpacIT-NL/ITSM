ALTER TABLE `itsm_im_incidentcomments`
  DROP FOREIGN KEY `itsm_im_incidentcomments_ibfk_2`;

ALTER TABLE `itsm_im_incidentcomments`
  MODIFY `operatorid` int(11) DEFAULT NULL,
  ADD COLUMN `personid` int(11) DEFAULT NULL AFTER `operatorid`,
  ADD KEY `personid` (`personid`);

ALTER TABLE `itsm_im_incidentcomments`
  ADD CONSTRAINT `itsm_im_incidentcomments_ibfk_2` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`),
  ADD CONSTRAINT `itsm_im_incidentcomments_ibfk_3` FOREIGN KEY (`personid`) REFERENCES `itsm_ob_persons` (`id`);

ALTER TABLE `itsm_cm_changecomments`
  DROP FOREIGN KEY `itsm_cm_changecomments_ibfk_2`;

ALTER TABLE `itsm_cm_changecomments`
  MODIFY `operatorid` int(11) DEFAULT NULL,
  ADD COLUMN `personid` int(11) DEFAULT NULL AFTER `operatorid`,
  ADD KEY `personid` (`personid`);

ALTER TABLE `itsm_cm_changecomments`
  ADD CONSTRAINT `itsm_cm_changecomments_ibfk_2` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`),
  ADD CONSTRAINT `itsm_cm_changecomments_ibfk_3` FOREIGN KEY (`personid`) REFERENCES `itsm_ob_persons` (`id`);

ALTER TABLE `itsm_core_templates`
  ADD COLUMN `persongroupid` int(11) DEFAULT NULL AFTER `changerequesttype`,
  ADD KEY `persongroupid` (`persongroupid`);

ALTER TABLE `itsm_core_templates`
  ADD CONSTRAINT `itsm_core_templates_ibfk_0` FOREIGN KEY (`persongroupid`) REFERENCES `itsm_ob_persongroups` (`id`);

ALTER TABLE `itsm_im_incidents`
  MODIFY `customerid` int(11) DEFAULT NULL,
  MODIFY `personid` int(11) DEFAULT NULL,
  ADD COLUMN `template_used` int(11) DEFAULT NULL AFTER `statusid`,
  ADD KEY `template_used` (`template_used`);

ALTER TABLE `itsm_im_incidents`
  ADD CONSTRAINT `itsm_im_incidents_ibfk_10` FOREIGN KEY (`template_used`) REFERENCES `itsm_core_templates` (`id`);

ALTER TABLE `itsm_cm_changes`
  ADD COLUMN `template_used` int(11) DEFAULT NULL AFTER `statusid`,
  ADD KEY `template_used` (`template_used`);

ALTER TABLE `itsm_cm_changes`
  ADD CONSTRAINT `itsm_cm_changes_ibfk_11` FOREIGN KEY (`template_used`) REFERENCES `itsm_core_templates` (`id`);

CREATE TABLE `itsm_core_tasklinks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lefttype` varchar(32) NOT NULL,
  `leftid` int(11) NOT NULL,
  `relationtype` varchar(64) NOT NULL,
  `righttype` varchar(32) NOT NULL,
  `rightid` int(11) NOT NULL,
  `createdby` int(11) NOT NULL,
  `createdat` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `link_unique` (`lefttype`,`leftid`,`relationtype`,`righttype`,`rightid`),
  KEY `createdby` (`createdby`),
  CONSTRAINT `itsm_core_tasklinks_ibfk_1` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_pm_problems` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `template_used` int(11) DEFAULT NULL,
  `createdby` int(11) NOT NULL,
  `createdat` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `problemnumber` (`problemnumber`),
  KEY `customerid` (`customerid`),
  KEY `personid` (`personid`),
  KEY `categoryid` (`categoryid`),
  KEY `subcategoryid` (`subcategoryid`),
  KEY `assetid` (`assetid`),
  KEY `operatorgroupid` (`operatorgroupid`),
  KEY `operatorid` (`operatorid`),
  KEY `statusid` (`statusid`),
  KEY `template_used` (`template_used`),
  KEY `createdby` (`createdby`),
  CONSTRAINT `itsm_pm_problems_ibfk_1` FOREIGN KEY (`customerid`) REFERENCES `itsm_ob_customers` (`id`),
  CONSTRAINT `itsm_pm_problems_ibfk_2` FOREIGN KEY (`personid`) REFERENCES `itsm_ob_persons` (`id`),
  CONSTRAINT `itsm_pm_problems_ibfk_3` FOREIGN KEY (`categoryid`) REFERENCES `itsm_core_category` (`id`),
  CONSTRAINT `itsm_pm_problems_ibfk_4` FOREIGN KEY (`subcategoryid`) REFERENCES `itsm_core_subcategory` (`id`),
  CONSTRAINT `itsm_pm_problems_ibfk_5` FOREIGN KEY (`assetid`) REFERENCES `itsm_am_assets` (`id`),
  CONSTRAINT `itsm_pm_problems_ibfk_6` FOREIGN KEY (`operatorgroupid`) REFERENCES `itsm_ob_operatorgroups` (`id`),
  CONSTRAINT `itsm_pm_problems_ibfk_7` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`),
  CONSTRAINT `itsm_pm_problems_ibfk_8` FOREIGN KEY (`statusid`) REFERENCES `itsm_core_status` (`id`),
  CONSTRAINT `itsm_pm_problems_ibfk_9` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`),
  CONSTRAINT `itsm_pm_problems_ibfk_10` FOREIGN KEY (`template_used`) REFERENCES `itsm_core_templates` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_pm_problemcomments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `problemid` int(11) NOT NULL,
  `operatorid` int(11) NOT NULL,
  `commenttext` longtext NOT NULL,
  `internalonly` int(1) NOT NULL DEFAULT 0,
  `createdat` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `problemid` (`problemid`),
  KEY `operatorid` (`operatorid`),
  CONSTRAINT `itsm_pm_problemcomments_ibfk_1` FOREIGN KEY (`problemid`) REFERENCES `itsm_pm_problems` (`id`) ON DELETE CASCADE,
  CONSTRAINT `itsm_pm_problemcomments_ibfk_2` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_em_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `eventnumber` (`eventnumber`),
  KEY `categoryid` (`categoryid`),
  KEY `subcategoryid` (`subcategoryid`),
  KEY `assetid` (`assetid`),
  KEY `incidentid` (`incidentid`),
  KEY `createdby` (`createdby`),
  CONSTRAINT `itsm_em_events_ibfk_1` FOREIGN KEY (`categoryid`) REFERENCES `itsm_core_category` (`id`),
  CONSTRAINT `itsm_em_events_ibfk_2` FOREIGN KEY (`subcategoryid`) REFERENCES `itsm_core_subcategory` (`id`),
  CONSTRAINT `itsm_em_events_ibfk_3` FOREIGN KEY (`assetid`) REFERENCES `itsm_am_assets` (`id`),
  CONSTRAINT `itsm_em_events_ibfk_4` FOREIGN KEY (`incidentid`) REFERENCES `itsm_im_incidents` (`id`),
  CONSTRAINT `itsm_em_events_ibfk_5` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ubm_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) DEFAULT NULL,
  `itemtype` varchar(32) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` longtext NOT NULL,
  `operatorgroupid` int(11) DEFAULT NULL,
  `operatorid` int(11) DEFAULT NULL,
  `statusid` int(11) DEFAULT NULL,
  `createdby` int(11) NOT NULL,
  `createdat` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `parentid` (`parentid`),
  KEY `operatorgroupid` (`operatorgroupid`),
  KEY `operatorid` (`operatorid`),
  KEY `statusid` (`statusid`),
  KEY `createdby` (`createdby`),
  CONSTRAINT `itsm_ubm_items_ibfk_1` FOREIGN KEY (`parentid`) REFERENCES `itsm_ubm_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `itsm_ubm_items_ibfk_2` FOREIGN KEY (`operatorgroupid`) REFERENCES `itsm_ob_operatorgroups` (`id`),
  CONSTRAINT `itsm_ubm_items_ibfk_3` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`),
  CONSTRAINT `itsm_ubm_items_ibfk_4` FOREIGN KEY (`statusid`) REFERENCES `itsm_core_status` (`id`),
  CONSTRAINT `itsm_ubm_items_ibfk_5` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
