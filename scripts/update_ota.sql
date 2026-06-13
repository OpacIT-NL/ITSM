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

CREATE TABLE IF NOT EXISTS `itsm_ubm_itemcomments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ubmitemid` int(11) NOT NULL,
  `operatorid` int(11) DEFAULT NULL,
  `commenttext` longtext NOT NULL,
  `internalonly` int(1) NOT NULL DEFAULT 0,
  `createdat` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ubmitemid` (`ubmitemid`),
  KEY `operatorid` (`operatorid`),
  CONSTRAINT `itsm_ubm_itemcomments_ibfk_1` FOREIGN KEY (`ubmitemid`) REFERENCES `itsm_ubm_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `itsm_ubm_itemcomments_ibfk_2` FOREIGN KEY (`operatorid`) REFERENCES `itsm_ob_operators` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
