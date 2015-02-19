DROP TABLE IF EXISTS civicrm_sdd_mandate;
DROP TABLE IF EXISTS civicrm_sdd_creditor;

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
     `sepa_file_format_id` int unsigned    COMMENT 'Variant of the pain.008 format to use when generating SEPA XML files for this creditor. FK to SEPA File Formats in civicrm_option_value.',
     `extra_advance_days` int unsigned   DEFAULT 1 COMMENT 'How many banking days (if any) to add on top of all minimum advance presentation deadlines defined in the SEPA rulebook.',
     `maximum_advance_days` tinyint   DEFAULT 14 COMMENT 'When generating SEPA XML files, include payments up to this many calender days from now. (14 is the minimum banks have to allow according to rulebook.)'
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
     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `reference` varchar(35) NOT NULL   COMMENT 'A unique mandate reference',
     `source` varchar(64)    COMMENT 'Information about the source of registration of the mandate',
     `entity_table` varchar(64)    COMMENT 'Physical tablename for the contract entity being joined, eg contributionRecur or Membership',
     `entity_id` int unsigned NOT NULL   COMMENT 'FK to contract entity table specified in entity_table column.',
     `date` datetime NOT NULL   COMMENT 'by default now()',
     `creditor_id` int unsigned    COMMENT 'FK to ssd_creditor',
     `contact_id` int unsigned    COMMENT 'FK to Contact ID of the debtor',
     `iban` varchar(42) NULL   COMMENT 'Iban of the debtor',
     `bic` varchar(11)    COMMENT 'BIC of the debtor',
     `type` varchar(4) NOT NULL  DEFAULT "RCUR" COMMENT 'RCUR for recurrent (default), OOFF for one-shot',
     `status` varchar(8) NOT NULL  DEFAULT "INIT" COMMENT 'Status of the mandate (INIT, OOFF, FRST, RCUR, INVALID, COMPLETE, ONHOLD)',
     `creation_date` datetime    ,
     `first_contribution_id` int unsigned    COMMENT 'FK to civicrm_contribution',
     `validation_date` datetime     
,
    PRIMARY KEY ( `id` )
 
    ,     UNIQUE INDEX `reference`(
        reference
  )
  ,     INDEX `index_entity`(
        entity_table
      , entity_id
  )
  ,     INDEX `iban`(
        iban
  )
  
,          CONSTRAINT FK_civicrm_sdd_mandate_creditor_id FOREIGN KEY (`creditor_id`) REFERENCES `civicrm_sdd_creditor`(`id`) ON DELETE SET NULL,          CONSTRAINT FK_civicrm_sdd_mandate_contact_id FOREIGN KEY (`contact_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE SET NULL,          CONSTRAINT FK_civicrm_sdd_mandate_first_contribution_id FOREIGN KEY (`first_contribution_id`) REFERENCES `civicrm_contribution`(`id`)   
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;

CREATE TABLE IF NOT EXISTS `civicrm_sdd_mandate` (
     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `reference` varchar(35) NOT NULL   COMMENT 'The unique mandate reference',
     `source` varchar(64)    COMMENT 'Needed or coming from ContributionRecur? phoning/online/face 2 face....',
     `entity_table` varchar(64)    COMMENT 'physical tablename for entity being joined, eg contributionRecur or Membership',
     `entity_id` int unsigned NOT NULL   COMMENT 'FK to entity table specified in entity_table column.',
     `date` datetime NOT NULL  COMMENT 'by default now()',
     `creditor_id` int unsigned    COMMENT 'FK to ssd_creditor',
     `contact_id` int unsigned    COMMENT 'FK to Contact ID that owns that account',
     `iban` varchar(42) NULL   COMMENT 'Iban of the debtor',
     `bic` varchar(11)    COMMENT 'BIC of the debtor',
     `type` varchar(1) NOT NULL  DEFAULT 'R' COMMENT 'R for recurrent (default) O for one-shot',
     `is_enabled` tinyint NOT NULL  DEFAULT 1 COMMENT 'If the mandate has been validated',
     `creation_date` datetime    ,
     `validation_date` datetime     
,
    PRIMARY KEY ( `id` )
 
    ,     UNIQUE INDEX `reference`(
        reference
  )
  ,     INDEX `index_entity`(
        entity_table
      , entity_id
  )
  ,     INDEX `iban`(
        iban
  )
  
,          CONSTRAINT FK_civicrm_sdd_mandate_creditor_id FOREIGN KEY (`creditor_id`) REFERENCES `civicrm_sdd_creditor`(`id`) ON DELETE SET NULL,          CONSTRAINT FK_civicrm_sdd_mandate_contact_id FOREIGN KEY (`contact_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE SET NULL  
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;


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
     `status_id` int unsigned NOT NULL   COMMENT 'fk to Contribution Status options in civicrm_option_values',
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





CREATE TABLE `civicrm_sdd_txgroup` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `reference` varchar(64)    COMMENT 'End-to-end reference for this tx group.',
     `is_cor1` tinyint    COMMENT 'Instrument for payments in this group will be COR1 (true/1) or CORE (false/0).',
     `type` char(4)    COMMENT 'FRST, RCUR or OOFF',
     `collection_date` datetime    COMMENT 'Target collection date',
     `latest_submission_date` datetime    COMMENT 'Latest submission date',
     `created_date` datetime    COMMENT 'When was this item created',
     `status_id` int unsigned NOT NULL   COMMENT 'fk to Contribution Status options in civicrm_option_values',
     `sdd_creditor_id` int unsigned    COMMENT 'fk to SDD Creditor Id',
     `sdd_file_id` int unsigned    COMMENT 'fk to SDD File Id'
,
    PRIMARY KEY ( `id` )

    ,     UNIQUE INDEX `UI_reference`(
        reference
  )
  ,     INDEX `creditor_id`(
        sdd_creditor_id
  )
  ,     INDEX `file_id`(
        sdd_file_id
  )

,          CONSTRAINT FK_civicrm_sdd_txgroup_sdd_creditor_id FOREIGN KEY (`sdd_creditor_id`) REFERENCES `civicrm_sdd_creditor`(`id`) ON DELETE SET NULL,
          CONSTRAINT FK_civicrm_sdd_txgroup_sdd_file_id FOREIGN KEY (`sdd_file_id`) REFERENCES `civicrm_sdd_file`(`id`) ON DELETE SET NULL
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;



CREATE TABLE `civicrm_sdd_contribution_txgroup` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'primary key',
     `contribution_id` int unsigned NOT NULL   COMMENT 'FK to Contribution ID',
     `txgroup_id` int unsigned NOT NULL   COMMENT 'FK to civicrm_sdd_txgroup'
,
    PRIMARY KEY ( `id` )

    ,     INDEX `contriblookup`(
        contribution_id
  )
  ,     INDEX `txglookup`(
        txgroup_id
  )

,          CONSTRAINT FK_civicrm_sdd_cGGoup_id FOREIGN KEY (`txgroup_id`) REFERENCES `civicrm_sdd_txgroup`(`id`) 
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;



