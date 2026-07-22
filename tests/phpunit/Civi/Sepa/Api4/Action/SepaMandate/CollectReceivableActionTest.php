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
use Civi\Api4\SepaMandate;
use Civi\Api4\SepaMandateLink;
use Civi\Sepa\AbstractSepaHeadlessTestCase;
use Civi\Sepa\Mandate\MandateLinkClasses;
use Systopia\TestFixtures\Fixtures\Builders\ContactBuilder;
use Systopia\TestFixtures\Fixtures\Builders\ContributionBuilder;
use Systopia\TestFixtures\Fixtures\Builders\FinancialTypeBuilder;
use Systopia\TestFixtures\Fixtures\Builders\SepaCreditorBuilder;

/**
 * @covers \Civi\Sepa\Api4\Action\SepaMandate\CollectReceivableAction
 * @covers \Civi\Api4\SepaMandate
 *
 * @group headless
 */
final class CollectReceivableActionTest extends AbstractSepaHeadlessTestCase {

  public function test(): void {
    $creditorId = SepaCreditorBuilder::createDefault([
      'pi_ooff' => '1',
      'pi_rcur' => '2',
    ]);
    $contactId = ContactBuilder::createDefault();
    $financialTypeId = FinancialTypeBuilder::create();
    $mandate = SepaMandate::createFull(FALSE)
      ->setValues([
        'creditor_id' => $creditorId,
        'type' => 'RCUR',
        'contact_id' => $contactId,
        'financial_type_id' => $financialTypeId,
        'iban' => 'DE02370501980001802057',
        'bic' => 'BELADEBEXXX',
        'amount' => 10.00,
      ])
      ->execute()
      ->single();

    $pendingOnHoldContribution1Id = ContributionBuilder::createPendingForContact(
      $contactId,
      [
        'contribution_recur_id' => $mandate['entity_id'],
        'total_amount' => 1.1,
        'sepa_contribution.is_on_hold' => TRUE,
      ]
    );

    $pendingOnHoldContribution2Id = ContributionBuilder::createPendingForContact(
      $contactId,
      [
        'contribution_recur_id' => $mandate['entity_id'],
        'total_amount' => 2.2,
        'sepa_contribution.is_on_hold' => TRUE,
      ]
    );

    // Cancelled should be ignored.
    ContributionBuilder::createCancelledForContact(
      $contactId,
      [
        'contribution_recur_id' => $mandate['entity_id'],
        'total_amount' => 100,
        'sepa_contribution.is_on_hold' => TRUE,
      ]
    );

    // Completed should be ignored.
    ContributionBuilder::createCompletedForContact(
      $contactId,
      [
        'contribution_recur_id' => $mandate['entity_id'],
        'total_amount' => 100,
        'sepa_contribution.is_on_hold' => TRUE,
      ]
    );

    // Not on hold should be ignored.
    ContributionBuilder::createPendingForContact(
      $contactId,
      [
        'contribution_recur_id' => $mandate['entity_id'],
        'total_amount' => 100,
        'sepa_contribution.is_on_hold' => FALSE,
      ]
    );

    $result = SepaMandate::collectReceivable()
      ->addWhere('id', '=', $mandate['id'])
      ->execute();

    $ooffMandate = $result[$mandate['id']];
    static::assertIsArray($ooffMandate);
    static::assertSame(
      "Receivable (pending on hold) contributions of mandate {$mandate['reference']}",
      $ooffMandate['source']
    );
    static::assertSame('OOFF', $ooffMandate['type']);
    static::assertSame($mandate['iban'], $ooffMandate['iban']);
    static::assertSame($mandate['bic'], $ooffMandate['bic']);

    $ooffContribution = Contribution::get(FALSE)
      ->addSelect('id', 'total_amount', 'payment_instrument_id', 'financial_type_id')
      ->addWhere('id', '=', $ooffMandate['entity_id'])
      ->execute()
      ->single();

    static::assertEquals(
      [
        'id' => $ooffMandate['entity_id'],
        'total_amount' => 3.3,
        'payment_instrument_id' => 1,
        'financial_type_id' => $financialTypeId,
      ],
      $ooffContribution
    );

    // Link to RCUR mandate should exist.
    static::assertSame(1, SepaMandateLink::get(FALSE)
      ->selectRowCount()
      ->addWhere('mandate_id', '=', $ooffMandate['id'])
      ->addWhere('class', '=', MandateLinkClasses::RECEIVABLE)
      ->addWhere('entity_table', '=', 'civicrm_sdd_mandate')
      ->addWhere('entity_id', '=', $mandate['id'])
      ->execute()
      ->countMatched()
    );

    // Link to RCUR first pending on hold contribution should exist.
    static::assertSame(1, SepaMandateLink::get(FALSE)
      ->selectRowCount()
      ->addWhere('mandate_id', '=', $ooffMandate['id'])
      ->addWhere('class', '=', MandateLinkClasses::RECEIVABLE)
      ->addWhere('entity_table', '=', 'civicrm_contribution')
      ->addWhere('entity_id', '=', $pendingOnHoldContribution1Id)
      ->execute()
      ->countMatched()
    );

    // Link to RCUR second pending on hold contribution should exist.
    static::assertSame(1, SepaMandateLink::get(FALSE)
      ->selectRowCount()
      ->addWhere('mandate_id', '=', $ooffMandate['id'])
      ->addWhere('class', '=', MandateLinkClasses::RECEIVABLE)
      ->addWhere('entity_table', '=', 'civicrm_contribution')
      ->addWhere('entity_id', '=', $pendingOnHoldContribution2Id)
      ->execute()
      ->countMatched()
    );

    // A second run should not generate an additional mandate.
    static::assertSame([], SepaMandate::collectReceivable()
      ->addWhere('id', '=', $mandate['id'])
      ->execute()
      ->getArrayCopy()
    );
  }

}
