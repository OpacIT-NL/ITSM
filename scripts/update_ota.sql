ALTER TABLE `itsm_cm_changeactivities`
  ADD COLUMN `activitynumber` varchar(11) DEFAULT NULL AFTER `id`;

SET @wa_seq := 0;
SET @wa_prev_prefix := '';

UPDATE `itsm_cm_changeactivities` AS target
JOIN (
  SELECT
    source.`id`,
    CONCAT(numbering.`prefix`, ' ', LPAD(numbering.`seq`, 4, '0')) AS `activitynumber`
  FROM (
    SELECT
      ordered.`id`,
      ordered.`prefix`,
      @wa_seq := IF(@wa_prev_prefix = ordered.`prefix`, @wa_seq + 1, 1) AS `seq`,
      @wa_prev_prefix := ordered.`prefix` AS `prev_prefix`
    FROM (
      SELECT
        `id`,
        CONCAT('WA', DATE_FORMAT(`createdat`, '%y%m')) AS `prefix`
      FROM `itsm_cm_changeactivities`
      ORDER BY `createdat` ASC, `id` ASC
    ) AS ordered
  ) AS numbering
  INNER JOIN `itsm_cm_changeactivities` AS source ON source.`id` = numbering.`id`
) AS generated ON generated.`id` = target.`id`
SET target.`activitynumber` = generated.`activitynumber`;

ALTER TABLE `itsm_cm_changeactivities`
  ADD UNIQUE KEY `activitynumber` (`activitynumber`);

ALTER TABLE `itsm_cm_changeactivities`
  MODIFY `activitynumber` varchar(11) NOT NULL;
