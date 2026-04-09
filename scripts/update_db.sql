CREATE TABLE `itsm_core_mailrules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fromstatusid` (`fromstatusid`),
  KEY `tostatusid` (`tostatusid`),
  KEY `createdby` (`createdby`),
  KEY `mailrule_lookup` (`tasktype`,`triggertype`,`fromstatusid`,`tostatusid`,`active`),
  CONSTRAINT `itsm_core_mailrules_ibfk_1` FOREIGN KEY (`fromstatusid`) REFERENCES `itsm_core_status` (`id`),
  CONSTRAINT `itsm_core_mailrules_ibfk_2` FOREIGN KEY (`tostatusid`) REFERENCES `itsm_core_status` (`id`),
  CONSTRAINT `itsm_core_mailrules_ibfk_3` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_imap_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `categoryid` (`categoryid`),
  KEY `subcategoryid` (`subcategoryid`),
  KEY `fallback_customerid` (`fallback_customerid`),
  KEY `fallback_personid` (`fallback_personid`),
  KEY `operatorgroupid` (`operatorgroupid`),
  KEY `createdby` (`createdby`),
  KEY `imap_rule_lookup` (`tasktype`,`folder`,`active`),
  CONSTRAINT `itsm_core_imap_rules_ibfk_1` FOREIGN KEY (`categoryid`) REFERENCES `itsm_core_category` (`id`),
  CONSTRAINT `itsm_core_imap_rules_ibfk_2` FOREIGN KEY (`subcategoryid`) REFERENCES `itsm_core_subcategory` (`id`),
  CONSTRAINT `itsm_core_imap_rules_ibfk_3` FOREIGN KEY (`fallback_customerid`) REFERENCES `itsm_ob_customers` (`id`),
  CONSTRAINT `itsm_core_imap_rules_ibfk_4` FOREIGN KEY (`fallback_personid`) REFERENCES `itsm_ob_persons` (`id`),
  CONSTRAINT `itsm_core_imap_rules_ibfk_5` FOREIGN KEY (`operatorgroupid`) REFERENCES `itsm_ob_operatorgroups` (`id`),
  CONSTRAINT `itsm_core_imap_rules_ibfk_6` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_imap_imported` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ruleid` int(11) NOT NULL,
  `folder` varchar(255) NOT NULL,
  `uid` int(11) NOT NULL,
  `messageid` varchar(255) DEFAULT NULL,
  `tasktype` varchar(32) NOT NULL,
  `taskid` int(11) NOT NULL,
  `importedat` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `imap_message_uid` (`folder`,`uid`),
  KEY `ruleid` (`ruleid`),
  KEY `messageid` (`messageid`),
  KEY `task_lookup` (`tasktype`,`taskid`),
  CONSTRAINT `itsm_core_imap_imported_ibfk_1` FOREIGN KEY (`ruleid`) REFERENCES `itsm_core_imap_rules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `createdat` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `task_lookup` (`tasktype`,`taskid`),
  KEY `comment_lookup` (`commenttype`,`commentid`),
  KEY `uploadedby` (`uploadedby`),
  KEY `uploadedbyperson` (`uploadedbyperson`),
  CONSTRAINT `itsm_core_attachments_ibfk_1` FOREIGN KEY (`uploadedby`) REFERENCES `itsm_ob_operators` (`id`),
  CONSTRAINT `itsm_core_attachments_ibfk_2` FOREIGN KEY (`uploadedbyperson`) REFERENCES `itsm_ob_persons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_form_presence` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token` varchar(64) NOT NULL,
  `tasktype` varchar(32) NOT NULL,
  `taskid` int(11) NOT NULL,
  `operatorid` int(11) NOT NULL,
  `operatorname` varchar(255) NOT NULL,
  `openedat` datetime NOT NULL DEFAULT current_timestamp(),
  `lastseen` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `presence_token` (`token`),
  KEY `presence_task` (`tasktype`,`taskid`,`lastseen`),
  KEY `operatorid` (`operatorid`),
  CONSTRAINT `itsm_core_form_presence_ibfk_1` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_form_saves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tasktype` varchar(32) NOT NULL,
  `taskid` int(11) NOT NULL,
  `savedby` int(11) NOT NULL,
  `lastsavedat` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `save_task` (`tasktype`,`taskid`),
  KEY `savedby` (`savedby`),
  CONSTRAINT `itsm_core_form_saves_ibfk_1` FOREIGN KEY (`savedby`) REFERENCES `itsm_ob_operators` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_tasklogs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tasktype` varchar(32) NOT NULL,
  `taskid` int(11) NOT NULL,
  `actiontype` varchar(64) NOT NULL,
  `message` longtext NOT NULL,
  `oldvalue` varchar(255) DEFAULT NULL,
  `newvalue` varchar(255) DEFAULT NULL,
  `createdby` int(11) DEFAULT NULL,
  `createdat` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `task_lookup` (`tasktype`,`taskid`,`createdat`),
  KEY `createdby` (`createdby`),
  CONSTRAINT `itsm_core_tasklogs_ibfk_1` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_public_password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personid` int(11) NOT NULL,
  `tokenhash` varchar(64) NOT NULL,
  `expiresat` datetime NOT NULL,
  `usedat` datetime DEFAULT NULL,
  `createdat` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tokenhash` (`tokenhash`),
  KEY `personid` (`personid`),
  KEY `expiresat` (`expiresat`),
  CONSTRAINT `itsm_public_password_resets_ibfk_1` FOREIGN KEY (`personid`) REFERENCES `itsm_ob_persons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_impacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `sortorder` int(11) NOT NULL DEFAULT 0,
  `active` int(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_urgencies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `sortorder` int(11) NOT NULL DEFAULT 0,
  `active` int(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_priorities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `sortorder` int(11) NOT NULL DEFAULT 0,
  `active` int(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_prioritymatrix` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `impactid` int(11) NOT NULL,
  `urgencyid` int(11) NOT NULL,
  `priorityid` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `impact_urgency` (`impactid`,`urgencyid`),
  KEY `urgencyid` (`urgencyid`),
  KEY `priorityid` (`priorityid`),
  CONSTRAINT `itsm_core_prioritymatrix_ibfk_1` FOREIGN KEY (`impactid`) REFERENCES `itsm_core_impacts` (`id`),
  CONSTRAINT `itsm_core_prioritymatrix_ibfk_2` FOREIGN KEY (`urgencyid`) REFERENCES `itsm_core_urgencies` (`id`),
  CONSTRAINT `itsm_core_prioritymatrix_ibfk_3` FOREIGN KEY (`priorityid`) REFERENCES `itsm_core_priorities` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

