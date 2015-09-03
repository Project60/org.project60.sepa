-- /*******************************************************
-- *
-- * civicrm_sdd_creditor
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_sdd_creditor` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `creditor_id` int(10) unsigned DEFAULT NULL COMMENT 'FK to Contact ID that owns that account',
  `identifier` varchar(35) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Provided by the bank. ISO country code+check digit+ZZZ+country specific identifier',
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'by default creditor_id.display_name snapshot at creation',
  `address` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'by default creditor_id.address (billing) at creation',
  `country_id` int(10) unsigned DEFAULT NULL COMMENT 'Which Country does this address belong to.',
  `iban` varchar(42) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Iban of the creditor',
  `bic` varchar(11) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'BIC of the creditor',
  `mandate_prefix` varchar(4) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'prefix for mandate identifiers',
  `payment_processor_id` int(10) unsigned DEFAULT NULL COMMENT 'FK to Payment processor',
  `category` varchar(4) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Default value',
  `tag` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Place this creditor''s transaction groups in an XML file tagged with this value.',
  `mandate_active` tinyint(4) DEFAULT NULL COMMENT 'If true, new Mandates for this Creditor are set to active directly upon creation; otherwise, they have to be activated explicitly later on.',
  `sepa_file_format_id` int(10) unsigned DEFAULT NULL COMMENT 'Variant of the pain.008 format to use when generating SEPA XML files for this creditor. FK to SEPA File Formats in civicrm_option_value.',
  PRIMARY KEY (`id`),
  KEY `FK_civicrm_sdd_creditor_creditor_id` (`creditor_id`),
  KEY `FK_civicrm_sdd_creditor_country_id` (`country_id`),
  KEY `FK_civicrm_sdd_creditor_payment_processor_id` (`payment_processor_id`),
  CONSTRAINT `FK_civicrm_sdd_creditor_country_id` FOREIGN KEY (`country_id`) REFERENCES `civicrm_country` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_civicrm_sdd_creditor_creditor_id` FOREIGN KEY (`creditor_id`) REFERENCES `civicrm_contact` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_civicrm_sdd_creditor_payment_processor_id` FOREIGN KEY (`payment_processor_id`) REFERENCES `civicrm_payment_processor` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


-- /*******************************************************
-- *
-- * civicrm_sdd_mandate
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_sdd_mandate` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `reference` varchar(35) COLLATE utf8_unicode_ci NOT NULL COMMENT 'The unique mandate reference',
  `source` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Needed or coming from ContributionRecur? phoning/online/face 2 face....',
  `entity_table` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'physical tablename for entity being joined, eg contributionRecur or Membership',
  `entity_id` int(10) unsigned NOT NULL COMMENT 'FK to entity table specified in entity_table column.',
  `date` datetime NOT NULL COMMENT 'signature date, by default now()',
  `creditor_id` int(10) unsigned DEFAULT NULL COMMENT 'FK to ssd_creditor',
  `contact_id` int(10) unsigned DEFAULT NULL COMMENT 'FK to Contact ID that owns that account',
  `iban` varchar(42) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Iban of the debtor',
  `bic` varchar(11) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'BIC of the debtor',
  `type` varchar(4) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'RCUR' COMMENT 'RCUR for recurrent (default), OOFF for one-shot',
  `status` varchar(8) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'INIT' COMMENT 'Status of the mandate (INIT, OOFF, FRST, RCUR, SENT, INVALID, COMPLETE, ONHOLD, PARTIAL)',
  `is_enabled` tinyint(4) NOT NULL DEFAULT '1' COMMENT 'If the mandate has been validated',
  `creation_date` datetime DEFAULT NULL COMMENT 'by default now()',
  `first_contribution_id` int(10) unsigned DEFAULT NULL COMMENT 'FK to civicrm_contribution',
  `validation_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `index_entity` (`entity_table`,`entity_id`),
  KEY `iban` (`iban`),
  KEY `FK_civicrm_sdd_mandate_creditor_id` (`creditor_id`),
  KEY `FK_civicrm_sdd_mandate_contact_id` (`contact_id`),
  KEY `FK_civicrm_sdd_mandate_first_contribution_id` (`first_contribution_id`),
  CONSTRAINT `FK_civicrm_sdd_mandate_contact_id` FOREIGN KEY (`contact_id`) REFERENCES `civicrm_contact` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_civicrm_sdd_mandate_creditor_id` FOREIGN KEY (`creditor_id`) REFERENCES `civicrm_sdd_creditor` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_civicrm_sdd_mandate_first_contribution_id` FOREIGN KEY (`first_contribution_id`) REFERENCES `civicrm_contribution` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


-- /*******************************************************
-- *
-- * civicrm_sdd_file
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_sdd_file` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `reference` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'End-to-end reference for this sdd file.',
  `filename` char(64) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Name of the generated file',
  `latest_submission_date` datetime DEFAULT NULL COMMENT 'Latest submission date',
  `created_date` datetime DEFAULT NULL COMMENT 'When was this item created',
  `created_id` int(10) unsigned DEFAULT NULL COMMENT 'FK to Contact ID of creator',
  `status_id` int(10) unsigned NOT NULL COMMENT 'fk to Batch Status options in civicrm_option_values',
  `comments` text COLLATE utf8_unicode_ci COMMENT 'Comments about processing of this file',
  `tag` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Tag used to group multiple creditors in this XML file.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UI_reference` (`reference`),
  UNIQUE KEY `UI_filename` (`filename`),
  KEY `FK_civicrm_sdd_file_created_id` (`created_id`),
  CONSTRAINT `FK_civicrm_sdd_file_created_id` FOREIGN KEY (`created_id`) REFERENCES `civicrm_contact` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


-- /*******************************************************
-- *
-- * civicrm_sdd_txgroup
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_sdd_txgroup` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `reference` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'End-to-end reference for this tx group.',
  `type` char(4) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'FRST, RCUR or OOFF',
  `collection_date` datetime DEFAULT NULL COMMENT 'Target collection date',
  `latest_submission_date` datetime DEFAULT NULL COMMENT 'Latest submission date',
  `created_date` datetime DEFAULT NULL COMMENT 'When was this item created',
  `status_id` int(10) unsigned NOT NULL COMMENT 'fk to Batch Status options in civicrm_option_values',
  `sdd_creditor_id` int(10) unsigned DEFAULT NULL COMMENT 'fk to SDD Creditor Id',
  `sdd_file_id` int(10) unsigned DEFAULT NULL COMMENT 'fk to SDD File Id',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UI_reference` (`reference`),
  KEY `FK_civicrm_sdd_txgroup_sdd_creditor_id` (`sdd_creditor_id`),
  KEY `FK_civicrm_sdd_txgroup_sdd_file_id` (`sdd_file_id`),
  CONSTRAINT `FK_civicrm_sdd_txgroup_sdd_creditor_id` FOREIGN KEY (`sdd_creditor_id`) REFERENCES `civicrm_sdd_creditor` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_civicrm_sdd_txgroup_sdd_file_id` FOREIGN KEY (`sdd_file_id`) REFERENCES `civicrm_sdd_file` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


-- /*******************************************************
-- *
-- * civicrm_sdd_contribution_txgroup
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_sdd_contribution_txgroup` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'primary key',
  `contribution_id` int(10) unsigned NOT NULL COMMENT 'FK to Contribution ID',
  `txgroup_id` int(10) unsigned NOT NULL COMMENT 'FK to civicrm_sdd_txgroup',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `contriblookup` (`contribution_id`),
  INDEX `txglookup` (`txgroup_id`),
  CONSTRAINT `FK_civicrm_sdd_contribution_txgroup_contribution` FOREIGN KEY (`contribution_id`) REFERENCES `civicrm_contribution` (`id`),
  CONSTRAINT `FK_civicrm_sdd_cGGoup_id` FOREIGN KEY (`txgroup_id`) REFERENCES `civicrm_sdd_txgroup` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

