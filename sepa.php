<?php
require_once 'sepa.civix.php';
require_once 'hooks.php';

function sepa_pageRun_contribute( &$page ) {
/*
  $recur = $page->getTemplate()->get_template_vars("contribution_recur_id");
  CRM_Core_Region::instance('page-body')->add(array(
    'markup' => "Should we mention special steps to update/alter the contribution, eg if part of a batch already"
  ));
*/
}

function sepa_civicrm_pageRun( &$page ) {
  if (get_class($page) == "CRM_Contribute_Page_Tab") {
    return sepa_pageRun_contribute( $page );
  }
  if ( get_class($page) != "CRM_Contribute_Page_ContributionRecur")
    return;
  $recur = $page->getTemplate()->get_template_vars("recur");

  $pp = civicrm_api('PaymentProcessor', 'getsingle', 
    array('version' => 3, 'sequential' => 1, 'id' => $recur["payment_processor_id"]));
  if ("Payment_SEPA_DD" !=  $pp["class_name"])
    return;
  $mandate = civicrm_api("SepaMandate","getsingle",array("version"=>3, "entity_id"=>$recur["id"]));
  if (!array_key_exists("id",$mandate)) {
      CRM_Core_Error::fatal(ts("Can't find the sepa mandate"));
  }
  $page->assign("sepa",$mandate);
  CRM_Core_Region::instance('page-body')->add(array(
    'template' => 'Sepa/Contribute/Page/ContributionRecur.tpl'
  ));
}

function _sepa_buildForm_Contribution_Main ($formName, &$form ){
  $pp= civicrm_api("PaymentProcessor","getsingle"
    ,array("version"=>3,"id"=>$form->_values["payment_processor"]));
  if("Payment_SEPA_DD" != $pp["class_name"])
    return;
  //$form->getElement('is_recur')->setValue(1); // recurring contrib as an option
  if (isset($form->_elementIndex['is_recur'])) {
    $form->removeElement('is_recur'); // force recurring contrib
  }
  $form->addElement('hidden','is_recur',1);
  //workaround the notice message, as ContributionBase assumes these fields exist in the confirm step
  foreach (array("account_holder","bank_identification_number","bank_name","bank_account_number") as $field){
    $form->addElement("hidden",$field);
  }
$js= <<<'EOD'
cj(function($) {
 $('#bank_iban,#bank_bic').keyup(function() {
   this.value = this.value.toUpperCase();
 });
});
EOD;
  CRM_Core_Region::instance('page-header')->add(array('script' => $js));
}

