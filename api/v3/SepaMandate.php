<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 TTTP / SYSTOPIA                |
| Author: X+                                             |
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

use Civi\Api4\SepaMandate;

/**
 * Add an SepaCreditor for a contact
 *
 * Allowed @params array keys are:
 *
 * @example SepaCreditorCreate.php Standard Create Example
 *
 * @param array<string, mixed> $params
 *
 * @return array<string, mixed> API result array
 *   {@getfields sepa_mandate_create}
 * @access public
 */
function civicrm_api3_sepa_mandate_create(array $params): array {
  _civicrm_api3_sepa_mandate_adddefaultcreditor($params);
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Provide Metadata for SepaMandate.create
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array<string, array<string, mixed>> $params array or parameters determined by getfields
 */
function _civicrm_api3_sepa_mandate_create_spec(array &$params): void {
  $params['entity_id'] = [
    'name'         => 'entity_id',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Entity ID of linked contribution',
  ];
  $params['entity_table'] = [
    'name'         => 'entity_table',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Table name of linked contribution',
  ];
  $params['type'] = [
    'name'         => 'type',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'RCUR for recurring, OOFF for one-off mandate',
  ];
  $params['status'] = [
    'name'         => 'status',
    'api.default'  => 'INIT',
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Mandate status',
  ];
}

/**
 * Creates a mandate object along with its "contract",
 * i.e. the payment details as recorded in an
 * associated contribution or recurring contribution
 *
 * @param array{
 *   creditor_id: int|numeric-string,
 *   type: string,
 *   status?: string,
 *   payment_instrument_id?: int|numeric-string,
 *   financial_type_id: int|numeric-string,
 *   bic?: string,
 *   contribution_contact_id?: int|numeric-string,
 *   currency?: string,
 *   contribution_status_id?: int|numeric-string,
 *   is_pay_later?: bool|scalar,
 *   total_amount?: int|float|numeric-string,
 *   amount: int|float|numeric-string,
 *   ...
 * } $params
 *
 * @return array<string, mixed> API result array
 */
// phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded
function civicrm_api3_sepa_mandate_createfull(array $params): array {
  $values = $params;
  unset($values['check_permissions']);
  unset($values['debug']);
  unset($values['version']);
  $result = SepaMandate::createFull($params['check_permissions'] ?? FALSE)
    ->setValues($values)
    ->execute()
    ->getArrayCopy();

  return civicrm_api3_create_success($result, $params, 'SepaMandate', 'createfull');
}

/**
 * Provide Metadata for SepaMandate.createfull
 *
 * @param array<string, array<string, mixed>> $params
 */
function _civicrm_api3_sepa_mandate_createfull_spec(array &$params): void {
  $params['contact_id'] = [
    'name'         => 'contact_id',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Contact ID for the new mandate',
  ];
  $params['type'] = [
    'name'         => 'type',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'RCUR for recurring, OOFF for one-off',
  ];
  $params['account_holder'] = [
    'name'         => 'account_holder',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Account holder for the mandate',
  ];
  $params['iban'] = [
    'name'         => 'iban',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'IBAN (account number) for the mandate',
  ];
  $params['bic'] = [
    'name'         => 'bic',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'BIC (bank number) for the mandate',
  ];
  $params['amount'] = [
    'name'         => 'amount',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_FLOAT,
    'title'        => 'Nominal (installment) amount',
  ];
  $params['reference'] = [
    'name'         => 'reference',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Mandate reference. Will be calculated if omitted',
  ];
  $params['source'] = [
    'name'         => 'source',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Source of the mandate',
  ];
  $params['date'] = [
    'name'         => 'date',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
    'title'        => 'Signature date',
  ];
  $params['creation_date'] = [
    'name'         => 'creation_date',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
    'title'        => 'Creation date',
  ];
  $params['validation_date'] = [
    'name'         => 'validation_date',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
    'title'        => 'Validation date',
  ];
  $params['is_enabled'] = [
    'name'         => 'is_enabled',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Mandate enabled?',
  ];
  $params['creditor_id'] = [
    'name'         => 'creditor_id',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Creditor ID. Default creditor used if omitted',
  ];
  $params['financial_type_id'] = [
    'name'         => 'financial_type_id',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Financial type of the contribution(s)',
  ];
  $params['payment_instrument_id'] = [
    'name'         => 'payment_instrument_id',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Payment Method',
  ];
  $params['campaign_id'] = [
    'name'         => 'campaign_id',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Campaign of the contribution(s)',
  ];
  $params['receive_date'] = [
    'name'         => 'receive_date',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
    'title'        => 'Collection date (only for OOFF)',
  ];
  $params['start_date'] = [
    'name'         => 'start_date',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
    'title'        => 'Start of collection (only for RCUR)',
  ];
  $params['end_date'] = [
    'name'         => 'end_date',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
    'title'        => 'End of collection (only for RCUR)',
  ];
  $params['frequency_interval'] = [
    'name'         => 'frequency_interval',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Collection interval (together with frequency_unit, only for RCUR)',
  ];
  $params['frequency_unit'] = [
    'name'         => 'frequency_unit',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Collection interval unit (together with frequency_interval, only for RCUR)',
  ];
  $params['cycle_day'] = [
    'name'         => 'cycle_day',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Day of the month (for collection, only for RCUR)',
  ];
}

/**
 * Update next scheduled collection day
 *
 * This can be used for migration or recovery after profound changes
 *
 * @param array{mode: string, mandate_ids?: string} $params
 *
 * @return array<string, mixed>
 */
function civicrm_api3_sepa_mandate_update_next_scheduled_date(array $params): array {
  $restrictions = [];
  if ($params['mode'] == 'empty') {
    $restrictions[] = 'civicrm_contribution_recur.next_sched_contribution_date IS NULL';
  }
  // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedElseif
  elseif ($params['mode'] == 'all') {
    // no extra restrictions
  }
  else {
    return civicrm_api3_create_error("Please select mode 'empty' or 'all'.");
  }

  if (!empty($params['mandate_ids'])) {
    // sanitize input string
    $mandate_id_list = preg_replace('#[^0-9,]#', '', $params['mandate_ids']);
    if (!empty($mandate_id_list)) {
      $restrictions[] = "civicrm_sdd_mandate.id IN ($mandate_id_list)";
    }
  }

  // add general restrictions
  // only type RCUR
  $restrictions[] = "civicrm_sdd_mandate.type = 'RCUR'";
  // only active ones
  $restrictions[] = "civicrm_sdd_mandate.status IN ('FRST','RCUR')";
  $restrictions_sql = '(' . implode(') AND (', $restrictions) . ')';

  $query = "
  SELECT
    civicrm_contribution_recur.id   AS civicrm_contribution_recur_id,
    civicrm_sdd_mandate.creditor_id AS creditor_id
  FROM civicrm_contribution_recur
  LEFT JOIN civicrm_sdd_mandate
    ON civicrm_contribution_recur.id = civicrm_sdd_mandate.entity_id
    AND civicrm_sdd_mandate.entity_table = 'civicrm_contribution_recur'
  WHERE {$restrictions_sql}
  ORDER BY civicrm_sdd_mandate.creditor_id";

  /** @var \CRM_Core_DAO $recurring_contributions */
  $recurring_contributions = CRM_Core_DAO::executeQuery($query);
  $counter = 0;
  $updater = NULL;
  while ($recurring_contributions->fetch()) {
    $counter++;

    if (!$updater || !$updater->usesCreditor((int) $recurring_contributions->creditor_id)) {
      $updater = new CRM_Sepa_Logic_NextCollectionDate((int) $recurring_contributions->creditor_id);
    }

    $updater->updateNextCollectionDate((int) $recurring_contributions->civicrm_contribution_recur_id, NULL);
  }

  $null = NULL;
  return civicrm_api3_create_success([], $params, $null, $null, $null, ['count' => $counter]);
}

/**
 * Provide Metadata for SepaMandate.create
 *
 * @param array<string, array<string, mixed>> $params
 */
function _civicrm_api3_sepa_mandate_update_next_scheduled_date_spec(array &$params): void {
  $params['mode'] = [
    'name'         => 'mode',
    'api.default'  => 'empty',
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Should all be updated ("all") or only empty ones ("empty")',
  ];
  $params['mandate_ids'] = [
    'name'         => 'mandate_ids',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Restrict to the given (comma separated) list of mandate IDs',
  ];
}

/**
 * Deletes an existing Mandate
 *
 * @param array<string, mixed> $params
 *
 * @return array<string, mixed>
 *   {@getfields sepa_mandate_delete}
 * @access public
 */
function civicrm_api3_sepa_mandate_delete(array $params): array {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Retrieve one or more sepa_mandates
 *
 * @example SepaCreditorGet.php Standard Get Example
 *
 * @param array<string, mixed> $params an associative array of name/value pairs.
 *
 * @return array<string, mixed> api result array
 *   {@getfields sepa_mandate_get}
 * @access public
 */
function civicrm_api3_sepa_mandate_get(array $params): array {
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Modify/update mandates
 *
 * @param array{mandate_id?: int|numeric-string, reference?: string, ...} $params
 *
 * @return array<string, mixed>
 *
 * @see https://github.com/Project60/org.project60.sepa/issues/413
 */
function civicrm_api3_sepa_mandate_modify(array $params): array {
  if (!CRM_Sepa_Logic_Settings::getSetting('allow_mandate_modification')) {
    return civicrm_api3_create_error('Mandate modification not allowed. Check your settings.');
  }

  // look up mandate ID if only reference is given
  if (empty($params['mandate_id']) && !empty($params['reference'])) {
    try {
      $params['mandate_id'] = SepaMandate::get(TRUE)
        ->addSelect('id')
        ->addWhere('reference', '=', $params['reference'])
        ->execute()
        ->single()['id'];
    }
    catch (\CRM_Core_Exception $exception) {
      return civicrm_api3_create_error("Couldn't identify mandate with reference '{$params['reference']}'.");
    }
  }

  // no mandate could be identified
  if (empty($params['mandate_id'])) {
    return civicrm_api3_create_error("You need to provide either 'mandate_id' or 'reference'.");
  }

  try {
    $changes = CRM_Sepa_BAO_SEPAMandate::modifyMandate((int) $params['mandate_id'], $params);
    return civicrm_api3_create_success($changes);
  }
  catch (Exception $e) {
    // @ignoreException
    return civicrm_api3_create_error($e->getMessage());
  }
}

/**
 * API specs for updating mandates
 *
 * @param array<string, array<string, mixed>> $params
 */
function _civicrm_api3_sepa_mandate_modify_spec(array &$params): void {
  $params['mandate_id'] = [
    'name'         => 'mandate_id',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Mandate ID',
  ];
  $params['reference'] = [
    'name'         => 'reference',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Mandate Reference',
  ];
  $params['amount'] = [
    'name'         => 'amount',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'New Amount',
  ];
  $params['iban'] = [
    'name'         => 'iban',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'New IBAN',
  ];
  $params['bic'] = [
    'name'         => 'bic',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'New BIC',
  ];
  $params['financial_type_id'] = [
    'name'         => 'financial_type_id',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'New Financial Type ID',
  ];
  $params['campaign_id'] = [
    'name'         => 'campaign_id',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'New Campaign ID',
  ];
}

/**
 * Terminate mandates responsibly
 *
 * @phpstan-param array{
 *   mandate_id?: int|numeric-string,
 *   reference?: string,
 *   end_date?: string,
 *   cancel_reason?: string,
 * } $params
 *
 * @see https://github.com/Project60/org.project60.sepa/issues/483
 */
function civicrm_api3_sepa_mandate_terminate(array $params): array {
  // look up mandate ID if only reference is given
  if (empty($params['mandate_id']) && !empty($params['reference'])) {
    try {
      $params['mandate_id'] = SepaMandate::get(TRUE)
        ->addSelect('id')
        ->addWhere('reference', '=', $params['reference'])
        ->execute()
        ->single()['id'];
    }
    catch (\CRM_Core_Exception $exception) {
      return civicrm_api3_create_error("Couldn't identify mandate with reference '{$params['reference']}'.");
    }
  }

  // no mandate could be identified
  if (empty($params['mandate_id'])) {
    return civicrm_api3_create_error("You need to provide either 'mandate_id' or 'reference'.");
  }

  try {
    $success = CRM_Sepa_BAO_SEPAMandate::terminateMandate(
      (int) $params['mandate_id'],
      date('Y-m-d', strtotime($params['end_date'] ?? 'today')),
      $params['cancel_reason'] ?? NULL,
      FALSE);
    if ($success) {
      return civicrm_api3_create_success();
    }
    else {
      return civicrm_api3_create_error("Mandate couldn't be closed (again).");
    }

  }
  catch (Exception $e) {
    // @ignoreException
    return civicrm_api3_create_error($e->getMessage());
  }
}

/**
 * API specs for updating mandates
 *
 * @param array<string, array<string, mixed>> $params
 */
function _civicrm_api3_sepa_mandate_terminate_spec(array &$params): void {
  $params['mandate_id'] = [
    'name'         => 'mandate_id',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Mandate ID',
  ];
  $params['reference'] = [
    'name'         => 'reference',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Mandate Reference',
  ];
  $params['end_date'] = [
    'name'         => 'end_date',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'End Date',
    'description'  => 'Default is NOW',
  ];
  $params['cancel_reason'] = [
    'name'         => 'cancel_reason',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Cancel Reason',
  ];
}

/**
 * HELPER FUNCTION
 *
 * will add the default creditor_id if no id and creditor_id is given, and the
 * default creditor is valid
 *
 * @template T of array<string, mixed>
 *
 * @phpstan-param T $params
 * }
 */
function _civicrm_api3_sepa_mandate_adddefaultcreditor(array &$params): void {
  if (empty($params['id']) && empty($params['creditor_id'])) {
    $default_creditor = CRM_Sepa_Logic_Settings::defaultCreditor();
    if ($default_creditor != NULL) {
      $params['creditor_id'] = (int) $default_creditor->id;
    }
  }
}
