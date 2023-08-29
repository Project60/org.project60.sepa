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
 * Tests for deferred collections regarding weekends. \
 * FIXME: There is something wrong (and odd) with all of these tests. \
 *        The configuration for exclude_weekends seems to be ignored completely. \
 *        This could be because of an error in the Sepa extension or because something \
 *        is wrong with the tests, either a bug or a wrong assumption regarding the \
 *        correct dates/days to set for it to work.
 * @group headless
 */
class CRM_Sepa_DeferredCollectionTest extends CRM_Sepa_TestBase
{
  public function setUp(): void
  {
    parent::setUp();
    $this->setSepaConfiguration('exclude_weekends', '0');
  }

  /**
   * Test that OOFF mandates do not get deferred Saturdays if the configuration is not set.
   * @see Case_ID D01
   */
  public function testOOFFNotBeingDeferredOnSaturday()
  {
    $this->setSepaConfiguration('exclude_weekends', '0');

    $mandateDate = 'next Saturday + 1 week';

    $mandate = $this->createMandate(
    [
      'type' => self::MANDATE_TYPE_OOFF,
    ],
      $mandateDate
    );

    $this->executeBatching(self::MANDATE_TYPE_OOFF);

    $contribution = $this->getLatestContributionForMandate($mandate);
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
    $this->setSepaConfiguration('exclude_weekends', '0');

    $mandateDate = 'next Sunday + 1 week';

    $mandate = $this->createMandate(
    [
      'type' => self::MANDATE_TYPE_OOFF,
    ],
      $mandateDate
    );

    $this->executeBatching(self::MANDATE_TYPE_OOFF);

    $contribution = $this->getLatestContributionForMandate($mandate);
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
    $this->setSepaConfiguration('exclude_weekends', '0');

    $mandateDate = 'next Saturday + 1 week';

    $mandate = $this->createMandate(
    [
      'type' => self::MANDATE_TYPE_RCUR,
    ],
      $mandateDate
    );

    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $this->executeBatching(self::MANDATE_TYPE_RCUR);

    $contribution = $this->getLatestContributionForMandate($mandate);
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
    $this->setSepaConfiguration('exclude_weekends', '0');

    $mandateDate = 'next Sunday + 1 week';

    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_RCUR,
      ],
      $mandateDate
    );

    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $this->executeBatching(self::MANDATE_TYPE_RCUR);

    $contribution = $this->getLatestContributionForMandate($mandate);
    $transactionGroup = $this->getTransactionGroupForContribution($contribution);

    $collectionDate = $transactionGroup['collection_date'];
    $collectionDate = strtotime($collectionDate);
    $dayOfWeek = date('l', $collectionDate);

    $this->assertSame('Sunday', $dayOfWeek, 'Collection date is not a Saturday.');
  }

  /**
   * Test that OOFF mandates do get deferred Saturdays if the configuration is set.
   * @see Case_ID D02
   */
  public function testOOFFBeingDeferredOnSaturday()
  {
    $this->setSepaConfiguration('exclude_weekends', '1');

    $mandateDate = 'next Saturday + 1 week';

    $mandate = $this->createMandate(
    [
      'type' => self::MANDATE_TYPE_OOFF,
    ],
      $mandateDate
    );

    $this->executeBatching(self::MANDATE_TYPE_OOFF);

    $contribution = $this->getLatestContributionForMandate($mandate);
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
    $this->setSepaConfiguration('exclude_weekends', '1');

    $mandateDate = 'next Sunday + 1 week';

    $mandate = $this->createMandate(
    [
      'type' => self::MANDATE_TYPE_OOFF,
    ],
      $mandateDate
    );

    $this->executeBatching(self::MANDATE_TYPE_OOFF);

    $contribution = $this->getLatestContributionForMandate($mandate);
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
    $this->setSepaConfiguration('exclude_weekends', '1');

    $mandateDate = 'next Saturday + 1 week';

    $mandate = $this->createMandate(
    [
      'type' => self::MANDATE_TYPE_RCUR,
    ],
      $mandateDate
    );

    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $this->executeBatching(self::MANDATE_TYPE_RCUR);

    $contribution = $this->getLatestContributionForMandate($mandate);
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
    $this->setSepaConfiguration('exclude_weekends', '1');

    $mandateDate = 'next Sunday + 1 week';

    $mandate = $this->createMandate(
    [
      'type' => self::MANDATE_TYPE_RCUR,
    ],
      $mandateDate
    );

    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $this->executeBatching(self::MANDATE_TYPE_RCUR);

    $contribution = $this->getLatestContributionForMandate($mandate);
    $transactionGroup = $this->getTransactionGroupForContribution($contribution);

    $collectionDate = $transactionGroup['collection_date'];
    $collectionDate = strtotime($collectionDate);
    $dayOfWeek = date('l', $collectionDate);

    $this->assertNotSame('Saturday', $dayOfWeek, 'Collection date is not being deferred.');
    $this->assertNotSame('Sunday', $dayOfWeek, 'Collection date is not being deferred.');
  }

  /**
   * Test that RCUR mandates is still deferred correctly
   *   with the weekend_defers option enabled
   */
  public function testRCURBeingDeferredOnSaturdayWithWeekendDefers()
  {
    $this->setSepaConfiguration('exclude_weekends', '1');
    $this->setSepaConfiguration('weekend_defers', '1');

    $mandateDate = 'next Saturday + 1 week';

    $mandate = $this->createMandate(['type' => self::MANDATE_TYPE_RCUR], $mandateDate);

    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $this->executeBatching(self::MANDATE_TYPE_RCUR);

    $contribution = $this->getLatestContributionForMandate($mandate);
    $transactionGroup = $this->getTransactionGroupForContribution($contribution);

    $collectionDate = $transactionGroup['collection_date'];
    $collectionDate = strtotime($collectionDate);
    $dayOfWeek = date('l', $collectionDate);

    $this->assertNotSame('Saturday', $dayOfWeek, 'Collection date is not being deferred.');
    $this->assertNotSame('Sunday', $dayOfWeek, 'Collection date is not being deferred.');
  }

  /**
   * Test that RCUR mandates do get deferred more days with the setting on
   * @see https://github.com/Project60/org.project60.sepa/issues/619
   */
  public function testWeekendDefersSetting1()
  {
    $this->setSepaConfiguration('exclude_weekends', '1');
    $this->setSepaConfiguration('weekends_defer', '0');
    $mandateDate = 'next Saturday + 1 week';

    $mandate = $this->createMandate(['type' => self::MANDATE_TYPE_RCUR], $mandateDate);

    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $this->executeBatching(self::MANDATE_TYPE_RCUR);

    $contribution = $this->getLatestContributionForMandate($mandate);
    $transactionGroup = $this->getTransactionGroupForContribution($contribution);

    $collectionDate = $transactionGroup['collection_date'];
    $collectionDate = strtotime($collectionDate);
    $dayOfWeek = date('l', $collectionDate);

    $this->assertSame('Monday', $dayOfWeek, 'Collection date is not being deferred to Monday.');



    // NOW: check with weekend deferral
    $this->setSepaConfiguration('weekends_defer', '1');

    $mandate2 = $this->createMandate(['type' => self::MANDATE_TYPE_RCUR], $mandateDate);

    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $this->executeBatching(self::MANDATE_TYPE_RCUR);

    $contribution2 = $this->getLatestContributionForMandate($mandate2);
    $transactionGroup2 = $this->getTransactionGroupForContribution($contribution2);

    $collectionDate2 = $transactionGroup2['collection_date'];
    $collectionDate2 = strtotime($collectionDate2);
    $dayOfWeek = date('l', $collectionDate2);
    $this->assertNotSame('Monday', $dayOfWeek, 'Collection date is not being deferred.');
  }

}
