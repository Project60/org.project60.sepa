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
    return sepa_pageRun_contribute( &$page );
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
  $form->removeElement('is_recur'); // force recurring contrib
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
  if ("CRM_Admin_Form_PaymentProcessor" == $formName) {
    $form->add('text', 'creditor_name', ts('Organisation Name'));
    $form->add('textarea', 'creditor_address', ts('Address'), array('cols' => '60', 'rows' => '3'));
    $form->add('checkbox', 'mandate_active', ts('Mandate created are active by default?'));
    $form->add( 'text', 'creditor_prefix',  ts('Mandate Prefix'))->setValue($mandate["iban"]);
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
    _sepa_buildForm_Contribution_Main ($formName, &$form );
    return;
  }

  if ("CRM_Contribute_Form_Contribution_ThankYou" == $formName && array_key_exists("bank_iban",$form->_params)) {
    $form->assign("iban",$form->_params["bank_iban"]);
    $form->assign("bic",$form->_params["bank_bic"]);
    CRM_Core_Region::instance('contribution-thankyou-billing-block')->add(array(
      'template' => 'Sepa/Contribute/Form/Contribution/ThankYou.tpl'));
  }

  if ("CRM_Contribute_Form_Contribution" == $formName) { 
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
    //TODO, add in the form, as a region?
    $e=$form->add( 'checkbox', 'sepa_active',  ts('Active mandate'))->setValue($mandate["is_enabled"]);
    $e=$form->add( 'text', 'bank_bic',  ts('BIC'))->setValue($mandate["bic"]);
    $form->add( 'text', 'bank_iban',  ts('IBAN'))->setValue($mandate["iban"]);
    CRM_Core_Region::instance('page-body')->add(array(
      'template' => 'CRM/Sepa/Form/SepaMandate.tpl'
     ));
  }
}

function sepa_civicrm_postProcess( $formName, &$form ) {
  $fieldMapping = array ("bank_iban"=>"iban",'bank_bic'=>"bic","sepa_active"=>"is_enabled");
  $newMandate = array();

  if ("CRM_Admin_Form_PaymentProcessor" == $formName) {
    $values=$form->getVar("_values");
    if ($values["class_name"]!="Payment_SEPA_DD") return;
print_r(    $values = $form->controller->exportValues($form->getVar("_name")));
//print_r($values);
//die ("aaa");
  }
  if ("CRM_Contribute_Form_UpdateSubscription" == $formName && $form->_paymentProcessor["class_name"] == "Payment_SEPA_DD") {
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
//TODO pdelbar:process properly (eg. update the first contribution linked to the recurring contrib...
    if ($newMandate) {
      $newMandate["id"]=$mandate["id"];
      //not strictly needed, uncomment if proven handy in the underlying api/bao
      //$newMandate["entity_id"]=$mandate["entity_id"];
      //$newMandate["entity_table"]=$mandate["entity_table"];
      $newMandate["version"] = 3;
      $mandate = civicrm_api("SepaMandate","create",$newMandate);
      if ($mandate["is_error"]) {
        CRM_Core_Error::fatal($r["error_message"]);
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
    if ($result['is_error']) {
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
    } else
      $group_id = $result['id'];

    if (is_array($group['values'])) {
      $groupValues = $group['values'];
      $weight = 1;
      foreach ($groupValues as $valueName => $value) {
        $result = civicrm_api('option_value', 'getsingle', array('version' => 3, 'name' => $valueName));
        if ($result['is_error']) {
          $params = array(
              'version' => 3,
              'sequential' => 1,
              'option_group_id' => $group_id,
              'name' => $valueName,
              'label' => $value['label'],
              'value' => $value['value'],
              'weight' => $weight,
              'is_default' => $value['is_default'],
              'is_active' => 1,
          );
          $result = civicrm_api('option_value', 'create', $params);
        } else {
          $weight = $result['weight'] + 1;
        }
      }
    }
  }
}

function sepa_civicrm_options() {
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
      'payment_instrument' => array(
          'values' => array(
              'SEPA DD' => array(
                  'label' => 'SEPA DD',
                  'value' => 9000,
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
