<?php

use Civi\Token\TokenRow;
use CRM_Sepa_ExtensionUtil as E;

class CRM_Utils_SepaTokens {

  public static function getTokenList() {
    return [
      'reference'               => E::ts('Reference'),
      'source'                  => E::ts('Source'),
      'type'                    => E::ts('Type'),
      'status'                  => E::ts('Status'),
      'date'                    => E::ts('Signature Date (raw)'),
      'date_text'               => E::ts('Signature Date'),
      'account_holder'          => E::ts('Account Holder'),
      'iban'                    => E::ts('IBAN'),
      'iban_anonymised'         => E::ts('IBAN (anonymised)'),
      'bic'                     => E::ts('BIC'),
      'amount'                  => E::ts('Amount (raw)'),
      'amount_text'             => E::ts('Amount'),
      'currency'                => E::ts('Currency'),
      'first_collection'        => E::ts('First Collection Date (raw)'),
      'first_collection_text'   => E::ts('First Collection Date'),
      'cycle_day'               => E::ts('Cycle Day'),
      'frequency_interval'      => E::ts('Interval Multiplier'),
      'frequency_unit'          => E::ts('Interval Unit'),
      'frequency'               => E::ts('Interval'),
    ];
  }

  public static function fillLastMandateTokenValues($contactId, $prefix, TokenRow $tokenRow) {
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

  private static function fillLastMandateCommonTokenValues($mandate, $prefix, TokenRow $tokenRow) {
    // copy the mandate values
    $tokenRow->tokens($prefix, 'reference', $mandate['reference'] ?? '');
    $tokenRow->tokens($prefix, 'source', $mandate['source'] ?? '');
    $tokenRow->tokens($prefix, 'type', $mandate['type'] ?? '');
    $tokenRow->tokens($prefix, 'status', $mandate['status'] ?? '');
    $tokenRow->tokens($prefix, 'date', $mandate['date'] ?? '');
    $tokenRow->tokens($prefix, 'iban', $mandate['iban'] ?? '');
    $tokenRow->tokens(
      $prefix,
      'iban_anonymised',
      $mandate['iban'] ? CRM_Sepa_Logic_Verification::anonymiseIBAN($mandate['iban']) : ''
    );
    $tokenRow->tokens($prefix, 'bic', $mandate['bic'] ?? '');

    if (!empty($mandate['date'])) {
      $tokenRow->tokens($prefix, 'date_text', CRM_Utils_Date::customFormat($mandate['date']));
    }
  }

  private static function fillLastMandateContributionTokenValues($mandate, $prefix, TokenRow $tokenRow) {
    $contribution = civicrm_api3('Contribution', 'getsingle', ['id' => $mandate['entity_id']]);
    $tokenRow->tokens($prefix, 'amount', $contribution['total_amount']);
    $tokenRow->tokens($prefix, 'currency', $contribution['currency']);
    $tokenRow->tokens(
      $prefix,
      'amount_text',
      CRM_Utils_Money::format($contribution['total_amount'], $contribution['currency'])
    );

    if (!empty($contribution['receive_date'])) {
      $formattedDate = CRM_Utils_Date::customFormat($contribution['receive_date']);
      $tokenRow->tokens($prefix, 'first_collection', $formattedDate);
    }
  }

  private static function fillLastMandateContributionRecurTokenValues($mandate, $prefix, TokenRow $tokenRow) {
    $rcontribution = civicrm_api3('ContributionRecur', 'getsingle', ['id' => $mandate['entity_id']]);
    $tokenRow->tokens($prefix, 'amount', $rcontribution['amount']);
    $tokenRow->tokens($prefix, 'currency', $rcontribution['currency']);
    $tokenRow->tokens(
      $prefix,
      'amount_text',
      CRM_Utils_Money::format($rcontribution['amount'], $rcontribution['currency'])
    );
    $tokenRow->tokens($prefix, 'cycle_day', $rcontribution['cycle_day']);
    $tokenRow->tokens($prefix, 'frequency_interval', $rcontribution['frequency_interval']);
    $tokenRow->tokens($prefix, 'frequency_unit', $rcontribution['frequency_unit']);
    $tokenRow->tokens(
      $prefix,
      'frequency',
      CRM_Utils_SepaOptionGroupTools::getFrequencyText(
        $rcontribution['frequency_interval'],
        $rcontribution['frequency_unit'],
        TRUE
      )
    );

    // first collection date
    if (empty($mandate['first_contribution_id'])) {
      // calculate
      $calculator = new CRM_Sepa_Logic_NextCollectionDate($mandate['creditor_id']);
      $firstCollectionDate = $calculator->calculateNextCollectionDate($mandate['entity_id']);
    }
    else {
      // use date of first contribution
      $fcontribution = civicrm_api3('Contribution', 'getsingle', ['id' => $mandate['first_contribution_id']]);
      $firstCollectionDate = $fcontribution['receive_date'];
    }

    if (!empty($firstCollectionDate)) {
      $formattedDate = CRM_Utils_Date::customFormat($firstCollectionDate);
      $tokenRow->tokens($prefix, 'first_collection', $formattedDate);
    }
  }

}
