<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 SYSTOPIA                       |
| Author: N. Bochan (bochan -at- systopia.de)            |
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

require_once 'CRM/Admin/Form/Setting.php';
require_once 'CRM/Core/BAO/CustomField.php';
 
class CRM_Admin_Form_Setting_SepaSettings extends CRM_Admin_Form_Setting
{
    private $config_fields = array(
                         array('alternative_batching_ooff_horizon_days', 'OOFF horizon'),
                         array('alternative_batching_ooff_notice_days', 'OOFF notice days'),
                         array('alternative_batching_rcur_horizon_days', 'RCUR horizon'),
                         array('alternative_batching_rcur_notice_days', 'RCUR notice days'),
                         array('alternative_batching_frst_horizon_days', 'FRST horizon'),
                         array('alternative_batching_frst_notice_days', 'FRST notice days'),
                         array('alternative_batching_update_lock_timeout', 'update lock timeout'),
                        );

    function setDefaultValues() {
        $fields = array();
        // get all default values (they are set once when the extension is being enabled)
        foreach ($this->config_fields as $key => $value) {
            $fields[$value[0]] = CRM_Core_BAO_Setting::getItem('org.project60', $value[0]);
        }
        return $fields; 
    }

    public function buildQuickForm( ) {
        CRM_Utils_System::setTitle(ts('Sepa Direct Debit - Settings'));

        $customFields = CRM_Core_BAO_CustomField::getFields();
        $cf = array();
        foreach ($customFields as $k => $v) {
            $cf[$k] = $v['label'];
        }

        // add all form elements and validation rules
 		    foreach ($this->config_fields as $key => $value) {
            // add element
            $this->addElement('text', $value[0], ts($value[1]));
            // add rule
            $this->addRule($value[0], 
                       ts("Please enter the $value[1] as number (integers only)."),
                      'positiveInteger');
            $this->addRule($value[0], 
                       ts("Please enter the $value[1] as number (integers only)."),
                      'required');
        }

        parent::buildQuickForm();
    }

    function postProcess() {
        $values = $this->controller->exportValues($this->_name);

        // save field values
        foreach ($this->config_fields as $key => $value) {
            if(array_key_exists($value[0], $values)) {
                CRM_Core_BAO_Setting::setItem($values[$value[0]], 'org.project60', $value[0]);
            }  
        }
        
        $session = CRM_Core_Session::singleton();
        $session->setStatus(ts("Settings successfully saved"));

        CRM_Core_DAO::triggerRebuild();
        $session->replaceUserContext(CRM_Utils_System::url('civicrm/admin/setting/sepa'));
    }
}