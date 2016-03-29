-- /*******************************************************
-- *
-- * Extend civicrm_sdd_creditor by `currency`
-- *
-- *******************************************************/

ALTER TABLE civicrm_sdd_creditor ADD COLUMN `currency` VARCHAR(3) NOT NULL DEFAULT 'EUR' COMMENT '3 character string symbol of currency. Required by external extension.';