ALTER TABLE `itsm_im_incidents`
  MODIFY `categoryid` int(11) DEFAULT NULL,
  ADD COLUMN `impactid` int(11) DEFAULT NULL AFTER `statusid`,
  ADD COLUMN `urgencyid` int(11) DEFAULT NULL AFTER `impactid`,
  ADD COLUMN `priorityid` int(11) DEFAULT NULL AFTER `urgencyid`,
  ADD KEY `impactid` (`impactid`),
  ADD KEY `urgencyid` (`urgencyid`),
  ADD KEY `priorityid` (`priorityid`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_11` FOREIGN KEY (`impactid`) REFERENCES `itsm_core_impacts` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_12` FOREIGN KEY (`urgencyid`) REFERENCES `itsm_core_urgencies` (`id`),
  ADD CONSTRAINT `itsm_im_incidents_ibfk_13` FOREIGN KEY (`priorityid`) REFERENCES `itsm_core_priorities` (`id`);

ALTER TABLE `itsm_cm_changes`
  MODIFY `customerid` int(11) DEFAULT NULL,
  MODIFY `personid` int(11) DEFAULT NULL,
  MODIFY `categoryid` int(11) DEFAULT NULL,
  ADD COLUMN `impactid` int(11) DEFAULT NULL AFTER `statusid`,
  ADD COLUMN `urgencyid` int(11) DEFAULT NULL AFTER `impactid`,
  ADD COLUMN `priorityid` int(11) DEFAULT NULL AFTER `urgencyid`,
  ADD KEY `impactid` (`impactid`),
  ADD KEY `urgencyid` (`urgencyid`),
  ADD KEY `priorityid` (`priorityid`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_12` FOREIGN KEY (`impactid`) REFERENCES `itsm_core_impacts` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_13` FOREIGN KEY (`urgencyid`) REFERENCES `itsm_core_urgencies` (`id`),
  ADD CONSTRAINT `itsm_cm_changes_ibfk_14` FOREIGN KEY (`priorityid`) REFERENCES `itsm_core_priorities` (`id`);

ALTER TABLE `itsm_pm_problems`
  ADD COLUMN `impactid` int(11) DEFAULT NULL AFTER `statusid`,
  ADD COLUMN `urgencyid` int(11) DEFAULT NULL AFTER `impactid`,
  ADD COLUMN `priorityid` int(11) DEFAULT NULL AFTER `urgencyid`,
  ADD KEY `impactid` (`impactid`),
  ADD KEY `urgencyid` (`urgencyid`),
  ADD KEY `priorityid` (`priorityid`),
  ADD CONSTRAINT `itsm_pm_problems_ibfk_11` FOREIGN KEY (`impactid`) REFERENCES `itsm_core_impacts` (`id`),
  ADD CONSTRAINT `itsm_pm_problems_ibfk_12` FOREIGN KEY (`urgencyid`) REFERENCES `itsm_core_urgencies` (`id`),
  ADD CONSTRAINT `itsm_pm_problems_ibfk_13` FOREIGN KEY (`priorityid`) REFERENCES `itsm_core_priorities` (`id`);

ALTER TABLE `itsm_ubm_items`
  ADD COLUMN `ubmnumber` varchar(32) DEFAULT NULL AFTER `id`,
  ADD UNIQUE KEY `ubmnumber` (`ubmnumber`);

SET @ubm_prev_prefix := '';
SET @ubm_seq := 0;

UPDATE `itsm_ubm_items`
JOIN (
  SELECT
    numbered.`id`,
    CONCAT(numbered.`prefix`, ' ', LPAD(numbered.`seq`, 4, '0')) AS `generated_number`
  FROM (
    SELECT
      ordered.`id`,
      ordered.`prefix`,
      (@ubm_seq := IF(@ubm_prev_prefix = ordered.`prefix`, @ubm_seq + 1, 1)) AS `seq`,
      (@ubm_prev_prefix := ordered.`prefix`) AS `ignored_prev`
    FROM (
      SELECT
        `id`,
        CONCAT(
          CASE `itemtype`
            WHEN 'initiative' THEN 'INI'
            WHEN 'epic' THEN 'EPI'
            WHEN 'feature' THEN 'FEA'
            WHEN 'story' THEN 'STR'
            WHEN 'subtask' THEN 'SUB'
            ELSE 'TSK'
          END,
          DATE_FORMAT(`createdat`, '%y%m')
        ) AS `prefix`
      FROM `itsm_ubm_items`
      ORDER BY `prefix` ASC, `createdat` ASC, `id` ASC
    ) AS ordered
  ) AS numbered
) AS mapped ON mapped.`id` = `itsm_ubm_items`.`id`
SET `itsm_ubm_items`.`ubmnumber` = mapped.`generated_number`
WHERE `itsm_ubm_items`.`ubmnumber` IS NULL OR `itsm_ubm_items`.`ubmnumber` = '';

ALTER TABLE `itsm_ubm_items`
  MODIFY `ubmnumber` varchar(32) NOT NULL;
