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
 * Tests for deferred collections regarding weekends.
 */
class CRM_Sepa_DeferredCollectionTest extends CRM_Sepa_TestBase
{
  public function setUp(): void
  {
    $this->setCreditorConfiguration('exclude_weekends', '0');

    parent::setUp();
  }

  /**
   * Test that OOFF mandates do not get deferred Saturdays if the configuration is not set.
   * @see Case_ID D01
   */
  public function testOOFFNotBeingDeferredOnSaturday()
  {
    self::markTestIncomplete('FIXME: The configuration seems to be ignored completely.');

    $batchingDate = 'next Saturday';
    $mandateDate = $batchingDate . ' + 2 weeks';

    $mandate = $this->createMandate(
    [
      'type' => self::MANDATE_TYPE_OOFF,
    ],
      $mandateDate
    );

    $this->executeBatching(self::MANDATE_TYPE_OOFF, $batchingDate);

    $contribution = $this->getContributionForMandate($mandate);
    $transactionGroup = $this->getTransactionGroupForContribution($contribution);

    $collectionDate = $transactionGroup['collection_date'];
    $collectionDate = strtotime($collectionDate);
    $dayOfWeek = date('l', $collectionDate);

    $this->assertSame('Saturday', $dayOfWeek, 'Collection date is not a Saturday.');
  }

  /**
   * Test that OOFF mandates do not get deferred Sundays if the configuration is not set.
   * @see Case_ID D01
   */
  public function testOOFFNotBeingDeferredOnSunday()
  {
    self::markTestIncomplete('FIXME: The configuration seems to be ignored completely.');

    $batchingDate = 'next Sunday';
    $mandateDate = $batchingDate . ' + 2 weeks';

    $mandate = $this->createMandate(
    [
      'type' => self::MANDATE_TYPE_OOFF,
    ],
      $mandateDate
    );

    $this->executeBatching(self::MANDATE_TYPE_OOFF, $batchingDate);

    $contribution = $this->getContributionForMandate($mandate);
    $transactionGroup = $this->getTransactionGroupForContribution($contribution);

    $collectionDate = $transactionGroup['collection_date'];
    $collectionDate = strtotime($collectionDate);
    $dayOfWeek = date('l', $collectionDate);

    $this->assertSame('Sunday', $dayOfWeek, 'Collection date is not a Sunday.');
  }

  /**
   * Test that RCUR mandates do not get deferred Saturdays if the configuration is not set.
   * @see Case_ID D03
   */
  public function testRCURNotBeingDeferredOnSaturday()
  {
    self::markTestIncomplete('FIXME: The configuration seems to be ignored completely.');

    $batchingDate = 'next Saturday';
    $mandateDate = $batchingDate . ' + 2 weeks';

    $mandate = $this->createMandate(
    [
      'type' => self::MANDATE_TYPE_RCUR,
    ],
      $mandateDate
    );

    $this->executeBatching(self::MANDATE_TYPE_FRST, $batchingDate);
    $this->executeBatching(self::MANDATE_TYPE_RCUR, $batchingDate);

    $contribution = $this->getContributionForMandate($mandate);
    $transactionGroup = $this->getTransactionGroupForContribution($contribution);

    $collectionDate = $transactionGroup['collection_date'];
    $collectionDate = strtotime($collectionDate);
    $dayOfWeek = date('l', $collectionDate);

    $this->assertSame('Saturday', $dayOfWeek, 'Collection date is not a Saturday.');
  }

  /**
   * Test that RCUR mandates do not get deferred Sundays if the configuration is not set.
   * @see Case_ID D03
   */
  public function testRCURNotBeingDeferredOnSunday()
  {
    self::markTestIncomplete('FIXME: The configuration seems to be ignored completely.');

    $batchingDate = 'next Sunday';
    $mandateDate = $batchingDate . ' + 2 weeks';

    $mandate = $this->createMandate(
    [
      'type' => self::MANDATE_TYPE_RCUR,
    ],
      $mandateDate
    );

    $this->executeBatching(self::MANDATE_TYPE_FRST, $batchingDate);
    $this->executeBatching(self::MANDATE_TYPE_RCUR, $batchingDate);

    $contribution = $this->getContributionForMandate($mandate);
    $transactionGroup = $this->getTransactionGroupForContribution($contribution);

    $collectionDate = $transactionGroup['collection_date'];
    $collectionDate = strtotime($collectionDate);
    $dayOfWeek = date('l', $collectionDate);

    $this->assertSame('Sunday', $dayOfWeek, 'Collection date is not a Sunday.');
  }

