<?php
/*
 * Copyright (C) 2024 SYSTOPIA GmbH
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation in version 3.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types = 1);

namespace Civi\Sepa\Api4\Action\SepaMandate;

use Civi\Api4\Contribution;
use Civi\Api4\ContributionRecur;
use Civi\Api4\Generic\AbstractBatchAction;
use Civi\Api4\Generic\Result;
use Civi\Api4\SepaContributionGroup;
use Civi\Api4\SepaMandate;
use CRM_Sepa_ExtensionUtil as E;

/**
 * Suspends SEPA mandates. Only mandates in status "FRST" and "RCUR" will
 * be suspended. Other mandates remain unchanged. The connected recurring
 * contribution will be canceled as well as related contributions in open
 * transaction groups.
 */
class SuspendAction extends AbstractBatchAction {

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
    $now = date('Y-m-d H:i:s');
    /** @var array{id: int, type: string, status: string, entity_table: string|null, entity_id: int|null} $mandate */
    foreach ($this->getBatchRecords() as $mandate) {
      if ('RCUR' !== $mandate['type'] || !in_array($mandate['status'], ['FRST', 'RCUR'], TRUE)) {
        continue;
      }

      SepaMandate::update($this->getCheckPermissions())
        ->addValue('status', 'ONHOLD')
        ->addWhere('id', '=', $mandate['id'])
        ->execute();

      $result[] = ['status' => 'ONHOLD'] + $mandate;

      if ('civicrm_contribution_recur' === $mandate['entity_table'] && NULL !== $mandate['entity_id']) {
        ContributionRecur::update(FALSE)
          ->setValues([
            'contribution_status_id:name' => 'Cancelled',
            'cancel_date' => $now,
            'cancel_reason' => E::ts('SEPA mandate was suspended.'),
          ])
          ->addWhere('id', '=', $mandate['entity_id'])
          ->addWhere('contribution_status_id:name', '!=', 'Cancelled')
          ->execute();

        $contributionIdsInOpenTransactionGroups = Contribution::get(FALSE)
          ->addJoin('SepaContributionGroup AS sepa_contribution_group', 'INNER', NULL,
            ['sepa_contribution_group.contribution_id', '=', 'id'],
          )
          ->addJoin('SepaTransactionGroup AS sepa_transaction_group', 'INNER', NULL,
            ['sepa_transaction_group.id', '=', 'sepa_contribution_group.txgroup_id'],
            ['sepa_contribution_group.status_id:name', '=', 'Open']
          )
          ->addWhere('contribution_recur_id', '=', $mandate['entity_id'])
          ->execute()
          ->column('id');

        if ([] !== $contributionIdsInOpenTransactionGroups) {
          SepaContributionGroup::delete(FALSE)
            ->addWhere('contribution_id', 'IN', $contributionIdsInOpenTransactionGroups)
            ->execute();

          Contribution::update(FALSE)
            ->setValues([
              'contribution_status_id:name' => 'Cancelled',
              'cancel_date' => $now,
              'cancel_reason' => E::ts('SEPA mandate was suspended.'),
            ])
            ->addWhere('id', 'IN', $contributionIdsInOpenTransactionGroups)
            ->addWhere('contribution_status_id:name', '!=', 'Cancelled')
            ->execute();
        }
      }
    }
  }

}