function sepa_civicrm_buildForm ( $formName, &$form ){
  $tag = str_replace('_', '', $formName);
  if (stream_resolve_include_path('CRM/Sepa/Hooks/'.$tag.'.php')) {
    $className = 'CRM_Sepa_Hooks_' . $tag;
    if (class_exists($className)) {
      if (method_exists($className, 'buildForm')) {
        CRM_Sepa_Logic_Base::debug(ts('Calling SEPA Hook '), $className . '::buildForm', 'alert');
        $className::buildForm($form);
      }
    }
  }

  if ("CRM_Admin_Form_PaymentProcessor" == $formName) {
    $pp=civicrm_api("PaymentProcessorType","getsingle",array("id"=>$form->_ppType, "version"=>3));
    if("Payment_SEPA_DD" != $pp["class_name"])
      return;
    $form->add('text', 'creditor_name', ts('Organisation Name'));
    $form->addRule("creditor_name", ts('%1 is a required field.', array(1 => ts('Organisation Name'))), 'required');

    $form->add('textarea', 'creditor_address', ts('Address'), array('cols' => '60', 'rows' => '3'));
    $form->add('checkbox', 'mandate_active', ts('Activate new mandates directly when submitted'));
    $form->add( 'text', 'creditor_prefix',  ts('Mandate Prefix'));
    $form->add( 'text', 'creditor_contact_id',  ts('Contact ID'));
    $form->add( 'text', 'creditor_bic',  ts('BIC'),"size=11 maxlength=11");
    $form->addElement( 'text', 'creditor_iban',  ts('IBAN'),array("size"=>34,"maxlength"=>34));
    $form->addRule("creditor_contact_id", ts('%1 must be a number', array(1 => ts('Contact ID'))),'numeric');
    $form->add( 'hidden', 'creditor_id');
    $form->addRule("creditor_prefix", ts('%1 is a required field.', array(1 => ts('Mandate Prefix'))), 'required');

    $fileFormatOptions = array();
    $fileFormats = CRM_Core_PseudoConstant::get('CRM_Sepa_DAO_SEPACreditor', 'sepa_file_format_id', array('localize' => TRUE));
    foreach ($fileFormats as $key => $var) {
      $fileFormatOptions[$key] = $form->createElement('radio', NULL,
        ts('SEPA File Format'), $var, $key,
        array('id' => "civicrm_sepa_file_format_{$var}_{$key}")
      );
    }
    $form->addGroup($fileFormatOptions, 'sepa_file_format_id', ts('SEPA File Format'));

    // get the creditor info as well
    $ppid=$form->getVar("_id");
    if (isset($ppid)) {
      $cred = civicrm_api3("SepaCreditor","get",array("sequential"=>1,"payment_processor_id"=>$ppid));
    }
    if (isset($ppid) && $cred['count']) {
      $cred = $cred["values"][0];
      $form->setDefaults(array(
        "creditor_id"=>$cred["id"],
        "creditor_name"=>$cred["name"],
        "creditor_contact_id"=>$cred["creditor_id"],
        "creditor_address"=>$cred["address"],
        "mandate_active"=>$cred["mandate_active"],
        "creditor_prefix"=>$cred["mandate_prefix"],
        "creditor_iban"=>$cred["iban"],
        "creditor_bic"=>$cred["bic"],
        "sepa_file_format_id"=>$cred["sepa_file_format_id"],
      ));
    } else {
      $session = CRM_Core_Session::singleton();
      $form->setDefaults(array("creditor_prefix"=>"SEPA","creditor_contact_id"=>$session->get('userID'),"sepa_file_format_id"=>CRM_Core_OptionGroup::getDefaultValue('sepa_file_format')));
    }
    CRM_Core_Region::instance('page-body')->add(array(
      'template' => 'Sepa/Admin/Form/PaymentProcessor.tpl'
    ));
  }

  if ("CRM_Contribute_Form_Contribution_Confirm" == $formName && array_key_exists("bank_iban",$form->_params) ) {
    require_once("packages/php-iban-1.4.0/php-iban.php");
    $form->assign("iban",iban_to_human_format($form->_params["bank_iban"]));
    $form->assign("bic",$form->_params["bank_bic"]);
    CRM_Core_Region::instance('contribution-confirm-billing-block')->add(array(
      'template' => 'Sepa/Contribute/Form/Contribution/Confirm.tpl'));
  };
  if ("CRM_Contribute_Form_Contribution_Main" == $formName) { 
    _sepa_buildForm_Contribution_Main ($formName, $form );
    return;
  }

  if ("CRM_Contribute_Form_Contribution_ThankYou" == $formName && array_key_exists("bank_iban",$form->_params)) {
    $form->assign("iban",$form->_params["bank_iban"]);
    $form->assign("bic",$form->_params["bank_bic"]);
    CRM_Core_Region::instance('contribution-thankyou-billing-block')->add(array(
      'template' => 'Sepa/Contribute/Form/Contribution/ThankYou.tpl'));
  }

  if (false && "CRM_Contribute_Form_Contribution" == $formName) { //TODO remove definitely if we don't do anything with it
    //should we be able to set the mandate info from the contribution?
    if (!array_key_exists("contribution_recur_id",$form->_values))
      return;
    $id=$form->_values['contribution_recur_id'];
    $mandate = civicrm_api("SepaMandate","getsingle",array("version"=>3, "entity_id"=>$id));
    if (!array_key_exists("id",$mandate))
      return;
    //TODO, add in the form? link to something else?
  }

  if ("CRM_Contribute_Form_UpdateSubscription" == $formName && $form->_paymentProcessor["class_name"] == "Payment_SEPA_DD") {
    $id= $form->getVar( '_crid' );
    $mandate = civicrm_api("SepaMandate","getsingle",array("version"=>3, "entity_id"=>$id));
    if (!array_key_exists("id",$mandate))
      return;
    if (!$form->getVar("_subscriptionDetails")->installments) {
      $form->getElement('installments')->setValue(0);//by default, sepa is without end date
    }
    $form->getElement('is_notify')->setValue(0); // the notification isn't clear, disable it
    $form->assign($mandate);
    //TODO, add in the form, as a region?
    $form->add( 'checkbox', 'sepa_active',  ts('Active mandate'))->setValue($mandate["is_enabled"]);
    $form->add( 'text', 'bank_bic',  ts('BIC'),"size=11 maxlength=11")->setValue($mandate["bic"]);
    $form->addElement( 'text', 'bank_iban',  ts('IBAN'),array("size"=>34,"maxlength"=>34))->setValue($mandate["iban"]);
    CRM_Core_Region::instance('page-body')->add(array(
      'template' => 'CRM/Sepa/Form/SepaMandate.tpl'
     ));
  }
  
}




