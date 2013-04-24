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
-- * civicrm_sdd_mandate
-- *
-- *******************************************************/
CREATE TABLE `civicrm_sdd_mandate` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `reference` varchar(35) NOT NULL   COMMENT 'The unique mandate reference',
     `source` varchar(64)    COMMENT 'Needed or coming from ContributionRecur? phoning/online/face 2 face....',
     `contribution_recur_id` int unsigned    COMMENT 'FK to Contact Recuring Contribution associated with the mandate',
     `creditor_id` int unsigned    COMMENT 'FK to ssd_creditor',
     `contact_id` int unsigned    COMMENT 'FK to Contact ID that owns that account',
     `iban` varchar(42) NULL   COMMENT 'Iban of the debtor',
     `bic` varchar(11) NULL   COMMENT 'BIC of the debtor',
     `type` varchar(1) NOT NULL  DEFAULT R COMMENT 'R for recurrent (default) O for one-shot',
     `enabled_id` tinyint NOT NULL  DEFAULT 1 COMMENT 'If the mandate has been validated',
     `creation_date` datetime    ,
     `validation_date` datetime     
,
    PRIMARY KEY ( `id` )
 
    ,     UNIQUE INDEX `reference`(
        reference
  )
  ,     UNIQUE INDEX `iban`(
        iban
  )
  
,          CONSTRAINT FK_civicrm_sdd_mandate_contribution_recur_id FOREIGN KEY (`contribution_recur_id`) REFERENCES `civicrm_contribution_recur`(`id`) ON DELETE SET NULL,          CONSTRAINT FK_civicrm_sdd_mandate_contact_id FOREIGN KEY (`contact_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE SET NULL  
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;


