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
use Civi\Api4\SepaTransactionGroup;

/**
 * Reinstates SEPA mandates that are in status "ONHOLD". The new status will be
 * "FRST", if there's no related contribution in any transaction group,
 * otherwise it will be "RCUR". The corresponding recurrent contribution will be
 * set to "Pending".
 */
class ReinstateAction extends AbstractBatchAction {

  public function _run(Result $result): void {
    $transaction = \CRM_Core_Transaction::create();
    $transaction->run(fn () => $this->doRun($result));
  }

  protected function getSelect(): array {
    return [
      'id',
      'type',
      'status',
      'entity_table',
      'entity_id',
    ];
  }

  private function doRun(Result $result): void {
    /** @var array{id: int, type: string, status: string, entity_table: string|null, entity_id: int|null} $mandate */
    foreach ($this->getBatchRecords() as $mandate) {
      if ('RCUR' !== $mandate['type'] || 'ONHOLD' !== $mandate['status']) {
        continue;
      }

      if (NULL === $mandate['entity_id'] || 'civicrm_contribution_recur' !== $mandate['entity_table']) {
        $status = 'FRST';
      }
      else {
        $status = $this->detectMandateStatusForRecurContribution($mandate['entity_id']);
        ContributionRecur::update(FALSE)
          ->addValue('contribution_status_id:name', 'Pending')
          ->addWhere('id', '=', $mandate['entity_id'])
          ->execute();
      }

      SepaMandate::update($this->getCheckPermissions())
        ->addValue('status', $status)
        ->addWhere('id', '=', $mandate['id'])
        ->execute();

      $result[] = ['status' => $status] + $mandate;
    }
  }

  private function detectMandateStatusForRecurContribution(int $contributionRecurId): string {
    $contributionIds = Contribution::get(FALSE)
      ->addSelect('id')
      ->addWhere('contribution_recur_id', '=', $contributionRecurId)
      ->execute()
      ->column('id');

    if ([] === $contributionIds) {
      return 'FRST';
    }

    $transactionGroup = SepaTransactionGroup::get(FALSE)
      ->addSelect('id')
      ->addJoin('SepaContributionGroup AS sepa_contribution_group', 'INNER', NULL,
        ['sepa_contribution_group.txgroup_id', '=', 'id'],
        ['sepa_contribution_group.contribution_id', 'IN', $contributionIds],
      )
      ->setLimit(1)
      ->execute()
      ->first();

    return NULL === $transactionGroup ? 'FRST' : 'RCUR';
  }

}
