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
use CRM_Sepa_ExtensionUtil as E;

return array (
  0 => 
  array (
    'name' => 'CRM_Sepa_Form_Search_SepaContactSearch',
    'entity' => 'CustomSearch',
    'params' => 
    array (
        'version'     => 3,
        'label'       => E::ts('SEPA Contact Search'),
        'description' => E::ts('Find contacts based on CiviSEPA Mandates'),
        'class_name'  => 'CRM_Sepa_Form_Search_SepaContactSearch',
    ),
  ),
);
