<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2020 SYSTOPIA                       |
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

use Civi\Sepa\SepaBatchLockManager;
use CRM_Sepa_ExtensionUtil as E;

/**
 * This class holds all SEPA batching functions
 */
class CRM_Sepa_Logic_Batching {

  /**
   * runs a batching update for all RCUR mandates for the given creditor
   *
   * @param $creditor_id  the creaditor to be batched
   * @param $mode         'FRST' or 'RCUR'
   * @param string $now   Overwrite what is used as "now" for batching, can be everything valid for strtotime, a "+n days" is added.
   */
  static function updateRCUR($creditor_id, $mode, $now = 'now', $offset=NULL, $limit=NULL) {
    // check lock
    $lock = SepaBatchLockManager::getInstance()->getLock();
    if (!$lock->acquire()) {
      return "Batching in progress. Please try again later.";
    }

    $horizon = (int) CRM_Sepa_Logic_Settings::getSetting("batching.RCUR.horizon", $creditor_id);
    $grace_period = (int) CRM_Sepa_Logic_Settings::getSetting("batching.RCUR.grace", $creditor_id);
    $latest_date = date('Y-m-d', strtotime("$now +$horizon days"));

    $rcur_notice = (int) CRM_Sepa_Logic_Settings::getSetting("batching.$mode.notice", $creditor_id);
    // (virtually) move ahead notice_days, but also go back grace days
    $now = strtotime("$now +$rcur_notice days -$grace_period days");
    $now = strtotime(date('Y-m-d', $now));        // round to full day
    $group_status_id_open = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Open');

    // get payment instruments
    $payment_instruments = CRM_Sepa_Logic_PaymentInstruments::getPaymentInstrumentsForCreditor($creditor_id, $mode);
    $payment_instrument_id_list = implode(',', array_keys($payment_instruments));
    if (empty($payment_instrument_id_list)) {
      return; // disabled
    }

    if ($offset !== NULL && $limit!==NULL) {
      $batch_clause = "LIMIT {$limit} OFFSET {$offset}";
    }
    else {
      $batch_clause = "";
    }

    // RCUR-STEP 0: check/repair mandates
    // TODO: Does this need changes for Financial ACLs?
    CRM_Sepa_Logic_MandateRepairs::runWithMandateSelector(
      "mandate.type = 'RCUR' AND mandate.status = '{$mode}' AND mandate.creditor_id = {$creditor_id} {$batch_clause}",
      true
    );


    // RCUR-STEP 1: find all active/pending RCUR mandates within the horizon that are NOT in a closed batch and that
    // have a corresponding contribution of a financial type the user has access to (implicit condition added by
    // Financial ACLs extension if enabled)
    $relevant_mandates = \Civi\Api4\SepaMandate::get(TRUE)
      ->addSelect(
        'id',
        'contact_id',
        'entity_id',
        'source',
        'creditor_id',
        'first_contribution.receive_date',
        'contribution_recur.cycle_day',
        'contribution_recur.frequency_interval',
        'contribution_recur.frequency_unit',
        'contribution_recur.start_date',
        'contribution_recur.cancel_date',
        'contribution_recur.end_date',
        'contribution_recur.amount',
        'contribution_recur.is_test',
        'contribution_recur.contact_id',
        'contribution_recur.financial_type_id',
        'contribution_recur.contribution_status_id',
        'contribution_recur.currency',
        'contribution_recur.campaign_id',
        'contribution_recur.payment_instrument_id'
      )
      ->addJoin(
        'ContributionRecur AS contribution_recur',
        'INNER',
        ['entity_table', '=', '"civicrm_contribution_recur"'],
        ['entity_id', '=', 'contribution_recur.id']
      )
      ->addJoin(
        'Contribution AS first_contribution',
        'LEFT',
        ['first_contribution_id', '=', 'first_contribution.id']
      )
      ->addWhere('type', '=', 'RCUR')
      ->addWhere('status', '=', $mode)
      ->addWhere('creditor_id', '=', $creditor_id)
      ->setLimit($limit)
      ->setOffset($offset)
      ->execute()
      ->getArrayCopy();

    foreach ($relevant_mandates as &$mandate) {
      $mandate += [
        'mandate_id' => $mandate['id'],
        'mandate_contact_id' => $mandate['contact_id'],
        'mandate_entity_id' => $mandate['entity_id'],
        'mandate_first_executed' => $mandate['first_contribution.receive_date'],
        'mandate_source' => $mandate['source'],
        'mandate_creditor_id' => $mandate['creditor_id'],
        'cycle_day' => $mandate['contribution_recur.cycle_day'],
        'frequency_interval' => $mandate['contribution_recur.frequency_interval'],
        'frequency_unit' => $mandate['contribution_recur.frequency_unit'],
        'start_date' => $mandate['contribution_recur.start_date'],
        'end_date' => $mandate['contribution_recur.end_date'],
        'cancel_date' => $mandate['contribution_recur.cancel_date'],
        'rc_contact_id' => $mandate['contribution_recur.contact_id'],
        'rc_amount' => $mandate['contribution_recur.amount'],
        'rc_currency' => $mandate['contribution_recur.currency'],
        'rc_financial_type_id' => $mandate['contribution_recur.financial_type_id'],
        'rc_contribution_status_id' => $mandate['contribution_recur.contribution_status_id'],
        'rc_campaign_id' => $mandate['contribution_recur.campaign_id'] ?? NULL,
        'rc_payment_instrument_id' => $mandate['contribution_recur.payment_instrument_id'],
        'rc_is_test' => $mandate['contribution_recur.is_test'],
      ];
    }

    // RCUR-STEP 2: calculate next execution date
    $mandates_by_nextdate = [];
    foreach ($relevant_mandates as $mandate) {
      $next_date = self::getNextExecutionDate($mandate, $now, ($mode=='FRST'));
      if (NULL === $next_date || $next_date > $latest_date) {
        continue;
      }
      if (!isset($mandates_by_nextdate[$next_date])) {
        $mandates_by_nextdate[$next_date] = [];
      }
      if (!isset($mandates_by_nextdate[$next_date][$mandate['rc_financial_type_id']])) {
        $mandates_by_nextdate[$next_date][$mandate['rc_financial_type_id']] = [];
      }
      array_push($mandates_by_nextdate[$next_date][$mandate['rc_financial_type_id']], $mandate);
    }
    // apply any deferrals:
    $collection_dates = array_keys($mandates_by_nextdate);
    foreach ($collection_dates as $collection_date) {
      $deferred_collection_date = $collection_date;
      self::deferCollectionDate($deferred_collection_date, $creditor_id);
      if ($deferred_collection_date != $collection_date) {
        if (empty($mandates_by_nextdate[$deferred_collection_date])) {
          $mandates_by_nextdate[$deferred_collection_date] = $mandates_by_nextdate[$collection_date];
        }
        else {
          $mandates_by_nextdate[$deferred_collection_date] = array_merge(
            $mandates_by_nextdate[$collection_date],
            $mandates_by_nextdate[$deferred_collection_date]
          );
        }
        unset($mandates_by_nextdate[$collection_date]);
      }
    }


    // RCUR-STEP 3: find already created contributions
    $existing_contributions_by_recur_id = [];
    foreach ($mandates_by_nextdate as $collection_date => $financial_type_mandates) {
      foreach ($financial_type_mandates as $financial_type => $mandates) {
        $rcontrib_ids = [];
        foreach ($mandates as $mandate) {
          array_push($rcontrib_ids, $mandate['mandate_entity_id']);
        }
        $rcontrib_id_strings = implode(',', $rcontrib_ids);

        $sql_query = "
        SELECT
          contribution.contribution_recur_id AS contribution_recur_id,
          contribution.id                    AS contribution_id
        FROM civicrm_contribution contribution
        LEFT JOIN civicrm_sdd_contribution_txgroup ctxg ON ctxg.contribution_id = contribution.id
        LEFT JOIN civicrm_sdd_txgroup               txg ON txg.id = ctxg.txgroup_id
        WHERE contribution.contribution_recur_id IN ({$rcontrib_id_strings})
          AND DATE(contribution.receive_date) = DATE('{$collection_date}')
          AND (txg.type IS NULL OR txg.type IN ('RCUR', 'FRST'))
          AND contribution.payment_instrument_id IN ({$payment_instrument_id_list});";
        $results = CRM_Core_DAO::executeQuery($sql_query);
        while ($results->fetch()) {
          $existing_contributions_by_recur_id[$results->contribution_recur_id] = $results->contribution_id;
        }
      }
    }

    // RCUR-STEP 4: create the missing contributions, store all in $mandate['mandate_entity_id']
    foreach ($mandates_by_nextdate as $collection_date => $financial_type_mandates) {
      foreach ($financial_type_mandates as $financial_type => $mandates) {
        foreach ($mandates as $index => $mandate) {
          $recur_id = $mandate['mandate_entity_id'];
          if (isset($existing_contributions_by_recur_id[$recur_id])) {
            // if the contribution already exists, store it
            $contribution_id = $existing_contributions_by_recur_id[$recur_id];
            unset($existing_contributions_by_recur_id[$recur_id]);
            $mandates_by_nextdate[$collection_date][$financial_type][$index]['mandate_entity_id'] = $contribution_id;
          }
          else {
            // else: create it
            $installment_pi = CRM_Sepa_Logic_PaymentInstruments::getInstallmentPaymentInstrument(
              $creditor_id, $mandate['rc_payment_instrument_id'], ($mode == 'FRST'));
            $contribution_data = array(
              "version"                             => 3,
              "total_amount"                        => $mandate['rc_amount'],
              "currency"                            => $mandate['rc_currency'],
              "receive_date"                        => $collection_date,
              "contact_id"                          => $mandate['rc_contact_id'],
              "contribution_recur_id"               => $recur_id,
              "source"                              => $mandate['mandate_source'],
              "financial_type_id"                   => $mandate['rc_financial_type_id'],
              "contribution_status_id"              => $mandate['rc_contribution_status_id'],
              "campaign_id"                         => $mandate['rc_campaign_id'],
              "is_test"                             => $mandate['rc_is_test'],
              "payment_instrument_id"               => $installment_pi
            );
            $contribution = civicrm_api('Contribution', 'create', $contribution_data);
            if (empty($contribution['is_error'])) {
              // Success! Call the post_create hook
              CRM_Utils_SepaCustomisationHooks::installment_created($mandate['mandate_id'], $recur_id, $contribution['id']);

              // 'mandate_entity_id' will now be overwritten with the contribution instance ID
              //  to allow compatibility in with OOFF groups in the syncGroups function
              $mandates_by_nextdate[$collection_date][$financial_type][$index]['mandate_entity_id'] = $contribution['id'];
            }
            else {
              // in case of an error, we will unset 'mandate_entity_id', so it cannot be
              //  interpreted as the contribution instance ID (see above)
              unset($mandates_by_nextdate[$collection_date][$financial_type][$index]['mandate_entity_id']);

              // log the error
              Civi::log()->debug("org.project60.sepa: batching:updateRCUR/createContrib ".$contribution['error_message']);

              // TODO: Error handling?
            }
            unset($existing_contributions_by_recur_id[$recur_id]);
          }
        }
      }
    }

    // delete unused contributions:
    foreach ($existing_contributions_by_recur_id as $contribution_id) {
      // TODO: is this needed?
      Civi::log()->debug("org.project60.sepa: batching: contribution $contribution_id should be deleted...");
    }

    // step 5: find all existing OPEN groups
    $sql_query = "
      SELECT
        txgroup.collection_date AS collection_date,
        txgroup.financial_type_id AS financial_type_id,
        txgroup.id AS txgroup_id
      FROM civicrm_sdd_txgroup AS txgroup
      WHERE txgroup.type = '$mode'
        AND txgroup.sdd_creditor_id = $creditor_id
        AND txgroup.status_id = $group_status_id_open;";
    $results = CRM_Core_DAO::executeQuery($sql_query);
    $existing_groups = [];
    while ($results->fetch()) {
      $collection_date = date('Y-m-d', strtotime($results->collection_date));
      $existing_groups[$collection_date][$results->financial_type_id ?? 0] = $results->txgroup_id;
    }

    // step 6: sync calculated group structure with existing (open) groups
    self::syncGroups(
      $mandates_by_nextdate,
      $existing_groups,
      $mode,
      'RCUR',
      $rcur_notice,
      $creditor_id,
      NULL !== $offset,
      0 === $offset
    );
  }



