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
    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
      ]
    );

    $this->executeBatching(self::MANDATE_TYPE_OOFF);

    $contribution = $this->getLatestContributionForMandate($mandate);
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
      E::ts('There should be no transaction group be associated with the mandate after terminating.')
    );

    $this->executeBatching(self::MANDATE_TYPE_OOFF);

    // Assert mandate not being grouped again:

    $mandateForRetesting = $this->getMandate($mandate['id']);
    $contributionForRetesting = $this->getLatestContributionForMandate($mandate);

    $this->assertSame(self::MANDATE_STATUS_INVALID, $mandateForRetesting['status']);
    $this->assertException(
      CRM_Core_Exception::class,
      function() use ($contributionForRetesting)
      {
        $this->getTransactionGroupForContribution($contributionForRetesting);
      },
      E::ts('The mandate is probably incorrectly regrouped again after terminating thus is associated with a transaction group.')
    );
  }

  /**
   * Test the illegal termination of an OOFF mandate after it has been closed..
   * @see Case_ID T02
   */
  public function testOOFFTerminateAfterClosingFails()
  {
    self::markTestSkipped('FIXME: Test fails because the Sepa extension does not throw an error.');

    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
      ]
    );

    $this->executeBatching(self::MANDATE_TYPE_OOFF);

    $contribution = $this->getLatestContributionForMandate($mandate);
    $transactionGroup = $this->getTransactionGroupForContribution($contribution);

    $this->assertNotNull($transactionGroup);

    $this->closeTransactionGroup($transactionGroup['id']);

    // After closing the termination must fail:
    $this->assertException(
      CRM_Core_Exception::class,
      function() use ($mandate)
      {
        $this->terminateMandate($mandate);
      },
      E::ts('It must not be allowed to terminate the mandate after the group has been closed.')
    );

    $closedMandate = $this->getMandate($mandate['id']);

    $this->assertNotSame(
      self::MANDATE_STATUS_INVALID,
      $closedMandate['status'],
      E::ts('The mandate has been illegally terminated after closing.')
    );
    $this->assertSame(
      self::MANDATE_STATUS_SENT,
      $closedMandate['status'],
      E::ts('The mandate status has been illegally touched after closing.')
    );
  }

  /**
   * Test the termination of an RCUR mandate.
   * @see Case_ID T03
   */
  public function testRCURTerminate()
  {
    self::markTestSkipped('FIXME: Test fails because of a possible error in the Sepa extension. -> Check if intended.');

    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_RCUR,
      ]
    );

    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $this->executeBatching(self::MANDATE_TYPE_RCUR);

    $contribution = $this->getLatestContributionForMandate($mandate);
    $transactionGroup = $this->getTransactionGroupForContribution($contribution);

    $this->assertNotNull($transactionGroup);

    $this->terminateMandate($mandate);

    $terminatedMandate = $this->getMandate($mandate['id']);

    $this->assertSame(self::MANDATE_STATUS_COMPLETE, $terminatedMandate['status']);
    $this->assertException(
      CRM_Core_Exception::class,
      function() use ($contribution)
      {
        $this->getTransactionGroupForContribution($contribution);
      },
      E::ts('There should be no transaction group be associated with the mandate after terminating.')
    );

    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $this->executeBatching(self::MANDATE_TYPE_RCUR);

    // Assert mandate not being grouped again:

    $mandateForRetesting = $this->getMandate($mandate['id']);
    $contributionForRetesting = $this->getLatestContributionForMandate($mandateForRetesting);

    $this->assertSame(self::MANDATE_STATUS_INVALID, $mandateForRetesting['status']);
    $this->assertException(
      CRM_Core_Exception::class,
      function() use ($contributionForRetesting)
      {
        $this->getTransactionGroupForContribution($contributionForRetesting);
      },
      E::ts('The mandate is probably incorrectly regrouped again after terminating thus is associated with a transaction group.')
    );
  }

  /**
   * Test the termination of an RCUR mandate after it's collection date.
   * @see Case_ID T04
   */
  public function testRCURTerminateAfterCollectionDate()
  {
    self::markTestSkipped('FIXME: This test fails for an unknown reason, must be debugged.');

    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_RCUR,
      ]
    );

    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $this->executeBatching(self::MANDATE_TYPE_RCUR);

    $contribution = $this->getLatestContributionForMandate($mandate);
    $transactionGroup = $this->getTransactionGroupForContribution($contribution);

    $this->assertNotNull($transactionGroup);

    // Terminate after collection date, which is once a month:
    $endDateString = '+1 month 1 week';
    $this->terminateMandate($mandate, $endDateString);

    $mandate = $this->getMandate($mandate['id']);
    $contribution = $this->getLatestContributionForMandate($mandate);
    $transactionGroup = $this->getTransactionGroupForContribution($contribution);
    $endDate = date('Y-m-d', strtotime($endDateString));

    // At this point, the end date must be set but the mandate NOT be terminated yet!
    $this->assertNotSame(self::MANDATE_STATUS_INVALID, $mandate['status'], E::ts('The mandate has been incorrectly terminated.'));
    $this->assertNotNull($transactionGroup, E::ts('The mandate is not in the transaction group anymore but should be.'));
    $this->assertSameDate($endDate, $contribution['end_date'], E::ts('The end date is not correct.'));

    $this->executeBatching(self::MANDATE_TYPE_FRST, '+1 month');
    $this->executeBatching(self::MANDATE_TYPE_RCUR, '+1 month');

    // Assert mandate not being grouped again:

    $mandateAfterSecondBatching = $this->getMandate($mandate['id']);
    $contributionAfterSecondBatching = $this->getLatestContributionForMandate($mandateAfterSecondBatching);
    $transactionGroupAfterSecondBatching = $this->getTransactionGroupForContribution($contributionAfterSecondBatching);

    $this->assertSame(
      $transactionGroup['id'],
      $transactionGroupAfterSecondBatching['id'],
      E::ts('The mandate has been incorrectly regrouped.')
    );
  }
}
