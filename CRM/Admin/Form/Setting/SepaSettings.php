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
    private $config_fields;
    private $custom_fields;

    function __construct() {
       parent::__construct();

       $this->config_fields = array(
                         array('cycledays',             ts('Cycle Day(s)'), array('size' => 6)),
                         array('batching.OOFF.horizon',  ts('One-off horizon'), array('size' => 2)),
                         array('batching.OOFF.notice',   ts('One-off&nbsp;notice&nbsp;days'), array('size' => 2)),
                         array('batching.RCUR.horizon',  ts('Recurring horizon'), array('size' => 2)),
                         array('batching.RCUR.grace',    ts('Recurring grace'), array('size' => 2)),
                         array('batching.RCUR.notice',   ts('Recurring&nbsp;notice&nbsp;days (follow-up)'), array('size' => 2)),
                         array('batching.FRST.notice',   ts('Recurring&nbsp;notice&nbsp;days (initial)'), array('size' => 2)),
                         array('batching.UPDATE.lock.timeout', ts('Update lock timeout'), array('size' => 2)),
                         array('custom_txmsg', ts('Transaction Message'), array('size' => 60, 'placeholder' => CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', 'custom_txmsg'))));

      $this->custom_fields = array(
                         array('custom_cycledays',      ts('Cycle Day(s)'), array('size' => 6)),
                         array('custom_OOFF_horizon',    ts('One-off horizon'), array('size' => 2)),
                         array('custom_OOFF_notice',     ts('One-off&nbsp;notice&nbsp;days'), array('size' => 2)),
                         array('custom_RCUR_horizon',    ts('Recurring horizon'), array('size' => 2)),
                         array('custom_RCUR_grace',      ts('Recurring grace'), array('size' => 2)),
                         array('custom_RCUR_notice',     ts('Recurring&nbsp;notice&nbsp;days (follow-up)'), array('size' => 2)),
                         array('custom_FRST_notice',     ts('Recurring&nbsp;notice&nbsp;days (initial)'), array('size' => 2)),
                         array('custom_update_lock_timeout', ts('Update lock timeout'), array('size' => 2)));
    }

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
            $elementName = $this->domainToString($value[0]);
            $elem = $this->addElement('text', $elementName, $value[1], (isset($value[2]) ? $value[2] : array()));
            if (!in_array($elementName, array('cycledays', 'custom_txmsg'))) {
                // integer only rules, except for cycledays (list)
              $this->addRule($this->domainToString($value[0]), 
                         sprintf(ts("Please enter the %s as number (integers only)."), $value[1]),
                        'positiveInteger');
              $this->addRule($this->domainToString($value[0]), 
                         sprintf(ts("Please enter the %s as number (integers only)."), $value[1]),
                        'required');
            }
        }

        // country drop down field
        $config = CRM_Core_Config::singleton();
        $i18n = CRM_Core_I18n::singleton();

        $climit = array();
        $cnames = array();
        $ciso = array();
        $filtered = array();

        $climit = $config->countryLimit();
        CRM_Core_PseudoConstant::populate($cnames, 'CRM_Core_DAO_Country', TRUE, 'name', 'is_active');
        CRM_Core_PseudoConstant::populate($ciso, 'CRM_Core_DAO_Country', TRUE, 'iso_code');

        foreach ($ciso as $key => $value) {
          foreach ($climit as $active_country) {
            if ($active_country == $value) {
              $filtered[$key] = $cnames[$key];
            }
          }
        }
        
        $i18n->localizeArray($filtered, array('context' => 'country'));
        asort($filtered);

        // do not use array_merge() because it discards the original indizes
        $country_ids = array('' => ts('- select -')) + $filtered;

        // look up some values
        $excld_we = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', 'exclude_weekends');
        $hide_bic = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', 'pp_hide_bic');
        $mendForm = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', 'pp_improve_frequency');

        // add creditor form elements
        $this->addElement('text',       'addcreditor_creditor_id',  ts("Creditor Contact"));
        $this->addElement('text',       'addcreditor_name',         ts("Name"));
        $this->addElement('text',       'addcreditor_id',           ts("Identifier"));
        $this->addElement('text',       'addcreditor_address',      ts("Address"), array('size' => 60));
        $this->addElement('select',     'addcreditor_country_id',   ts("Country"), $country_ids);
        $this->addElement('text',       'addcreditor_bic',          ts("BIC"));
        $this->addElement('text',       'addcreditor_iban',         ts("IBAN"), array('size' => 30));
        $this->addElement('select',     'addcreditor_pain_version', ts("PAIN Version"), array('' => ts('- select -')) + CRM_Core_OptionGroup::values('sepa_file_format'));
        $this->addElement('checkbox',   'is_test_creditor',         ts("Is a Test Creditor"), "", array('value' =>'0'));
        $this->addElement('checkbox',   'exclude_weekends',         ts("Exclude Weekends"), "", ($excld_we?array('checked'=>'checked'):array()));
        $this->addElement('checkbox',   'pp_hide_bic',              ts("Hide BIC in PP"),   "", ($hide_bic?array('checked'=>'checked'):array()));
        $this->addElement('checkbox',   'pp_improve_frequency',     ts("Improve payment processor form"),   "", ($mendForm?array('checked'=>'checked'):array()));
        $this->addElement('hidden',     'edit_creditor_id',         '', array('id' => 'edit_creditor_id'));
        $this->addElement('hidden',     'add_creditor_id',          '', array('id' => 'add_creditor_id'));

        // add custom form elements and validation rules
        $index = 0;
        foreach ($this->custom_fields as $key => $value) {
            if (isset($value[2])) {
              $properties = $value[2];
            } else {
              $properties = array();
            }
            $properties['placeholder'] = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', $this->domainToString($this->config_fields[$index][0]));
            $this->addElement('text', $this->domainToString($value[0]), $value[1], $properties);
            $elementName = $this->domainToString($value[0]);
            if (!in_array($elementName, array('custom_cycledays', 'custom_txmsg'))) {
              // integer only rules, except for cycledays (list)
              $this->addRule($elementName, 
                       sprintf(ts("Please enter the %s as number (integers only)."), $value[1]),
                      'positiveInteger');
            }
            $index++;
        }

        // register and add extra validation rules
        $this->registerRule('sepa_cycle_day_list', 'callback', 'sepa_cycle_day_list', 'CRM_Sepa_Logic_Settings');
        $this->addRule('cycledays',        ts('Please give a comma separated list of valid days.'), 'sepa_cycle_day_list');
        $this->addRule('custom_cycledays', ts('Please give a comma separated list of valid days.'), 'sepa_cycle_day_list');

        // get creditor list
        $creditors_default_list = array();
        $creditor_query = civicrm_api('SepaCreditor', 'get', array('version' => 3, 'option.limit' => 99999));
        if (!empty($creditor_query['is_error'])) {
          return civicrm_api3_create_error("Cannot get creditor list: " . $creditor_query['error_message']);
        } else {
          $creditors = array();
          foreach ($creditor_query['values'] as $creditor) {
              $creditors[] = $creditor;
              $creditors_default_list[$creditor['id']] = $creditor['name'];
          }
        }
        $this->assign('creditors', $creditors);
        $default_creditors = $this->addElement('select', 'batching_default_creditor', ts("Default Creditor"), array('' => ts('- select -')) + $creditors_default_list);
        $default_creditors->setSelected(CRM_Sepa_Logic_Settings::getSetting('batching.default.creditor'));

        // add general config options
        $amm_options = CRM_Sepa_Logic_Settings::getSetting('allow_mandate_modification')?array('checked'=>'checked'):array();
        $this->addElement('checkbox', 'allow_mandate_modification', ts("Mandate Modifications"), NULL, $amm_options);
        $mandate_types = array(
          'OOFF' => ts('One Time (OOFF)'),
          'RCUR' => ts('Recurring (RCUR)'),
        );
        $default_mandate_element = $this->addElement('select', 'default_mandate_type', ts("Default Mandate Type"), $mandate_types);
        $default_mandate_element->setSelected(CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', 'default_mandate_type'));

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

        // save general config options:
        // default creditor
        CRM_Core_BAO_Setting::setItem($values['batching_default_creditor'], 'SEPA Direct Debit Preferences', 'batching_default_creditor');
        CRM_Core_BAO_Setting::setItem($values['default_mandate_type'], 'SEPA Direct Debit Preferences', 'default_mandate_type');

        // mandate modification
        $allow_mandate_modification = empty($values['allow_mandate_modification'])?'0':'1';
        CRM_Core_BAO_Setting::setItem($allow_mandate_modification, 'SEPA Direct Debit Preferences', 'allow_mandate_modification');

        CRM_Core_BAO_Setting::setItem((isset($values['exclude_weekends'])     ? "1" : "0"), 'SEPA Direct Debit Preferences', 'exclude_weekends');
        CRM_Core_BAO_Setting::setItem((isset($values['pp_hide_bic'])          ? "1" : "0"), 'SEPA Direct Debit Preferences', 'pp_hide_bic');
        CRM_Core_BAO_Setting::setItem((isset($values['pp_improve_frequency']) ? "1" : "0"), 'SEPA Direct Debit Preferences', 'pp_improve_frequency');

        $session = CRM_Core_Session::singleton();
        $session->setStatus(ts("Settings successfully saved"));

        CRM_Core_DAO::triggerRebuild();
        $session->replaceUserContext(CRM_Utils_System::url('civicrm/admin/setting/sepa'));
    }
}