  /**
   * runs a batching update for all OOFF mandates
   *
   * @param $creditor_id  the creditor ID to run this for
   * @param string $now   Overwrite what is used as "now" for batching, can be everything valid for strtotime, a "+n days" is added.
   * @param $offset       used for segmented updates
   * @param $limit        used for segmented updates
   */
  static function updateOOFF($creditor_id, $now = 'now', $offset = NULL, $limit = NULL) {
    // check lock
    $lock = SepaBatchLockManager::getInstance()->getLock();
    if (!$lock->acquire()) {
      return "Batching in progress. Please try again later.";
    }

    $horizon = (int) CRM_Sepa_Logic_Settings::getSetting('batching.OOFF.horizon', $creditor_id);
    $ooff_notice = (int) CRM_Sepa_Logic_Settings::getSetting('batching.OOFF.notice', $creditor_id);
    $group_status_id_open = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Open');
    $date_limit = date('Y-m-d', strtotime("$now +$horizon days"));

    // step 1: find all active/pending OOFF mandates within the horizon that are NOT in a closed batch and that have a
    // corresponding contribution of a financial type the user has access to (implicit condition added by Financial ACLs
    // extension if enabled).
    $relevant_mandates = \Civi\Api4\SepaMandate::get(TRUE)
      ->addSelect('id', 'contact_id', 'entity_id', 'contribution.receive_date', 'contribution.financial_type_id')
      ->addJoin(
        'Contribution AS contribution',
        'INNER',
        ['entity_table', '=', "'civicrm_contribution'"],
        ['entity_id', '=', 'contribution.id']
      )
      ->addWhere('contribution.receive_date', '<=', $date_limit)
      ->addWhere('type', '=', 'OOFF')
      ->addWhere('status', '=', 'OOFF')
      ->addWhere('creditor_id', '=', $creditor_id)
      ->setLimit($limit ?? 0)
      ->setOffset($offset ?? 0)
      ->execute()
      ->getArrayCopy();

    // step 2: group mandates in collection dates
    $calculated_groups = [];
    $earliest_collection_date = date('Y-m-d', strtotime("$now +$ooff_notice days"));
    $latest_collection_date = '';

    foreach ($relevant_mandates as $mandate) {
      $mandate['mandate_id'] = $mandate['id'];
      $mandate['mandate_contact_id'] = $mandate['contact_id'];
      $mandate['mandate_entity_id'] = $mandate['entity_id'];
      $mandate['start_date'] = $mandate['contribution.receive_date'];
      $mandate['financial_type_id'] = $mandate['contribution.financial_type_id'];
      $collection_date = date('Y-m-d', strtotime($mandate['start_date']));
      if ($collection_date <= $earliest_collection_date) {
        $collection_date = $earliest_collection_date;
      }

      if (!isset($calculated_groups[$collection_date][$mandate['financial_type_id']])) {
        $calculated_groups[$collection_date][$mandate['financial_type_id']] = [];
      }

      array_push($calculated_groups[$collection_date][$mandate['financial_type_id']], $mandate);

      if ($collection_date > $latest_collection_date) {
        $latest_collection_date = $collection_date;
      }
    }
    if (!$latest_collection_date) {
      // nothing to do...
      return [];
    }

    // step 3: find all existing OPEN groups in the horizon
    $sql_query = "
      SELECT
        txgroup.collection_date AS collection_date,
        txgroup.financial_type_id AS financial_type_id,
        txgroup.id AS txgroup_id
      FROM civicrm_sdd_txgroup AS txgroup
      WHERE txgroup.sdd_creditor_id = $creditor_id
        AND txgroup.type = 'OOFF'
        AND txgroup.status_id = $group_status_id_open;";
    $results = CRM_Core_DAO::executeQuery($sql_query);
    $existing_groups = array();
    while ($results->fetch()) {
      $collection_date = date('Y-m-d', strtotime($results->collection_date));
      $existing_groups[$collection_date][$results->financial_type_id ?? 0] = $results->txgroup_id;
    }

    // step 4: sync calculated group structure with existing (open) groups
    self::syncGroups(
      $calculated_groups,
      $existing_groups,
      'OOFF',
      'OOFF',
      $ooff_notice,
      $creditor_id,
      $offset !== NULL,
      $offset === 0
    );
  }



