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
 );