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

use Civi\Api4\SepaContributionGroup;
use Civi\Api4\SepaMandate;
use Civi\Api4\SepaSddFile;
use Civi\Api4\SepaTransactionGroup;
use CRM_Sepa_ExtensionUtil as E;
use Systopia\TestFixtures\Fixtures\Builders\ContactBuilder;
use Systopia\TestFixtures\Fixtures\Builders\FinancialTypeBuilder;
use Systopia\TestFixtures\Fixtures\Builders\SepaCreditorBuilder;

/**
 * @covers \CRM_Sepa_Logic_Format_pain_008_001_08
 *
 * @group headless
 */
final class CRM_Sepa_Logic_Format_pain_008_001_08Test extends CRM_Sepa_TestBase {

  public function test(): void {
    $contactId = ContactBuilder::createDefault();
    $creditorId = SepaCreditorBuilder::createDefault([
      'pi_ooff' => '1',
      'sepa_file_format_id:name' => 'pain.008.001.08',
      'iban' => 'DE12500105170648489890',
      'bic' => 'TESTDEFFXXX',
    ]);

    $financialTypeId = FinancialTypeBuilder::create();
    $mandate = SepaMandate::createFull(FALSE)
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

    $sepaSddFile = SepaSddFile::create(FALSE)
      ->setValues([
        'reference' => 'test',
        'status_id:name' => 'Closed',
        'created_date' => '2026-10-01 01:02:03',
      ])
      ->execute()
      ->single();

    $transactionGroup = SepaTransactionGroup::create(FALSE)
      ->setValues([
        'reference' => 'test',
        'type' => 'OOFF',
        'status_id:name' => 'Closed',
        'sdd_creditor_id' => $creditorId,
        'sdd_file_id' => $sepaSddFile['id'],
        'collection_date' => '2026-10-01',
      ])
      ->execute()
      ->single();

    SepaContributionGroup::create(FALSE)
      ->setValues([
        'contribution_id' => $mandate['entity_id'],
        'txgroup_id' => $transactionGroup['id'],
      ])
      ->execute();

    $xml = (new CRM_Sepa_BAO_SEPASddFile())->generatexml($sepaSddFile['id']);

    static::assertStringContainsString('<IBAN>DE12500105170648489890</IBAN>', $xml);
    static::assertStringContainsString('<BICFI>TESTDEFFXXX</BICFI>', $xml);

    static::assertStringContainsString('<NbOfTxs>1</NbOfTxs>', $xml);

    static::assertStringContainsString('<IBAN>DE02370501980001802057</IBAN>', $xml);
    static::assertStringContainsString('<BICFI>BELADEBEXXX</BICFI>', $xml);
    static::assertStringContainsString('<InstdAmt Ccy="EUR">10.00</InstdAmt>', $xml);

    $doc = new DOMDocument();
    $doc->loadXML($xml);
    static::assertTrue($doc->schemaValidate(E::path('templates/Sepa/Formats/pain_008_001_08/pain.008.001.08.xsd')));
  }

}
