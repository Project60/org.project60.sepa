<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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

/**
 * back office mandate creation form
 *
 * @todo this implementation should use the CiviCRM Form pattern 
 *        and should be refactored
 *
 * @package CiviCRM_SEPA
 *
 */


require_once 'CRM/Core/Page.php';
require_once 'packages/php-iban-1.4.0/php-iban.php';

class CRM_Sepa_Page_CreateMandate extends CRM_Core_Page {

  function run() {
    if (isset($_REQUEST['mandate_type'])) {
      $contact_id = $_REQUEST['contact_id'];
      $this->assign("back_url", CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid=${contact_id}&selectedChild=contribute"));

      $errors = $this->validateParameters($_REQUEST['mandate_type']);
      if (count($errors) > 0) {
        // i.e. validation failed
        $this->assign('validation_errors', $errors);
        $_REQUEST['cid'] = $contact_id;
        $this->prepareCreateForm($contact_id);
      } else {
        // validation o.k. = > create
        if ($_REQUEST['mandate_type']=='OOFF') {
          $this->createMandate('OOFF');
        } else if ($_REQUEST['mandate_type']=='RCUR') {
          $this->createMandate('RCUR');
        }
      }

    } else if (isset($_REQUEST['cid'])) {
      // create a new form
      $this->prepareCreateForm($_REQUEST['cid']);

    } else if (isset($_REQUEST['clone'])) {
      // this is a cloned form
      $this->prepareClonedData($_REQUEST['clone']);

    } else if (isset($_REQUEST['replace'])) {
      // this is a replace form
      $this->prepareClonedData($_REQUEST['replace']);
      $this->assign('replace', $_REQUEST['replace']);
      if (isset($_REQUEST['replace_date'])) {
        $this->assign('replace_date', $_REQUEST['replace_date']);
        $this->assign('start_date', $_REQUEST['replace_date']);
        $this->assign('end_date', '');
      }
      if (isset($_REQUEST['replace_reason'])) {
        $this->assign('replace_reason', $_REQUEST['replace_reason']);
      }
    } else {
      // error -> no parameters set
      die(ts("This page cannot be called w/o parameters."));
    }
    parent::run();
  }



  /**
   * Creates a SEPA mandate for the given type
   */
  function createMandate($type) {
    // first create a contribution
    $payment_instrument_id = CRM_Core_OptionGroup::getValue('payment_instrument', $type, 'name');
    $contribution_status_id = CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name');

    $params = array(
      'id' => $_REQUEST['creditor_id'],
      'return' => 'currency'
    );
    $creditor = civicrm_api3('SepaCreditor', 'getsingle', $params);

    $contribution_data = array(
        'version'                   => 3,
        'contact_id'                => $_REQUEST['contact_id'],
        'campaign_id'               => $_REQUEST['campaign_id'],
        'financial_type_id'         => $_REQUEST['financial_type_id'],
        'payment_instrument_id'     => $payment_instrument_id,
        'contribution_status_id'    => $contribution_status_id,
        'currency'                  => $creditor['currency'],
      );

    if ($type=='OOFF') {
      $initial_status = 'OOFF';
      $entity_table = 'civicrm_contribution';
      $contribution_data['total_amount'] = number_format($_REQUEST['total_amount'], 2, '.', '');
      $contribution_data['receive_date'] = $_REQUEST['date'];
      $contribution_data['source'] = $_REQUEST['source'];
      $contribution = civicrm_api('Contribution', 'create', $contribution_data);
    } else if ($type=='RCUR') {
      $initial_status = 'FRST';
      $entity_table = 'civicrm_contribution_recur';
      $contribution_data['amount']              = number_format($_REQUEST['total_amount'], 2, '.', '');
      $contribution_data['start_date']          = $_REQUEST['start_date'];
      $contribution_data['end_date']            = $_REQUEST['end_date'];
      $contribution_data['create_date']         = date('YmdHis');
      $contribution_data['modified_date']       = date('YmdHis');
      $contribution_data['frequency_unit']      = 'month';
      $contribution_data['frequency_interval']  = $_REQUEST['interval'];
      $contribution_data['cycle_day']           = $_REQUEST['cycle_day'];
      $contribution_data['is_email_receipt']    = 0;
      $contribution = civicrm_api('ContributionRecur', 'create', $contribution_data);
    }

    if (isset($contribution['is_error']) && $contribution['is_error']) {
      $this->processError(
        sprintf(ts("Couldn't create contribution for contact #%s"), $_REQUEST['contact_id']),
        ts("Couldn't create contribution"),
        $contribution['error_message'],
        $_REQUEST['contact_id']);
      return;
    }

    // create a note, if requested
    if ($_REQUEST['note']) {
      // add note
      $create_note = array(
        'version'                   => 3,
        'entity_table'              => $entity_table,
        'entity_id'                 => $contribution['id'],
        'note'                      => $_REQUEST['note'],
        'privacy'                   => 0,
      );

      $create_note_result = civicrm_api('Note', 'create', $create_note);
      if (isset($create_note_result['is_error']) && $create_note_result['is_error']) {
        // don't consider this a fatal error...
        CRM_Core_Session::setStatus(sprintf(ts("Couldn't create note for contribution #%s"), $contribution['id']), ts('Error'), 'alert');
        error_log("org.project60.sepa_dd: error creating note - ".$create_note_result['error_message']);
      }
    }

    // next, create mandate
    $mandate_data = array(
        'version'                   => 3,
        'debug'                     => 1,
        'contact_id'                => $_REQUEST['contact_id'],
        'source'                    => $_REQUEST['source'],
        'entity_table'              => $entity_table,
        'entity_id'                 => $contribution['id'],
        'creation_date'             => date('YmdHis'),
        'validation_date'           => date('YmdHis'),
        'date'                      => date('YmdHis'),
        'iban'                      => $_REQUEST['iban'],
        'bic'                       => $_REQUEST['bic'],
        'reference'                 => $_REQUEST['reference'],
        'status'                    => $initial_status,
        'type'                      => $type,
        'creditor_id'               => $_REQUEST['creditor_id'],
        'is_enabled'                => 1,
      );
    // call the hook for mandate generation

    $mandate = civicrm_api('SepaMandate', 'create', $mandate_data);
    if (isset($mandate['is_error']) && $mandate['is_error']) {
      $this->processError(
        sprintf(ts("Couldn't create %s mandate for contact #%s"), $type, $_REQUEST['contact_id']),
        ts("Couldn't create mandate"),
        $mandate['error_message'],
        $_REQUEST['contact_id']);
      return;
    }

    // if we want to replace an old mandate:
    if (isset($_REQUEST['replace'])) {
      CRM_Sepa_BAO_SEPAMandate::terminateMandate($_REQUEST['replace'], $_REQUEST['replace_date'], $_REQUEST['replace_reason']);
    }

    // if we get here, everything went o.k.
    $reference   = $mandate['values'][$mandate['id']]['reference'];
    $mandate_url = CRM_Utils_System::url('civicrm/sepa/xmandate', "mid={$mandate['id']}");
    CRM_Core_Session::setStatus(ts("'%3' SEPA Mandate <a href=\"%2\">%1</a> created.", array(1 => $reference, 2 => $mandate_url, 3 => $type)), ts("Success"), 'info');

    if (!$this->isPopup()) {
      $contact_url = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$contribution_data['contact_id']}&selectedChild=contribute");
      CRM_Utils_System::redirect($contact_url);
    }
  }


