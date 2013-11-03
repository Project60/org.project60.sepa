<?php

require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_Testrig extends CRM_Core_Page {

  function run() {
    CRM_Core_Resources::singleton()
            ->addScriptFile('civicrm', 'packages/backbone/underscore.js', 110, 'html-header', FALSE);

    $action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';
    $methodName = 'do' . ucfirst($action);
    if (method_exists('CRM_Sepa_Page_Testrig', $methodName)) {
      self::$methodName();
    }

    self::showme();
    /*
      $r = civicrm_api("SepaTransactionGroup","getdetail",array("version"=>3,"sequential"=>1,
      'options' => array(
      'sort' => 'created_date DESC',
      'limit' => 1,
      ),
      ));
      $this->assign("groups",$r["values"]);
     */
    parent::run();
  }

  public static function doZap() {
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_contribution");
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_contribution_recur");
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_sdd_mandate");
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_sdd_contribution_txgroup");
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_sdd_txgroup");
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_sdd_file");
  }
      
  public static function doDummyCreditor() {
    $params = array(
        'creditor_id' => 1,
        'identifier' => 'ISO99ZZZ1234567890',
        'name' => 'DUMMY CREDITOR',
        'address' => 'Some address',
        'country_id' => 1020,
        'iban' => 'DUMMY IBAN',
        'bic' => 'DUMMYBIC',
        'mandate_prefix' => 'DUMM',
        'payment_processor_id' => 1,
        'category' => '',
        'tag' => 'DUMMY',
    );
    $r = civicrm_api3( 'sepa_creditor','create',$params);
    if(!$r['is_error']) {
      echo 'Created the dummy creditor.';
      return;
    }
    echo '<pre>';print_r($r);echo '</pre>';
  }
  
  
  public static function doNewMandate() {
    $ref = strtoupper(md5(date('YmdHis')));

    $status = isset($_REQUEST['status']) ? trim($_REQUEST['status']) : '';
    $contract = isset($_REQUEST['contract']) ? trim($_REQUEST['contract']) : '';
    
    $mparams = array(
        "reference" => "M-" . $ref,
        "source" => "Testrig",
        "entity_table" => "civicrm_contribution_recur",
        "entity_id" => "1",
        "date" => date("Y-m-d H:i:s"),
        "creditor_id" => "1",
        "contact_id" => "1",
        "iban" => "IBAN",
        "bic" => "BIC",
        "type" => "RCUR",
        "status" => $status,
        "creation_date" => date("Y-m-d H:i:s"),
        );
    switch ($status) {
      case 'INIT':
        break;
      default :
        $mparams[ 'validation_date' ] = date("Y-m-d H:i:s");
    }
    
    switch ($contract) {
      case 'none' :
        break;
      case 'rc0' :
        echo 'Creating recurring contribution without a contribution ...';
        $rcontrib = self::createRecurringContrib($ref);
        $mparams['entity_table'] = 'civicrm_contribution_recur';
        $mparams['entity_id'] = $rcontrib['id'];
        break;
      case 'rc1' :
        $rcontrib = self::createRecurringContrib($ref);
        $mparams['entity_table'] = 'civicrm_contribution_recur';
        $mparams['entity_id'] = $rcontrib['id'];
        $contrib = self::createContrib($rcontrib,'FRST');
        break;
      case 'running' :
        $rcontrib = self::createRecurringContrib($ref);
        $mparams['entity_table'] = 'civicrm_contribution_recur';
        $mparams['entity_id'] = $rcontrib['id'];
        $contrib = self::createContrib($rcontrib,'RCUR');
        $status = 'RCUR';
        break;
    }
    
    $r = civicrm_api3( 'sepa_mandate','create',$mparams);
    if(!$r['is_error']) {
      echo '<br/>Successfully created mandate #', $r['id'], ' M-' . $ref;
      return;
    }
    echo '<pre>';print_r($r);echo '</pre>';
  }
  
  
  private static function createRecurringContrib($ref) {
    $pi_rcur = CRM_Core_OptionGroup::getValue('payment_instrument', 'RCUR', 'name', 'String', 'value');
    
    $params = array(
      'contact_id' => 1,
      'frequency_interval' => '1',
      'frequency_unit' => 'day',
      'amount' => '5',
      'contribution_status_id' => 1,
      'start_date' => date("Y-m-d H:i:s"),
      'currency' => 'EUR',
      'payment_instrument_id' => $pi_rcur,
      'trxn_id' => 'RTX-' . $ref,
    );
    $r = civicrm_api3('contribution_recur', 'create', $params);
    if(!$r['is_error']) {
      echo '<br/>Successfully created recurring contribution #', $r['id'], ' RTX-' . $ref;
      return $r['values'][ $r['id'] ];
    }
    echo '<pre>';print_r($r);echo '</pre>';
    return false;
  }

  
  private static function createContrib($rcontrib,$type) {
    $pi = CRM_Core_OptionGroup::getValue('payment_instrument', $type, 'name', 'String', 'value');
    
    $params = array(
      'contact_id' => 1,
      'receive_date' => date("Y-m-d H:i:s"),
      'total_amount' => '5',
      'financial_type_id' => 1,
      'trxn_id' => 'TX-'.$rcontrib['trxn_id'],
      'invoice_id' => 'IX-'.$rcontrib['trxn_id'],
      'source' => 'TEST',
      'contribution_status_id' => 2,
      'contribution_recur_id' => $rcontrib['id'],
      'payment_instrument_id' => $pi,
    );
    $r = civicrm_api3('contribution', 'create', $params);
    if(!$r['is_error']) {
      echo '<br/>Successfully created contribution #', $r['id'], ' TX-' . $rcontrib['trxn_id'];
      return $r['values'][ $r['id'] ];
    }
    echo '<pre>';print_r($r);echo '</pre>';
    return false;
  }

  public static function showme() {
    $files = array();
    $dao = CRM_Core_DAO::executeQuery("SELECT * FROM civicrm_sdd_file");
    while ($dao->fetch()) $files[ $dao->latest_submission_date ] = $dao->toArray();

    $txgs = array();
    $dao = CRM_Core_DAO::executeQuery("SELECT * FROM civicrm_sdd_txgroup");
    while ($dao->fetch()) $txgs[ $dao->sdd_file_id ][] = $dao->toArray();
    
    $contribs = array();
    $dao = CRM_Core_DAO::executeQuery("SELECT *, ctxg.id as id FROM civicrm_sdd_contribution_txgroup ctxg JOIN civicrm_contribution c ON c.id = ctxg.contribution_id ");
    while ($dao->fetch()) $contribs[ $dao->txgroup_id ][] = $dao->toArray();
    
    echo '<ul id="sdd">';
    foreach ($files as $filedate => $file) {
      $cls = $file['status_id'] == 1 ? "sddfileopen" : "sddfileclosed";
      echo '<li class="' . $cls . '"><ul>';
        echo '<span>', $file['reference'], '</span>';
        echo ' - latest submission date <b>', substr($file['latest_submission_date'],0,10) . '</b>';
        echo ' - tag <b>', $file['tag'] . '</b>';
        $gs = $txgs[ $file['id'] ];
        echo ' (', count($gs), ' txgroups)';
        foreach ($gs as $txgdate => $txg) {
          $cls = $txg['status_id'] == 1 ? "txgopen" : "txgclosed";
          echo '<li class="' . $cls . '"><ul>';
          echo '<span>', $txg['reference'], '</span>';
          echo ' - collection date <b>', substr($txg['collection_date'],0,10) . '</b>';
            $cons = $contribs[ $txg['id'] ];
            echo ' (', count($cons), ' transactions)';
            foreach ($cons as $contrib) {
              echo '<li>';
              echo '<span>', '#', $contrib['contribution_id'], '</span>';
              echo ' - ' . $contrib['receive_date'];
              echo ' - ' . $contrib['total_amount'] . ' ' . $contrib['currency'];
              echo '</li>';
            }
          echo '</ul></li>';
        }
      echo '</ul></li>';
    }
    echo '</ul>';
  }
  
  function getTemplateFileName() {
    return "CRM/Sepa/Page/Testrig.tpl";
  }

}
