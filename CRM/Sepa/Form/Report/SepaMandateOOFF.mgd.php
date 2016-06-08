<?php
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