# issue 12: first contrib on mandate
alter table civicrm_sdd_mandate add column first_contribution_id int unsigned COMMENT 'FK to civicrm_contribution';
alter table civicrm_sdd_mandate add constraint FK_civicrm_sdd_mandate_first_contribution_id FOREIGN KEY (first_contribution_id) REFERENCES civicrm_contribution(id);
update civicrm_sdd_mandate,civicrm_contribution set first_contribution_id=civicrm_contribution.id where civicrm_sdd_mandate.entity_id=civicrm_contribution.contribution_recur_id AND civicrm_sdd_mandate.entity_table = "civicrm_contribution_recur";

#issue 13: creditor more complete
alter table civicrm_sdd_mandate add column `payment_instrument_id` int unsigned    COMMENT 'FK to civicrm_payment_instrument';
alter table civicrm_sdd_mandate add column `payment_processor_id` int unsigned    COMMENT 'FK to civicrm_payment_processor';
alter table civicrm_sdd_creditor add column `payment_processor_id` int unsigned    COMMENT 'FK to civicrm_payment_processor';



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



CREATE TABLE  IF NOT EXISTS `civicrm_sdd_contribution_txgroup` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'primary key',
     `contribution_id` int unsigned NOT NULL   COMMENT 'FK to Contribution ID',
     `txgroup_id` int unsigned NOT NULL   COMMENT 'FK to civicrm_sdd_txgroup'
,
    PRIMARY KEY ( `id` )

    ,     UNIQUE INDEX `contriblookup`(
        contribution_id
  )
  ,     UNIQUE INDEX `txglookup`(
        txgroup_id
  )

,          CONSTRAINT FK_civicrm_sdd_contribution_txgroup_txgroup_id FOREIGN KEY (`txgroup_id`) REFERENCES `civicrm_sdd_txgroup`(`id`) ON DELETE SET NULL
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;


-- /*******************************************************
-- *
-- * civicrm_sdd_file
-- *
-- *******************************************************/
CREATE TABLE  IF NOT EXISTS `civicrm_sdd_file` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',