function sepa_civicrm_postProcess( $formName, &$form ) {
  $tag = str_replace('_', '', $formName);
  if (stream_resolve_include_path('CRM/Sepa/Hooks/'.$tag.'.php')) {
    $className = 'CRM_Sepa_Hooks_' . $tag;
    if (class_exists($className)) {
      if (method_exists($className, 'postProcess')) {
        CRM_Sepa_Logic_Base::debug(ts('Calling SEPA Hook '), $className . '::postProcess', 'alert');
        $className::postProcess($form);
      }
    }
  }
 
  if ("CRM_Admin_Form_PaymentProcessor" == $formName) {
    $ppType = civicrm_api3('PaymentProcessorType', 'getsingle', array('id' => $form->_ppType));
    if ($ppType["class_name"]!="Payment_SEPA_DD") return;
    $paymentProcessor = civicrm_api3('PaymentProcessor', 'getsingle', array('name' => $form->_submitValues['name'], 'is_test' => 0));
    $creditor = array ("version"=>3,"payment_processor_id"=>$paymentProcessor['id']);
    foreach (array("user_name"=>"identifier","creditor_name"=>"name","creditor_id"=>"id","creditor_address"=>"address","creditor_prefix"=>"mandate_prefix","creditor_contact_id"=>"creditor_id","creditor_iban"=>"iban","creditor_bic"=>"bic","sepa_file_format_id"=>"sepa_file_format_id") as $field => $api) {
      $creditor[$api] = $form->_submitValues[$field];
    }
    $creditor['mandate_active'] = isset($form->_submitValues['mandate_active']);
    if (!$creditor["id"]) {
      unset($creditor["id"]);
    } 
    $r= civicrm_api("SepaCreditor","create",$creditor);
    if ($r["is_error"]) {
      CRM_Core_Session::setStatus($r["error_message"], ts("SEPA Creditor"), "error");
    } else {
     CRM_Core_Session::setStatus("created new creditor ".$r["id"], ts("SEPA Creditor"), "info");
    }
//CRM_Admin_Form_PaymentProcessor
  }
  if ("CRM_Contribute_Form_UpdateSubscription" == $formName && $form->_paymentProcessor["class_name"] == "Payment_SEPA_DD") {
    $fieldMapping = array ("bank_iban"=>"iban",'bank_bic'=>"bic","sepa_active"=>"is_enabled");
    $newMandate = array();
   $id= $form->getVar( '_crid' );
    $mandate = civicrm_api("SepaMandate","getsingle",array("version"=>3, "entity_table"=>"civicrm_contribution_recur","entity_id"=>$id));
    if (!array_key_exists("id",$mandate))
      return;
    if (!array_key_exists("is_enabled",$mandate)) {
      $mandate["is_enabled"] = false;
    }
    if (!array_key_exists("sepa_active",$form->_submitValues)) {
      $form->_submitValues["sepa_active"] = false;
    }
    foreach ($fieldMapping as $field => $api) {
      if($mandate[$api] !=$form->_submitValues[$field]) {
        $newMandate[$api] = $form->_submitValues[$field];
      }
    }
    if ($newMandate) {
      $newMandate["id"]=$mandate["id"];
      //not strictly needed, uncomment if proven handy in the underlying api/bao
      //$newMandate["entity_id"]=$mandate["entity_id"];
      //$newMandate["entity_table"]=$mandate["entity_table"];
      $newMandate["version"] = 3;
      $mandate = civicrm_api("SepaMandate","create",$newMandate);
      if ($mandate["is_error"]) {
        CRM_Core_Error::fatal($mandate["error_message"]);
      }
    }
  }
  
}

/**
 * Implementation of hook_civicrm_config
 */
