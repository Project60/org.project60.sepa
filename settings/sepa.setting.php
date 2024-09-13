<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 Project60                      |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

/*
* Settings metadata file
*/

return array(
  'allow_mandate_modification' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'allow_mandate_modification',
    'type' => 'Boolean',
    'quick_form_type' => 'YesNo',
    'html_type' => 'checkbox',
    'default' => '0',
    'add' => '4.3',
    'title' => 'allow SEPA mandate modifications',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => "Set this value to Yes if you want want to allow SEPA mandate modifications",
    'help_text' => "Set this value to Yes if you want want to allow SEPA mandate modifications",
  ),
  'batching_default_creditor' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_default_creditor',
    'type' => 'Integer',
    'html_type' => 'Select',
    'default' => 0,
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Default Creditor',
    'help_text' => 'This creditor will be used when no other creditor is explicitely set',
  ),
  'batching_OOFF_horizon_override' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_OOFF_horizon_override',
    'type' => 'String',
    'default' => "undefined",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'OOFF Horizon override',
    'help_text' => 'OOFF horizon override',
  ),
  'cycledays_override' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'cycledays_override',
    'type' => 'String',
    'default' => "undefined",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'cycle days override',
    'help_text' => 'cycle days override',
  ),
  'cycledays' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'cycledays',
    'type' => 'String',
    'default' => "",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'cycle days override',
    'help_text' => 'cycle days override',
  ),
  'batching_OOFF_notice_override' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_OOFF_notice_override',
    'type' => 'String',
    'default' => "undefined",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'OOFF Notice override',
    'help_text' => 'OOFF notice override',
  ),
  'batching_RCUR_horizon_override' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_RCUR_horizon_override',
    'type' => 'String',
    'default' => "undefined",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'RCUR horizon override',
    'help_text' => 'RCUR horizon override',
  ),
  'batching_RCUR_grace_override' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_RCUR_grace_override',
    'type' => 'String',
    'default' => "undefined",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'RCUR grace override',
    'help_text' => 'RCUR grace override',
  ),
  'batching_RCUR_notice_override' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_RCUR_notice_override',
    'type' => 'String',
    'default' => "undefined",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'RCUR notice override',
    'help_text' => 'RCUR notice override',
  ),
  'batching_FRST_notice_override' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_FRST_notice_override',
    'type' => 'String',
    'default' => "undefined",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'FRST notice override',
    'help_text' => 'FRST notice override',
  ),
  'batching_OOFF_horizon' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_OOFF_horizon',
    'type' => 'String',
    'default' => "30",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'OOFF Horizon',
    'help_text' => 'OOFF horizon',
  ),
  'batching_OOFF_notice' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_OOFF_notice',
    'type' => 'String',
    'default' => "6",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'OOFF Notice',
    'help_text' => 'OOFF notice',
  ),
  'batching_RCUR_horizon' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_RCUR_horizon',
    'type' => 'String',
    'default' => "30",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'RCUR horizon',
    'help_text' => 'RCUR horizon',
  ),
  'batching_RCUR_grace' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_RCUR_grace',
    'type' => 'String',
    'default' => "14",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'RCUR grace',
    'help_text' => 'RCUR grace',
  ),
  'batching_RCUR_notice' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_RCUR_notice',
    'type' => 'String',
    'default' => "6",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'RCUR notice',
    'help_text' => 'RCUR notice',
  ),
  'batching_FRST_notice' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_FRST_notice',
    'type' => 'String',
    'default' => "6",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'FRST notice',
    'help_text' => 'FRST notice',
  ),
  'batching_UPDATE_lock_timeout' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_UPDATE_lock_timeout',
    'type' => 'String',
    'default' => "180",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'UPDATE lock timeout',
    'help_text' => 'UPDATE lock timeout',
  ),
  'custom_txmsg' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'custom_txmsg',
    'type' => 'String',
    'default' => "Thank you.",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Transaction message',
    'help_text' => 'Transaction message',
  ),
  'custom_txmsg_override' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'custom_txmsg_override',
    'type' => 'String',
    'default' => "undefined",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Transaction message override',
    'help_text' => 'Transaction message override',
  ),
  'exclude_weekends' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'exclude_weekends',
    'type' => 'Integer',
    'html_type' => 'Select',
    'default' => 0,
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Exclude weekends',
    'help_text' => 'Exclude weekends',
  ),
  'sdd_async_batching' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'sdd_async_batching',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 0,
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Enables asychronous batching',
  ),
  'sdd_financial_type_grouping' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'sdd_financial_type_grouping',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 0,
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Groups by Financial Types.',
  ),
  'sdd_skip_closed' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'sdd_skip_closed',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 0,
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Skip status closed for SEPA collection groups',
  ),
  'sdd_no_draft_xml' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'sdd_no_draft_xml',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 0,
    'is_domain' => 1,
    'is_contact' => 0,
    'help_text' => "Don't allow XML download unless the group is closed",
  ),
  'pp_buffer_days' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'pp_buffer_days',
    'type' => 'Integer',
    'html_type' => 'Select',
    'default' => 0,
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => "Contribution page buffer (in days) before debit needs to be collected",
    'help_text' => "Contribution page buffer (in days) before debit needs to be collected",
  )
 );
