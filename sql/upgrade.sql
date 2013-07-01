# issue 12: first contrib on mandate
alter table civicrm_sdd_mandate add column first_contribution_id int unsigned COMMENT 'FK to civicrm_contribution';
alter table civicrm_sdd_mandate add constraint FK_civicrm_sdd_mandate_first_contribution_id FOREIGN KEY (first_contribution_id) REFERENCES civicrm_contribution(id);
update civicrm_sdd_mandate,civicrm_contribution set first_contribution_id=civicrm_contribution.id where civicrm_sdd_mandate.entity_id=civicrm_contribution.contribution_recur_id AND civicrm_sdd_mandate.entity_table = "civicrm_contribution_recur";

#issue 13: creditor more complete
alter table civicrm_sdd_mandate add column `payment_instrument_id` int unsigned    COMMENT 'FK to civicrm_payment_instrument';
alter table civicrm_sdd_mandate add column `payment_processor_id` int unsigned    COMMENT 'FK to civicrm_payment_processor';
alter table civicrm_sdd_creditor add column `payment_processor_id` int unsigned    COMMENT 'FK to civicrm_payment_processor';

