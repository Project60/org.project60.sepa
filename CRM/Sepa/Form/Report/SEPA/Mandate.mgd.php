<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 => 
  array (
    'name' => 'CRM_Sepa_Form_Report_SEPA_Mandate',
    'entity' => 'ReportTemplate',
    'params' => 
    array (
      'version' => 3,
      'label' => 'SEPA_Mandate',
      'description' => 'SEPA_Mandate (org.project60.sepa)',
      'class_name' => 'CRM_Sepa_Form_Report_SEPA_Mandate',
      'report_url' => 'org.project60.sepa/sepa_mandate',
      'component' => 'CiviContribute',
    ),
  ),
);