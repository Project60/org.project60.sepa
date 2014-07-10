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
                         array('batching.alt.OOFF.horizon', 'OOFF horizon'),
                         array('batching.alt.OOFF.notice', 'OOFF notice days'),
                         array('batching.alt.RCUR.horizon', 'RCUR horizon'),
                         array('batching.alt.RCUR.notice', 'RCUR notice days'),
                         array('batching.alt.FRST.horizon', 'FRST horizon'),
                         array('batching.alt.FRST.notice', 'FRST notice days'),
                         array('batching.alt.UPDATE.lock.timeout', 'Update lock timeout'),
                        );

    private $custom_fields = array(
                         array('custom_OOFF_horizon', 'OOFF horizon'),
                         array('custom_OOFF_notice', 'OOFF notice days'),
                         array('custom_RCUR_horizon', 'RCUR horizon'),
                         array('custom_RCUR_notice', 'RCUR notice days'),
                         array('custom_FRST_horizon', 'FRST horizon'),
                         array('custom_FRST_notice', 'FRST notice days'),
                         array('custom_update_lock_timeout', 'Update lock timeout'),
                        );

    function domainToString($raw) {
      return str_replace('.', '_', $raw);
    }

    function stringToDomain($raw) {
      return str_replace('_', '.', $raw);
    }


    function setDefaultValues() {
        $fields = array();
        // get all default values (they are set once when the extension is being enabled)
        foreach ($this->config_fields as $key => $value) {
            $fields[$this->domainToString($value[0])] = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', $this->domainToString($value[0]));
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
            $this->addElement('text', $this->domainToString($value[0]), ts($value[1]));
            $this->addRule($this->domainToString($value[0]), 
                       ts("Please enter the $value[1] as number (integers only)."),
                      'positiveInteger');
            $this->addRule($this->domainToString($value[0]), 
                       ts("Please enter the $value[1] as number (integers only)."),
                      'required');
        }

        // country drop down field
        $i18n = CRM_Core_I18n::singleton();
        $country = array();
        CRM_Core_PseudoConstant::populate($country, 'CRM_Core_DAO_Country', TRUE, 'name', 'is_active');
        $i18n->localizeArray($country, array('context' => 'country'));
        asort($country);

        // do not use array_merge() because it discards the original indizes
        $country_ids = array('' => ts('- select -')) + $country;

        // add creditor form elements
        $this->addElement('text', 'addcreditor_name', ts("Name"));
        $this->addElement('text', 'addcreditor_id', ts("Identifier"));
        $this->addElement('text', 'addcreditor_address', ts("Address"));
        $this->addElement('select', 'addcreditor_country_id', ts("Country"), $country_ids);
        $this->addElement('text', 'addcreditor_bic', ts("BIC"));
        $this->addElement('text', 'addcreditor_iban', ts("IBAN"));
        $this->addElement('select', 'addcreditor_pain_version', ts("PAIN Version"), array('' => ts('- select -')) + CRM_Core_OptionGroup::values('sepa_file_format'));
        $this->addElement('hidden', 'edit_creditor_id', '', array('id' => 'edit_creditor_id'));

        // add all form elements and validation rules
        $index = 0;
        foreach ($this->custom_fields as $key => $value) {
            $this->addElement('text', $this->domainToString($value[0]), ts($value[1]), array('placeholder' => CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', $this->domainToString($this->config_fields[$index][0]))));
            $this->addRule($this->domainToString($value[0]), 
                       ts("Please enter the $value[1] as number (integers only)."),
                      'positiveInteger');
            $index++;
        }

        // get creditor list
        $creditor_query = civicrm_api('SepaCreditor', 'get', array('version' => 3, 'option.limit' => 99999));
        if (!empty($creditor_query['is_error'])) {
          return civicrm_api3_create_error("Cannot get creditor list: " . $creditor_query['error_message']);
        } else {
          $creditors = array();
          foreach ($creditor_query['values'] as $creditor) {
              $creditors[] = $creditor;
          }
        }
        $this->assign('creditors', $creditors);
        parent::buildQuickForm();
    }

    function postProcess() {
        $values = $this->controller->exportValues($this->_name);

        // save field values
        foreach ($this->config_fields as $key => $value) {
            if(array_key_exists($this->domainToString($value[0]), $values)) {
                CRM_Core_BAO_Setting::setItem($values[$this->domainToString($value[0])], 'SEPA Direct Debit Preferences', $this->domainToString($value[0]));
            }  
        }
        
        $session = CRM_Core_Session::singleton();
        $session->setStatus(ts("Settings successfully saved"));

        CRM_Core_DAO::triggerRebuild();
        $session->replaceUserContext(CRM_Utils_System::url('civicrm/admin/setting/sepa'));
    }
}