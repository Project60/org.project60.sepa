<?php

/**
 * Sepa Direct Debit Batching Logic
 * Author: Project60 
 * 
 * See Batching.md for more info (Seriously, read it. Now.)
 */

/**
 * This class contains the logic to create SDD batches on both levels.
 */
class CRM_Sepa_Logic_Batching extends CRM_Sepa_Logic_Base {

  public static function batch_initial_contribution($objectId, $objectRef) {
    self::debug('Running initial contribution batching process for mandate ' . $objectId);
    $mandate = new CRM_Sepa_BAO_SEPAMandate();
    $mandate->get('id', $objectId);

    // Get the first contribution for this mandate
    $contrib = $mandate->findContribution();

    self::batchContribution($contrib, $mandate);
  }

  /**
   * Batch a contribution into a TXG.
   * @param CRM_Sepa_BAO_SEPATransaction $bao
   */
  public static function batchContribution(CRM_Contribute_BAO_Contribution $contrib, CRM_Sepa_BAO_SEPAMandate $mandate) {
    self::debug('Batching contribution '. $contrib->id);

    // what are the criteria to find an existing suitable batch ?
    $payment_instrument_id = $contrib->payment_instrument_id;
    $params = array(
        'payment_instrument_id' => $payment_instrument_id,
        'sequential' => 1,
        'version' => 3,
    );
    $result = civicrm_api3('SepaCreditor', 'get', $params);
    if ($result['count'] == 0) {
      $result = civicrm_api('SepaCreditor', 'create', array("version"=>3,"identifier"=>"FIXME","name"=>"Workaround","payment_instrument_id" => $payment_instrument_id,));
      if ($result['is_error']) {
        CRM_Core_Error::fatal($result['error_message']);
        return null;
      }
    }
    $creditor_id = $result["values"][0]['creditor_id'];

    CRM_Sepa_Logic_Batching::batchContributionByCreditor ($contrib,$creditor_id);
}

  /**
   * Batch a contribution into a TXG.
   * @param CRM_Sepa_BAO_SEPATransaction $bao
   */
  public static function batchContributionByCreditor (CRM_Contribute_BAO_Contribution $contrib, $creditor_id,$payment_instrument_id,$type = null) {
    $receive_date = $contrib->receive_date;
    if (!$type)
      $type = self::getSDDType($contrib);

    // the range for batch collection date is [ this date - MAXPULL, this date + MAXPUSH ]
    $maxpull = 0;
    $maxpush = 0;
    $earliest_date = self::adjustBankDays($receive_date, - $maxpull);
    $latest_date = self::adjustBankDays($receive_date, $maxpush);


    // we have a query : look for an open batch in this date range and of the 
    // appropriate type and creditor
    $txGroup = self::findTxGroup($creditor_id, $type, $earliest_date, $latest_date);

    // if not found, create a nex batch 
    if ($txGroup === null) {
      $txGroup = self::createTxGroup($creditor_id, $type, $receive_date,$payment_instrument_id);
    }

    // now add the tx to teh batch
    self::addToTxGroup($contrib, $txGroup);
    return $txGroup;
  }

  /**
   * Find an appropriate civicrm_batch instance.
   * 
   * @param int $payment_instrument_id
   * @param string $type
   * @param date $earliest_date
   * @param date $latest_date
   * 
   * @return CRM_Sepa_BAO_SEPATransactionGroup or null
   */
  public static function findTxGroup($creditor_id, $type, $earliest_date, $latest_date) {
    CRM_Sepa_Logic_Base::debug("Locating suitable TXG : CRED=$creditor_id TYPE=$type RANGE=" . substr($earliest_date,0,10) . ' / ' . substr($latest_date,0,10));
    $openStatus = CRM_Core_OptionGroup::getValue('batch_status', 'Open', 'name', 'String', 'value');
    $query = "SELECT 
                id 
              FROM
                civicrm_sdd_txgroup
              WHERE         
                status_id = " . $openStatus . "
                AND
                sdd_creditor_id = " . (int) $creditor_id . "
                AND 
                collection_date >= '" . $earliest_date . "'
                AND 
                collection_date <= '" . $latest_date . "'
              ORDER BY 
                collection_date ASC
              LIMIT 1
              ";
    $dao = CRM_Core_DAO::executeQuery($query);
    if ($dao->fetch()) {
      $txgroup = new CRM_Sepa_BAO_SEPATransactionGroup();
      $txgroup->get('id', $dao->id);
      return $txgroup;
    }
    return null;
  }

