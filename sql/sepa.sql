
-- DROP TABLE IF EXISTS 'civicrm_sdd_creditor';
-- DROP TABLE IF EXISTS `civicrm_sdd_mandate`;
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
     `mandate_prefix` varchar(3)    COMMENT 'prefix for mandate identifiers',
     `category` varchar(4)    COMMENT 'Default value' 
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
     `reference` varchar(35) NOT NULL   COMMENT 'The unique mandate reference',
     `source` varchar(64)    COMMENT 'Needed or coming from ContributionRecur? phoning/online/face 2 face....',
     `entity_table` varchar(64)    COMMENT 'physical tablename for entity being joined, eg contributionRecur or Membership',
     `entity_id` int unsigned NOT NULL   COMMENT 'FK to entity table specified in entity_table column.',
     `date` datetime NOT NULL  DEFAULT now() COMMENT 'by default now()',
     `creditor_id` int unsigned    COMMENT 'FK to ssd_creditor',
     `contact_id` int unsigned    COMMENT 'FK to Contact ID that owns that account',
     `iban` varchar(42) NULL   COMMENT 'Iban of the debtor',
     `bic` varchar(11)    COMMENT 'BIC of the debtor',
     `type` varchar(1) NOT NULL  DEFAULT R COMMENT 'R for recurrent (default) O for one-shot',
     `enabled_id` tinyint NOT NULL  DEFAULT 1 COMMENT 'If the mandate has been validated',
     `creation_date` datetime    ,
     `validation_date` datetime     
,
    PRIMARY KEY ( `id` )
 
    ,     UNIQUE INDEX `reference`(
        reference

