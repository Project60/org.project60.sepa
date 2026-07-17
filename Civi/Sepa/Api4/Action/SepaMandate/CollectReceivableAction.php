<?php
/*
 * Copyright (C) 2026 SYSTOPIA GmbH
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation in version 3.
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
use Civi\Api4\Generic\AbstractBatchAction;
use Civi\Api4\Generic\Result;
use Civi\Api4\SepaMandate;
use Civi\Api4\SepaMandateLink;
use Civi\Sepa\Contribution\PaymentInstrumentDeterminer;
use Civi\Sepa\Mandate\MandateLinkClasses;
use CRM_Sepa_ExtensionUtil as E;

/**
 * Creates a OOFF mandate with the sum of the amounts of pending
 * on hold contributions that aren't associated with a OOFF
 * mandate created this way. If there are no such contributions no
 * mandate will be created.
 */
class CollectReceivableAction extends AbstractBatchAction {

  public function _run(Result $result): void {
    $transaction = \CRM_Core_Transaction::create();
    $transaction->run(fn () => $this->doRun($result));
  }

  protected function getSelect(): array {
    return [
      'id',
      'creditor_id',
      'contact_id',
      'type',
      'entity_id',
      'financial_type_id',
      'account_holder',
      'iban',
      'bic',
    ];
  }

  /**
   * @throws \CRM_Core_Exception
   */
  private function doRun(Result $result): void {
    /** @var array{
     *   id: int,
     *   creditor_id: ?int,
     *   contact_id: ?int,
     *   type: string,
     *   entity_id: ?int,
     *   account_holder: ?string,
     *   iban: ?string,
     *   bic: ?string,
     * } $mandate
     */
    foreach ($this->getBatchRecords() as $mandate) {
      if ('RCUR' !== $mandate['type'] || NULL === $mandate['entity_id']) {
        continue;
      }

      /** @var array{financial_type_id: positive-int, payment_instrument_id: positive-int, campaign_id?: ?positive-int} $contributionRecur */
      $contributionRecur = ContributionRecur::get(FALSE)
        ->addSelect('financial_type_id', 'payment_instrument_id', 'campaign_id')
        ->addWhere('id', '=', $mandate['entity_id'])
        ->execute()
        ->single();

      /** @var list<array{id: int, total_amount: float}> $contributions */
      $contributions = Contribution::get(FALSE)
        ->addSelect('id', 'total_amount')
        ->addJoin(
          'SepaMandateLink AS sepa_mandate_link',
          'EXCLUDE',
          NULL,
          [
            'sepa_mandate_link.entity_table',
            '=',
            '"civicrm_contribution"',
          ],
          [
            'sepa_mandate_link.entity_id',
            '=',
            'id',
          ],
          [
            'sepa_mandate_link.class',
            '=',
            '"' . MandateLinkClasses::RECEIVABLE . '"',
          ]
        )
        ->addWhere('contribution_recur_id', '=', $mandate['entity_id'])
        ->addWhere('contribution_status_id:name', '=', 'Pending')
        ->addWhere('sepa_contribution.is_on_hold', '=', TRUE)
        ->execute()
        ->getArrayCopy();

      $amount = array_reduce(
        $contributions,
        fn ($carry, array $contribution) => $carry + $contribution['total_amount'],
        0.0
      );

      if (0.0 === $amount) {
        continue;
      }

      $creditorId = $mandate['creditor_id'] ?? $this->getDefaultCreditorId();
      $paymentInstrumentDeterminer = new PaymentInstrumentDeterminer();

      $ooffMandate = SepaMandate::createFull(FALSE)
        ->setValues([
          'creditor_id' => $creditorId,
          'type' => 'OOFF',
          'source' => E::ts('Receivable (pending on hold) contributions of mandate %1', [1 => $mandate['id']]),
          'contact_id' => $mandate['contact_id'],
          'financial_type_id' => $contributionRecur['financial_type_id'],
          'payment_instrument_id' => $paymentInstrumentDeterminer->determineCollectReceivablePaymentInstrument(
            $creditorId,
            $contributionRecur['payment_instrument_id']
          ),
          'account_holder' => $mandate['account_holder'],
          'iban' => $mandate['iban'],
          'bic' => $mandate['bic'],
          'amount' => $amount,
        ])
        ->execute()
        ->single();

      $now = date('Y-m-d H:i:s');
      SepaMandateLink::create(FALSE)
        ->setValues([
          'mandate_id' => $ooffMandate['id'],
          'entity_table' => 'civicrm_sdd_mandate',
          'entity_id' => $mandate['id'],
          'class' => MandateLinkClasses::RECEIVABLE,
          'creation_date' => $now,
          'start_date' => $now,
        ])
        ->execute()
        ->single();

      foreach ($contributions as $contribution) {
        SepaMandateLink::create(FALSE)
          ->setValues([
            'mandate_id' => $ooffMandate['id'],
            'entity_table' => 'civicrm_contribution',
            'entity_id' => $contribution['id'],
            'class' => MandateLinkClasses::RECEIVABLE,
            'creation_date' => $now,
            'start_date' => $now,
          ])
          ->execute()
          ->single();
      }

      $result[$mandate['id']] = $ooffMandate;
    }
  }

  private function getDefaultCreditorId(): int {
    $defaultCreditor = \CRM_Sepa_Logic_Settings::defaultCreditor();
    if ($defaultCreditor === NULL) {
      throw new \CRM_Core_Exception('creditor_id is missing and no active default creditor is configured.');
    }

    return (int) $defaultCreditor->id;
  }

}