  public static function createTxGroup($creditor_id, $type, $receive_date,$payment_instrument_id=9000) {
    CRM_Sepa_Logic_Base::debug("Creating new TXG CRED=$creditor_id TYPE=$type COLLDATE=" . substr($receive_date,0,10));
    // as per Batching.md
    // submission_date = latest( today, tx.collection_date - delay - 1 )
    $delay = ($type == 'RCUR') ? 2 : 5;
    $submission_date = self::adjustBankDays($receive_date, - $delay - 1);
    // effective_collection_date = submission_date + delay + 1
    $collection_date = self::adjustBankDays($receive_date, $delay + 1);

    $session = CRM_Core_Session::singleton();
    $reference = "TXG - $type - $collection_date";
    $params = array(
        'reference' => $reference,
        'type' => $type,
        'sdd_creditor_id' => $creditor_id,
        'status_id' => CRM_Core_OptionGroup::getValue('batch_status', 'Open', 'name', 'String', 'value'),
        'payment_instrument_id' => $payment_instrument_id,
        'collection_date' => $collection_date,
        'latest_submission_date' => $submission_date,
        'created_date' => date('Ymdhis'),
        'modified_date' => date('Ymdhis'),
        'created_id' => $session->get('userID'),
        'modified_id' => $session->get('userID'),
        'version' => 3,
    );
    $result = civicrm_api('SepaTransactionGroup', 'create', $params);
    if ($result['is_error']) {
      CRM_Core_Error::fatal($result["error_message"]);
      return null;
    }
    $txgroup_id = $result['id'];
    $txgroup = new CRM_Sepa_BAO_SEPATransactionGroup();
    $txgroup->get('id', $txgroup_id);
    return $txgroup;
  }

  public static function addToTxGroup($contrib, $txGroup) {
    self::debug('Adding Contrib(' . $contrib->id . ') to TXG(' . $txGroup->id . ')');
    $params = array(
        'contribution_id' => $contrib->id,
        'txgroup_id' => $txGroup->id,
        'version' => 3,
    );
    $result = civicrm_api('SepaContributionGroup', 'create', $params);
    //TODO need error andling here
  }

  public static function hook_post_sepatransactiongroup_create($objectId, $objectRef) {
    CRM_Sepa_Logic_Batching::batchTxGroup($objectId, $objectRef);
  }

  public static function batchTxGroup($objectId, $objectRef) {
    self::debug('Batching TXG('. $objectId . ')');

    // look for the earliest SDD File (based on latest_submission_date)
    $sddFile = self::findSddFile($objectRef->latest_submission_date);

    // if not found, create a new SDD File 
    if ($sddFile === null) {
      $sddFile = self::createSddFile($objectRef->latest_submission_date);
    }

    // now add the txGroup to the SDD File
    self::addToSddFile($objectRef, $sddFile);
  }

  public static function findSddFile($latest_submission_date) {
    self::debug('Locating suitable SDD File LATEST_SUBMISSION=' . substr($latest_submission_date,0,8));
    $openStatus = CRM_Core_OptionGroup::getValue('batch_status', 'Open', 'name', 'String', 'value');
    $query = "SELECT 
                id 
              FROM
                civicrm_sdd_file
              WHERE         
                status_id = " . $openStatus . "
                AND 
                latest_submission_date >= '" . $latest_submission_date . "'
              ORDER BY 
                latest_submission_date ASC
              LIMIT 1
              ";
    $dao = CRM_Core_DAO::executeQuery($query);
    if ($dao->fetch()) {
      $sddfile = new CRM_Sepa_BAO_SEPASddFile();
      $sddfile->get('id', $dao->id);
      return $sddfile;
    }
    return null;
    
  }

  public static function createSddFile($latest_submission_date) {
    self::debug('Creating new SDD File LATEST_SUBMISSION=' . substr($latest_submission_date,0,8));

    $reference = "SDDXML_" . substr($latest_submission_date,0,8);
    $filename = $reference . ".xml";

    $session = CRM_Core_Session::singleton();
    $params = array(
        'reference' => $reference,
        'filename' => $filename,
        'status_id' => CRM_Core_OptionGroup::getValue('batch_status', 'Open', 'name', 'String', 'value'),
        'latest_submission_date' => $latest_submission_date,
        'created_date' => date('Ymdhis'),
        'created_id' => $session->get('userID'),
        'version' => 3,
    );
    $result = civicrm_api('SepaSddFile', 'create', $params);
    if ($result['is_error']) {
      CRM_Core_Error::fatal(ts("ERROR creating SDD file"));
    }
    $sddfile_id = $result['id'];
    $sddfile = new CRM_Sepa_BAO_SepaSddFile();
    $sddfile->get('id', $sddfile_id);
    return $sddfile;
    
  }

  public static function addToSddFile($txGroup, $sddFile) {
    self::debug('Adding TXG(' . $txGroup->id . ') to SDD File(' . $sddFile->id . ')', '', 'info');
    $params = array(
        'id' => $txGroup->id,
        'sdd_file_id' => $sddFile->id,
        'version' => 3,
    );
    $result = civicrm_api('SepaTransactionGroup', 'update', $params);
  }

}

