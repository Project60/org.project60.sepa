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
  const MANDATE_TYPE_OOFF = 'OOFF';
  const MANDATE_TYPE_RCUR = 'RCUR';
  const MANDATE_TYPE_FRST = 'FRST';
  const MANDATE_TYPE_SENT = 'SENT';

  //
  //  Tests
  //


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

  //
  //  Actions
  //

  /**
   * Create a mandate.
   * @param string $mandateType The type of the mandate, possible values can be found in the class constants as "MANDATE_TYPE_X"..
   * @return array The mandate.
   */
  protected function createMandate(string $mandateType): array
  {
    $parameters = [
      'contact_id' => $this->createContact(),
      'type' => $mandateType,
      'iban' => self::TEST_IBAN,
      'amount' => 8,
      'financial_type_id' => 1,
    ];

    if ($mandateType == self::MANDATE_TYPE_RCUR)
    {
      $parameters['frequency_unit'] = 'month';
      $parameters['frequency_interval'] = 1;
    }

    $result = $this->callAPISuccess(
      'SepaMandate',
      'createfull',
      $parameters
    );

    $mandateId = $result['id'];
    $mandate = $result['values'][$mandateId];

    return $mandate;
  }

  /**
   * Execute batching for mandates, resulting in the creation of a group.
   * @param string $type The type of the mandates to batch, possible values can be found in the class constants as "MANDATE_TYPE_X".
  */
  protected function executeBatching(string $type): void
  {
    $this->callAPISuccess(
      'SepaAlternativeBatching',
      'update',
      [
        'type' => $type,
      ]
    );
  }

  /**
   * Close a transaction group of the given ID.
   */
  protected function closeTransactionGroup(string $groupId): void
  {
    $this->callAPISuccess(
      'SepaAlternativeBatching',
      'close',
      [
        'txgroup_id' =>  $groupId
      ]
    );
  }

  //
  //  Getters
  //

  /**
   * Get a mandate by it's ID.
   */
  protected function getMandate(string $mandateId): array
  {
    $mandate = $this->callAPISuccessGetSingle(
      'SepaMandate',
      [
        'id' => $mandateId
      ]
    );

      return $mandate;
  }

  /**
   * Get the contribution for a given mandate.
   * @param array $mandate The mandate to get the contribution for.
   * @param string $mandateType The type of the mandate, possible values can be found in the class constants as "MANDATE_TYPE_X".
   */
  protected function getContributionForMandate(array $mandate, string $mandateType): array
  {
    $contributionId = $mandate['entity_id'];

    $contributionEntity = $mandateType == self::MANDATE_TYPE_OOFF ? 'Contribution' : 'ContributionRecur';

    $contribution = $this->callAPISuccessGetSingle(
      $contributionEntity,
      [
        'id' => $contributionId,
      ]
    );

    return $contribution;
  }

  /**
   * Get the only active transaction group.
   * @param string $type The type of the mandate, possible values can be found in the class constants as "MANDATE_TYPE_X".
   * @return array The transaction group.
   */
  protected function getActiveTransactionGroup(string $type): array
  {
    $group = $this->callAPISuccessGetSingle(
      'SepaTransactionGroup',
      [
        'type' => $type,
        'status_id' => 1,
      ]
    );

    return $group;
  }

  /**
   * Get a transaction group by ID.
   */
  protected function getTransactionGroup(string $groupId): array
  {
    $group = $this->callAPISuccessGetSingle(
      'SepaTransactionGroup',
      [
        'id' => $groupId
      ]
    );

    return $group;
  }

  //
  //  Helpers
  //

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