  /**
   * Will prepare the form and look up all necessary data
   */
  function prepareCreateForm($contact_id) {
    // load financial types
    $this->assign("financial_types", CRM_Contribute_PseudoConstant::financialType());
    $this->assign("date", date('Y-m-d'));
    $this->assign("start_date", date('Y-m-d'));

    // first, try to load contact
    $contact = civicrm_api('Contact', 'getsingle', array('version' => 3, 'id' => $contact_id));
    if (isset($contact['is_error']) && $contact['is_error']) {
      CRM_Core_Session::setStatus(sprintf(ts("Couldn't find contact #%s"), $contact_id), ts('Error'), 'error');
      $this->assign("display_name", "ERROR");
      return;
    }

    $this->assign("contact_id", $contact_id);
    $this->assign("display_name", $contact['display_name']);

    // look up campaigns
    $campaign_query = civicrm_api('Campaign', 'get', array('version'=>3, 'is_active'=>1, 'option.limit' => 9999, 'option.sort'=>'title'));
    $campaigns = array();
    $campaigns[''] = ts("No Campaign");
    if (isset($campaign_query['is_error']) && $campaign_query['is_error']) {
      CRM_Core_Session::setStatus(ts("Couldn't load campaign list."), ts('Error'), 'error');
    } else {
      foreach ($campaign_query['values'] as $campaign_id => $campaign) {
        $campaigns[$campaign_id] = $campaign['title'];
      }
    }
    $this->assign('campaigns', $campaigns);

    // look up account in other SEPA mandates
    $known_accounts = array();
    $query_sql = "SELECT DISTINCT iban, bic FROM civicrm_sdd_mandate WHERE contact_id=$contact_id AND (creation_date >= (NOW() - INTERVAL 60 DAY));";
    $old_mandates = CRM_Core_DAO::executeQuery($query_sql);
    while ($old_mandates->fetch()) {
      $value = $old_mandates->iban.'/'.$old_mandates->bic;
      array_push($known_accounts, 
        array("name" => $old_mandates->iban, "value"=>$value));
    }


    // look up account in CiviBanking (if enabled...)
    $iban_reference_type = CRM_Core_OptionGroup::getValue('civicrm_banking.reference_types', 'IBAN', 'value', 'String', 'id');
    if ($iban_reference_type) {
      $accounts = civicrm_api('BankingAccount', 'get', array('version' => 3, 'contact_id' => $contact_id));
      if (isset($accounts['is_error']) && $accounts['is_error']) {
        // this probably means, that CiviBanking is not installed...
      } else {
        foreach ($accounts['values'] as $account_id => $account) {
          $account_ref = civicrm_api('BankingAccountReference', 'getsingle', array('version' => 3, 'ba_id' => $account_id, 'reference_type_id' => $iban_reference_type));
          if (isset($account_ref['is_error']) && $account_ref['is_error']) {
            // this would also be an error, if no reference is set...
          } else {
            $account_data = json_decode($account['data_parsed']);
            if (isset($account_data->BIC)) {
              // we have IBAN and BIC -> add:
              $value = $account_ref['reference'].'/'.$account_data->BIC;
              array_push($known_accounts, 
                array("name" => $account_ref['reference'], "value"=>$value));
            }
          }
        }
      }
    }
    
    // remove duplicate entries
    $known_account_names = array();
    foreach ($known_accounts as $index => $entry) {
      if (isset($known_account_names[$entry['name']])) {
        unset($known_accounts[$index]);
      } else {
        $known_account_names[$entry['name']] = $index;
      }
    }

    // add default entry
    array_push($known_accounts, array("name" => ts("enter new account"), "value"=>"/"));
    $this->assign("known_accounts", $known_accounts);

    // look up creditors
    $creditor_query = civicrm_api('SepaCreditor', 'get', array('version' => 3));
    $creditors = array();
    $creditor_currency = array();
    if (isset($creditor_query['is_error']) && $creditor_query['is_error']) {
      CRM_Core_Session::setStatus(ts("Couldn't find any creditors."), ts('Error'), 'error');
    } else {
      foreach ($creditor_query['values'] as $creditor_id => $creditor) {
        $creditors[$creditor_id] = $creditor['name'];
        $creditor_currency[$creditor_id] = $creditor['currency'];
      }
    }
    $this->assign('creditors', $creditors);
    $this->assign('creditor_currency', json_encode($creditor_currency));
    
    // add cycle_days per creditor
    $creditor2cycledays = array();
    foreach ($creditors as $creditor_id => $creditor_name) {
      $creditor2cycledays[$creditor_id] = CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $creditor_id);
    }
    $this->assign("creditor2cycledays", json_encode($creditor2cycledays));

