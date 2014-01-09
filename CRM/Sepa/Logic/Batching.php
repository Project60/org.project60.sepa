<?php

/**
 * Sepa Direct Debit Batching Logic
 * Author: Project60 
 * 
 * See Batching.md for more info (Seriously, read it. Now. Until you understand it.)
 */

/**
 * This class contains the logic to create SDD batches on both levels.
 */
class CRM_Sepa_Logic_Batching extends CRM_Sepa_Logic_Base {

  public static function batch_initial_contribution($objectId, $objectRef) {
    self::debug('Running initial contribution batching process for mandate M#' . $objectId);
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
    self::debug('Batching contribution C#'. $contrib->id);

    // what are the criteria to find an existing suitable batch ?
    $payment_instrument_id = $contrib->payment_instrument_id;
    $creditor_id = $mandate->creditor_id;
    CRM_Sepa_Logic_Batching::batchContributionByCreditor ($contrib,$creditor_id,$payment_instrument_id);
}

  /**
   * Batch a contribution into a TXG.
   * @param CRM_Sepa_BAO_SEPATransaction $bao
   */
  public static function batchContributionByCreditor (CRM_Contribute_BAO_Contribution $contrib, $creditor_id,$payment_instrument_id) {
    $type = CRM_Core_OptionGroup::getValue('payment_instrument', $contrib->payment_instrument_id, 'value', 'String', 'name');
    self::debug(' Contribution is of type '. $type);

    // calculate the ideal times
    $receive_date = $contrib->receive_date;
    $tomorrow = date('Y-m-d',strtotime("+1days"));
    $delay = ($type == 'RCUR') ? 2 : 5;
    echo '<br>Calculation latest submission date ... ';
    $submission_date = self::adjustBankDays($receive_date, - $delay - 1);
    if ($submission_date < $tomorrow) {
      $submission_date = $tomorrow;
      echo '<br>In the past, so pushing out latest submission date to tomorrow ...';
    }
    // effective_collection_date = submission_date + delay + 1
    echo '<br>Calculation corresponding collection date based on a delay of ', $delay, ' bank days ... ';
    $collection_date = self::adjustBankDays($submission_date, $delay);

    // the range for batch collection date is [ this date - MAXPULL, this date + MAXPUSH ]
    $maxpull = 0;
    $maxpush = 0;

    echo '<br>Calculating earliest date for search window ... ';
    $earliest_date = self::adjustBankDays($collection_date, - $maxpull);
    if ($earliest_date < $tomorrow) {
      $earliest_date = $tomorrow;
      echo ' ... pushing out earliest date to tomorrow ...';
    }
    echo '<br>Calculating corresponding latest date for window ... ';
    $latest_date = self::adjustBankDays($collection_date, $maxpush );
    if ($latest_date < $tomorrow) {
      $latest_date = $tomorrow;
      echo ' ... pushing out latest date to tomorrow ...';
    }

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
    CRM_Sepa_Logic_Base::debug("Locating suitable TXG with CRED=$creditor_id, TYPE=$type and COLLDATE IN " . substr($earliest_date,0,10) . ' / ' . substr($latest_date,0,10));
    $openStatus = CRM_Core_OptionGroup::getValue('batch_status', 'Open', 'name', 'String', 'value');
    $query = "SELECT 
                id 
              FROM
                civicrm_sdd_txgroup
              WHERE         
                status_id = " . $openStatus . "
                AND
                type = '" . $type . "'
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

  public static function createTxGroup($creditor_id, $type, $receive_date,$payment_instrument_id) {
    CRM_Sepa_Logic_Base::debug("Creating new TXG( CRED=$creditor_id, TYPE=$type, COLLDATE=" . substr($receive_date,0,10) . ')');
    // as per Batching.md
    // submission_date = latest( today, tx.collection_date - delay - 1 )
    $tomorrow = date('Y-m-d',strtotime("+1days"));
    $delay = ($type == 'RCUR') ? 2 : 5;
    echo '<br>Calculation latest submission date ... ';
    $submission_date = self::adjustBankDays($receive_date, - $delay - 1);
    if ($submission_date < $tomorrow) {
      $submission_date = $tomorrow;
      echo '<br>Pushing out latest submission date to tomorrow ...';
    }
    // effective_collection_date = submission_date + delay + 1
    echo '<br>Calculation corresponding collection date based on a delay of ', $delay, ' bank days ... ';
    $collection_date = self::adjustBankDays($submission_date, $delay);

    $session = CRM_Core_Session::singleton();
    $reference = time() . rand(); // Just need something unique at this point. (Will generate a nicer one once we have the auto ID from the DB -- see further down.)
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
    );
    $result = civicrm_api3('SepaTransactionGroup', 'create', $params);
    if ($result['is_error']) {
      CRM_Core_Error::fatal($result["error_message"]);
      return null;
    }
    $txgroup_id = $result['id'];

    // Now that we have the auto ID, create the proper reference.
    $reference = "TXG-$creditor_id-$type-$collection_date-$txgroup_id";
    civicrm_api3('SEPATransactionGroup', 'create', array('id' => $txgroup_id, 'reference' => $reference)); // Not very efficient, but easier than fiddling with BAO mess...

    $txgroup = new CRM_Sepa_BAO_SEPATransactionGroup();
    $txgroup->get('id', $txgroup_id);
    return $txgroup;
  }

