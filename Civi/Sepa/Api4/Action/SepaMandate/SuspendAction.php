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
use Civi\Api4\SepaContributionGroup;
use Civi\Api4\SepaMandate;
use CRM_Sepa_ExtensionUtil as E;

/**
 * Suspends SEPA mandates. Only RCUR mandates in status "FRST" and "RCUR" will
 * be suspended. Other mandates remain unchanged. The is_on_hold flag of the
 * connected recurring contribution will be set to TRUE as well as on related
 * pending contributions in open transaction groups.
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
      'entity_id',
    ];
  }

  private function doRun(Result $result): void {
    /** @var array{id: int, type: string, status: string, entity_id: ?int} $mandate */
    foreach ($this->getBatchRecords() as $mandate) {
      if ('RCUR' !== $mandate['type'] || !in_array($mandate['status'], ['FRST', 'RCUR'], TRUE)) {
        continue;
      }

      SepaMandate::update($this->getCheckPermissions())
        ->addValue('status', 'ONHOLD')
        ->addWhere('id', '=', $mandate['id'])
        ->execute();

      $result[] = ['status' => 'ONHOLD'] + $mandate;

      if (NULL !== $mandate['entity_id']) {
        ContributionRecur::update(FALSE)
          ->setValues(['sepa_contribution_recur.is_on_hold' => TRUE])
          ->addWhere('id', '=', $mandate['entity_id'])
          ->execute();

        $pendingContributionIdsInOpenTransactionGroups = Contribution::get(FALSE)
          ->addJoin('SepaContributionGroup AS sepa_contribution_group', 'INNER', NULL,
            ['sepa_contribution_group.contribution_id', '=', 'id'],
          )
          ->addJoin('SepaTransactionGroup AS sepa_transaction_group', 'INNER', NULL,
            ['sepa_transaction_group.id', '=', 'sepa_contribution_group.txgroup_id'],
            ['sepa_transaction_group.status_id:name', '=', '"Open"']
          )
          ->addWhere('contribution_recur_id', '=', $mandate['entity_id'])
          ->addWhere('contribution_status_id:name', '=', 'Pending')
          ->execute()
          ->column('id');

        if ([] !== $pendingContributionIdsInOpenTransactionGroups) {
          // Remove contributions from transaction groups.
          SepaContributionGroup::delete(FALSE)
            ->addWhere('contribution_id', 'IN', $pendingContributionIdsInOpenTransactionGroups)
            ->execute();

          Contribution::update(FALSE)
            ->addValue('sepa_contribution.is_on_hold', TRUE)
            ->addWhere('id', 'IN', $pendingContributionIdsInOpenTransactionGroups)
            ->execute();
        }
      }
    }
  }

}