function sepa_civicrm_config(&$config) {
/*
when civi 4.4, not sure how to make it compatible with both
CRM_Core_DAO_AllCoreTables::$daoToClass["SepaMandate"] = "CRM_Sepa_DAO_SEPAMandate";
CRM_Core_DAO_AllCoreTables::$daoToClass["SepaCreditor"] = "CRM_Sepa_DAO_SEPACreditor";
*/ 
  _sepa_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function sepa_civicrm_xmlMenu(&$files) {
  _sepa_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function sepa_civicrm_install() {
  $config = CRM_Core_Config::singleton();
  //create the tables
  $sql = file_get_contents(dirname( __FILE__ ) .'/sql/sepa.sql', true);
  CRM_Utils_File::sourceSQLFile($config->dsn, $sql, NULL, true);

    //add the required option groups
  sepa_civicrm_install_options(sepa_civicrm_options());

  return _sepa_civix_civicrm_install();
}


function sepa_civicrm_install_options($data) {
  foreach ($data as $groupName => $group) {
    // check group existence
    $result = civicrm_api('option_group', 'getsingle', array('version' => 3, 'name' => $groupName));
    if (isset($result['is_error']) && $result['is_error']) {
      $params = array(
          'version' => 3,
          'sequential' => 1,
          'name' => $groupName,
          'is_reserved' => 1,
          'is_active' => 1,
          'title' => $group['title'],
          'description' => $group['description'],
      );
      $result = civicrm_api('option_group', 'create', $params);
      $group_id = $result['values'][0]['id'];
    } else {
      $group_id = $result['id'];
    }

    if (is_array($group['values'])) {
      $groupValues = $group['values'];
      $weight = 1;
      foreach ($groupValues as $valueName => $value) {
        $result = civicrm_api('option_value', 'getsingle', array('version' => 3, 'name' => $valueName));
        if (isset($result['is_error']) && $result['is_error']) {
          $params = array(
              'version' => 3,
              'sequential' => 1,
              'option_group_id' => $group_id,
              'name' => $valueName,
              'label' => $value['label'],
              'weight' => $weight,
              'is_default' => $value['is_default'],
              'is_active' => 1,
          );
          if (isset($value['value'])) {
            $params['value'] = $value['value'];
          }
          $result = civicrm_api('option_value', 'create', $params);
        } else {
          $weight = $result['weight'] + 1;
        }
      }
    }
  }
}

function sepa_civicrm_options() {
  $result = civicrm_api('option_group', 'getsingle', array('version' => 3, 'name' => 'payment_instrument'));
  if (!isset($result['id']))
    die ($result["error_message"]);
  $gid= $result['id'];
  //find the value to give to the payment instruments
  $query = "SELECT max( `weight` ) as weight FROM `civicrm_option_value` where option_group_id=" . $gid;
  $dao = new CRM_Core_DAO();
  $dao->query($query);
  $dao->fetch();
  $weight = $dao->weight + 1;

  // start with the lowest weight value
  return array(
      'msg_tpl_workflow_contribution' => array(
          'values' => array(
              'sepa_mandate_pdf' => array(
                  'label' => 'PDF Mandate',
                  'value' => 1,
                  'is_default' => 0,
              ),
              'sepa_mandate' => array(
                  'label' => 'Mail Sepa Mandate',
                  'value' => 1,
                  'is_default' => 0,
              ),
          ),
       ),
      
      // These will be used to mark a contribution with the correct type and will
      // greatly facilitate batching later on
      
      'payment_instrument' => array(
          'values' => array(
              'FRST' => array(
                  'label' => 'SEPA DD First Transaction',
                  'weight' => $weight,
                  'is_default' => 0,
              ),
              'RCUR' => array(
                  'label' => 'SEPA DD Recurring Transaction',
                  'weight' => $weight+1,
                  'is_default' => 0,
              ),
              'OOFF' => array(
                  'label' => 'SEPA DD One-off Transaction',
                  'weight' => $weight+2,
                  'is_default' => 0,
              ),
          ),
      ),
  );
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function sepa_civicrm_uninstall() {
  //should we delete the tables?
  return _sepa_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function sepa_civicrm_enable() {
  return _sepa_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function sepa_civicrm_disable() {
  return _sepa_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function sepa_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _sepa_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function sepa_civicrm_managed(&$entities) {
  return _sepa_civix_civicrm_managed($entities);
}