  public static function addToTxGroup($contrib, $txGroup) {
    self::debug('Adding C#' . $contrib->id . ' to TXG#' . $txGroup->id);
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
    self::debug('Batching TXG#'. $objectId);

    $cred = civicrm_api3('SepaCreditor','getsingle',array('id'=>$objectRef->sdd_creditor_id));
    
    // look for the earliest SDD File (based on latest_submission_date)
    $sddFile = self::findSddFile($objectRef, $cred['tag']);

    // if not found, create a new SDD File 
    if ($sddFile === null) {
      $sddFile = self::createSddFile($objectRef, $cred['tag']);
    }

    // now add the txGroup to the SDD File
    self::addToSddFile($objectRef, $sddFile);
  }

  public static function findSddFile($txgroup, $tag) {
    self::debug('Locating suitable SDDFILE with LATEST_SUBMISSION=' . substr($txgroup->latest_submission_date,0,8) . ' and TAG = ' . $tag);
    $openStatus = CRM_Core_OptionGroup::getValue('batch_status', 'Open', 'name', 'String', 'value');
    $query = "SELECT 
                id 
              FROM
                civicrm_sdd_file
              WHERE         
                status_id = " . $openStatus . "
                AND 
                latest_submission_date = '" . $txgroup->latest_submission_date . "'
                AND 
                tag = '" . $tag . "'
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

  public static function createSddFile($txgroup, $tag) {
    self::debug('Creating new SDDFILE( LATEST_SUBMISSION=' . substr($txgroup->latest_submission_date,0,8) . ', TAG=' . $tag . ')');

    // Just need something unique at this point. (Will generate a nicer one once we have the auto ID from the DB -- see further down.)
    $filename = $reference = time() . rand();

    $session = CRM_Core_Session::singleton();
    $params = array(
        'reference' => $reference,
        'filename' => $filename,
        'status_id' => CRM_Core_OptionGroup::getValue('batch_status', 'Open', 'name', 'String', 'value'),
        'latest_submission_date' => $txgroup->latest_submission_date,
        'tag' => $tag,
        'created_date' => date('Ymdhis'),
        'created_id' => $session->get('userID'),
        'version' => 3,
    );
    $result = civicrm_api('SepaSddFile', 'create', $params);
    if ($result['is_error']) {
      CRM_Core_Error::fatal(ts("ERROR creating SDDFILE"));
    }
    $sddfile_id = $result['id'];

    // Now that we have the auto ID, create the proper reference.
    $reference = "SDDXML-" . (isset($tag) ? $tag . '-' : '') . substr($txgroup->latest_submission_date, 0, 8) . '-' . $sddfile_id;
    $filename = str_replace('-', '_', $reference . ".xml");
    civicrm_api3('SEPASddFile', 'create', array('id' => $sddfile_id, 'reference' => $reference, 'filename' => $filename)); // Not very efficient, but easier than fiddling with BAO mess...

    $sddfile = new CRM_Sepa_BAO_SepaSddFile();
    $sddfile->get('id', $sddfile_id);
    return $sddfile;
    
  }

  public static function addToSddFile($txGroup, $sddFile) {
    self::debug('Adding TXG#' . $txGroup->id . ' to SDDFILE#' . $sddFile->id, '', 'info');
    $params = array(
        'id' => $txGroup->id,
        'sdd_file_id' => $sddFile->id,
        'version' => 3,
    );
    $result = civicrm_api('SepaTransactionGroup', 'update', $params);
  }

}

