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

namespace Civi\Sepa\Contribution;

use Civi\Api4\Contribution;
use Civi\Sepa\Mandate\MandateLinkClasses;
use Civi\Sepa\Util\CurrencyUtil;

final class CollectReceivableHelper {

  /**
   * @param non-empty-array<int> $mandateIds
   *
   * @throws \CRM_Core_Exception
   */
  public function cancelLinkedOnHoldContributions(array $mandateIds): void {
    /** @var list<int> $linkedPendingOnHoldContributionIds */
    $linkedPendingOnHoldContributionIds = Contribution::get(FALSE)
      ->addSelect('id')
      ->addJoin(
        'SepaMandateLink AS sepa_mandate_link',
        'INNER',
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
          'sepa_mandate_link.mandate_id',
          'IN',
          $mandateIds,
        ],
        [
          'sepa_mandate_link.class',
          '=',
          '"' . MandateLinkClasses::RECEIVABLE . '"',
        ],
      )
      ->addWhere('contribution_status_id:name', '=', 'Pending')
      ->addWhere('sepa_contribution.is_on_hold', '=', TRUE)
      ->execute()
      ->column('id');

    if ([] !== $linkedPendingOnHoldContributionIds) {
      Contribution::update(FALSE)
        ->addValue('contribution_status_id:name', 'Cancelled')
        ->addWhere('id', 'IN', $linkedPendingOnHoldContributionIds)
        ->execute();
    }
  }

  /**
   * @return list<array{id: int, total_amount: float, currency: ?string}>
   *
   * @throws \CRM_Core_Exception
   */
  public function getReceivableContributions(int $contributionRecurId): array {
    /** @var list<array{id: int, total_amount: float, currency: ?string}> */
    return Contribution::get(FALSE)
      ->addSelect('id', 'total_amount', 'currency')
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
      ->addWhere('contribution_recur_id', '=', $contributionRecurId)
      ->addWhere('contribution_status_id:name', '=', 'Pending')
      ->addWhere('sepa_contribution.is_on_hold', '=', TRUE)
      ->execute()
      ->getArrayCopy();
  }

  /**
   * @param list<array{total_amount: float, currency: ?string}> $contributions
   *   Contributions as returned by {@link getReceivableContributions()}.
   */
  public function getReceivableAmount(array $contributions): float {
    if ([] === $contributions) {
      return 0.0;
    }

    return round(
      array_reduce(
        $contributions,
        fn($carry, array $contribution) => $carry + $contribution['total_amount'],
        0.0
      ),
      // @phpstan-ignore argument.type
      CurrencyUtil::getPrecision($contributions[0]['currency'] ?? \Civi::settings()->get('defaultCurrency'))
    );
  }

}
