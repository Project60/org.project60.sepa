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

use Civi\Api4\ContributionRecur;
use Civi\Api4\SepaMandate;
use Civi\Api4\SepaMandateLink;
use Civi\Sepa\AbstractSepaHeadlessTestCase;
use Civi\Sepa\Mandate\MandateLinkClasses;
use Systopia\TestFixtures\Fixtures\Builders\ContactBuilder;
use Systopia\TestFixtures\Fixtures\Builders\FinancialTypeBuilder;
use Systopia\TestFixtures\Fixtures\Builders\SepaCreditorBuilder;

/**
 * @covers \Civi\Sepa\Api4\Action\SepaMandate\ReplaceWithAction
 * @covers \Civi\Api4\SepaMandate
 *
 * @group headless
 */
final class ReplaceWithActionTest extends AbstractSepaHeadlessTestCase {

  public function test(): void {
    \CRM_Utils_Time::setTime('2026-10-01');

    $creditorId = SepaCreditorBuilder::createDefault(['uses_bic' => FALSE]);
    $contactId = ContactBuilder::createDefault();
    $financialTypeId = FinancialTypeBuilder::create();
    $mandate1 = SepaMandate::createFull(FALSE)
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
    $mandate2 = SepaMandate::createFull(FALSE)
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

    SepaMandate::replaceWith()
      ->setId($mandate1['id'])
      ->setReplacementId($mandate2['id'])
      ->setDate('2026-10-01')
      ->setReason('test')
      ->execute();

    $contributionRecurEnded = ContributionRecur::get(FALSE)
      ->addSelect('end_date', 'cancel_date')
      ->addWhere('id', '=', $mandate1['entity_id'])
      ->execute()
      ->single();

    static::assertSame('2026-10-01 00:00:00', $contributionRecurEnded['end_date']);
    static::assertSame('2026-10-01 00:00:00', $contributionRecurEnded['cancel_date']);

    static::assertSame(1, SepaMandateLink::get(FALSE)
      ->selectRowCount()
      ->addWhere('mandate_id', '=', $mandate2['id'])
      ->addWhere('entity_id', '=', $mandate1['id'])
      ->addWhere('entity_table', '=', 'civicrm_sdd_mandate')
      ->addWhere('class', '=', MandateLinkClasses::REPLACES)
      ->execute()
      ->countMatched()
    );
  }

}
