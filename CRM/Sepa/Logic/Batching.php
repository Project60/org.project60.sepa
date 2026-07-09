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

declare(strict_types = 1);

use Civi\Api4\Contribution;
use Civi\Api4\SepaMandate;
use Civi\Sepa\Lock\SepaBatchLockManager;
use CRM_Sepa_ExtensionUtil as E;

/**
 * This class holds all SEPA batching functions
 */
class CRM_Sepa_Logic_Batching {

  /**
   * runs a batching update for all RCUR mandates for the given creditor
   *
   * @param int $creditorId the creaditor to be batched
   * @param 'FRST'|'RCUR' $mode
   * @param string $now
   *   Overwrite what is used as "now" for batching, can be everything valid for
   *   strtotime, a "+n days" is added.
   * @param int|null $offset
   * @param int|null $limit
   */
  // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded
  public static function updateRCUR(
    int $creditorId,
    string $mode,
    string $now = 'now',
    ?int $offset = NULL,
    ?int $limit = NULL
  ): ?string {
    // check lock
    $lock = SepaBatchLockManager::getInstance()->getLock();
    if (!$lock->acquire()) {
      return 'Batching in progress. Please try again later.';
    }

    // @phpstan-ignore cast.int
    $horizon = (int) CRM_Sepa_Logic_Settings::getSetting('batching.RCUR.horizon', $creditorId);
    // @phpstan-ignore cast.int
    $grace_period = (int) CRM_Sepa_Logic_Settings::getSetting('batching.RCUR.grace', $creditorId);
    $latestDate = date('Y-m-d', strtotime("$now +$horizon days"));

    // @phpstan-ignore cast.int
    $rcurNotice = (int) CRM_Sepa_Logic_Settings::getSetting("batching.$mode.notice", $creditorId);
    // (virtually) move ahead notice_days, but also go back grace days
    /** @var int $now */
    $now = strtotime("$now +$rcurNotice days -$grace_period days");
    // round to full day
    /** @var int $now */
    $now = strtotime(date('Y-m-d', $now));
    $groupStatusIdOpen = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Open');

    // get payment instruments
    $paymentInstruments = CRM_Sepa_Logic_PaymentInstruments::getPaymentInstrumentsForCreditor($creditorId, $mode);
    $paymentInstrumentIds = array_keys($paymentInstruments);
    if ([] === $paymentInstrumentIds) {
      // disabled
      return NULL;
    }

    $firstContributionIdOperator = 'FRST' === $mode ? 'IS NULL' : 'IS NOT NULL';

    // RCUR-STEP 0: check/repair mandates
    $mandateClause = "mandate.type = 'RCUR' AND mandate.creditor_id = $creditorId
    AND (mandate.status = '$mode'
      OR mandate.status = 'ONHOLD' AND mandate.first_contribution_id $firstContributionIdOperator)";
    if ($limit !== NULL) {
      $mandateClause .= " LIMIT $limit";
    }
    if ($offset !== NULL) {
      $mandateClause .= " OFFSET $offset";
    }
    // TODO: Does this need changes for Financial ACLs?
    CRM_Sepa_Logic_MandateRepairs::runWithMandateSelector(
      $mandateClause,
      TRUE
    );

    // RCUR-STEP 1: find all active/pending RCUR mandates within the horizon that are NOT in a closed batch and that
    // have a corresponding contribution of a financial type the user has access to (implicit condition added by
    // Financial ACLs extension if enabled)
    /**
     * @var list<array{
     *   id: positive-int,
     *   contact_id: ?positive-int,
     *   entity_id: positive-int,
     *   source: ?string,
     *   creditor_id: ?positive-int,
     *   status: string,
     *   "first_contribution.receive_date": ?string,
     *   "contribution_recur.cycle_day": positive-int,
     *   "contribution_recur.frequency_interval": positive-int,
     *   "contribution_recur.frequency_unit": ?string,
     *   "contribution_recur.start_date": string,
     *   "contribution_recur.cancel_date": ?string,
     *   "contribution_recur.end_date": ?string,
     *   "contribution_recur.amount": float,
     *   "contribution_recur.is_test": bool,
     *   "contribution_recur.contact_id": positive-int,
     *   "contribution_recur.financial_type_id": ?positive-int,
     *   "contribution_recur.contribution_status_id:name": ?string,
     *   "contribution_recur.currency": ?string,
     *   "contribution_recur.campaign_id"?: ?positive-int,
     *   "contribution_recur.payment_instrument_id": ?positive-int,
     *  }> $relevantMandates */
    $relevantMandates = SepaMandate::get(TRUE)
      ->addSelect(
        'id',
        'contact_id',
        'entity_id',
        'source',
        'creditor_id',
        'status',
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
        'contribution_recur.contribution_status_id:name',
        'contribution_recur.currency',
        'contribution_recur.campaign_id',
        'contribution_recur.payment_instrument_id'
      )
      ->addJoin(
        'ContributionRecur AS contribution_recur',
        'INNER',
        NULL,
        ['entity_table', '=', '"civicrm_contribution_recur"'],
        ['entity_id', '=', 'contribution_recur.id']
      )
      ->addJoin(
        'Contribution AS first_contribution',
        'LEFT',
        NULL,
        ['first_contribution_id', '=', 'first_contribution.id']
      )
      ->addWhere('type', '=', 'RCUR')
      ->addClause(
        'OR',
        ['status', '=', $mode],
        [
          'AND', [
            ['status', '=', 'ONHOLD'],
            ['first_contribution_id', $firstContributionIdOperator],
          ],
        ],
      )
      ->addWhere('creditor_id', '=', $creditorId)
      ->setLimit($limit ?? 0)
      ->setOffset($offset ?? 0)
      ->execute()
      ->getArrayCopy();

    $deferredCollectionDates = [];
    $rcontribIdsByCollectionDate = [];
    $mandatesByCollectionDateAndFinancialTypeId = [];
    foreach ($relevantMandates as $mandate) {
      // TODO: Use API attribute names in subsequent code instead of copying to legacy name elements.
      $mandate += [
        'mandate_id' => $mandate['id'],
        'mandate_first_executed' => $mandate['first_contribution.receive_date'],
        'cycle_day' => $mandate['contribution_recur.cycle_day'],
        'frequency_interval' => $mandate['contribution_recur.frequency_interval'],
        'frequency_unit' => $mandate['contribution_recur.frequency_unit'],
        'start_date' => $mandate['contribution_recur.start_date'],
        'end_date' => $mandate['contribution_recur.end_date'],
        'cancel_date' => $mandate['contribution_recur.cancel_date'],
      ];

      // RCUR-STEP 2: calculate next execution date
      $nextDate = self::getNextExecutionDate($mandate, $now, $mode === 'FRST');
      if (NULL === $nextDate || $nextDate > $latestDate) {
        continue;
      }

      // apply any deferrals
      if (!isset($deferredCollectionDates[$nextDate])) {
        $deferredDate = $nextDate;
        self::deferCollectionDate($deferredDate, $creditorId);
        $deferredCollectionDates[$nextDate] = $deferredDate;
      }
      $nextDate = $deferredCollectionDates[$nextDate];

      $rcontribIdsByCollectionDate[$nextDate][] = $mandate['entity_id'];
      $financialTypeId = $mandate['contribution_recur.financial_type_id'];
      $mandatesByCollectionDateAndFinancialTypeId[$nextDate][$financialTypeId][] = $mandate;
    }

    // RCUR-STEP 3: find already created contributions
    $existingContributionsByRecurId = [];
    foreach ($rcontribIdsByCollectionDate as $collectionDate => $rcontribIds) {
      $existingContributionsByRecurId += Contribution::get(FALSE)
        ->addSelect('id', 'contribution_recur_id', 'civi_sepa_contribution.is_on_hold')
        ->addJoin('SepaContributionGroup AS ctxg', 'LEFT', NULL, ['ctxg.contribution_id', '=', 'id'])
        ->addJoin('SepaTransactionGroup AS txg', 'LEFT', NULL, ['txg.id', '=', 'ctxg.txgroup_id'])
        ->addWhere('contribution_recur_id', 'IN', $rcontribIds)
        ->addWhere('DATE(receive_date)', '=', $collectionDate)
        ->addClause('OR', [['txg.type', 'IS NULL'], ['txg.type', 'IN', ['RCUR', 'FRST']]])
        ->addWhere('payment_instrument_id', 'IN', $paymentInstrumentIds)
        // CiviCRM would automatically add "is_test = 0" without this condition.
        ->addWhere('is_test', 'IS NOT NULL')
        ->execute()
        ->indexBy('contribution_recur_id')
        ->getArrayCopy();
      /** @var array<int, array{id: int, contribution_recur_id: int, "civi_sepa_contribution.is_on_hold": ?bool}> $existingContributionsByRecurId */
    }

    // RCUR-STEP 4: Create the missing contributions. Store contribution IDs
    // (existing and created) in $mandate['mandate_entity_id']. Remove mandates
    // in status "ONHOLD" so contribution won't be added to transaction group.
    foreach ($mandatesByCollectionDateAndFinancialTypeId as $collectionDate => &$mandatesByFinancialTypeId) {
      foreach ($mandatesByFinancialTypeId as &$mandates) {
        foreach ($mandates as $index => &$mandate) {
          $recurId = $mandate['entity_id'];
          if (isset($existingContributionsByRecurId[$recurId])) {
            // if the contribution already exists, store it
            $mandate['mandate_entity_id'] = $existingContributionsByRecurId[$recurId]['id'];
            if (TRUE === $existingContributionsByRecurId[$recurId]['civi_sepa_contribution.is_on_hold']) {
              // Don't add on hold contribution to transaction group.
              unset($mandates[$index]);
            }
            unset($existingContributionsByRecurId[$recurId]);
          }
          else {
            // else: create it
            $installmentPaymentInstrumentId = CRM_Sepa_Logic_PaymentInstruments::getInstallmentPaymentInstrument(
              $creditorId, $mandate['contribution_recur.payment_instrument_id'], $mode === 'FRST'
            );

            /** @var array{id: positive-int, ...} $contribution */
            $contribution = Contribution::create(FALSE)
              ->setValues([
                'total_amount' => $mandate['contribution_recur.amount'],
                'currency' => $mandate['contribution_recur.currency'],
                'receive_date' => $collectionDate,
                'contact_id' => $mandate['contribution_recur.contact_id'],
                'contribution_recur_id' => $recurId,
                'source' => $mandate['source'],
                'financial_type_id' => $mandate['contribution_recur.financial_type_id'],
                'contribution_status_id:name' => 'ONHOLD' === $mandate['status']
                ? 'Pending' : $mandate['contribution_recur.contribution_status_id:name'],
                'campaign_id' => $mandate['contribution_recur.campaign_id'] ?? NULL,
                'is_test' => $mandate['contribution_recur.is_test'],
                'payment_instrument_id' => $installmentPaymentInstrumentId,
                'civi_sepa_contribution.is_on_hold' => 'ONHOLD' === $mandate['status'],
              ])
              ->execute()
              ->single();

            $mandate['mandate_entity_id'] = $contribution['id'];
            if ('ONHOLD' === $mandate['status']) {
              // Don't add on hold contribution to transaction group.
              unset($mandates[$index]);
            }

            CRM_Utils_SepaCustomisationHooks::installment_created($mandate['id'], $recurId, $contribution['id']);
          }
        }
      }
    }

    // delete unused contributions:
    foreach ($existingContributionsByRecurId as $contribution) {
      // TODO: is this needed?
      Civi::log()->debug("org.project60.sepa: batching: contribution {$contribution['id']} should be deleted...");
    }

    // step 5: find all existing OPEN groups
    $sqlQuery = "
      SELECT
        txgroup.collection_date AS collection_date,
        txgroup.financial_type_id AS financial_type_id,
        txgroup.id AS txgroup_id
      FROM civicrm_sdd_txgroup AS txgroup
      WHERE txgroup.type = '$mode'
        AND txgroup.sdd_creditor_id = $creditorId
        AND txgroup.status_id = $groupStatusIdOpen;";
    /** @var \CRM_Core_DAO $results */
    $results = CRM_Core_DAO::executeQuery($sqlQuery);
    $existingGroups = [];
    while ($results->fetch()) {
      $collection_date = date('Y-m-d', strtotime($results->collection_date));
      $existingGroups[$collection_date][$results->financial_type_id ?? 0] = (int) $results->txgroup_id;
    }

    // step 6: sync calculated group structure with existing (open) groups
    self::syncGroups(
      $mandatesByCollectionDateAndFinancialTypeId,
      $existingGroups,
      $mode,
      $rcurNotice,
      $creditorId,
      NULL !== $offset,
      0 === $offset
    );

    return NULL;
  }

