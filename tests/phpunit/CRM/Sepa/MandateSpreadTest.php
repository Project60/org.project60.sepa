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

/**
 * Tests for mandates spreaded over a time period.
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
class CRM_Sepa_MandateSpreadTest extends CRM_Sepa_TestBase
{
  /**
   * Test a spread of collection dates with timetravel.
   * @see Case_ID M03
   */
  public function testOOFFSpread()
  {
    self::markTestIncomplete('Test for test case M03 incomplete.'); // FIXME: Complete the test.

    $this->setCreditorConfiguration('batching.OOFF.horizon', 31);
    $this->setCreditorConfiguration('batching.RCUR.horizon', 31);

    for ($n = 0; $n < 28; $n++)
    {
      // For the following collection date generation:
      // Starting at the next monday guarantees the determinism of this test.
      // Every four days spreads seven mandates once for every day of the week over 3.5 weeks.
      // If we use a multiple of seven we have an equal distribution over every day of the week.
      $date = 'next Monday + ' . $n * 4 . ' days';

      $this->createMandate(self::MANDATE_TYPE_OOFF, $date);
    }

    $this->executeBatching(self::MANDATE_TYPE_OOFF);

    $transactionGroups = $this->getActiveTransactionGroups(self::MANDATE_TYPE_OOFF);

    $this->closeTransactionGroups($transactionGroups);

    // TODO: Verify dates
    // TODO: Timetravel one month and repeat

    // TODO: In the specification for this test there is talk of an annual spread. This is not
    //       implemented and replaced by a fixed spread over the next n times four days. This is
    //       because more than that is not needed in this scenario and having the time frame
    //       resulting out of the amount of mandates makes the test simpler and deterministic.
  }
}
