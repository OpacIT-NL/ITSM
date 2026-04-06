ALTER TABLE `itsm_im_incidents`
  ADD COLUMN `template_used` int(11) DEFAULT NULL AFTER `statusid`,
  ADD KEY `template_used` (`template_used`);

ALTER TABLE `itsm_im_incidents`
  ADD CONSTRAINT `itsm_im_incidents_ibfk_10` FOREIGN KEY (`template_used`) REFERENCES `itsm_core_templates` (`id`);

ALTER TABLE `itsm_cm_changes`
  ADD COLUMN `template_used` int(11) DEFAULT NULL AFTER `statusid`,
  ADD KEY `template_used` (`template_used`);

ALTER TABLE `itsm_cm_changes`
  ADD CONSTRAINT `itsm_cm_changes_ibfk_11` FOREIGN KEY (`template_used`) REFERENCES `itsm_core_templates` (`id`);

ALTER TABLE `itsm_pm_problems`
  ADD COLUMN `template_used` int(11) DEFAULT NULL AFTER `statusid`,
  ADD KEY `template_used` (`template_used`);

ALTER TABLE `itsm_pm_problems`
  ADD CONSTRAINT `itsm_pm_problems_ibfk_10` FOREIGN KEY (`template_used`) REFERENCES `itsm_core_templates` (`id`);