  /**
   * runs a batching update for all OOFF mandates
   *
   * @param int $creditor_id  the creditor ID to run this for
   * @param string $now
   *   Overwrite what is used as "now" for batching, can be everything valid for
   *   strtotime, a "+n days" is added.
   * @param int|null $offset used for segmented updates
   * @param int|null $limit used for segmented updates
   */
  // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
  public static function updateOOFF(
    int $creditor_id,
    string $now = 'now',
    ?int $offset = NULL,
    ?int $limit = NULL
  ): ?string {
    // check lock
    $lock = SepaBatchLockManager::getInstance()->getLock();
    if (!$lock->acquire()) {
      return 'Batching in progress. Please try again later.';
    }

    // @phpstan-ignore cast.int
    $horizon = (int) CRM_Sepa_Logic_Settings::getSetting('batching.OOFF.horizon', $creditor_id);
    // @phpstan-ignore cast.int
    $ooff_notice = (int) CRM_Sepa_Logic_Settings::getSetting('batching.OOFF.notice', $creditor_id);
    $group_status_id_open = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Open');
    $date_limit = date('Y-m-d', strtotime("$now +$horizon days"));

    // step 1: find all active/pending OOFF mandates within the horizon that are NOT in a closed batch and that have a
    // corresponding contribution of a financial type the user has access to (implicit condition added by Financial ACLs
    // extension if enabled).
    /** @var list<array<string, mixed>> $relevant_mandates */
    $relevant_mandates = SepaMandate::get(TRUE)
      ->addSelect('id', 'contact_id', 'entity_id', 'contribution.receive_date', 'contribution.financial_type_id')
      ->addJoin(
        'Contribution AS contribution',
        'INNER',
        NULL,
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
      // TODO: Use API attribute names in subsequent code instead of copying to legacy name elements.
      $mandate['mandate_id'] = $mandate['id'];
      $mandate['mandate_contact_id'] = $mandate['contact_id'];
      $mandate['mandate_entity_id'] = $mandate['entity_id'];
      $mandate['start_date'] = $mandate['contribution.receive_date'];
      /** @var int $financialTypeId */
      $financialTypeId = $mandate['financial_type_id'] = $mandate['contribution.financial_type_id'];

      $collection_date = date('Y-m-d', strtotime($mandate['start_date']));
      if ($collection_date <= $earliest_collection_date) {
        $collection_date = $earliest_collection_date;
      }
      // Defer collection date if necessary (e.g. due to bank holidays)
      self::deferCollectionDate($collection_date, $creditor_id);

      $calculated_groups[$collection_date][$financialTypeId] ??= [];
      $calculated_groups[$collection_date][$financialTypeId][] = $mandate;

      if ($collection_date > $latest_collection_date) {
        $latest_collection_date = $collection_date;
      }
    }

    if (!$latest_collection_date) {
      // nothing to do...
      return NULL;
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
    /** @var \CRM_Core_DAO $results */
    $results = CRM_Core_DAO::executeQuery($sql_query);
    $existing_groups = [];
    while ($results->fetch()) {
      $collection_date = date('Y-m-d', strtotime($results->collection_date));
      $existing_groups[$collection_date][$results->financial_type_id ?? 0] = (int) $results->txgroup_id;
    }

    // step 4: sync calculated group structure with existing (open) groups
    self::syncGroups(
      $calculated_groups,
      $existing_groups,
      'OOFF',
      $ooff_notice,
      $creditor_id,
      $offset !== NULL,
      $offset === 0
    );

    return NULL;
  }

  /**
   * Maintenance: Close all mandates that have expired
   *
   * @return string|null
   *   Error message on error, NULL otherwise.
   */
  public static function closeEnded(): ?string {
    // check lock
    $lock = SepaBatchLockManager::getInstance()->getLock();
    if (!$lock->acquire()) {
      return 'Batching in progress. Please try again later.';
    }

    $contribution_status_closed = (int) CRM_Core_PseudoConstant::getKey(
      'CRM_Contribute_BAO_Contribution',
      'contribution_status_id',
      'Completed'
    );

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
      INNER JOIN civicrm_contribution_recur AS rcontribution
        ON mandate.entity_id = rcontribution.id
        AND mandate.entity_table = 'civicrm_contribution_recur'
      WHERE mandate.type = 'RCUR'
        AND mandate.status IN ('RCUR','FRST')
        AND end_date <= DATE(NOW());";
    /** @var \CRM_Core_DAO $results */
    $results = CRM_Core_DAO::executeQuery($sql_query);
    $mandates_to_end = [];
    while ($results->fetch()) {
      $mandates_to_end[] = [
        'mandate_id'      => $results->mandate_id,
        'recur_id'        => $results->mandate_entity_id,
        'creation_date'   => $results->mandate_creation_date,
        'validation_date' => $results->mandate_validation_date,
        'date'            => $results->mandate_date,
        'currency'        => $results->currency,
      ];
    }

    // then, end them one by one
    foreach ($mandates_to_end as $mandate_to_end) {
      $change_mandate = civicrm_api3('SepaMandate', 'create', [
        'id'                      => $mandate_to_end['mandate_id'],
        'date'                    => $mandate_to_end['date'],
        'creation_date'           => $mandate_to_end['creation_date'],
        'validation_date'         => $mandate_to_end['validation_date'],
        'status'                  => 'COMPLETE',
      ]);
      if (isset($change_mandate['is_error']) && $change_mandate['is_error']) {
        return sprintf(
          "Couldn't set mandate '%s' to 'complete. Error was: '%s'",
          $mandate_to_end['mandate_id'],
          $change_mandate['error_message']
        );
      }

      $change_rcur = civicrm_api3('ContributionRecur', 'create', [
        'id'                      => $mandate_to_end['recur_id'],
        'contribution_status_id'  => $contribution_status_closed,
        'modified_date'           => date('YmdHis'),
        'currency'                => $mandate_to_end['currency'],
      ]);
      if (isset($change_rcur['is_error']) && $change_rcur['is_error']) {
        return sprintf(
          "Couldn't set recurring contribution '%s' to 'complete. Error was: '%s'",
          $mandate_to_end['recur_id'],
          $change_rcur['error_message']
        );
      }
    }

    return NULL;
  }

  /**
   * HELPERS *
   */
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

      $group = civicrm_api3('SepaTransactionGroup', 'create', [
        'reference'               => $reference,
        'type'                    => $mode,
        'collection_date'         => $collection_date,
        // Financial type may be NULL if not grouping by financial type.
        'financial_type_id'       => $financial_type_id,
        'latest_submission_date'  => date('Y-m-d', strtotime("-$notice days", strtotime($collection_date))),
        'created_date'            => date('Y-m-d'),
        'status_id'               => $group_status_id_open,
        'sdd_creditor_id'         => $creditor_id,
      ]);
      if (!empty($group['is_error'])) {
        throw new \CRM_Core_Exception(sprintf('Could not create transaction group: %s', $group['error_message']));
      }
    }
    else {
      $group = \Civi\Api4\SepaTransactionGroup::get(TRUE)
        ->addWhere('id', '=', $existing_groups[$collection_date][$financial_type_id ?? 0])
        ->addWhere('status_id', '=', $group_status_id_open)
        ->execute()
        ->single();
      unset($existing_groups[$collection_date][$financial_type_id ?? 0]);
    }

