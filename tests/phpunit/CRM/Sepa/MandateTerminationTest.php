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
class CRM_Sepa_MandateTerminationTest extends CRM_Sepa_TestBase
{
  public function setUp(): void
  {
    parent::setUp();

    $this->setCreditorConfiguration('batching.OOFF.horizon', 31);
    $this->setCreditorConfiguration('batching.RCUR.horizon', 31);
  }

  /**
   * Test the legal termination of an OOFF mandate.
   * @see Case_ID T01
   */
  public function testOOFFLegalTerminate()
  {
    $mandate = $this->createMandate(self::MANDATE_TYPE_OOFF);

    $this->executeBatching(self::MANDATE_TYPE_OOFF);

    $contribution = $this->getContributionForMandate($mandate);
    $transactionGroup = $this->getTransactionGroupForContribution($contribution);

    $this->assertNotNull($transactionGroup);

    $this->terminateMandate($mandate);

    $terminatedMandate = $this->getMandate($mandate['id']);

    $this->assertSame(self::MANDATE_STATUS_INVALID, $terminatedMandate['status']);
    $this->assertException(
      CRM_Core_Exception::class,
      function() use ($contribution)
      {
        $this->getTransactionGroupForContribution($contribution);
      },
      'There should be no transaction group be associated with the mandate after terminating.'
    );

    $this->executeBatching(self::MANDATE_TYPE_OOFF);

    // Assert mandate not being grouped again:

    $mandateForRetesting = $this->getMandate($mandate['id']);
    $contributionForRetesting = $this->getContributionForMandate($mandate);

    $this->assertSame(self::MANDATE_STATUS_INVALID, $mandateForRetesting['status']);
    $this->assertException(
      CRM_Core_Exception::class,
      function() use ($contributionForRetesting)
      {
        $this->getTransactionGroupForContribution($contributionForRetesting);
      },
      'The mandate is probably incorrectly regrouped again after terminating thus is associated with a transaction group.'
    );
  }
}
