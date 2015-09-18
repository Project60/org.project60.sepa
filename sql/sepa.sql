-- /*******************************************************
-- *
-- * civicrm_sdd_creditor
-- *
-- *******************************************************/

CREATE TABLE IF NOT EXISTS `civicrm_sdd_creditor`(
     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `creditor_id` int unsigned    COMMENT 'FK to Contact ID that owns that account',
     `identifier` varchar(35)    COMMENT 'Provided by the bank. ISO country code+check digit+ZZZ+country specific identifier',
     `name` varchar(255)    COMMENT 'by default creditor_id.display_name snapshot at creation',
     `address` varchar(255)    COMMENT 'by default creditor_id.address (billing) at creation',
     `country_id` int unsigned    COMMENT 'Which Country does this address belong to.',
     `iban` varchar(42) NULL   COMMENT 'Iban of the creditor',
     `bic` varchar(11)    COMMENT 'BIC of the creditor',
     `mandate_prefix` varchar(4)    COMMENT 'prefix for mandate identifiers',
     `payment_processor_id` int unsigned    ,
     `category` varchar(4)    COMMENT 'Default value',
     `tag` varchar(64) NULL   COMMENT 'Place this creditor\'s transaction groups in an XML file tagged with this value.',
     `mandate_active` tinyint    COMMENT 'If true, new Mandates for this Creditor are set to active directly upon creation; otherwise, they have to be activated explicitly later on.',
     `sepa_file_format_id` int unsigned    COMMENT 'Variant of the pain.008 format to use when generating SEPA XML files for this creditor. FK to SEPA File Formats in civicrm_option_value.'
,
    PRIMARY KEY ( `id` )

,          CONSTRAINT FK_civicrm_sdd_creditor_creditor_id FOREIGN KEY (`creditor_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE SET NULL,          CONSTRAINT FK_civicrm_sdd_creditor_country_id FOREIGN KEY (`country_id`) REFERENCES `civicrm_country`(`id`) ON DELETE SET NULL
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;


-- /*******************************************************
-- *
-- * civicrm_sdd_mandate
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_sdd_mandate` (
     `id`                    int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `reference`             varchar(35) NOT NULL                  COMMENT 'The unique mandate reference',
     `source`                varchar(64)                           COMMENT 'Needed or coming from ContributionRecur? phoning/online/face 2 face....',
     `entity_table`          varchar(64)                           COMMENT 'physical tablename for entity being joined, eg contributionRecur or Membership',
     `entity_id`             int unsigned NOT NULL                 COMMENT 'FK to entity table specified in entity_table column.',
     `date`                  datetime NOT NULL                     COMMENT 'signature date, by default now()',
     `creditor_id`           int unsigned                          COMMENT 'FK to ssd_creditor',
     `contact_id`            int unsigned                          COMMENT 'FK to Contact ID that owns that account',
     `iban`                  varchar(42) NULL                      COMMENT 'Iban of the debtor',
     `bic`                   varchar(11)                           COMMENT 'BIC of the debtor',
     `type`                  varchar(4) NOT NULL DEFAULT 'RCUR'    COMMENT 'RCUR for recurrent (default), OOFF for one-shot',
     `status`                varchar(8) NOT NULL  DEFAULT 'INIT'   COMMENT 'Status of the mandate (INIT, OOFF, FRST, RCUR, SENT, INVALID, COMPLETE, ONHOLD, PARTIAL)',
     `is_enabled`            tinyint NOT NULL  DEFAULT 1           COMMENT 'If the mandate has been validated',
     `creation_date`         datetime                              COMMENT 'by default now()',
     `first_contribution_id` int unsigned                          COMMENT 'FK to civicrm_contribution',
     `validation_date`       datetime,

     PRIMARY KEY (`id`),
     UNIQUE INDEX `reference`      (reference), 
     INDEX        `index_entity`   (entity_table, entity_id),
     INDEX        `iban`           (iban),

     CONSTRAINT FK_civicrm_sdd_mandate_creditor_id           FOREIGN KEY (`creditor_id`)           REFERENCES `civicrm_sdd_creditor`(`id`) ON DELETE SET NULL,
     CONSTRAINT FK_civicrm_sdd_mandate_contact_id            FOREIGN KEY (`contact_id`)            REFERENCES `civicrm_contact`(`id`) ON DELETE SET NULL,
     CONSTRAINT FK_civicrm_sdd_mandate_first_contribution_id FOREIGN KEY (`first_contribution_id`) REFERENCES `civicrm_contribution`(`id`)

)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;


-- /*******************************************************
-- *
-- * civicrm_sdd_file
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_sdd_file` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `reference` varchar(64)    COMMENT 'End-to-end reference for this sdd file.',
     `filename` char(64)    COMMENT 'Name of the generated file',
     `latest_submission_date` datetime    COMMENT 'Latest submission date',
     `created_date` datetime    COMMENT 'When was this item created',
     `created_id` int unsigned    COMMENT 'FK to Contact ID of creator',
     `status_id` int unsigned NOT NULL   COMMENT 'fk to Batch Status options in civicrm_option_values',
     `comments` text    COMMENT 'Comments about processing of this file',
     `tag` varchar(64) NULL   COMMENT 'Tag used to group multiple creditors in this XML file.'
,
    PRIMARY KEY ( `id` )

    ,     UNIQUE INDEX `UI_reference`(
        reference
  )
  ,     UNIQUE INDEX `UI_filename`(
        filename
  )

,          CONSTRAINT FK_civicrm_sdd_file_created_id FOREIGN KEY (`created_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE SET NULL
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;





CREATE TABLE IF NOT EXISTS `civicrm_sdd_txgroup` (


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

    ,     UNIQUE INDEX `UI_reference`(
        reference
  )

,          CONSTRAINT FK_civicrm_sdd_txgroup_sdd_creditor_id FOREIGN KEY (`sdd_creditor_id`) REFERENCES `civicrm_sdd_creditor`(`id`) ON DELETE SET NULL,
          CONSTRAINT FK_civicrm_sdd_txgroup_sdd_file_id FOREIGN KEY (`sdd_file_id`) REFERENCES `civicrm_sdd_file`(`id`) ON DELETE SET NULL
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;



CREATE TABLE IF NOT EXISTS `civicrm_sdd_contribution_txgroup` (


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


CREATE TABLE IF NOT EXISTS `civicrm_sdd_mandate_file` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creditor_id` int(10) unsigned NOT NULL,
  `contact_id` int(10) unsigned NOT NULL,
  `filename` varchar(255) NOT NULL COMMENT 'Filename generated for creditor',
  `create_date` datetime NOT NULL,
  `submission_date` datetime DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_civicrm_sdd_mandate__civicrm_contact1_idx` (`contact_id`),
  KEY `fk_civicrm_sdd_mandate__civicrm_sdd_creditor1_idx` (`creditor_id`),
  CONSTRAINT `fk_civicrm_sdd_mandate_file_civicrm_contact` FOREIGN KEY (`contact_id`) REFERENCES `civicrm_contact` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_civicrm_sdd_mandate_file_civicrm_sdd_creditor` FOREIGN KEY (`creditor_id`) REFERENCES `civicrm_sdd_creditor` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;


CREATE TABLE IF NOT EXISTS `civicrm_sdd_mandate_file_row` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mandate_file_id` int(10) unsigned NOT NULL,
  `mandate_id` int(10) unsigned NOT NULL,
  `response_date` datetime DEFAULT NULL COMMENT 'DateTime when creditor responsed to uploaded file with mandates.',
  `response_filename` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Filename from creditor',
  `response_status` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Status response',
  `response_comment` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Optional response comment',
  PRIMARY KEY (`id`),
  KEY `fk_civicrm_sdd_mandate_group_civicrm_sdd_mandate1_idx` (`mandate_id`),
  KEY `fk_civicrm_sdd_mandate_file_civicrm_sdd_mandate_1_idx` (`mandate_file_id`),
  CONSTRAINT `fk_civicrm_sdd_mandate_file_row_civicrm_sdd_mandate` FOREIGN KEY (`mandate_id`) REFERENCES `civicrm_sdd_mandate` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_civicrm_sdd_mandate_file_row_civicrm_sdd_mandate_file` FOREIGN KEY (`mandate_file_id`) REFERENCES `civicrm_sdd_mandate_file` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

