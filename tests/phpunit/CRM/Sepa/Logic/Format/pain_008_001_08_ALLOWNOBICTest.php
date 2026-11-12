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

use CRM_Sepa_ExtensionUtil as E;

/**
 * @covers \CRM_Sepa_Logic_Format_pain_008_001_08_ALLOWNOBIC
 *
 * @group headless
 */
final class CRM_Sepa_Logic_Format_pain_008_001_08_ALLOWNOBICTest extends CRM_Sepa_TestBase {

  public function test(): void {
    $creditorParams = [
      'sepa_file_format_id:name' => 'pain.008.001.08_ALLOWNOBIC',
      'iban' => 'DE12500105170648489890',
      'bic' => 'TESTDEFFXXX',
    ];
    $mandateParams = [
      'type' => 'OOFF',
      'iban' => 'DE02370501980001802057',
      'bic' => 'BELADEBEXXX',
      'amount' => 10.00,
    ];
    $sepaSddFile = $this->prepareSddFileForXmlGeneration($creditorParams, $mandateParams);
    $xml = (new CRM_Sepa_BAO_SEPASddFile())->generatexml($sepaSddFile['id']);

    static::assertStringContainsString('<IBAN>DE12500105170648489890</IBAN>', $xml);
    static::assertStringContainsString('<BICFI>TESTDEFFXXX</BICFI>', $xml);

    static::assertStringContainsString('<NbOfTxs>1</NbOfTxs>', $xml);

    static::assertStringContainsString('<IBAN>DE02370501980001802057</IBAN>', $xml);
    static::assertStringContainsString('<BICFI>BELADEBEXXX</BICFI>', $xml);
    static::assertStringContainsString('<InstdAmt Ccy="EUR">10.00</InstdAmt>', $xml);

    $doc = new DOMDocument();
    $doc->loadXML($xml);
    // The same xsd as in pain_008_001_08 applies
    static::assertTrue($doc->schemaValidate(E::path('templates/Sepa/Formats/pain_008_001_08/pain.008.001.08.xsd')));
  }

  public function testWithoutMandateBIC(): void {
    $creditorParams = [
      'sepa_file_format_id:name' => 'pain.008.001.08_ALLOWNOBIC',
      'iban' => 'DE12500105170648489890',
      'bic' => 'TESTDEFFXXX',
      'uses_bic' => FALSE,
    ];
    $mandateParams = [
      'type' => 'OOFF',
      'iban' => 'DE02370501980001802057',
      'bic' => '',
      'amount' => 10.00,
    ];
    $sepaSddFile = $this->prepareSddFileForXmlGeneration($creditorParams, $mandateParams);
    $xml = (new CRM_Sepa_BAO_SEPASddFile())->generatexml($sepaSddFile['id']);

    static::assertStringContainsString('<IBAN>DE12500105170648489890</IBAN>', $xml);
    static::assertStringContainsString('<BICFI>TESTDEFFXXX</BICFI>', $xml);

    static::assertStringContainsString('<NbOfTxs>1</NbOfTxs>', $xml);

    static::assertStringContainsString('<IBAN>DE02370501980001802057</IBAN>', $xml);
    // Must not contain debtor <BICFI>
    static::assertDoesNotMatchRegularExpression(
      '/<DbtrAgt>\s*<FinInstnId>\s*<BICFI>[^<]*<\/BICFI>\s*<\/FinInstnId>\s*<\/DbtrAgt>/i',
      str_replace('\n', '', $xml)
    );
    // Instead must contain <Othr><Id>NOTPROVIDED</Id></Othr>
    static::assertMatchesRegularExpression(
      '/<DbtrAgt>\s*<FinInstnId>\s*<Othr>\s*<Id>NOTPROVIDED<\/Id>\s*<\/Othr>\s*<\/FinInstnId>\s*<\/DbtrAgt>/i',
      str_replace('\n', '', $xml)
    );
    static::assertStringContainsString('<InstdAmt Ccy="EUR">10.00</InstdAmt>', $xml);

    $doc = new DOMDocument();
    $doc->loadXML($xml);
    // The same xsd as in pain_008_001_08 applies
    static::assertTrue($doc->schemaValidate(E::path('templates/Sepa/Formats/pain_008_001_08/pain.008.001.08.xsd')));
  }

}
