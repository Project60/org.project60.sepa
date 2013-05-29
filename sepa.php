<?php
require_once 'sepa.civix.php';
require_once 'hooks.php';

function sepa_civicrm_pageRun( &$page ) {
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
    'template' => 'CRM/Contribute/Page/ContributionRecur.page-body.tpl'
  ));
}

function sepa_civicrm_buildForm ( $formName, &$form ){
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
//    $e=$form->getElement('bank_iban');
//    $e->setValue($mandate["iban"]);
    CRM_Core_Region::instance('page-body')->add(array(
      'template' => 'CRM/Sepa/Form/SepaMandate.tpl'
     ));
  }
}

function sepa_civicrm_postProcess( $formName, &$form ) {
  $fieldMapping = array ("bank_iban"=>"iban",'bank_bic'=>"bic","sepa_active"=>"is_enabled");
  $newMandate = array();

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

  return _sepa_civix_civicrm_install();
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