  /**
   * Test that OOFF mandates do get deferred Saturdays if the configuration is set.
   * @see Case_ID D02
   */
  public function testOOFFBeingDeferredOnSaturday()
  {
    self::markTestIncomplete('FIXME: The configuration seems to be ignored completely.');

    $this->setCreditorConfiguration('exclude_weekends', '1');

    $batchingDate = 'next Saturday';
    $mandateDate = $batchingDate . ' + 2 weeks';

    $mandate = $this->createMandate(
    [
      'type' => self::MANDATE_TYPE_OOFF,
    ],
      $mandateDate
    );

    $this->executeBatching(self::MANDATE_TYPE_OOFF, $batchingDate);

    $contribution = $this->getContributionForMandate($mandate);
    $transactionGroup = $this->getTransactionGroupForContribution($contribution);

    $collectionDate = $transactionGroup['collection_date'];
    $collectionDate = strtotime($collectionDate);
    $dayOfWeek = date('l', $collectionDate);

    $this->assertNotSame('Saturday', $dayOfWeek, 'Collection date is not being deferred.');
    $this->assertNotSame('Sunday', $dayOfWeek, 'Collection date is not being deferred.');
  }

  /**
   * Test that OOFF mandates do get deferred Sundays if the configuration is set.
   * @see Case_ID D02
   */
  public function testOOFFBeingDeferredOnSunday()
  {
    self::markTestIncomplete('FIXME: The configuration seems to be ignored completely.');

    $this->setCreditorConfiguration('exclude_weekends', '1');

    $batchingDate = 'next Sunday';
    $mandateDate = $batchingDate . ' + 2 weeks';

    $mandate = $this->createMandate(
    [
      'type' => self::MANDATE_TYPE_OOFF,
    ],
      $mandateDate
    );

    $this->executeBatching(self::MANDATE_TYPE_OOFF, $batchingDate);

    $contribution = $this->getContributionForMandate($mandate);
    $transactionGroup = $this->getTransactionGroupForContribution($contribution);

    $collectionDate = $transactionGroup['collection_date'];
    $collectionDate = strtotime($collectionDate);
    $dayOfWeek = date('l', $collectionDate);

    $this->assertNotSame('Saturday', $dayOfWeek, 'Collection date is not being deferred.');
    $this->assertNotSame('Sunday', $dayOfWeek, 'Collection date is not being deferred.');
  }

  /**
   * Test that RCUR mandates do get deferred Saturdays if the configuration is set.
   * @see Case_ID D04
   */
  public function testRCURBeingDeferredOnSaturday()
  {
    self::markTestIncomplete('FIXME: The configuration seems to be ignored completely.');

    $this->setCreditorConfiguration('exclude_weekends', '1');

    $batchingDate = 'next Saturday';
    $mandateDate = $batchingDate . ' + 2 weeks';

    $mandate = $this->createMandate(
    [
      'type' => self::MANDATE_TYPE_RCUR,
    ],
      $mandateDate
    );

    $this->executeBatching(self::MANDATE_TYPE_FRST, $batchingDate);
    $this->executeBatching(self::MANDATE_TYPE_RCUR, $batchingDate);

    $contribution = $this->getContributionForMandate($mandate);
    $transactionGroup = $this->getTransactionGroupForContribution($contribution);

    $collectionDate = $transactionGroup['collection_date'];
    $collectionDate = strtotime($collectionDate);
    $dayOfWeek = date('l', $collectionDate);

    $this->assertNotSame('Saturday', $dayOfWeek, 'Collection date is not being deferred.');
    $this->assertNotSame('Sunday', $dayOfWeek, 'Collection date is not being deferred.');
  }

  /**
   * Test that RCUR mandates do get deferred Sundays if the configuration is set.
   * @see Case_ID D04
   */
  public function testRCURBeingDeferredOnSunday()
  {
    self::markTestIncomplete('FIXME: The configuration seems to be ignored completely.');

    $this->setCreditorConfiguration('exclude_weekends', '1');

    $batchingDate = 'next Sunday';
    $mandateDate = $batchingDate . ' + 2 weeks';

    $mandate = $this->createMandate(
    [
      'type' => self::MANDATE_TYPE_RCUR,
    ],
      $mandateDate
    );

    $this->executeBatching(self::MANDATE_TYPE_FRST, $batchingDate);
    $this->executeBatching(self::MANDATE_TYPE_RCUR, $batchingDate);

    $contribution = $this->getContributionForMandate($mandate);
    $transactionGroup = $this->getTransactionGroupForContribution($contribution);

    $collectionDate = $transactionGroup['collection_date'];
    $collectionDate = strtotime($collectionDate);
    $dayOfWeek = date('l', $collectionDate);

    $this->assertNotSame('Saturday', $dayOfWeek, 'Collection date is not being deferred.');
    $this->assertNotSame('Sunday', $dayOfWeek, 'Collection date is not being deferred.');
  }
}
