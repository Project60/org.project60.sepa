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
use Civi\Api4\ContributionRecur;
use Civi\Api4\SepaMandate;
use Civi\Sepa\AbstractSepaHeadlessTestCase;
use Systopia\TestFixtures\Fixtures\Builders\ContactBuilder;
use Systopia\TestFixtures\Fixtures\Builders\ContributionBuilder;
use Systopia\TestFixtures\Fixtures\Builders\FinancialTypeBuilder;
use Systopia\TestFixtures\Fixtures\Builders\SepaCreditorBuilder;

/**
 * @covers \Civi\Sepa\Api4\Action\SepaMandate\ReinstateAction
 * @covers \Civi\Api4\SepaMandate
 *
 * @group headless
 */
final class ReinstateActionTest extends AbstractSepaHeadlessTestCase {

  public function testFrst(): void {
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

    SepaMandate::update(FALSE)
      ->addValue('status', 'ONHOLD')
      ->addWhere('id', '=', $mandate['id'])
      ->execute()
      ->single();

    ContributionRecur::update(FALSE)
      ->addValue('sepa_contribution_recur.is_on_hold', TRUE)
      ->addWhere('id', '=', $mandate['entity_id'])
      ->execute()
      ->single();

    $reinstatedMandate = SepaMandate::reinstate()
      ->addWhere('id', '=', $mandate['id'])
      ->execute()
      ->single();

    static::assertEquals(
      [
        'status' => 'FRST',
        'id' => $mandate['id'],
        'type' => 'RCUR',
        'entity_id' => $mandate['entity_id'],
        'first_contribution_id' => NULL,
      ],
      $reinstatedMandate
    );

    static::assertSame(
      'FRST',
      SepaMandate::get(FALSE)
        ->addSelect('status')
        ->addWhere('id', '=', $mandate['id'])
        ->execute()
        ->single()['status']
    );

    static::assertFalse(
      ContributionRecur::get(FALSE)
        ->addSelect('sepa_contribution_recur.is_on_hold')
        ->addWhere('id', '=', $mandate['entity_id'])
        ->execute()
        ->single()['sepa_contribution_recur.is_on_hold']
    );
  }

  public function testRcur(): void {
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

    $contributionId = ContributionBuilder::createCompletedForContact(
      $contactId,
      ['contribution_recur_id' => $mandate['entity_id']]
    );

    SepaMandate::update(FALSE)
      ->addValue('status', 'ONHOLD')
      ->addValue('first_contribution_id', $contributionId)
      ->addWhere('id', '=', $mandate['id'])
      ->execute()
      ->single();

    ContributionRecur::update(FALSE)
      ->addValue('sepa_contribution_recur.is_on_hold', TRUE)
      ->addWhere('id', '=', $mandate['entity_id'])
      ->execute()
      ->single();

    $reinstatedMandate = SepaMandate::reinstate()
      ->addWhere('id', '=', $mandate['id'])
      ->execute()
      ->single();

    static::assertEquals(
      [
        'status' => 'RCUR',
        'id' => $mandate['id'],
        'type' => 'RCUR',
        'entity_id' => $mandate['entity_id'],
        'first_contribution_id' => $contributionId,
      ],
      $reinstatedMandate
    );

    static::assertSame(
      'RCUR',
      SepaMandate::get(FALSE)
        ->addSelect('status')
        ->addWhere('id', '=', $mandate['id'])
        ->execute()
        ->single()['status']
    );

    static::assertFalse(
      ContributionRecur::get(FALSE)
        ->addSelect('sepa_contribution_recur.is_on_hold')
        ->addWhere('id', '=', $mandate['entity_id'])
        ->execute()
        ->single()['sepa_contribution_recur.is_on_hold']
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
    static::expectExceptionMessage('Authorization failed: CiviCRM APIv4 (SepaMandate::reinstate)');
    SepaMandate::reinstate()->execute();
  }

}
