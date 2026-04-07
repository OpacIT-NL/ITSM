ALTER TABLE `itsm_core_imap_rules`
  MODIFY `categoryid` int(11) DEFAULT NULL,
  MODIFY `fallback_customerid` int(11) DEFAULT NULL,
  MODIFY `fallback_personid` int(11) DEFAULT NULL;

ALTER TABLE `itsm_im_incidents`
  MODIFY `categoryid` int(11) DEFAULT NULL;

ALTER TABLE `itsm_cm_changes`
  MODIFY `customerid` int(11) DEFAULT NULL,
  MODIFY `personid` int(11) DEFAULT NULL,
  MODIFY `categoryid` int(11) DEFAULT NULL;
