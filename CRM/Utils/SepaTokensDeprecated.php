<?php

use CRM_Sepa_ExtensionUtil as E;

class CRM_Utils_SepaTokensDeprecated {
  public static function getTokenList() {
    return CRM_Utils_SepaTokens::getTokenList();
  }

  public static function fillLastMandateTokenValues($contactId, $prefix, &$values) {
    $mandate = CRM_Sepa_BAO_SEPAMandate::getLastMandateOfContact($contactId);
    if (!$mandate) {
      return;
    }

    try {
      // copy the mandate values
      $values[$contactId]["$prefix.reference"] = $mandate['reference'];
      $values[$contactId]["$prefix.source"] = $mandate['source'];
      $values[$contactId]["$prefix.type"] = $mandate['type'];
      $values[$contactId]["$prefix.status"] = $mandate['status'];
      $values[$contactId]["$prefix.date"] = $mandate['date'];
      $values[$contactId]["$prefix.account_holder"] = $mandate['account_holder'];
      $values[$contactId]["$prefix.iban"] = $mandate['iban'];
      $values[$contactId]["$prefix.iban_anonymised"] = CRM_Sepa_Logic_Verification::anonymiseIBAN($mandate['iban']);
      $values[$contactId]["$prefix.bic"] = $mandate['bic'];

      // load and copy the contribution information
      if ($mandate['entity_table'] == 'civicrm_contribution') {
        $contribution = civicrm_api3('Contribution', 'getsingle', ['id' => $mandate['entity_id']]);
        $values[$contactId]["$prefix.amount"] = $contribution['total_amount'];
        $values[$contactId]["$prefix.currency"] = $contribution['currency'];
        $values[$contactId]["$prefix.amount_text"] = CRM_Utils_Money::format($contribution['total_amount'], $contribution['currency']);
        $values[$contactId]["$prefix.first_collection"] = $contribution['receive_date'];

      }
      elseif ($mandate['entity_table'] == 'civicrm_contribution_recur') {
        $rcontribution = civicrm_api3('ContributionRecur', 'getsingle', ['id' => $mandate['entity_id']]);
        $values[$contactId]["$prefix.amount"] = $rcontribution['amount'];
        $values[$contactId]["$prefix.currency"] = $rcontribution['currency'];
        $values[$contactId]["$prefix.amount_text"] = CRM_Utils_Money::format($rcontribution['amount'], $rcontribution['currency']);
        $values[$contactId]["$prefix.cycle_day"] = $rcontribution['cycle_day'];
        $values[$contactId]["$prefix.frequency_interval"] = $rcontribution['frequency_interval'];
        $values[$contactId]["$prefix.frequency_unit"] = $rcontribution['frequency_unit'];
        $values[$contactId]["$prefix.frequency"] = CRM_Utils_SepaOptionGroupTools::getFrequencyText($rcontribution['frequency_interval'], $rcontribution['frequency_unit'], TRUE);

        // first collection date
        if (empty($mandate['first_contribution_id'])) {
          // calculate
          $calculator = new CRM_Sepa_Logic_NextCollectionDate($mandate['creditor_id']);
          $values[$contactId]["$prefix.first_collection"] = $calculator->calculateNextCollectionDate($mandate['entity_id']);

        }
        else {
          // use date of first contribution
          $fcontribution = civicrm_api3('Contribution', 'getsingle', ['id' => $mandate['first_contribution_id']]);
          $values[$contactId]["$prefix.first_collection"] = $fcontribution['receive_date'];
        }
      }

      // format dates
      if (!empty($values[$contactId]["$prefix.first_collection"])) {
        $values[$contactId]["$prefix.first_collection_text"] = CRM_Utils_Date::customFormat($values[$contactId]["$prefix.first_collection"]);
      }
      if (!empty($values[$contactId]["$prefix.date"])) {
        $values[$contactId]["$prefix.date_text"] = CRM_Utils_Date::customFormat($values[$contactId]["$prefix.date"]);
      }
    }
    catch (Exception $e) {
      // probably just a minor issue, see SEPA-461
    }
  }

}