  /**
   * Maintenance: Close all mandates that have expired
   */
  static function closeEnded() {
    // check lock
    $lock = SepaBatchLockManager::getInstance()->getLock();
    if (!$lock->acquire()) {
      return "Batching in progress. Please try again later.";
    }

    $contribution_status_closed = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');

    // first, load all of the mandates, that have run out
    $sql_query = "
      SELECT
        mandate.id              AS mandate_id,
        mandate.date            AS mandate_date,
        mandate.entity_id       AS mandate_entity_id,
        mandate.creation_date   AS mandate_creation_date,
        mandate.validation_date AS mandate_validation_date,
        rcontribution.currency  AS currency,
        rcontribution.end_date  AS end_date
      FROM civicrm_sdd_mandate AS mandate
      INNER JOIN civicrm_contribution_recur AS rcontribution       ON mandate.entity_id = rcontribution.id AND mandate.entity_table = 'civicrm_contribution_recur'
      WHERE mandate.type = 'RCUR'
        AND mandate.status IN ('RCUR','FRST')
        AND end_date <= DATE(NOW());";
    $results = CRM_Core_DAO::executeQuery($sql_query);
    $mandates_to_end = array();
    while ($results->fetch()) {
      $mandates_to_end[] = array(
        'mandate_id'      => $results->mandate_id,
        'recur_id'        => $results->mandate_entity_id,
        'creation_date'   => $results->mandate_creation_date,
        'validation_date' => $results->mandate_validation_date,
        'date'            => $results->mandate_date,
        'currency'        => $results->currency);
    }

    // then, end them one by one
    foreach ($mandates_to_end as $mandate_to_end) {
      $change_mandate = civicrm_api('SepaMandate', 'create', array(
        'id'                      => $mandate_to_end['mandate_id'],
        'date'                    => $mandate_to_end['date'],
        'creation_date'           => $mandate_to_end['creation_date'],
        'validation_date'         => $mandate_to_end['validation_date'],
        'status'                  => 'COMPLETE',
        'version'                 => 3));
      if (isset($change_mandate['is_error']) && $change_mandate['is_error']) {
        return sprintf("Couldn't set mandate '%s' to 'complete. Error was: '%s'", $mandates_to_end['mandate_id'], $change_mandate['error_message']);
      }

      $change_rcur = civicrm_api('ContributionRecur', 'create', array(
        'id'                      => $mandate_to_end['recur_id'],
        'contribution_status_id'  => $contribution_status_closed,
        'modified_date'           => date('YmdHis'),
        'currency'                => $mandate_to_end['currency'],
        'version'                 => 3));
      if (isset($change_rcur['is_error']) && $change_rcur['is_error']) {
        return sprintf("Couldn't set recurring contribution '%s' to 'complete. Error was: '%s'", $mandates_to_end['recur_id'], $change_rcur['error_message']);
      }
    }
  }







