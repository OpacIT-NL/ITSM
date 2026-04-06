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