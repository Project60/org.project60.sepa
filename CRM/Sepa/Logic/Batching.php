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

    $receive_date = date('Y-m-d', strtotime($contrib->receive_date));
    $txGroup = self::findTxGroup($creditor_id, $type, $receive_date, $receive_date);

    // if not found, create a nex batch 
    if ($txGroup === null) {
      $txGroup = self::createTxGroup($creditor_id, $type, $receive_date,$payment_instrument_id);
    }

    // now add the tx to teh batch
    self::addToTxGroup($contrib, $txGroup);
    return $txGroup;
  }

  /*
   * Change Contribution status, using BAO.
   *
   * This is needed because the Contribution API only accepts a very limited set of status transitions;
   * while the BAO allows us changing from any status to any other one.
   *
   * @param int $statusId The 'value' of the desired `contribution_status` option value
   * @param int $contributionId ID of the Contribution to update
   * @return void
   */
  static function setContributionStatus($contributionId, $statusId) {
    $contribution = new CRM_Contribute_BAO_Contribution();
    $contribution->get($contributionId);

    $contribution->contribution_status_id = $statusId;
    $contribution->receive_date = date('YmdHis', strtotime($contribution->receive_date)); /* BAO fails to accept own date format... */
    if (isset($contribution->receipt_date)) {
      $contribution->receipt_date = date('YmdHis', strtotime($contribution->receipt_date));
    }
    if (isset($contribution->thankyou_date)) {
      $contribution->thankyou_date = date('YmdHis', strtotime($contribution->thankyou_date));
    }
    $contribution->save();
  }

  /**
   */
  public static function batchForSubmit($submitDate, $creditorId) {
    set_time_limit(0); /* This action can take quite long... */

    $creditor = civicrm_api3('SepaCreditor', 'getsingle', array('id' => $creditorId));
    $creditorCountry = substr($creditor['iban'], 0, 2); /* IBAN begins with country code. (Needed for COR1 handling.) */

    $maximumAdvanceDays = 14; // Default per SEPA Rulebook.
    $dateRangeEnd = date('Y-m-d', strtotime("$submitDate + $maximumAdvanceDays days"));

    #$result = civicrm_api3('SepaCreditor', 'getsingle', array(
    #  'id' => $creditorId,
    #  'api.SepaTransactionGroup.get' => array(
    #    'sdd_creditor_id' => '$value.id',
    #    'status_id' => CRM_Core_OptionGroup::getValue('batch_status', 'Open', 'name'),
    #    #'filter.collection_date_high' => $dateRangeEnd,
    #    'return' => array('id', 'type', 'collection_date'),
    #  ),
    #  'return' => 'api.SepaTransactionGroup.get',
    #));
    #if (!$result['count']) {
    #  throw new API_Exception("No matching Creditor found.");
    #}
    #
    #$pendingGroups = array();
    #foreach ($result['api.SepaTransactionGroup.get']['values'] as $group) {

    $result = civicrm_api3('SepaContributionPending', 'get', array(
      'options' => array('sort' => 'payment_instrument_id, receive_date'), /* Make sure Groups are batched in a clear order. */
      'filter.receive_date_high' => date('Ymd', strtotime($dateRangeEnd)), /* Pre-filter the ones obviously out of range, to improve performance. (Needs further filtering after bank days adjustment.) */
      'return' => array('payment_instrument_id', 'receive_date'),
      'mandate' => array(
        'creditor_id' => $creditorId,
        'return' => array('iban'),
      )
    ));

    /* First, group by type (`payment_instrument_id`) and original `receive_date`. */
    $pendingGroups = array();
    foreach ($result['values'] as $contributionId => $contribution) {
      $receiveDate = date('Y-m-d', strtotime($contribution['receive_date']));
      $instrument = $contribution['payment_instrument_id'];

      $isCor1 = (substr($contribution['iban'], 0, 2) == $creditorCountry) ? 'cor1' : ''; /* Need to use strings rather than actual bool values, so we can use them as array indices. */

      $pendingGroups = array_replace_recursive($pendingGroups, array($isCor1 => array($instrument => array($receiveDate => array())))); /* Create any missing array levels, to avoid PHP notice. */
      $pendingGroups[$isCor1][$instrument][$receiveDate][] = $contributionId;
    }

    /* Now adjust each temporary group to earliest possible Collection Date, and merge groups accordingly. */
    $groups = array();
    foreach ($pendingGroups as $isCor1 => $instruments) {
      foreach ($instruments as $instrument => $dates) {
        foreach ($dates as $receiveDate => $ids) {
          $bestCollectionDate = self::adjustBankDays($receiveDate, 0);
          /* Re-check, as date might exceed range after adjustment. */
          if ($bestCollectionDate > $dateRangeEnd) {
            continue;
          }

          $type = CRM_Core_OptionGroup::getValue('payment_instrument', $instrument, 'value', 'String', 'name');

          $advanceDays = ($isCor1 ? 1 : ($type == 'RCUR' ? 2 : 5)) + $creditor['extra_advance_days'];
          $earliestCollectionDate = self::adjustBankDays($submitDate, $advanceDays);
          $collectionDate = max($earliestCollectionDate, $bestCollectionDate);

          $groups = array_merge_recursive($groups, array($isCor1 => array($type => array($collectionDate => $ids))));
        }
      }
    }

    /* Finally, create the File(s) and Groups in DB. */
    if (!empty($groups)) { /* Have anything to generate. */
      $tag = (isset($creditor['tag'])) ? $creditor['tag'] : $creditor['mandate_prefix'];

      $groupBatchingMode = 'COR'; /* DiCo hack */

      if ($groupBatchingMode == 'ALL') {
        $sddFile = self::createSddFile((object)array('latest_submission_date' => date('Ymd', strtotime($submitDate))), $tag, null, null, null);
      }

      foreach ($groups as $isCor1 => $types) {
        $instrument = $isCor1 ? 'COR1' : 'CORE';
        if ($groupBatchingMode == 'COR') {
          $sddFile = self::createSddFile((object)array('latest_submission_date' => date('Ymd', strtotime($submitDate))), $tag, $instrument, null, null);
        }

        foreach ($types as $type => $dates) {
          if ($groupBatchingMode == 'TYPE') {
            $sddFile = self::createSddFile((object)array('latest_submission_date' => date('Ymd', strtotime($submitDate))), $tag, $instrument, $type, null);
          }

          foreach ($dates as $collectionDate => $ids) {
            if ($groupBatchingMode == 'NONE') {
              $sddFile = self::createSddFile((object)array('latest_submission_date' => date('Ymd', strtotime($submitDate))), $tag, $instrument, $type, $collectionDate);
            }

            $paymentInstrumentId = CRM_Core_OptionGroup::getValue('payment_instrument', $type, 'name');
            $txGroup = self::createTxGroup($creditorId, (bool)$isCor1, $type, $collectionDate, $paymentInstrumentId, $sddFile->id);

            foreach ($ids as $contributionId) {
              $result = civicrm_api3('SepaContributionGroup', 'create', array(
                'txgroup_id' => $txGroup->id,
                'contribution_id' => $contributionId,
              ));

              self::setContributionStatus($contributionId, CRM_Core_OptionGroup::getValue('contribution_status', 'Batched', 'name'));
            }

            if ($groupBatchingMode == 'NONE') {
              civicrm_api3('SepaSddFile', 'generatexml', array('id' => $sddFile->id));
            }
          }

          if ($groupBatchingMode == 'TYPE') {
            civicrm_api3('SepaSddFile', 'generatexml', array('id' => $sddFile->id));
          }
        }

        if ($groupBatchingMode == 'COR') {
          civicrm_api3('SepaSddFile', 'generatexml', array('id' => $sddFile->id));
        }
      }

      if ($groupBatchingMode == 'ALL') {
        civicrm_api3('SepaSddFile', 'generatexml', array('id' => $sddFile->id));
      }
    } /* !empty($groups) */
  }

  /**
   */
  public static function updateStatus($txgroupParams, $statusId, $fromStatusId) {
    set_time_limit(0); /* This action can take quite long... */

    $useApi = false;
    $contributionStatusId = $groupStatusId = $statusId;
    switch (CRM_Core_OptionGroup::getValue('contribution_status', $statusId, 'value', 'String', 'name')) {
      case 'Cancelled':
        $contributionStatusId = CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name');
        break;
      case 'Completed':
        $useApi = true;
        break;
    }

    $result = civicrm_api3('SepaTransactionGroup', 'get', array_merge($txgroupParams, array(
      'options' => array('limit' => 1234567890),
      'status_id' => $fromStatusId,
      'return' => array('sdd_creditor_id', 'collection_date'),
      'api.SepaTransactionGroup.create' => array(
        /* 'id' inherited */
        'status_id' => $groupStatusId,
      ),
      'api.SepaContributionGroup.get' => array(
        'options' => array('limit' => 1234567890),
        'txgroup_id' => '$value.id',
        'api.Contribution.getsingle' => array(
          'id' => '$value.contribution_id',
          'return' => array('contribution_status_id'),
        ),
      ),
    )));
    if (!$result['count']) {
      throw new API_Exception("No matching Transaction Group found.");
    }

    foreach ($result['values'] as $group) {
      foreach ($group['api.SepaContributionGroup.get']['values'] as $groupMember) {
        if ($groupMember['api.Contribution.getsingle']['contribution_status_id'] != $fromStatusId) {
          continue;
        }

        if ($useApi) {
          civicrm_api3('Contribution', 'create', array(
            'id' => $groupMember['contribution_id'],
            'contribution_status_id' => $contributionStatusId,
            'receive_date' => $group['collection_date'],
          ));
        } else {
          self::setContributionStatus($groupMember['contribution_id'], $contributionStatusId);
        }
      }
    }
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
    $openStatus = CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name', 'String', 'value');
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

  public static function createTxGroup($creditor_id, $isCor1, $type, $receive_date, $payment_instrument_id, $sddFileId = null) {
    $collection_date = substr($receive_date, 0, 10);
    CRM_Sepa_Logic_Base::debug("Creating new TXG( CRED=$creditor_id, IS_COR1=$isCor1, TYPE=$type, COLLDATE=" . $collection_date . ')');

    $status = isset($sddFileId) ? 'Batched' : 'Pending';

    $session = CRM_Core_Session::singleton();
    $reference = time() . rand(); // Just need something unique at this point. (Will generate a nicer one once we have the auto ID from the DB -- see further down.)
    $params = array(
        'reference' => $reference,
        'is_cor1' => $isCor1,
        'type' => $type,
        'sdd_creditor_id' => $creditor_id,
        'status_id' => CRM_Core_OptionGroup::getValue('contribution_status', $status, 'name'),
        'payment_instrument_id' => $payment_instrument_id,
        'collection_date' => $collection_date,
        'created_date' => date('Ymdhis'),
        'modified_date' => date('Ymdhis'),
        'created_id' => $session->get('userID'),
        'modified_id' => $session->get('userID'),
        'sdd_file_id' => $sddFileId,
    );
    $result = civicrm_api3('SepaTransactionGroup', 'create', $params);
    if ($result['is_error']) {
      CRM_Core_Error::fatal($result["error_message"]);
      return null;
    }
    $txgroup_id = $result['id'];

    // Now that we have the auto ID, create the proper reference.
    $prefix = ($status == 'Pending') ? 'PENDING' : 'TXG';
    $creditorPrefix = civicrm_api3('SepaCreditor', 'getvalue', array('id' => $creditor_id, 'return' => 'mandate_prefix'));
    $instrument = $isCor1 ? 'COR1' : 'CORE';
    $reference = "$prefix-$creditorPrefix-$creditor_id-$instrument-$type-$collection_date-$txgroup_id";
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
    $openStatus = CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name');
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

  public static function createSddFile($txgroup, $tag, $instrument, $type, $collectionDate) {
    self::debug('Creating new SDDFILE( LATEST_SUBMISSION=' . substr($txgroup->latest_submission_date,0,8) . ', TAG=' . $tag . ')');

    // Just need something unique at this point. (Will generate a nicer one once we have the auto ID from the DB -- see further down.)
    $filename = $reference = time() . rand();

    $session = CRM_Core_Session::singleton();
    $params = array(
        'reference' => $reference,
        'filename' => $filename,
        'status_id' => CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name'),
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
    $reference = "SDDXML-" . $tag . '-' . substr($txgroup->latest_submission_date, 0, 8) . (isset($instrument) ? "-$instrument" : '') . (isset($type) ? "-$type" : '') . (isset($collectionDate) ? '-' . date('Ymd', strtotime($collectionDate)) : '') . '-' . $sddfile_id;
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