  /****************************************************************************
   **                                                                        **
   **                            HELPERS                                     **
   **                                                                        **
   ****************************************************************************/

  public static function getOrCreateTransactionGroup(
    int $creditor_id,
    string $mode,
    string $collection_date,
    ?int $financial_type_id,
    int $notice,
    array &$existing_groups
  ): int {
    $group_status_id_open = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Open');

    if (!isset($existing_groups[$collection_date][$financial_type_id ?? 0])) {
      // this group does not yet exist -> create

      // find unused reference
      $reference = self::getTransactionGroupReference($creditor_id, $mode, $collection_date, $financial_type_id);

      $group = civicrm_api('SepaTransactionGroup', 'create', array(
        'version'                 => 3,
        'reference'               => $reference,
        'type'                    => $mode,
        'collection_date'         => $collection_date,
        // Financial type may be NULL if not grouping by financial type.
        'financial_type_id'       => $financial_type_id,
        'latest_submission_date'  => date('Y-m-d', strtotime("-$notice days", strtotime($collection_date))),
        'created_date'            => date('Y-m-d'),
        'status_id'               => $group_status_id_open,
        'sdd_creditor_id'         => $creditor_id,
      ));
      if (!empty($group['is_error'])) {
        // TODO: Error handling
        Civi::log()->debug("org.project60.sepa: batching:syncGroups/createGroup ".$group['error_message']);
      }
    }
    else {
      try {
        $group = \Civi\Api4\SepaTransactionGroup::get(TRUE)
          ->addWhere('id', '=', $existing_groups[$collection_date][$financial_type_id ?? 0])
          ->addWhere('status_id', '=', $group_status_id_open)
          ->execute()
          ->single();
      }
      catch (\CRM_Core_Exception $exception) {
        // TODO: Error handling
        Civi::log()->debug('org.project60.sepa: batching:syncGroups/getGroup ' . $exception->getMessage());
      }
      unset($existing_groups[$collection_date][$financial_type_id ?? 0]);
    }

    return (int) $group['id'];
  }

