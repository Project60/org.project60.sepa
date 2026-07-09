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

use Civi\API\Exception\UnauthorizedException;
use Civi\Api4\Contribution;
use Civi\Api4\ContributionRecur;
use Civi\Api4\SepaContributionGroup;
use Civi\Api4\SepaMandate;
use Civi\Api4\SepaTransactionGroup;
use Civi\Sepa\AbstractSepaHeadlessTestCase;
use Systopia\TestFixtures\Fixtures\Builders\ContactBuilder;
use Systopia\TestFixtures\Fixtures\Builders\ContributionBuilder;
use Systopia\TestFixtures\Fixtures\Builders\FinancialTypeBuilder;
use Systopia\TestFixtures\Fixtures\Builders\SepaCreditorBuilder;

/**
 * @covers \Civi\Sepa\Api4\Action\SepaMandate\SuspendAction
 * @covers \Civi\Api4\SepaMandate
 *
 * @group headless
 */
final class SuspendActionTest extends AbstractSepaHeadlessTestCase {

  public function test(): void {
    $creditorId = SepaCreditorBuilder::createDefault(['uses_bic' => FALSE]);
    $contactId = ContactBuilder::createDefault();
    $financialTypeId = FinancialTypeBuilder::create();
    $mandate = SepaMandate::createFull(FALSE)
      ->setValues([
        'creditor_id' => $creditorId,
        'type' => 'RCUR',
        'contact_id' => $contactId,
        'financial_type_id' => $financialTypeId,
        'iban' => 'DE02370501980001802057',
        'amount' => 10.00,
      ])
      ->execute()
      ->single();

    $contributionPending1Id = ContributionBuilder::createPendingForContact(
      $contactId,
      ['contribution_recur_id' => $mandate['entity_id']]
    );

    $contributionCancelledId = ContributionBuilder::createCancelledForContact(
      $contactId,
      ['contribution_recur_id' => $mandate['entity_id']]
    );

    $contributionPending2Id = ContributionBuilder::createPendingForContact(
      $contactId,
      ['contribution_recur_id' => $mandate['entity_id']]
    );

    $transactionGroupOpen1 = SepaTransactionGroup::create(FALSE)
      ->setValues([
        'status_id:name' => 'Open',
        'reference' => 'open1',
        'type' => 'FRST',
      ])
      ->execute()
      ->single();

    $transactionGroupOpen2 = SepaTransactionGroup::create(FALSE)
      ->setValues([
        'status_id:name' => 'Open',
        'reference' => 'open2',
        'type' => 'FRST',
      ])
      ->execute()
      ->single();

    $transactionGroupClosed = SepaTransactionGroup::create(FALSE)
      ->setValues([
        'status_id:name' => 'Closed',
        'reference' => 'closed',
        'type' => 'FRST',
      ])
      ->execute()
      ->single();

    SepaContributionGroup::create(FALSE)
      ->setValues([
        'contribution_id' => $contributionPending1Id,
        'txgroup_id' => $transactionGroupOpen1['id'],
      ])
      ->execute();

    SepaContributionGroup::create(FALSE)
      ->setValues([
        'contribution_id' => $contributionCancelledId,
        'txgroup_id' => $transactionGroupOpen2['id'],
      ])
      ->execute();

    SepaContributionGroup::create(FALSE)
      ->setValues([
        'contribution_id' => $contributionPending2Id,
        'txgroup_id' => $transactionGroupClosed['id'],
      ])
      ->execute();

    $suspendedMandate = SepaMandate::suspend()
      ->addWhere('id', '=', $mandate['id'])
      ->execute();
    static::assertEquals([
      [
        'status' => 'ONHOLD',
        'id' => $mandate['id'],
        'type' => 'RCUR',
        'entity_id' => $mandate['entity_id'],
      ],
    ], $suspendedMandate->getArrayCopy());

    static::assertSame(
      'ONHOLD',
      SepaMandate::get(FALSE)
        ->addSelect('status')
        ->addWhere('id', '=', $mandate['id'])
        ->execute()
        ->single()['status']
    );

    // Recurring contribution should be set to on hold.
    static::assertTrue(
      ContributionRecur::get(FALSE)
        ->addSelect('civi_sepa_contribution_recur.is_on_hold')
        ->addWhere('id', '=', $mandate['entity_id'])
        ->execute()
        ->single()['civi_sepa_contribution_recur.is_on_hold']
    );

    // Pending contribution in open transaction group should be set to on hold.
    static::assertTrue(
      Contribution::get(FALSE)
        ->addSelect('civi_sepa_contribution.is_on_hold')
        ->addWhere('id', '=', $contributionPending1Id)
        ->execute()
        ->single()['civi_sepa_contribution.is_on_hold']
    );

    // Pending contribution in closed transaction group should be unchanged.
    static::assertFalse(
      Contribution::get(FALSE)
        ->addSelect('civi_sepa_contribution.is_on_hold')
        ->addWhere('id', '=', $contributionPending2Id)
        ->execute()
        ->single()['civi_sepa_contribution.is_on_hold']
    );

    // Canceled contribution in open transaction group should be unchanged.
    static::assertFalse(
      Contribution::get(FALSE)
        ->addSelect('civi_sepa_contribution.is_on_hold')
        ->addWhere('id', '=', $contributionCancelledId)
        ->execute()
        ->single()['civi_sepa_contribution.is_on_hold']
    );
  }

  public function testPermissionMissing(): void {
    // Test that "edit sepa mandates" permission is required.
    $this->setUserPermissions([
      'view all contacts',
      'access CiviCRM',
      'access CiviContribute',
      'view sepa mandates',
    ]);

    static::expectException(UnauthorizedException::class);
    static::expectExceptionMessage('Authorization failed: CiviCRM APIv4 (SepaMandate::suspend)');
    SepaMandate::suspend()->execute();
  }

}
