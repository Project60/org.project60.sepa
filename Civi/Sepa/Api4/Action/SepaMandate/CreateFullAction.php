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

use Civi\Api4\Contribution;
use Civi\Api4\ContributionRecur;
use Civi\Api4\Generic\AbstractCreateAction;
use Civi\Api4\Generic\Result;
use Civi\Api4\SepaCreditor;
use Civi\Api4\SepaMandate;
use Civi\Sepa\Contribution\PaymentInstrumentDeterminer;
use Civi\Sepa\Mandate\MandateStatusDeterminer;
use Civi\Sepa\Util\ContributionUtil;

final class CreateFullAction extends AbstractCreateAction {

  public function _run(Result $result): void {
    $transaction = \CRM_Core_Transaction::create();
    $transaction->run(fn() => $this->doRun($result));
  }

  /**
   * @throws \CRM_Core_Exception
   */
  // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
  public function doRun(Result $result): void {
    $this->validateValues();
    // TODO: more sanity checks?

    /**
     * @var array{
     *    contact_id: int|numeric-string,
     *    iban: string,
     *    creditor_id?: int|numeric-string,
     *    type: string,
     *    status?: string,
     *    payment_instrument_id?: int|numeric-string,
     *    financial_type_id: int|numeric-string,
     *    bic?: string,
     *    contribution_contact_id?: int|numeric-string,
     *    currency?: string,
     *    contribution_status_id?: int|numeric-string,
     *    amount: int|float|numeric-string,
     *    first_contribution_id?: int|numeric-string|null,
     *    receive_date?: ?string,
     *    start_date?: string,
     *    end_date?: string,
     *    frequency_interval?: int|numeric-string,
     *    frequency_unit?: string,
     *    cycle_day?: int|numeric-string,
     *    campaign_id?: int|numeric-string|null,
     *    ...
     *  } $values
     */
    $values = $this->getValues();

    $values['creditor_id'] ??= $this->getDefaultCreditorId();

    /** @var array{uses_bic: bool, currency: string} $creditor */
    $creditor = SepaCreditor::get(FALSE)
      ->addSelect('uses_bic', 'currency')
      ->addWhere('id', '=', $values['creditor_id'])
      ->execute()
      ->single();
    $values['creditor_id'] = (int) $values['creditor_id'];

    $values['status'] ??= MandateStatusDeterminer::determineMandateStatus(
      $values['type'], $values['first_contribution_id'] ?? NULL
    );

    $paymentInstrumentDeterminer = new PaymentInstrumentDeterminer();
    $values['payment_instrument_id'] = $paymentInstrumentDeterminer->determineInitialPaymentInstrument(
      $values['creditor_id'],
      $values['type'],
      isset($values['payment_instrument_id']) ? (int) $values['payment_instrument_id'] : NULL
    );

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

    // @todo Put only actual contribution values into $contributionValues.
    $contributionValues = $values;
    if (isset($contributionValues['contribution_contact_id'])) {
      // in case someone wants another contact for the contribution than for the mandate...
      $contributionValues['contact_id'] = $contributionValues['contribution_contact_id'];
    }

    $contributionValues['currency'] ??= $creditor['currency'];

    if (!isset($contributionValues['contribution_status_id'])) {
      $contributionValues['contribution_status_id:name'] ??= 'Pending';
    }

    if ($values['type'] === 'RCUR') {
      $contributionTable  = 'civicrm_contribution_recur';
      $contributionValues['frequency_interval'] ??= 1;
      $contributionValues['frequency_unit'] ??= 'month';
      $contributionValues['cycle_day'] ??= 1;
      $contribution = ContributionRecur::create(FALSE)
        ->setValues($contributionValues)
        ->execute()
        ->single();
    }
    elseif ($values['type'] === 'OOFF') {
      $contributionTable  = 'civicrm_contribution';
      $contributionValues['total_amount'] = $contributionValues['amount'];
      $contribution = Contribution::create(FALSE)
        ->setValues($contributionValues)
        ->execute()
        ->single();
    }
    else {
      throw new \CRM_Core_Exception('Unknown mandate type: ' . $values['type']);
    }

    // create the mandate object itself
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

  private function getDefaultCreditorId(): int {
    $defaultCreditor = \CRM_Sepa_Logic_Settings::defaultCreditor();
    if ($defaultCreditor === NULL) {
      throw new \CRM_Core_Exception('creditor_id is missing and no active default creditor is configured.');
    }

    return (int) $defaultCreditor->id;
  }

}