  /**
   * subroutine to create the group/contribution structure as calculated
   * @param $calculated_groups  array [collection_date] -> array(contributions) as calculated
   * @param $existing_groups    array [collection_date] -> array(contributions) as currently present
   * @param $mode               SEPA mode (OOFF, RCUR, FRST)
   * @param $type               SEPA type (RCUR, FRST)
   * @param $notice             notice days
   * @param $creditor_id        SDD creditor ID
   * @param $partial_groups     Is this a partial update?
   * @param $partial_first      Is this the first call in a partial update?
   */
  protected static function syncGroups(
    $calculated_groups,
    $existing_groups,
    $mode,
    $type,
    $notice,
    $creditor_id,
    $partial_groups=FALSE,
    $partial_first=FALSE
  ) {
    $group_status_id_open = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Open');

    foreach ($calculated_groups as $collection_date => $financial_type_groups) {
      // check if we need to defer the collection date (e.g. due to bank holidays)
      self::deferCollectionDate($collection_date, $creditor_id);

      // If not using financial type grouping, flatten to a "0" financial type.
      if (!CRM_Sepa_Logic_Settings::getGenericSetting('sdd_financial_type_grouping')) {
        $financial_type_groups = [0 => array_merge(...$financial_type_groups)];
      }

      foreach ($financial_type_groups as $financial_type_id => $mandates) {
        $group_id = self::getOrCreateTransactionGroup(
          (int) $creditor_id,
          $mode,
          $collection_date,
          0 === $financial_type_id ? NULL : $financial_type_id,
          (int) $notice,
          $existing_groups
        );

        // now we have the right group. Prepare some parameters...
        $entity_ids = [];
        foreach ($mandates as $mandate) {
          // remark: "mandate_entity_id" in this case means the contribution ID
          if (empty($mandate['mandate_entity_id'])) {
            // this shouldn't happen
            Civi::log()
              ->debug("org.project60.sepa: batching:syncGroups mandate with bad mandate_entity_id ignored:" . $mandate['mandate_id']);
          }
          else {
            array_push($entity_ids, $mandate['mandate_entity_id']);
          }
        }
        if (count($entity_ids) <= 0) {
          continue;
        }

        // now, filter out the entity_ids that are are already in a non-open group
        //   (DO NOT CHANGE CLOSED GROUPS!)
        $entity_ids_list = implode(',', $entity_ids);
        $already_sent_contributions = CRM_Core_DAO::executeQuery(
          <<<SQL
              SELECT contribution_id
              FROM civicrm_sdd_contribution_txgroup
              LEFT JOIN civicrm_sdd_txgroup ON civicrm_sdd_contribution_txgroup.txgroup_id = civicrm_sdd_txgroup.id
              WHERE contribution_id IN ($entity_ids_list)
              AND  civicrm_sdd_txgroup.status_id <> $group_status_id_open;
              SQL
        );
        while ($already_sent_contributions->fetch()) {
          $index = array_search($already_sent_contributions->contribution_id, $entity_ids);
          if ($index !== FALSE) {
            unset($entity_ids[$index]);
          }
        }
        if (count($entity_ids) <= 0) {
          continue;
        }

        // remove all the unwanted entries from our group
        $entity_ids_list = implode(',', $entity_ids);
        if (!$partial_groups || $partial_first) {
          CRM_Core_DAO::executeQuery(
            <<<SQL
                DELETE FROM civicrm_sdd_contribution_txgroup
                  WHERE
                    txgroup_id=$group_id
                    AND contribution_id NOT IN ($entity_ids_list);
                SQL
          );
        }

        // remove all our entries from other groups, if necessary
        CRM_Core_DAO::executeQuery(
          <<<SQL
              DELETE FROM civicrm_sdd_contribution_txgroup
                WHERE txgroup_id!=$group_id
                AND contribution_id IN ($entity_ids_list);
              SQL
        );

        // now check which ones are already in our group...
        $existing = CRM_Core_DAO::executeQuery(
          <<<SQL
              SELECT *
              FROM civicrm_sdd_contribution_txgroup
              WHERE txgroup_id=$group_id
              AND contribution_id IN ($entity_ids_list);
              SQL
        );
        while ($existing->fetch()) {
          // remove from entity ids, if in there:
          if (($key = array_search($existing->contribution_id, $entity_ids)) !== FALSE) {
            unset($entity_ids[$key]);
          }
        }

        // the remaining must be added
        foreach ($entity_ids as $entity_id) {
          CRM_Core_DAO::executeQuery(
            <<<SQL
                INSERT INTO civicrm_sdd_contribution_txgroup (txgroup_id, contribution_id) VALUES ($group_id, $entity_id);
                SQL
          );
        }
      }
    }

    if (!$partial_groups) {
      // do some cleanup
      CRM_Sepa_Logic_Group::cleanup($mode);
    }
  }


