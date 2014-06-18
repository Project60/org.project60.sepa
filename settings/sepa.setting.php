<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 Project60                      |
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
  'batching_alt_OOFF_horizon_override' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_alt_OOFF_horizon_override',
    'type' => 'String',
    'default' => "undefined",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'OOFF Horizon override',
    'help_text' => 'OOFF horizon override',
  ),
  'batching_alt_OOFF_notice_override' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_alt_OOFF_notice_override',
    'type' => 'String',
    'default' => "undefined",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'OOFF Notice override',
    'help_text' => 'OOFF notice override',
  ),
  'batching_alt_RCUR_horizon_override' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_alt_RCUR_horizon_override',
    'type' => 'String',
    'default' => "undefined",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'RCUR horizon override',
    'help_text' => 'RCUR horizon override',
  ),
  'batching_alt_RCUR_notice_override' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_alt_RCUR_notice_override',
    'type' => 'String',
    'default' => "undefined",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'RCUR notice override',
    'help_text' => 'RCUR notice override',
  ),
    'batching_alt_FRST_horizon_override' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_alt_FRST_horizon_override',
    'type' => 'String',
    'default' => "undefined",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'FRST horizon override',
    'help_text' => 'FRST horizon override',
  ),
  'batching_alt_FRST_notice_override' => array(
    'group_name' => 'SEPA Direct Debit Preferences',
    'group' => 'org.project60',
    'name' => 'batching_alt_FRST_notice_override',
    'type' => 'String',
    'default' => "undefined",
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'FRST notice override',
    'help_text' => 'FRST notice override',
  )
 );