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
    CRM_Core_Session::setStatus('Running initial contribution batching process for mandate', $objectId, 'alert');
    $mandate = new CRM_Sepa_BAO_SEPAMandate();
    $mandate->get('id', $objectId);

    // Get the first contribution for this mandate
    $contrib = $mandate->findContribution();

    self::batchContribution($contrib, $mandate);
  }

  /**
   * Batch a contribution into a TXG.
   * 
   * @param CRM_Sepa_BAO_SEPATransaction $bao
   */
  public static function batchContribution(CRM_Contribute_BAO_Contribution $contrib, CRM_Sepa_BAO_SEPAMandate $mandate) {
    CRM_Core_Session::setStatus('Batching this contribution', $contrib->id, 'info');

    // what are the criteria to find an existing suitable batch ?
    $type = self::getSDDType($contrib);
    $payment_instrument_id = $contrib->payment_instrument_id;
    $params = array(
        'contribution_payment_instrument_id' => $payment_instrument_id,
        'version' => 3,
    );
    $result = civicrm_api('SepaCreditor', 'getsingle', $params);
    if ($result['is_error']) {
      CRM_Core_Session::setStatus('No creditor found', $payment_instrument_id, 'alert');
      return null;
    }
    $creditor_id = $result['id'];

    $receive_date = $contrib->receive_date;

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
      $txGroup = self::createTxGroup($creditor_id, $type, $receive_date);
    }

    // now add the tx to teh batch
    self::addToTxGroup($contrib, $txGroup);
  }

  /**
   * Find an appropriate civicrm_batch instance.
   * 
   * @param type $payment_instrument_id
   * @param type $type
   * @param type $earliest_date
   * @param type $latest_date
   * 
   * @return null
   */
  public static function findTxGroup($creditor_id, $type, $earliest_date, $latest_date) {
    CRM_Core_Session::setStatus('Locating suitable TXG', '', 'info');
    $openStatus = CRM_Core_OptionGroup::getValue('batch_status', 'Open', 'name', 'String', 'id');
    $query = "SELECT 
                id 
              FROM
                civicrm_sdd_txgroup
              WHERE         
                status_id = " . $openStatus . "
                AND
                sdd_creditor_id = " . $creditor_id . "
                AND 
                collection_date >= '" . $earliest_date . "'
                AND 
                collection_date <= '" . $latest_date . "'
              ORDER BY 
                collection_date ASC
              LIMIT 1
              ";
    $dao = CRM_Core_DAO::executeQuery($query);
    if ($dao->fetch())
      return $dao->id;
    return null;
  }

  public static function createTxGroup($creditor_id, $type, $receive_date) {
    CRM_Core_Session::setStatus('Creating TXG', 0, 'info');

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
    die(print_r($result));
    if ($result['is_error'])
      return null;
    $txgroup_id = $result['id'];
    $txgroup = new CRM_Sepa_BAO_SepaTransactionGroup();
    $txgroup->get('id', $txgroup_id);
    return $txgroup;
  }

  public static function addToTxGroup($contrib, $txGroup) {
    CRM_Core_Session::setStatus('Adding Contrib(' . $contrib->id . ') to TXG(' . $txGroup->id . ')', '', 'info');
    $params = array(
        'contribution_id' => $contrib->id,
        'txgroup_id' => $txGroup->id,
        'version' => 3,
    );
    $result = civicrm_api('ContributionToGroup', 'create', $params);
  }

  public static function hook_post_sepatransactiongroup_create($objectId, $objectRef) {
    CRM_Sepa_Logic_Batching::batchTxGroup($objectId, $objectRef);
  }

  public static function batchTxGroup($objectId, $objectRef) {
    CRM_Core_Session::setStatus('Batching this TXG', $objectId, 'info');

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
    
  }

  public static function createSddFile($latest_submission_date) {
    
  }

  public static function addToSddFile($txGroup, $sddFile) {
    CRM_Core_Session::setStatus('Adding TXG(' . $txGroup->id . ') to SDD File(' . $sddFile->id . ')', '', 'info');
    $params = array(
        'id' => $txGroup->id,
        'sdd_file_id' => $sddFile->id,
        'version' => 3,
    );
    $result = civicrm_api('ContributionToGroup', 'create', $params);
  }

}

