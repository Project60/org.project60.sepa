<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit - PHPUnit tests         |
| Copyright (C) 2019 SYSTOPIA                            |
| Author: B. Zschiedrich (zschiedrich@systopia.de)       |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

use CRM_Sepa_ExtensionUtil as E;

include_once('TestBase.php');

/**
 * Tests for mandates.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *  Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *  rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *  If this test needs to manipulate schema or truncate tables, then either:
 *     a. Do all that using setupHeadless() and Civi\Test.
 *     b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Sepa_MandateTest extends CRM_Sepa_TestBase
{
  public function setUp(): void
  {
    parent::setUp();
  }

  public function testOOFFCreate()
  {
    $mandate = $this->createOOFFMandate();
    $contribution = $this->getContributionForOOFFMandate($mandate);

    $this->assertSame('OOFF', $mandate['status'], E::ts('OOFF Mandate after creation is incorrect.'));
    $this->assertSame('1', $contribution['contribution_status_id'], E::ts('OOFF contribution after creation is incorrect.'));
  }



  /**
   * Create an OOFF mandate.
   * @return array The mandate.
   */
  protected function createOOFFMandate(): array
  {
    $result = $this->callAPISuccess(
      'SepaMandate',
      'createfull',
      [
        'contact_id' => $this->createContact(),
        'type' => 'OOFF',
        'iban' => self::TEST_IBAN,
        'amount' => 8,
        'financial_type_id' => 1
      ]
    );

    $mandateId = $result['id'];
    $mandate = $result['values'][$mandateId];

    return $mandate;
  }

  /**
   * Get the contribution for a given OOFF mandate.
   */
  protected function getContributionForOOFFMandate(array $mandate): array
  {
    $contributionId = $mandate['entity_id'];

    $contribution = $this->callAPISuccessGetSingle(
      'Contribution',
      [
        'id' => $contributionId,
      ]
    );

    return $contribution;
  }

  /**
   * Checks if two date strings or date and time strings have the same date.
   */
  protected function dateIsTheSame(string $dateOrDatetimeA, string $dateOrDatetimeB): bool
  {
    $lengthOfDate = 8; // 4 (the year) + 2 (the month) + 2 (the day) NOTE: This will break in the year 10000.

    $cleanedDateA = preg_replace('/[^0-9]/', '', $dateOrDatetimeA); // Remove everything that is not a number.
    $cleanedDateB = preg_replace('/[^0-9]/', '', $dateOrDatetimeB);

    $dateA = substr($cleanedDateA, 0, $lengthOfDate);
    $dateB = substr($cleanedDateB, 0, $lengthOfDate);

    $datesAreTheSame = $dateA == $dateB;

    return $datesAreTheSame;
  }
}
