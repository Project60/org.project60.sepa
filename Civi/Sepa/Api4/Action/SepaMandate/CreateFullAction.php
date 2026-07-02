<?php
/*
 * Copyright (C) 2026 SYSTOPIA GmbH
 *
 * This program is free software: you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types = 1);

namespace Civi\Sepa\Api4\Action\SepaMandate;

use Civi\Api4\Generic\AbstractCreateAction;
use Civi\Api4\Generic\Result;
use Civi\Api4\SepaCreditor;
use Civi\Api4\SepaMandate;
use Civi\Sepa\Util\ContributionUtil;

final class CreateFullAction extends AbstractCreateAction {

  public function _run(Result $result): void {
    $transaction = \CRM_Core_Transaction::create();
    $transaction->run(fn() => $this->doRun($result));
  }

  public function doRun(Result $result): void {
    /**
     * @var array{
     *    creditor_id: int|numeric-string,
     *    type: string,
     *    status?: string,
     *    payment_instrument_id?: int|numeric-string,
     *    financial_type_id: int|numeric-string,
     *    bic?: string,
     *    contribution_contact_id?: int|numeric-string,
     *    currency?: string,
     *    contribution_status_id?: int|numeric-string,
     *    is_pay_later?: bool|scalar,
     *    amount: int|float|numeric-string,
     *    ...
     *  } $values
     */
    $values = $this->getValues();

    if (!isset($values['creditor_id'])) {
      $defaultCreditor = \CRM_Sepa_Logic_Settings::defaultCreditor();
      if ($defaultCreditor === NULL) {
        throw new \CRM_Core_Exception('creditor_id is missing and no active default creditor is configured.');
      }

      $values['creditor_id'] = (int) $defaultCreditor->id;
    }

    $creditor = SepaCreditor::get(FALSE)
      ->addWhere('id', '=', $values['creditor_id'])
      ->execute()
      ->single();

    // verify/set payment_instrument_id
    $values['status'] ??= $values['type'] === 'OOFF'
      ? 'OOFF'
      : (isset($values['first_contribution_id']) ? 'FRST' : 'RCUR');
    $rcurPaymentInstrumentType = $values['status'] === 'FRST' ? 'FRST' : $values['type'];
    $eligiblePaymentInstruments = \CRM_Sepa_Logic_PaymentInstruments::getPaymentInstrumentsForCreditor(
      (int) $values['creditor_id'],
      $rcurPaymentInstrumentType
    );
    if (!isset($values['payment_instrument_id'])) {
      // no payment instrument given, see if there is a unique one set
      if (count($eligiblePaymentInstruments) === 1) {
        // there is exactly one instrument defined -> use that
        $values['payment_instrument_id'] = reset($eligiblePaymentInstruments)['id'];

      }
      elseif (count($eligiblePaymentInstruments) === 0) {
        // no payment instrument -> disabled
        throw new \CRM_Core_Exception(
        // phpcs:ignore Generic.Files.LineLength.TooLong
          "{$values['status']} mandate for creditor ID [{$values['creditor_id']}] disabled, i.e. no valid payment instrument set."
        );
      }
      else {
        // unclear which one to take
        throw new \CRM_Core_Exception(
        // phpcs:ignore Generic.Files.LineLength.TooLong
          "You have to define the payment_instrument_id for {$values['status']} mandates for creditor ID [{$values['creditor_id']}], there are multiple options."
        );
      }

    }
    else {
      // a payment instrument is set, verify that it's allowed
      if (!array_key_exists($values['payment_instrument_id'], $eligiblePaymentInstruments)) {
        throw new \CRM_Core_Exception(
        // phpcs:ignore Generic.Files.LineLength.TooLong
          "Payment instrument [{$values['payment_instrument_id']}] invalid for {$values['status']} mandates with creditor ID [{$values['creditor_id']}]."
        );
      }
    }

    // Validate financial type.
    if (!array_key_exists($values['financial_type_id'], ContributionUtil::getFinancialTypeList())) {
      throw new \CRM_Core_Exception(
        "No permission for creating SEPA mandates with financial type [{$values['financial_type_id']}]."
      );
    }

    // if BIC is used for this creditor, it is required (see #245)
    if ('' === ($values['bic'] ?? '')) {
      if ($creditor['uses_bic']) {
        throw new \CRM_Core_Exception("BIC is required for creditor [{$values['creditor_id']}].");
      }
      else {
        $values['bic'] = 'NOTPROVIDED';
      }
    }

    // copy array
    // @todo Put only actual contribution values into $contributionValues.
    $contributionValues = $values;
    if (isset($contributionValues['contribution_contact_id'])) {
      // in case someone wants another contact for the contribution than for the mandate...
      $contributionValues['contact_id'] = $contributionValues['contribution_contact_id'];
    }
    if (!isset($contributionValues['currency'])) {
      $contributionValues['currency'] = $creditor['currency'];
    }

    if (!isset($contributionValues['contribution_status_id'])) {
      $contributionValues['contribution_status_id:name'] ??= 'Pending';
    }

    if ($values['type'] === 'RCUR') {
      $contributionEntity = 'ContributionRecur';
      $contributionTable  = 'civicrm_contribution_recur';
      $contributionValues['payment_instrument_id'] = $values['payment_instrument_id'];
      $contributionValues['is_pay_later'] ??= TRUE;
    }
    elseif ($values['type'] === 'OOFF') {
      $contributionEntity = 'Contribution';
      $contributionTable  = 'civicrm_contribution';
      $contributionValues['payment_instrument_id'] = $values['payment_instrument_id'];
      $contributionValues['total_amount'] = $contributionValues['amount'];
    }
    else {
      throw new \CRM_Core_Exception('Unknown mandate type: ' . $values['type']);
    }

    // create the contribution
    $contribution = civicrm_api4($contributionEntity, 'create', [
      'checkPermissions' => FALSE,
      'values' => $contributionValues,
    ])->single();

    // create the mandate object itself
    // TODO: sanity checks
    // copy array
    // @todo Put only actual mandate values into $mandateValues.
    $mandateValues = $values;
    $mandateValues['entity_table'] = $contributionTable;
    $mandateValues['entity_id'] = $contribution['id'];
    /** @var array<string, mixed> $mandate */
    $mandate = SepaMandate::create($this->getCheckPermissions())
      ->setValues($mandateValues)
      ->execute()
      ->single();

    $result[] = $mandate;
  }

}
