DROP TABLE IF EXISTS `civicrm_sdd_creditor`;
DROP TABLE IF EXISTS `civicrm_sdd_mandate`;

-- /*******************************************************
-- *
-- * civicrm_sdd_mandate
-- *
-- *******************************************************/
CREATE TABLE `civicrm_sdd_mandate` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `reference` varchar(35) NOT NULL   COMMENT 'The unique mandate reference',
     `source` varchar(64)    COMMENT 'Needed or coming from ContributionRecur? phoning/online/face 2 face....',
     `iban` varchar(42) NULL   ,
     `contact_id` int unsigned    COMMENT 'FK to Contact ID that owns that account',
     `status_id` int unsigned NOT NULL  DEFAULT 3 COMMENT 'pseudo FK into civicrm_option_value.',
     `BIX` varchar(64)    COMMENT 'don\'t know' 
,
    PRIMARY KEY ( `id` )
 
    ,     UNIQUE INDEX `iban`(
        iban
  )
  
,          CONSTRAINT FK_civicrm_sdd_mandate_contact_id FOREIGN KEY (`contact_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE SET NULL  
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;

-- /*******************************************************
-- *
-- * civicrm_sdd_creditor
-- *
-- *******************************************************/
CREATE TABLE `civicrm_sdd_creditor` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `creditor_id` int unsigned    COMMENT 'FK to Contact ID that owns that account',
     `identifier` varchar(35)    COMMENT 'Provided by the bank. ISO country code+check digit+ZZZ+country specific identifier',
     `mandate_prefix` varchar(3)    COMMENT 'prefix for mandate identifiers',
     `category` varchar(4)    COMMENT 'Default value' 
,
    PRIMARY KEY ( `id` )
 
 
,          CONSTRAINT FK_civicrm_sdd_creditor_creditor_id FOREIGN KEY (`creditor_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE SET NULL  
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;