  /**
   * Check if a transaction group reference is already in use
   */
  public static function referenceExists($reference) {
    return \Civi\Api4\SepaTransactionGroup::get(TRUE)
        ->selectRowCount()
        ->addWhere('reference', '=', $reference)
        ->execute()
        ->count() === 1;
  }

  public static function getTransactionGroupReference(
    int $creditorId,
    string $mode,
    string $collectionDate,
    ?int $financialTypeId = NULL
  ): string {
    $defaultReference = "TXG-{$creditorId}-{$mode}-{$collectionDate}";
    if (isset($financialTypeId)) {
      $defaultReference .= "-{$financialTypeId}";
    }

    $counter = 0;
    $reference = $defaultReference;
    while (self::referenceExists($reference)) {
      $counter += 1;
      $reference = "{$defaultReference}--".$counter;
    }

    // Call the hook.
    CRM_Utils_SepaCustomisationHooks::modify_txgroup_reference(
      $reference,
      $creditorId,
      $mode,
      $collectionDate,
      $financialTypeId
    );

    return $reference;
  }

  /**
   * Calculate the next execution date for a recurring contribution
   */
  public static function getNextExecutionDate($rcontribution, $now, $FRST = FALSE) {
    $now =  strtotime(date('Y-m-d', $now));     // ignore time of day
    $cycle_day = $rcontribution['cycle_day'];
    $interval = $rcontribution['frequency_interval'];
    $unit = $rcontribution['frequency_unit'];

    // calculate the first date
    $start_date = strtotime($rcontribution['start_date']);
    $next_date = mktime(0, 0, 0, date('n', $start_date) + (date('j', $start_date) > $cycle_day), $cycle_day, date('Y', $start_date));
    if (!$FRST && !empty($rcontribution['mandate_first_executed'])) {
      // if there is a first contribution, that dictates the cycle (12am)
      $next_date = strtotime(date('Y-m-d', strtotime($rcontribution['mandate_first_executed'])));

      // go back to last cycle day (in case the collection was delayed)
      while (date('j', $next_date) != $cycle_day) {
        $next_date = strtotime("-1 day", $next_date);
      }

      // then add one full cyle (to avoid problems with the FRST/RCUR status change)
      $next_date = strtotime("+{$interval} {$unit}", $next_date);
    }

    // for the FRST (first, start) contribution, only
    //  advance monthly, see ticket #309
    if ($FRST && ($unit=='month' || $unit=='year')) {
      $search_step = "+1 month";
    } else {
      $search_step = "+{$interval} {$unit}";
    }

    // take the first next_date that is in the future
    while ($next_date < $now) {
      $next_date = strtotime($search_step, $next_date);
    }

    // and check if it's not after the end_date
    $return_date = date('Y-m-d', $next_date);

    // Call a hook so extensions could alter the next collection date.
    CRM_Utils_SepaCustomisationHooks::alter_next_collection_date($return_date, $rcontribution);
    if (!empty($rcontribution['end_date']) && strtotime($rcontribution['end_date']) < strtotime($return_date)) {
      return NULL;
    }
    // ..or the cancel_date
    if (!empty($rcontribution['cancel_date']) && strtotime($rcontribution['cancel_date']) < strtotime($next_date)) {
      return NULL;
    }

    return $return_date;
  }

