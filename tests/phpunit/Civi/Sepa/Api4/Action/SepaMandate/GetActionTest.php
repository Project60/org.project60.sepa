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

use Civi\Api4\SepaMandate;
use Civi\Sepa\AbstractSepaHeadlessTestCase;
use Systopia\TestFixtures\Fixtures\Builders\ContactBuilder;
use Systopia\TestFixtures\Fixtures\Builders\ContributionBuilder;
use Systopia\TestFixtures\Fixtures\Builders\FinancialTypeBuilder;
use Systopia\TestFixtures\Fixtures\Builders\SepaCreditorBuilder;

/**
 * @covers \Civi\Api4\SepaMandate
 * @covers \Civi\Sepa\Api4\Action\SepaMandate\GetAction
 * @covers \Civi\Sepa\Api4\Action\SepaMandate\GetFieldsAction
 *
 * @group headless
 */
final class GetActionTest extends AbstractSepaHeadlessTestCase {

  public function testGetReceivableAmount(): void {
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

    static::assertSame(
      0.0,
      SepaMandate::get()->addSelect('receivable_amount')->execute()->single()['receivable_amount']
    );

    ContributionBuilder::createPendingForContact(
      $contactId,
      [
        'contribution_recur_id' => $mandate['entity_id'],
        'total_amount' => 1.1,
        'sepa_contribution.is_on_hold' => TRUE,
      ]
    );

    static::assertSame(
      1.1,
      SepaMandate::get()->addSelect('receivable_amount')->execute()->single()['receivable_amount']
    );
  }

}
