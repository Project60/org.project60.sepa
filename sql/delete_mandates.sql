-- Will delete ALL sepa data except for creditors, including the attached recurring contributions
-- Will NOT delte the contributions (that is a more complex process). 
-- Simply delte them by searching for the SEPA-Payment types

-- delete the recurring contributions
DELETE FROM civicrm_contribution_recur WHERE id IN (SELECT entity_id FROM civicrm_sdd_mandate WHERE entity_table='civicrm_contribution_recur');

-- delete the mandates
DELETE FROM civicrm_sdd_mandate;

-- delete the files
DELETE FROM civicrm_sdd_file;

-- delete the groups
DELETE FROM civicrm_sdd_contribution_txgroup;
DELETE FROM civicrm_sdd_txgroup;