    return (int) $group['id'];
  }

  /**
   * subroutine to create the group/contribution structure as calculated
   *
   * @param array<string, array<int, array<array<string, mixed>>>> $calculated_groups
   *   [(deferred) collection_date] => [financial_type_id] => list(mandates) as calculated
   * @param array<string, array<int, int>> $existing_groups
   *   [collection_date]=> [financial_type_id] => txgroup_id as currently present
   * @param 'OOFF'|'RCUR'|'FRST' $mode SEPA mode (OOFF, RCUR, FRST)
   * @param int $notice notice days
   * @param int $creditor_id SDD creditor ID
   * @param bool $partial_groups Is this a partial update?
   * @param bool $partial_first Is this the first call in a partial update?
   */
  // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
  protected static function syncGroups(
    array $calculated_groups,
    array $existing_groups,
    string $mode,
    int $notice,
    int $creditor_id,
    bool $partial_groups = FALSE,
    bool $partial_first = FALSE
  ): void {
    $group_status_id_open = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Open');

    foreach ($calculated_groups as $collection_date => $financial_type_groups) {
      // If not using financial type grouping, flatten to a "0" financial type.
      if (!CRM_Sepa_Logic_Settings::getGenericSetting('sdd_financial_type_grouping')) {
        $financial_type_groups = [0 => array_merge(...$financial_type_groups)];
      }

      foreach ($financial_type_groups as $financial_type_id => $mandates) {
        $group_id = self::getOrCreateTransactionGroup(
          $creditor_id,
          $mode,
          $collection_date,
          0 === $financial_type_id ? NULL : $financial_type_id,
          $notice,
          $existing_groups
        );

        // now we have the right group. Prepare some parameters...
        $entity_ids = [];
        foreach ($mandates as $mandate) {
          // remark: "mandate_entity_id" in this case means the contribution ID
          if (empty($mandate['mandate_entity_id'])) {
            // this shouldn't happen
            Civi::log()->debug(
              'org.project60.sepa: batching:syncGroups mandate with bad mandate_entity_id ignored:'
              . $mandate['mandate_id']
            );
          }
          else {
            array_push($entity_ids, $mandate['mandate_entity_id']);
          }
        }
        if (count($entity_ids) <= 0) {
          continue;
        }

        // now, filter out the entity_ids that are already in a non-open group
        //   (DO NOT CHANGE CLOSED GROUPS!)
        $entity_ids_list = implode(',', $entity_ids);
        /** @var \CRM_Core_DAO $already_sent_contributions */
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
        /** @var \CRM_Core_DAO $existing */
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
              INSERT INTO civicrm_sdd_contribution_txgroup (txgroup_id, contribution_id)
              VALUES ($group_id, $entity_id);
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
  public static function referenceExists(string $reference): bool {
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
      $reference = "{$defaultReference}--" . $counter;
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
  public static function getNextExecutionDate(array $rcontribution, int $now, bool $FRST = FALSE): ?string {
    // ignore time of day
    $now = strtotime(date('Y-m-d', $now));
    $cycle_day = (int) $rcontribution['cycle_day'];
    $interval = $rcontribution['frequency_interval'];
    $unit = $rcontribution['frequency_unit'];

    // calculate the first date
    $start_date = strtotime($rcontribution['start_date']);
    $next_date = mktime(
      0,
      0,
      0,
      (int) date('n', $start_date) + (((int) date('j', $start_date) > $cycle_day) ? 1 : 0),
      $cycle_day,
      (int) date('Y', $start_date)
    );
    if (!$FRST && !empty($rcontribution['mandate_first_executed'])) {
      // if there is a first contribution, that dictates the cycle (12am)
      $next_date = strtotime(date('Y-m-d', strtotime($rcontribution['mandate_first_executed'])));

      // go back to last cycle day (in case the collection was delayed)
      while (((int) date('j', $next_date)) != $cycle_day) {
        $next_date = strtotime('-1 day', $next_date);
      }

      // then add one full cycle (to avoid problems with the FRST/RCUR status change)
      $next_date = strtotime("+{$interval} {$unit}", $next_date);
    }

    // for the FRST (first, start) contribution, only
    //  advance monthly, see ticket #309
    if ($FRST && ($unit == 'month' || $unit == 'year')) {
      $search_step = '+1 month';
    }
    else {
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
    // ...or the cancel_date
    if (!empty($rcontribution['cancel_date']) && strtotime($rcontribution['cancel_date']) < $next_date) {
      return NULL;
    }

    return $return_date;
  }

  /**
   * Get a string representation of the recurring contribution cycle day.
   * For monthly payments, this would be the cycle day,
   * while for annual payments this would be the cycle day and the month.
   */
  public static function getCycleDay(array $rcontribution, int $creditor_id): string {
    $cycle_day = $rcontribution['cycle_day'];
    $interval  = $rcontribution['frequency_interval'];
    $unit      = $rcontribution['frequency_unit'];
    if ($unit == 'year' || ($unit == 'month' && !($interval % 12))) {
      // this is an annual payment
      if (!empty($rcontribution['mandate_first_executed'])) {
        $date = $rcontribution['mandate_first_executed'];
      }
      else {
        $mandate = SepaMandate::get(TRUE)
          ->addSelect('status')
          ->addWhere('entity_table', '=', 'civicrm_contribution_recur')
          ->addWhere('entity_id', '=', $rcontribution['id'])
          ->execute()
          ->single();

        if ($mandate['status'] == 'RCUR') {
          // support for RCUR without first contribution
          $now = strtotime('now');
          $date = CRM_Sepa_Logic_Batching::getNextExecutionDate($rcontribution, $now, FALSE);

        }
        else {
          // hasn't been collected yet
          // @phpstan-ignore cast.int
          $rcur_notice = (int) CRM_Sepa_Logic_Settings::getSetting('batching.FRST.notice', $creditor_id);
          $now = strtotime("now +$rcur_notice days");
          $date = CRM_Sepa_Logic_Batching::getNextExecutionDate($rcontribution, $now, TRUE);
        }
      }
      return CRM_Utils_Date::customFormat($date, E::ts('%B %E%f'));
    }
    elseif ($unit == 'week') {
      // FIXME: weekly not supported yet
      return '';

    }
    else {
      // this is a x-monthly payment
      return E::ts('%1.', [1 => $cycle_day]);
    }
  }

  /**
   * Get a string representation of the recurring contribution cycle,
   * e.g. 'weekly' or 'annually'
   */
  public static function getCycle(array $rcontribution): string {
    $interval  = (int) $rcontribution['frequency_interval'];
    $unit      = $rcontribution['frequency_unit'];
    return CRM_Utils_SepaOptionGroupTools::getFrequencyText($interval, $unit, TRUE);
  }

  /**
   * Apply any (date-based) defers on the collection date
   */
  public static function deferCollectionDate(string &$collection_date, int $creditor_id): void {
    // first check if the weekends are to be excluded
    $exclude_weekends = CRM_Sepa_Logic_Settings::getGenericSetting('exclude_weekends');
    if ($exclude_weekends) {
      // skip (western) week ends, if the option is activated.
      $day_of_week = (int) date('N', strtotime($collection_date));
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