    // all seems to be ok.
    $this->assign("submit_url", CRM_Utils_System::url('civicrm/sepa/cmandate'));

    // copy known parameters
    $copy_params = array('contact_id', 'creditor_id', 'total_amount', 'financial_type_id', 'campaign_id', 'source', 'note',
      'iban', 'bic', 'date', 'mandate_type', 'start_date', 'cycle_day', 'interval', 'end_date', 'reference');
    foreach ($copy_params as $parameter) {
      if (isset($_REQUEST[$parameter]))
        $this->assign($parameter, $_REQUEST[$parameter]);
    }

    // set default creditor, if not provided
    if (empty($_REQUEST['creditor_id'])) {
      $default_creditor = CRM_Sepa_Logic_Settings::defaultCreditor();
      if ($default_creditor != NULL) {
        $this->assign('creditor_id', $default_creditor->id);
      }
    }

    $default_mandate_type = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', 'default_mandate_type');
    if ($default_mandate_type && in_array($default_mandate_type, array('OOFF', 'RCUR'))) {
      $this->assign('mandate_type', isset($_REQUEST['mandate_type']) ? $_REQUEST['mandate_type'] : $default_mandate_type);
    }
  }



  /**
   * Will prepare the form by cloning the data from the given mandate
   */
  function prepareClonedData($mandate_id) {
    $mandate = civicrm_api('SepaMandate', 'getsingle', array('id'=>$mandate_id, 'version'=>3));
    if (isset($mandate['is_error']) && $mandate['is_error']) {
      CRM_Core_Session::setStatus(sprintf(ts("Couldn't load mandate #%s"), $mandate_id), ts('Error'), 'error');
      return;
    } 

    // prepare the form
    $this->prepareCreateForm($mandate['contact_id']);

    // load the attached contribution
    if ($mandate['entity_table']=='civicrm_contribution') {
      $contribution = civicrm_api('Contribution', 'getsingle', array('id'=>$mandate['entity_id'], 'version'=>3));
    } else if ($mandate['entity_table']=='civicrm_contribution_recur') {
      $contribution = civicrm_api('ContributionRecur', 'getsingle', array('id'=>$mandate['entity_id'], 'version'=>3));
    } else {
      CRM_Core_Session::setStatus(sprintf(ts("Mandate #%s seems to be broken!"), $mandate_id), ts('Error'), 'error');
      $contribution = array();
    }

    if (isset($contribution['is_error']) && $contribution['is_error']) {
      CRM_Core_Session::setStatus(sprintf(ts("Couldn't load associated (r)contribution #%s"), $contribution), ts('Error'), 'error');
      return;
    } 

    // set all the relevant values
    $this->assign('iban', $mandate['iban']);
    $this->assign('bic', $mandate['bic']);
    $this->assign('financial_type_id', $contribution['financial_type_id']);
    $this->assign('mandate_type', $mandate['type']);
    $this->assign('source', CRM_Utils_Array::value('source', $mandate));
    $this->assign('creditor_id', $mandate['creditor_id']);
    if (isset($contribution['campaign_id'])) $this->assign('campaign_id', $contribution['campaign_id']);
    if (isset($contribution['contribution_campaign_id'])) $this->assign('campaign_id', $contribution['contribution_campaign_id']);
    if (isset($contribution['source'])) $this->assign('source', $contribution['source']);

    if (isset($contribution['total_amount'])) {
      // this is a contribution
      $this->assign('total_amount', $contribution['total_amount']);
      $this->assign('date', date('Y-m-d', strtotime($contribution['receive_date'])));
    } else {
      // this has to be a recurring contribution
      $this->assign('total_amount', $contribution['amount']);
      $this->assign('start_date', date('Y-m-d', strtotime($contribution['start_date'])));
      $this->assign('cycle_day', $contribution['cycle_day']);
      $this->assign('interval', $contribution['frequency_interval']);
      if (isset($contribution['end_date']) && $contribution['end_date']) {
        // only set end date, if it's in the future (to prevent accidentally creating completed mandates)
        if (strtotime($contribution['end_date']) > strtotime("today") ) {
          $this->assign('end_date', date('Y-m-d', strtotime($contribution['end_date'])));
        } 
      } else {
        $this->assign('end_date', '');
      }
    }
  }

  /**
   * Will checks all the POSTed data with respect to creating a mandate
   *
   * @return array('<field_id>' => '<error message>') with the fields that have not passed
   */
  function validateParameters() {
    $errors = array();

    // check amount
    if (!isset($_REQUEST['total_amount'])) {
      $errors['total_amount'] = sprintf(ts("'%s' is a required field."), ts("Amount"));
    } else {
      $_REQUEST['total_amount'] = str_replace(',', '.', $_REQUEST['total_amount']);
      if (strlen($_REQUEST['total_amount']) == 0) {
        $errors['total_amount'] = sprintf(ts("'%s' is a required field."), ts("Amount"));
      } elseif (!is_numeric($_REQUEST['total_amount'])) {
        $errors['total_amount'] = ts("Cannot parse amount");
      } elseif ($_REQUEST['total_amount'] <= 0) {
        $errors['total_amount'] = ts("Amount has to be positive");
      }
    }

    // check BIC
    if (!isset($_REQUEST['bic'])) {
      $errors['bic'] = sprintf(ts("'%s' is a required field."), "BIC");
    } else {
      $_REQUEST['bic'] = strtoupper($_REQUEST['bic']);
      if (strlen($_REQUEST['bic']) == 0) {
        $errors['bic'] = sprintf(ts("'%s' is a required field."), "BIC");
      } else {
        $bic_error = CRM_Sepa_Logic_Verification::verifyBIC($_REQUEST['bic']);
        if (!empty($bic_error)) {
          $errors['bic'] = $bic_error;
        }
      }
    }

    // check IBAN
    if (!isset($_REQUEST['iban'])) {
      $errors['iban'] = sprintf(ts("'%s' is a required field."), "IBAN");
    } else {
      if (strlen($_REQUEST['iban']) == 0) {
        $errors['iban'] = sprintf(ts("'%s' is a required field."), "IBAN");
      } else {
        $iban_error = CRM_Sepa_Logic_Verification::verifyIBAN($_REQUEST['iban']);
        if (!empty($iban_error)) {
          $errors['iban'] = $iban_error;
        }
      }
    }

    // check reference
    if (!empty($_REQUEST['reference'])) {
      // check if it is formally correct
      if (!preg_match("/^[A-Z0-9\\-]{4,35}$/", $_REQUEST['reference'])) {
        $errors['reference'] = ts("Reference has to be an upper case alphanumeric string between 4 and 35 characters long.");
      } else {
        // check if the reference is taken
        $count = civicrm_api3('SepaMandate', 'getcount', array("reference" => $_REQUEST['reference']));        
        if ($count > 0) {
          $errors['reference'] = ts("This reference is already in use.");
        }
      }
    }

    // check date fields
    if ($_REQUEST['mandate_type']=='OOFF') {
      if (!$this->_check_date('date'))
        $errors['date'] = ts("Incorrect date format");
    } elseif ($_REQUEST['mandate_type']=='RCUR') {
      if (!$this->_check_date('start_date'))
        $errors['start_date'] = ts("Incorrect date format");
      if (isset($_REQUEST['end_date']) && strlen($_REQUEST['end_date'])) {
        if (!$this->_check_date('end_date')) {
          $errors['end_date'] = ts("Incorrect date format");
        } else {
          // check if end_date AFTER start_date (#341)
          if (!isset($errors['start_date']) && ($_REQUEST['end_date'] < $_REQUEST['start_date']))
            $errors['end_date'] = ts("End date cannot be earlier than start date.");
        }
      }
    }

    // check replace fields
    if (isset($_REQUEST['replace'])) {
      if (!$this->_check_date('replace_date'))
        $errors['replace_date'] = ts("Incorrect date format");

      if (!isset($_REQUEST['replace_reason']) || strlen($_REQUEST['replace_reason']) == 0) {
        $errors['replace_reason'] = sprintf(ts("'%s' is a required field."), ts("replace reason"));
      }
    }

    return $errors;
  }

  function _check_date($date_field) {
    if (!isset($_REQUEST[$date_field])) {
      return false;
    } else {
      $parsed_date = date_parse_from_format('Y-m-d', $_REQUEST[$date_field]);
      if ($parsed_date['errors']) {
        return false;
      } else {
        return true;
      }
    }
  }

  /**
   * test if this page is called as a popup
   */
  protected function isPopup() {
    return CRM_Utils_Array::value('snippet', $_REQUEST);
  }

  /**
   * report error data
   */
  protected function processError($status, $title, $message, $contact_id) {
    CRM_Core_Session::setStatus($status . "<br/>" . $message, ts('Error'), 'error');
    $this->assign("error_title",   $title);
    $this->assign("error_message", $message);

    if (!$this->isPopup()) {
      $contact_url = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$contact_id}&selectedChild=contribute");
      CRM_Utils_System::redirect($contact_url);
    }   
  }
}
