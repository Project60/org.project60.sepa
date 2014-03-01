<?php

/**
 */
function _civicrm_api3_sepa_import_create_spec(&$params) {
  $params = array(
    'contact_id' => array(
      'api.required' => 1,
      'title' => 'Contact ID',
      'description' => 'ID of exising Contact to create the Contribution for.',
    ),
    'reference' => array(
      'title' => 'Mandate Reference',
      'description' => 'Existing SEPA Mandate Reference. (optional)',
    ),
    'iban' => array(
      'api.required' => 1,
      'title' => 'IBAN',
    ),
    'bic' => array(
      'title' => 'BIC',
    ),
    'status' => array(
      'title' => 'Mandate Status',
      'description' => "Status of SEPA Mandate: 'INIT', 'OOFF', 'FRST', 'RCUR', 'INVALID', 'COMPLETE', 'ONHOLD'. 'FRST' or 'RCUR' => payments created immediately; 'INIT' => need to be activated first. ('FRST' and 'RCUR' are presently not distinguished; and other values are not implemented at all...)",
    ),
    'create_date' => array(
      'api.default' => date('Y-m-d H:i:s'),
      'title' => 'Create Date',
      'description' => 'Creation Date of Recurring Contribution and SEPA Mandate. For migrated mandates, use migration date. (Defaults to now.)',
    ),
    'date' => array(
      'title' => 'Signature Date',
      'description' => 'SEPA Mandate signature date. (Defaults to Create Date.)',
    ),
    'validation_date' => array(
      'title' => 'Validation Date',
      'description' => 'SEPA Mandate validation date. (Defaults to Create Date.)',
    ),
    'start_date' => array(
      'api.required' => 1,
      'title' => 'Start Date',
      'description' => 'Beginning of payments. (Possibly in the past when importing from legacy systems.) Important for scheduling of followup payments!',
    ),
    'end_date' => array(
      'title' => 'End Date',
      'description' => 'Stop payments at this (future) date. (optional)'
    ),
    'installments' => array(
      'title' => 'Installments',
      'description' => 'Total number of payments to be made, if not an open-ended commitment. (Presently unused.)',
    ),
    'frequency_unit' => array(
      'api.required' => 1,
      'title' => 'Frequency Unit',
      'description' => "Time unit for recurrence of payment. ('year', 'month', 'week', 'day')",
    ),
    'frequency_interval' => array(
      'api.required' => 1,
      'title' => 'Frequency Interval',
      'description' => 'Number of time units for recurrence of payment.',
    ),
    'cycle_day' => array(
      'title' => 'Cycle Day',
      'description' => 'Day in the period when the payment should be charged e.g. 1st of month, 15th etc. (Presently unused.)',
    ),
    'amount' => array(
      'api.required' => 1,
      'title' => 'Installment Amount',
      'description' => 'Amount of each payment.',
    ),
    'amount_level' => array(
      'title' => 'Amount Label',
    ),
    'payment_processor_id' => array(
      'api.required' => 1,
      'title' => 'Payment Processor ID',
    ),
    'financial_type_id' => array(
      'api.required' => 1,
      'title' => 'Financial Type ID',
    ),
    'is_email_receipt' => array(
      'title' => 'Send email Receipt?',
      'description' => 'If true, receipt is automatically emailed to contact on each successful payment. (Presently unused.)',
    ),
    'is_test' => array(
      #default?
      'title' => 'Test',
      'description' => 'Is this a test payment? (Presently unused.)',
    ),
    'source' => array(
      'title' => 'Source of Contribution/Mandate',
      'description' => 'Origin of this Contribution and SEPA Mandate. (optional)',
    ),
    'contribution_page_id' => array(
      'title' => 'Contribution Page',
      'description' => 'The Contribution Page which triggered this contribution. (optional)',
    ),
    'campaign_id' => array(
      'title' => 'Campaign ID',
      'description' => 'The campaign for which this contribution has been triggered. (optional)',
    ),
    'honor_contact_id' => array(
      'title' => 'Honor Contact ID',
    ),
    'honor_type_id' => array(
      'title' => 'Honor Type ID',
    ),
    'address_id' => array(
      'title' => 'Billing Address ID',
    ),
  );
}

/**
 */
function civicrm_api3_sepa_import_create($params) {
  $startDate = date_create(substr($params['start_date'], 0, 10));
  $createDate = date_create(substr($params['create_date'], 0, 10));
  if ($createDate > $startDate) {
    $frequencyUnit = $params['frequency_unit'];
    $frequencyInterval = $params['frequency_interval'];
    $startPeriod = CRM_Sepa_Logic_Base::countPeriods($startDate, date_sub(clone $createDate, new DateInterval('P1D')), $frequencyUnit, $frequencyInterval) + 1;
#echo('<pre>'.print_r($startDate, true).print_r($createDate, true).print_r($startPeriod, true).'</pre>'); #DEBUG
    $firstPaymentDate = CRM_Sepa_Logic_Base::addPeriods($startDate, $startPeriod, $frequencyUnit, $frequencyInterval);
  } else {
    $firstPaymentDate = $startDate;
  }

  $params = array_merge($params, array(
    'currency' => 'EUR',
    'contribution_status_id' => CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name'),
    'payment_instrument_id' => CRM_Core_OptionGroup::getValue('payment_instrument', 'FRST', 'name'),
    'receive_date' => date_format($firstPaymentDate, 'Y-m-d'),
    'total_amount' => $params['amount'],
    'type' => 'RCUR',
    'creation_date' => $params['create_date'],
  ));

  if (isset($params['status']) && $params['status'] != 'INIT') {
    if (!isset($params['date'])) {
      $params['date'] = $params['create_date'];
    }
    if (!isset($params['validation_date'])) {
      $params['validation_date'] = $params['create_date'];
    }
  }

  $result = civicrm_api3('SepaCreditor', 'get', array(
    'payment_processor_id' => $params['payment_processor_id'],
    'return' => 'id',
    'api.ContributionRecur.create' => array_merge($params, array(
      'format.only_id' => 1,
    )),
    'api.Contribution.create' => array_merge($params, array(
      'contribution_recur_id' => '$value.api.ContributionRecur.create',
      'format.only_id' => 1,
    )),
    'api.SepaMandate.create' => array_merge($params, array(
      'entity_table' => 'civicrm_contribution_recur',
      'entity_id' => '$value.api.ContributionRecur.create',
      'creditor_id' => '$value.id',
      'first_contribution_id' => '$value.api.Contribution.create',
      'sequential' => 0,
    )),
    'sequential' => 1,
  ));

  return civicrm_api3_create_success($result['values'][0]['api.SepaMandate.create']['values'], $params, 'SepaImport', 'create');
}
