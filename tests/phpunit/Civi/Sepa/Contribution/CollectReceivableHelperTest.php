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
use Civi\Api4\SepaMandate;
use Civi\Api4\SepaMandateLink;
use Civi\Sepa\AbstractSepaHeadlessTestCase;
use Civi\Sepa\Mandate\MandateLinkClasses;
use Systopia\TestFixtures\Fixtures\Builders\ContactBuilder;
use Systopia\TestFixtures\Fixtures\Builders\ContributionBuilder;
use Systopia\TestFixtures\Fixtures\Builders\FinancialTypeBuilder;
use Systopia\TestFixtures\Fixtures\Builders\SepaCreditorBuilder;

/**
 * @covers \Civi\Sepa\Contribution\CollectReceivableHelper
 *
 * @group headless
 */
final class CollectReceivableHelperTest extends AbstractSepaHeadlessTestCase {

  public function testCancelLinkedOnHoldContributions(): void {
    $collectReceivableHelper = new CollectReceivableHelper();

    $creditorId = SepaCreditorBuilder::createDefault(['pi_ooff' => '1']);
    $contactId = ContactBuilder::createDefault();
    $financialTypeId = FinancialTypeBuilder::create();
    $mandate1 = SepaMandate::createFull(FALSE)
      ->setValues([
        'creditor_id' => $creditorId,
        'type' => 'OOFF',
        'contact_id' => $contactId,
        'financial_type_id' => $financialTypeId,
        'iban' => 'DE02370501980001802057',
        'bic' => 'BELADEBEXXX',
        'amount' => 10.00,
      ])
      ->execute()
      ->single();

    $mandate2 = SepaMandate::createFull(FALSE)
      ->setValues([
        'creditor_id' => $creditorId,
        'type' => 'OOFF',
        'contact_id' => $contactId,
        'financial_type_id' => $financialTypeId,
        'iban' => 'DE02370501980001802057',
        'bic' => 'BELADEBEXXX',
        'amount' => 10.00,
      ])
      ->execute()
      ->single();

    $createLink = SepaMandateLink::create(FALSE)
      ->setValues([
        'mandate_id' => $mandate1['id'],
        'entity_table' => 'civicrm_contribution',
        'class' => MandateLinkClasses::RECEIVABLE,
      ]);

    $pendingOnHoldContributionId = ContributionBuilder::createPendingForContact($contactId, [
      'sepa_contribution.is_on_hold' => TRUE,
    ]);
    $createLink->addValue('entity_id', $pendingOnHoldContributionId)->execute();

    $pendingContributionId = ContributionBuilder::createPendingForContact($contactId, [
      'sepa_contribution.is_on_hold' => FALSE,
    ]);
    $createLink->addValue('entity_id', $pendingContributionId)->execute();

    $completedOnHoldContributionId = ContributionBuilder::createCompletedForContact($contactId, [
      'sepa_contribution.is_on_hold' => FALSE,
    ]);
    $createLink->addValue('entity_id', $completedOnHoldContributionId)->execute();

    $pendingOnHoldContributionDifferentLinkId = ContributionBuilder::createPendingForContact($contactId, [
      'sepa_contribution.is_on_hold' => TRUE,
    ]);
    $createLink
      ->addValue('class', 'test')
      ->addValue('entity_id', $pendingOnHoldContributionDifferentLinkId)
      ->execute();

    $pendingOnHoldContributionDifferentMandateId = ContributionBuilder::createPendingForContact($contactId, [
      'sepa_contribution.is_on_hold' => TRUE,
    ]);
    $createLink = SepaMandateLink::create(FALSE)
      ->setValues([
        'mandate_id' => $mandate2['id'],
        'entity_table' => 'civicrm_contribution',
        'entity_id' => $pendingOnHoldContributionDifferentMandateId,
        'class' => MandateLinkClasses::RECEIVABLE,
      ]);

    $collectReceivableHelper->cancelLinkedOnHoldContributions([$mandate1['id']]);

    // Status of pending on hold contribution linked with class
    // "RECEIVABLE" to mandate1 should be cancelled.
    // Other contributions shouldn't be cancelled.
    $cancelledContributions = Contribution::get(FALSE)
      ->addSelect('id')
      ->addWhere('contribution_status_id:name', '=', 'Cancelled')
      ->execute();

    static::assertCount(1, $cancelledContributions);
    static::assertSame($pendingOnHoldContributionId, $cancelledContributions->first()['id']);
  }

  public function testGetReceivableContributions(): void {
    $creditorId = SepaCreditorBuilder::createDefault();
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

    $collectReceivableHelper = new CollectReceivableHelper();
    static::assertSame([], $collectReceivableHelper->getReceivableContributions($mandate['entity_id']));

    $pendingOnHoldContribution1Id = ContributionBuilder::createPendingForContact(
      $contactId,
      [
        'contribution_recur_id' => $mandate['entity_id'],
        'total_amount' => 1.1,
        'currency' => 'EUR',
        'sepa_contribution.is_on_hold' => TRUE,
      ]
    );

    $pendingOnHoldContribution2Id = ContributionBuilder::createPendingForContact(
      $contactId,
      [
        'contribution_recur_id' => $mandate['entity_id'],
        'total_amount' => 2.2,
        'currency' => 'EUR',
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

    static::assertEquals([
      ['id' => $pendingOnHoldContribution1Id, 'total_amount' => 1.1, 'currency' => 'EUR'],
      ['id' => $pendingOnHoldContribution2Id, 'total_amount' => 2.2, 'currency' => 'EUR'],
    ], $collectReceivableHelper->getReceivableContributions($mandate['entity_id']));
  }

  public function testGetReceivableAmount(): void {
    $collectReceivableHelper = new CollectReceivableHelper();
    static::assertSame(0.0, $collectReceivableHelper->getReceivableAmount([]));
    static::assertSame(
      3.3,
      $collectReceivableHelper->getReceivableAmount([
        ['total_amount' => 1.1, 'currency' => 'EUR'],
        ['total_amount' => 2.2, 'currency' => 'EUR'],
      ])
    );
  }

}
