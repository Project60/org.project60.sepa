<?php

use CRM_Sepa_ExtensionUtil as E;

class SepaTokens {
  public static function getTokenList() {
    return [
      'reference'               => E::ts('Reference', ['domain' => 'org.project60.sepa']),
      'source'                  => E::ts('Source', ['domain' => 'org.project60.sepa']),
      'type'                    => E::ts('Type', ['domain' => 'org.project60.sepa']),
      'status'                  => E::ts('Status', ['domain' => 'org.project60.sepa']),
      'date'                    => E::ts('Signature Date (raw)', ['domain' => 'org.project60.sepa']),
      'date_text'               => E::ts('Signature Date', ['domain' => 'org.project60.sepa']),
      'account_holder'          => E::ts('Account Holder', ['domain' => 'org.project60.sepa']),
      'iban'                    => E::ts('IBAN', ['domain' => 'org.project60.sepa']),
      'iban_anonymised'         => E::ts('IBAN (anonymised)', ['domain' => 'org.project60.sepa']),
      'bic'                     => E::ts('BIC', ['domain' => 'org.project60.sepa']),
      'amount'                  => E::ts('Amount (raw)', ['domain' => 'org.project60.sepa']),
      'amount_text'             => E::ts('Amount', ['domain' => 'org.project60.sepa']),
      'currency'                => E::ts('Currency', ['domain' => 'org.project60.sepa']),
      'first_collection'        => E::ts('First Collection Date (raw)', ['domain' => 'org.project60.sepa']),
      'first_collection_text'   => E::ts('First Collection Date', ['domain' => 'org.project60.sepa']),
      'cycle_day'               => E::ts('Cycle Day', ['domain' => 'org.project60.sepa']),
      'frequency_interval'      => E::ts('Interval Multiplier', ['domain' => 'org.project60.sepa']),
      'frequency_unit'          => E::ts('Interval Unit', ['domain' => 'org.project60.sepa']),
      'frequency'               => E::ts('Interval', ['domain' => 'org.project60.sepa']),
    ];
  }

  public static function fillLastMandateTokenValues($contactId, $prefix, \Civi\Token\TokenRow $tokenRow) {
    $mandate = CRM_Sepa_BAO_SEPAMandate::getLastMandateOfContact($contactId);
    if ($mandate) {
      self::fillLastMandateCommonTokenValues($mandate, $prefix, $tokenRow);

      if (CRM_Sepa_BAO_SEPAMandate::isContributionMandate($mandate)) {
        self::fillLastMandateContributionTokenValues($mandate, $prefix, $tokenRow);
      }
      elseif (CRM_Sepa_BAO_SEPAMandate::isContributionRecurMandate($mandate)) {
        self::fillLastMandateContributionRecurTokenValues($mandate, $prefix, $tokenRow);
      }
    }
  }

  private static function fillLastMandateCommonTokenValues($mandate, $prefix, \Civi\Token\TokenRow $tokenRow) {
    // copy the mandate values
    $tokenRow->tokens($prefix, 'reference',        $mandate['reference']);
    $tokenRow->tokens($prefix, 'source',           $mandate['source']);
    $tokenRow->tokens($prefix, 'type',             $mandate['type']);
    $tokenRow->tokens($prefix, 'status',           $mandate['status']);
    $tokenRow->tokens($prefix, 'date',             $mandate['date']);
    //$tokenRow->tokens($prefix, 'account_holder',   $mandate['account_holder']);
    $tokenRow->tokens($prefix, 'iban',             $mandate['iban']);
    $tokenRow->tokens($prefix, 'iban_anonymised',  CRM_Sepa_Logic_Verification::anonymiseIBAN($mandate['iban']));
    $tokenRow->tokens($prefix, 'bic',              $mandate['bic']);

    if (!empty($mandate['date'])) {
      $tokenRow->tokens($prefix, 'date_text', CRM_Utils_Date::customFormat($mandate['date']));
    }
  }

  private static function fillLastMandateContributionTokenValues($mandate, $prefix, \Civi\Token\TokenRow $tokenRow) {
    $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $mandate['entity_id']));
    $tokenRow->tokens($prefix, 'amount',           $contribution['total_amount']);
    $tokenRow->tokens($prefix, 'currency',         $contribution['currency']);
    $tokenRow->tokens($prefix, 'amount_text',      CRM_Utils_Money::format($contribution['total_amount'], $contribution['currency']));

    if (!empty($contribution['receive_date'])) {
      $formattedDate = CRM_Utils_Date::customFormat($contribution['receive_date']);
      $tokenRow->tokens($prefix, 'first_collection', $formattedDate);
    }
  }

  private static function fillLastMandateContributionRecurTokenValues($mandate, $prefix, \Civi\Token\TokenRow $tokenRow) {
    $rcontribution = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $mandate['entity_id']));
    $tokenRow->tokens($prefix, 'amount',             $rcontribution['amount']);
    $tokenRow->tokens($prefix, 'currency',           $rcontribution['currency']);
    $tokenRow->tokens($prefix, 'amount_text',        CRM_Utils_Money::format($rcontribution['amount'], $rcontribution['currency']));
    $tokenRow->tokens($prefix, 'cycle_day',          $rcontribution['cycle_day']);
    $tokenRow->tokens($prefix, 'frequency_interval', $rcontribution['frequency_interval']);
    $tokenRow->tokens($prefix, 'frequency_unit',     $rcontribution['frequency_unit']);
    $tokenRow->tokens($prefix, 'frequency',          CRM_Utils_SepaOptionGroupTools::getFrequencyText($rcontribution['frequency_interval'], $rcontribution['frequency_unit'], true));

    // first collection date
    if (empty($mandate['first_contribution_id'])) {
      // calculate
      $calculator = new CRM_Sepa_Logic_NextCollectionDate($mandate['creditor_id']);
      $firstCollectionDate = $calculator->calculateNextCollectionDate($mandate['entity_id']);
    }
    else {
      // use date of first contribution
      $fcontribution = civicrm_api3('Contribution', 'getsingle', array('id' => $mandate['first_contribution_id']));
      $firstCollectionDate = $fcontribution['receive_date'];
    }

    if (!empty($firstCollectionDate)) {
      $formattedDate = CRM_Utils_Date::customFormat($firstCollectionDate);
      $tokenRow->tokens($prefix, 'first_collection', $formattedDate);
    }
  }
}
