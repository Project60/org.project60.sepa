<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 TTTP                           |
| Author: X+                                             |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/


/**
 * SEPA_DD payment processor
 *
 * @package CiviCRM_SEPA
 * @todo: deprecated, fix
 */


return array (
  0 => 
  array (
    'name' => 'CRM_Sepa_Direct_Debit',
    'entity' => 'PaymentProcessorType',
    'params' => 
    array (
      'version' => 3,
      'description' => 'SEPA Direct Debit (org.project60.sepa)',
    'name' => 'sepa_dd',
    'title' => 'SEPA Direct Debit',
    "is_active"=> 1,
    "user_name_label"=>"SEPA Creditor identifier",
    "class_name"=>"Payment_SEPA_DD",
    "url_site_default"=>"",
    "url_recur_default"=>"",
    "url_site_test_default"=>"",
    "url_recur_test_default"=>"",
    "billing_mode"=>"1", //form, seems to be the easiest
    "is_recur"=>"1",
    "payment_type"=>"9000" // SDD - needs to be changed and pick up the value from the option values
    ),
  ),
);
