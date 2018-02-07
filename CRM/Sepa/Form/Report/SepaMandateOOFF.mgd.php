<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 SYSTOPIA                       |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 =>
  array (
    'name' => 'CRM_Sepa_Form_Report_SepaMandateOOFF',
    'entity' => 'ReportTemplate',
    'params' =>
    array (
      'version' => 3,
      'label' => ts('SEPA Mandates (One-Off)', array('domain' => 'org.project60.sepa')),
      'description' => ts('SEPA One-Off Mandate Report (org.project60.sepa)', array('domain' => 'org.project60.sepa')),
      'class_name' => 'CRM_Sepa_Form_Report_SepaMandateOOFF',
      'report_url' => 'org.project60.sepa/sepamandateooff',
      'component' => 'CiviContribute',
    ),
  ),
);