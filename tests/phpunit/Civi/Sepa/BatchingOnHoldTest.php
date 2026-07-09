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

namespace Civi\Sepa;

use Civi\Api4\Contribution;
use Civi\Api4\ContributionRecur;
use Civi\Api4\SepaContributionGroup;
use Civi\Api4\SepaMandate;
use Civi\Api4\SepaTransactionGroup;
use Systopia\TestFixtures\Fixtures\Builders\ContactBuilder;
use Systopia\TestFixtures\Fixtures\Builders\ContributionBuilder;
use Systopia\TestFixtures\Fixtures\Builders\FinancialTypeBuilder;
use Systopia\TestFixtures\Fixtures\Builders\SepaCreditorBuilder;

/**
 * Tests batching of RCUR mandates in status "ONHOLD".
 *
 * @covers \CRM_Sepa_Logic_Batching
 *
 * @group headless
 */
final class BatchingOnHoldTest extends AbstractSepaHeadlessTestCase {

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
      ->addValue('civi_sepa_contribution_recur.is_on_hold', TRUE)
      ->addWhere('id', '=', $mandate['entity_id'])
      ->execute()
      ->single();

    \CRM_Sepa_Logic_Batching::updateRCUR($creditorId, 'FRST');

    // One contribution with "is_on_hold" enabled should be created.
    $contribution = Contribution::get(FALSE)
      ->addSelect('id', 'civi_sepa_contribution.is_on_hold')
      ->addWhere('contribution_recur_id', '=', $mandate['entity_id'])
      ->execute()
      ->single();
    static::assertTrue($contribution['civi_sepa_contribution.is_on_hold']);

    // No transaction group should be created.
    static::assertEmpty(SepaTransactionGroup::get(FALSE)->execute());

    SepaMandate::reinstate(FALSE)
      ->addWhere('id', '=', $mandate['id'])
      ->execute()
      ->single();

    // When the mandate is reinstated the single contribution should stay on
    // hold and not being added to an transaction group.
    \CRM_Sepa_Logic_Batching::updateRCUR($creditorId, 'FRST');
    static::assertTrue(Contribution::get(FALSE)
      ->addSelect('civi_sepa_contribution.is_on_hold')
      ->addWhere('contribution_recur_id', '=', $mandate['entity_id'])
      ->execute()
      ->single()['civi_sepa_contribution.is_on_hold']);
    static::assertEmpty(SepaTransactionGroup::get(FALSE)->execute());

    Contribution::update(FALSE)
      ->addValue('civi_sepa_contribution.is_on_hold', FALSE)
      ->addWhere('id', '=', $contribution['id'])
      ->execute();

    // When the is_on_hold flag is set to FALSE the contribution should be added
    // to a transaction group.
    \CRM_Sepa_Logic_Batching::updateRCUR($creditorId, 'FRST');
    $transactionGroupId = SepaTransactionGroup::get(FALSE)->execute()->single()['id'];
    static::assertCount(1, SepaContributionGroup::get(FALSE)
      ->addWhere('contribution_id', '=', $contribution['id'])
      ->addWhere('txgroup_id', '=', $transactionGroupId)
      ->execute()
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

    $firstContributionId = ContributionBuilder::createCompletedForContact(
      $contactId,
      ['contribution_recur_id' => $mandate['entity_id']]
    );

    SepaMandate::update(FALSE)
      ->addValue('status', 'ONHOLD')
      ->addValue('first_contribution_id', $firstContributionId)
      ->addWhere('id', '=', $mandate['id'])
      ->execute()
      ->single();

    ContributionRecur::update(FALSE)
      ->addValue('civi_sepa_contribution_recur.is_on_hold', TRUE)
      ->addWhere('id', '=', $mandate['entity_id'])
      ->execute()
      ->single();

    \CRM_Sepa_Logic_Batching::updateRCUR($creditorId, 'RCUR');

    // One contribution with "is_on_hold" enabled should be created.
    $contribution = Contribution::get(FALSE)
      ->addSelect('id', 'civi_sepa_contribution.is_on_hold')
      ->addWhere('contribution_recur_id', '=', $mandate['entity_id'])
      ->addWhere('id', '!=', $firstContributionId)
      ->execute()
      ->single();
    static::assertTrue($contribution['civi_sepa_contribution.is_on_hold']);

    // No transaction group should be created.
    static::assertEmpty(SepaTransactionGroup::get(FALSE)->execute());

    SepaMandate::reinstate(FALSE)
      ->addWhere('id', '=', $mandate['id'])
      ->execute()
      ->single();

    // When the mandate is reinstated the single contribution should stay on
    // hold and not being added to an transaction group.
    \CRM_Sepa_Logic_Batching::updateRCUR($creditorId, 'RCUR');
    static::assertTrue(Contribution::get(FALSE)
      ->addSelect('civi_sepa_contribution.is_on_hold')
      ->addWhere('id', '=', $contribution['id'])
      ->execute()
      ->single()['civi_sepa_contribution.is_on_hold']);
    static::assertEmpty(SepaTransactionGroup::get(FALSE)->execute());

    Contribution::update(FALSE)
      ->addValue('civi_sepa_contribution.is_on_hold', FALSE)
      ->addWhere('id', '=', $contribution['id'])
      ->execute();

    // When the is_on_hold flag is set to FALSE the contribution should be added
    // to a transaction group.
    \CRM_Sepa_Logic_Batching::updateRCUR($creditorId, 'RCUR');
    $transactionGroupId = SepaTransactionGroup::get(FALSE)->execute()->single()['id'];
    static::assertCount(1, SepaContributionGroup::get(FALSE)
      ->addWhere('contribution_id', '=', $contribution['id'])
      ->addWhere('txgroup_id', '=', $transactionGroupId)
      ->execute()
    );
  }

  public function testRcurWithFrstMandate(): void {
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
      ->addValue('civi_sepa_contribution_recur.is_on_hold', TRUE)
      ->addWhere('id', '=', $mandate['entity_id'])
      ->execute()
      ->single();

    \CRM_Sepa_Logic_Batching::updateRCUR($creditorId, 'RCUR');

    // FRST mandate in ONHOLD should not be handled.
    static::assertEmpty(Contribution::get(FALSE)->execute());
    static::assertEmpty(SepaTransactionGroup::get(FALSE)->execute());
  }

  public function testFrstWithRcurMandate(): void {
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

    $firstContributionId = ContributionBuilder::createCompletedForContact(
      $contactId,
      ['contribution_recur_id' => $mandate['entity_id']]
    );

    SepaMandate::update(FALSE)
      ->addValue('status', 'ONHOLD')
      ->addValue('first_contribution_id', $firstContributionId)
      ->addWhere('id', '=', $mandate['id'])
      ->execute()
      ->single();

    ContributionRecur::update(FALSE)
      ->addValue('civi_sepa_contribution_recur.is_on_hold', TRUE)
      ->addWhere('id', '=', $mandate['entity_id'])
      ->execute()
      ->single();

    \CRM_Sepa_Logic_Batching::updateRCUR($creditorId, 'FRST');

    // RCUR mandate in ONHOLD should not handled.
    $contribution = Contribution::get(FALSE)->addSelect('id', 'civi_sepa_contribution.is_on_hold')->execute()->single();
    static::assertSame($firstContributionId, $contribution['id']);
    static::assertFalse($contribution['civi_sepa_contribution.is_on_hold']);
    static::assertEmpty(SepaTransactionGroup::get(FALSE)->execute());
  }

}