  /**
   * Get a string representation of the recurring contribution cycle day.
   * For monthly payments, this would be the cycle day,
   * while for annual payments this would be the cycle day and the month.
   */
  public static function getCycleDay($rcontribution, $creditor_id) {
    $cycle_day = $rcontribution['cycle_day'];
    $interval  = $rcontribution['frequency_interval'];
    $unit      = $rcontribution['frequency_unit'];
    if ($unit == 'year' || ($unit == 'month' && !($interval % 12))) {
      // this is an annual payment
      if (!empty($rcontribution['mandate_first_executed'])) {
        $date = $rcontribution['mandate_first_executed'];
      } else {
        $mandate = \Civi\Api4\SepaMandate::get(TRUE)
          ->addSelect('status')
          ->addWhere('entity_table', '=', 'civicrm_contribution_recur')
          ->addWhere('entity_id', '=', $rcontribution['id'])
          ->execute()
          ->single();

        if ($mandate['status'] == 'RCUR') {
          // support for RCUR without first contribution
          $now = strtotime("now");
          $date = CRM_Sepa_Logic_Batching::getNextExecutionDate($rcontribution, $now, FALSE);

        } else {
          // hasn't been collected yet
          $rcur_notice = (int) CRM_Sepa_Logic_Settings::getSetting("batching.FRST.notice", $creditor_id);
          $now = strtotime("now +$rcur_notice days");
          $date = CRM_Sepa_Logic_Batching::getNextExecutionDate($rcontribution, $now, TRUE);
        }
      }
      return CRM_Utils_Date::customFormat($date, E::ts("%B %E%f"));
    } elseif ($unit == 'week') {
      // FIXME: weekly not supported yet
      return '';

    } else {
      // this is a x-monthly payment
      return ts("%1.", array(1=>$cycle_day, 'domain' => 'org.project60.sepa'));
    }
  }

  /**
   * Get a string representation of the recurring contribution cycle,
   * e.g. 'weekly' or 'annually'
   */
  public static function getCycle($rcontribution) {
    $interval  = $rcontribution['frequency_interval'];
    $unit      = $rcontribution['frequency_unit'];
    return CRM_Utils_SepaOptionGroupTools::getFrequencyText($interval, $unit, TRUE);
  }

  /**
   * Apply any (date-based) defers on the collection date
   */
  public static function deferCollectionDate(&$collection_date, $creditor_id) {
    // first check if the weekends are to be excluded
    $exclude_weekends = CRM_Sepa_Logic_Settings::getGenericSetting('exclude_weekends');
    if ($exclude_weekends) {
      // skip (western) week ends, if the option is activated.
      $day_of_week = date('N', strtotime($collection_date));
      if ($day_of_week > 5) {
        // this is a weekend -> skip to Monday
        $defer_days = 8 - $day_of_week;
        $collection_date = date('Y-m-d', strtotime("+$defer_days day", strtotime($collection_date)));
      }
    }

    // also run the hook, in case somebody has implemented special holidays
    CRM_Utils_SepaCustomisationHooks::defer_collection_date($collection_date, $creditor_id);
  }
}
