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
  /**
   * Test the creation of a OOFF mandate.
   */
  public function testOOFFCreate()
  {
    $mandate = $this->createMandate(self::MANDATE_TYPE_OOFF);
    $contribution = $this->getContributionForMandate($mandate, self::MANDATE_TYPE_OOFF);

    $this->assertSame(self::MANDATE_TYPE_OOFF, $mandate['status'], E::ts('OOFF Mandate after creation is incorrect.'));
    $this->assertSame('2', $contribution['contribution_status_id'], E::ts('OOFF contribution after creation is incorrect.'));
  }

  /**
   * Test the creation of a RCUR mandate.
   */
  public function testRCURCreate()
  {
    $mandate = $this->createMandate(self::MANDATE_TYPE_RCUR);
    $contribution = $this->getContributionForMandate($mandate, self::MANDATE_TYPE_RCUR);

    $this->assertSame(self::MANDATE_TYPE_FRST, $mandate['status'], E::ts('RCUR Mandate after creation is incorrect.'));
    $this->assertSame('2', $contribution['contribution_status_id'], E::ts('RCUR contribution after creation is incorrect.'));
  }

  /**
   * Test the batching for an OOFF mandate.
   */
  public function testOOFFBatch()
  {
    $mandate = $this->createMandate(self::MANDATE_TYPE_OOFF);

    $this->executeBatching(self::MANDATE_TYPE_OOFF);

    $batchedMandate = $this->getMandate($mandate['id']);
    $batchedContribution = $this->getContributionForMandate($batchedMandate, self::MANDATE_TYPE_OOFF);
    $transactionGroup = $this->getActiveTransactionGroup(self::MANDATE_TYPE_OOFF);

    $this->assertSame(self::MANDATE_TYPE_OOFF, $batchedMandate['status'], E::ts('OOFF Mandate status after batching is incorrect.'));
    $this->assertSame('2', $batchedContribution['contribution_status_id'], E::ts('OOFF contribution status after batching is incorrect.'));
    $this->assertSame('1', $transactionGroup['status_id'], E::ts('OOFF transaction group status after batching is incorrect.'));
  }

  /**
   * Test the batching for a RCUR mandate.
   */
  public function testRCURBatch()
  {
    $mandate = $this->createMandate(self::MANDATE_TYPE_RCUR);

    // RCUR mandates are splitted into two types: FRST for the first contribution, RCUR for every one after that:
    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $this->executeBatching(self::MANDATE_TYPE_RCUR);

    $batchedMandate = $this->getMandate($mandate['id']);
    $batchedContribution = $this->getContributionForMandate($batchedMandate, self::MANDATE_TYPE_RCUR);
    $transactionGroup = $this->getActiveTransactionGroup(self::MANDATE_TYPE_FRST);

    $this->assertSame(self::MANDATE_TYPE_FRST, $batchedMandate['status'], E::ts('RCUR Mandate status after batching is incorrect.'));
    $this->assertSame('2', $batchedContribution['contribution_status_id'], E::ts('RCUR contribution status after batching is incorrect.'));
    $this->assertSame('1', $transactionGroup['status_id'], E::ts('RCUR transaction group status after batching is incorrect.'));
  }

  /**
   * Test the closing of an OOFF mandate.
   */
  public function testOOFFClose()
  {
    $mandate = $this->createMandate(self::MANDATE_TYPE_OOFF);

    $this->executeBatching(self::MANDATE_TYPE_OOFF);

    $transactionGroup = $this->getActiveTransactionGroup(self::MANDATE_TYPE_OOFF);

    $this->closeTransactionGroup($transactionGroup['id']);

    $closedMandate = $this->getMandate($mandate['id']);
    $closedContribution = $this->getContributionForMandate($closedMandate, self::MANDATE_TYPE_OOFF);
    $closedTransactionGroup = $this->getTransactionGroup($transactionGroup['id']);

    $this->assertSame(self::MANDATE_TYPE_SENT, $closedMandate['status'], E::ts('OOFF Mandate status after closing is incorrect.'));
    $this->assertSame('5', $closedContribution['contribution_status_id'], E::ts('OOFF contribution status after closing is incorrect.'));
    $this->assertSame('2', $closedTransactionGroup['status_id'], E::ts('OOFF transaction group status after closing is incorrect.'));
  }

  /**
   * Test the closing of a RCUR mandate.
   */
  public function testRCURClose()
  {
    $mandate = $this->createMandate(self::MANDATE_TYPE_RCUR);

    // RCUR mandates are splitted into two types: FRST for the first contribution, RCUR for every one after that:
      $this->executeBatching(self::MANDATE_TYPE_FRST);
      $this->executeBatching(self::MANDATE_TYPE_RCUR);

    $transactionGroup = $this->getActiveTransactionGroup(self::MANDATE_TYPE_FRST);

    $this->closeTransactionGroup($transactionGroup['id']);

    $closedMandate = $this->getMandate($mandate['id']);
    $closedContribution = $this->getContributionForMandate($closedMandate, self::MANDATE_TYPE_RCUR);
    $closedTransactionGroup = $this->getTransactionGroup($transactionGroup['id']);

    $this->assertSame(self::MANDATE_TYPE_RCUR, $closedMandate['status'], E::ts('RCUR Mandate status after closing is incorrect.'));
    $this->assertSame('2', $closedContribution['contribution_status_id'], E::ts('RCUR contribution status after closing is incorrect.'));
    $this->assertSame('2', $closedTransactionGroup['status_id'], E::ts('RCUR transaction group status after closing is incorrect.'));
  }
}
