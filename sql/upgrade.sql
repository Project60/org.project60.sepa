UPDATE `civicrm_payment_processor_type` SET `payment_type` = 1 WHERE `payment_type` = 9000;
UPDATE `civicrm_payment_processor` SET `payment_type` = 1 WHERE `payment_type` = 9000;


DELETE FROM `civicrm_sdd_contribution_txgroup` USING `civicrm_sdd_txgroup` LEFT JOIN `civicrm_sdd_contribution_txgroup` ON `civicrm_sdd_contribution_txgroup`.`txgroup_id` = `civicrm_sdd_txgroup`.`id` WHERE `civicrm_sdd_txgroup`.`sdd_file_id` IS NULL;
DELETE FROM `civicrm_sdd_txgroup` WHERE `civicrm_sdd_txgroup`.`sdd_file_id` IS NULL;


-- Before executing following queries, manually add option 'Batched' to 'contribution_status' option group!
-- (Under civicrm/admin/options )
-- Parameters:
--   'Name' => 'Batched'
--   'Title' (label visible in UI) can be freely choosen (installer sets it to 'Pending/Batched')
--   'Weight' => keep default (doesn't really matter; can be rearranged late if desired)
--   'Value' => set same as default 'Weight' (doesn't matter either -- but this has a good chance not to conflict...)
UPDATE `civicrm_sdd_txgroup`
  SET `status_id` = (SELECT `value` FROM `civicrm_option_value` WHERE `name` = 'Batched' AND `option_group_id` = (SELECT `id` FROM `civicrm_option_group` WHERE `name` = 'contribution_status'))
  WHERE `status_id` = 2 AND `sdd_file_id` IS NOT NULL;
UPDATE `civicrm_contribution`
  SET `contribution_status_id` = (SELECT `value` FROM `civicrm_option_value` WHERE `name` = 'Batched' AND `option_group_id` = (SELECT `id` FROM `civicrm_option_group` WHERE `name` = 'contribution_status'))
  WHERE `contribution_status_id` = 2
    AND EXISTS (
      SELECT * FROM `civicrm_sdd_contribution_txgroup`
        WHERE `contribution_id` = `civicrm_contribution`.`id`
          AND (SELECT `status_id` FROM `civicrm_sdd_txgroup` WHERE `id` = `civicrm_sdd_contribution_txgroup`.`txgroup_id`)
            = (SELECT `value` FROM `civicrm_option_value` WHERE `name` = 'Batched' AND `option_group_id` = (SELECT `id` FROM `civicrm_option_group` WHERE `name` = 'contribution_status'))
    );
DELETE FROM `civicrm_sdd_contribution_txgroup`
  WHERE (SELECT `status_id` FROM `civicrm_sdd_txgroup` WHERE `id` = `txgroup_id`) = 2
    AND (SELECT `contribution_status_id` FROM `civicrm_contribution` WHERE `id` = `contribution_id`) != 2;


UPDATE `civicrm_sdd_txgroup` SET `status_id` = CASE `status_id`
  WHEN 1 THEN 2 -- 'Open' (batch_status) => 'Pending' (contribution_status)
  WHEN 2 THEN 2 -- 'Closed' (batch_status) => 'Pending' (contribution_status)
  WHEN 666 THEN 3 -- 'Cancelled'
END;
UPDATE `civicrm_sdd_file` SET `status_id` = 2 WHERE `status_id` = 1; -- 'Open' (batch_status) => 'Pending' (contribution_status)


ALTER TABLE `civicrm_sdd_contribution_txgroup` DROP INDEX `contriblookup`, ADD INDEX `contriblookup`(`contribution_id`); -- Drop UNIQUE.


ALTER TABLE `civicrm_sdd_mandate` DROP `is_enabled`;


ALTER TABLE `civicrm_sdd_txgroup` ADD INDEX `creditor_id` (`sdd_creditor_id`);
ALTER TABLE `civicrm_sdd_txgroup` ADD INDEX `file_id` (`sdd_file_id`);


ALTER TABLE `civicrm_sdd_txgroup` ADD UNIQUE KEY `UI_reference` (`reference`);

ALTER TABLE `civicrm_sdd_file` ADD UNIQUE KEY `UI_reference` (`reference`);
ALTER TABLE `civicrm_sdd_file` ADD UNIQUE KEY `UI_filename` (`filename`);


ALTER TABLE `civicrm_sdd_creditor` ADD `mandate_active` tinyint COMMENT 'If true, new Mandates for this Creditor are set to active directly upon creation; otherwise, they have to be activated explicitly later on.';
UPDATE `civicrm_sdd_creditor` SET `mandate_active` = 0;

ALTER TABLE `civicrm_sdd_creditor` ADD `sepa_file_format_id` int unsigned COMMENT 'Variant of the pain.008 format to use when generating SEPA XML files for this creditor. FK to SEPA File Formats in civicrm_option_value.';
INSERT INTO `civicrm_option_group` (`name`, `title`, `is_active`) VALUES ('sepa_file_format', 'SEPA XML File Format Variants', 1);
SET @option_group_id = (SELECT `id` FROM `civicrm_option_group` WHERE `name` = 'sepa_file_format');
INSERT INTO `civicrm_option_value` (`option_group_id`, `name`, `label`, `value`, `is_default`, `weight`, `is_reserved`) VALUES
  (@option_group_id, 'pain.008.001.02', 'pain.008.001.02 (ISO 20022/official SEPA guidelines)', 1, 1, 1, 1),
  (@option_group_id, 'pain.008.003.02', 'pain.008.003.02 (German Sonderwurst)', 2, 0, 2, 1);
UPDATE `civicrm_sdd_creditor` SET `sepa_file_format_id` = (SELECT `value` FROM `civicrm_option_value` WHERE `name` = 'pain.008.001.02' AND `option_group_id` = (SELECT `id` FROM `civicrm_option_group` WHERE `name` = 'sepa_file_format'));


alter table civicrm_sdd_mandate  modify type varchar(4);
update table civicrm_sdd_mandate set type = "RCUR";
 
alter table civicrm_sdd_mandate add column    `status` varchar(8) NOT NULL  DEFAULT INIT COMMENT 'Status of the mandate (INIT, OOFF, FRST, RCUR, INVALID, COMPLETE, ONHOLD)';
 


alter table civicrm_sdd_creditor add column   `iban` varchar(42) NULL   COMMENT 'Iban of the creditor';
alter table civicrm_sdd_creditor add column      `bic` varchar(11)    COMMENT 'BIC of the creditor';

update civicrm_contribution_recur set cycle_day=8 where cycle_day = 1;

alter table civicrm_sdd_creditor modify mandate_prefix VARCHAR(4);

# issue 12: first contrib on mandate
alter table civicrm_sdd_mandate add column first_contribution_id int unsigned COMMENT 'FK to civicrm_contribution';
alter table civicrm_sdd_mandate add constraint FK_civicrm_sdd_mandate_first_contribution_id FOREIGN KEY (first_contribution_id) REFERENCES civicrm_contribution(id);
update civicrm_sdd_mandate,civicrm_contribution set first_contribution_id=civicrm_contribution.id where civicrm_sdd_mandate.entity_id=civicrm_contribution.contribution_recur_id AND civicrm_sdd_mandate.entity_table = "civicrm_contribution_recur";

#issue 13: creditor more complete
alter table civicrm_sdd_creditor add column `payment_instrument_id` int unsigned    COMMENT 'FK to civicrm_payment_instrument';
alter table civicrm_sdd_creditor add column `payment_processor_id` int unsigned    COMMENT 'FK to civicrm_payment_processor';


-- /*******************************************************
-- *
-- * civicrm_sdd_file
-- *
-- *******************************************************/
CREATE TABLE `civicrm_sdd_file` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `reference` varchar(64)    COMMENT 'End-to-end reference for this sdd file.',
     `filename` char(64)    COMMENT 'Name of the generated file',
     `latest_submission_date` datetime    COMMENT 'Latest submission date',
     `created_date` datetime    COMMENT 'When was this item created',
     `created_id` int unsigned    COMMENT 'FK to Contact ID of creator',
     `status_id` int unsigned NOT NULL   COMMENT 'fk to Batch Status options in civicrm_option_values',
     `comments` text    COMMENT 'Comments about processing of this file'
,
    PRIMARY KEY ( `id` )


,          CONSTRAINT FK_civicrm_sdd_file_created_id FOREIGN KEY (`created_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE SET NULL
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;




CREATE TABLE IF NOT EXISTS  `civicrm_sdd_txgroup` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `reference` varchar(64)    COMMENT 'End-to-end reference for this tx group.',
     `type` char(4)    COMMENT 'FRST, RCUR or OOFF',
     `collection_date` datetime    COMMENT 'Target collection date',
     `latest_submission_date` datetime    COMMENT 'Latest submission date',
     `created_date` datetime    COMMENT 'When was this item created',
     `status_id` int unsigned NOT NULL   COMMENT 'fk to Batch Status options in civicrm_option_values',
     `sdd_creditor_id` int unsigned    COMMENT 'fk to SDD Creditor Id',
     `sdd_file_id` int unsigned    COMMENT 'fk to SDD File Id'
,
    PRIMARY KEY ( `id` )


,          CONSTRAINT FK_civicrm_sdd_txgroup_sdd_creditor_id FOREIGN KEY (`sdd_creditor_id`) REFERENCES `civicrm_sdd_creditor`(`id`) ON DELETE SET NULL,          CONSTRAINT FK_civicrm_sdd_txgroup_sdd_file_id FOREIGN KEY (`sdd_file_id`) REFERENCES `civicrm_sdd_file`(`id`) ON DELETE SET NULL
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;


CREATE TABLE `civicrm_sdd_contribution_txgroup` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'primary key',
     `contribution_id` int unsigned NOT NULL   COMMENT 'FK to Contribution ID',
     `txgroup_id` int unsigned NOT NULL   COMMENT 'FK to civicrm_sdd_txgroup'
,
    PRIMARY KEY ( `id` )

    ,     UNIQUE INDEX `contriblookup`(
        contribution_id
  )
  ,     INDEX `txglookup`(
        txgroup_id
  )

,          CONSTRAINT FK_civicrm_sdd_cGGoup_id FOREIGN KEY (`txgroup_id`) REFERENCES `civicrm_sdd_txgroup`(`id`) 
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;


