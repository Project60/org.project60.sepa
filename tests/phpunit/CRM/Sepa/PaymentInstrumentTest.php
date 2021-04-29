<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit - PHPUnit tests         |
| Copyright (C) 2020 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)       |
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
 * Tests for the variable payment instruments introduced in #572
 *
 * Tests:
 *  payment instruments in a traditional SEPA mandate live cycle (OOFF / RCUR)
 *  creditors with customised payment instruments
 *    - unique payment instruments
 *    - multiple payment instruments
 *    - no ooff (disabled)
 *    - no rcur (disabled)
 * TODO Tests:
 *  - custom frst/rcur cycles
 *  - check payemnt instrument ID for recurring contributions
 *
 * @group headless
 */
class CRM_Sepa_PaymentInstrumentTest extends CRM_Sepa_TestBase
{
  /**
   * Test the payment instruments of a traditional OOFF mandate
   */
  public function testTraditionalMandateOOFF()
  {
    $sdd_instruments = CRM_Sepa_Logic_PaymentInstruments::getClassicSepaPaymentInstruments();
    $mandate         = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
      ]
    );
    $contribution    = $this->getLatestContributionForMandate($mandate);

    // we're expecting the OOFF payment instrument
    $this->assertNotEmpty($sdd_instruments['OOFF'], "Couldn't find OOFF payment instrument");
    $this->assertEquals(
      $sdd_instruments['OOFF'],
      $contribution['payment_instrument_id'],
      "Traditional OOFF mandate's contribution should have payment instrument OOFF"
    );
  }

  /**
   * Test the payment instruments of a traditional OOFF mandate
   */
  public function testTraditionalMandateRCUR()
  {
    $sdd_instruments = CRM_Sepa_Logic_PaymentInstruments::getClassicSepaPaymentInstruments();
    $mandate         = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_RCUR,
      ]
    );

    // batch & check
    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $mandate      = $this->getMandate($mandate['id']);
    $contribution = $this->getLatestContributionForMandate($mandate);
    $this->assertNotEmpty($sdd_instruments['FRST'], "Couldn't find FRST payment instrument");
    $this->assertEquals(
      $sdd_instruments['FRST'],
      $contribution['payment_instrument_id'],
      "Traditional RCUR mandate's first contribution should have payment instrument FRST"
    );

    // close & check
    $transactionGroup = $this->getActiveTransactionGroup(self::MANDATE_TYPE_FRST);
    $this->closeTransactionGroup($transactionGroup['id']);
    $this->executeBatching(self::MANDATE_TYPE_RCUR, "now + 1 month");
    $mandate      = $this->getMandate($mandate['id']);
    $contribution = $this->getLatestContributionForMandate($mandate);
    $this->assertNotEmpty($sdd_instruments['RCUR'], "Couldn't find RCUR payment instrument");
    $this->assertEquals(
      $sdd_instruments['RCUR'],
      $contribution['payment_instrument_id'],
      "Traditional RCUR mandate's follow-up contribution should have payment instrument RCUR"
    );
  }

  /**
   * Test the OOFF for multi-payment-instruments creditor
   */
  public function testMultiPIMandateOOFF()
  {
    $custom_creditor_id = $this->getCustomCreditor(['pi_ooff' => '1,2,3']);

    // test 1: you shouldn't be able to create a mandate without PI
    try {
      $mandate = $this->createMandate(
        [
          'type'        => self::MANDATE_TYPE_OOFF,
          'creditor_id' => $custom_creditor_id,
        ]
      );
      $this->assertFalse(
        true,
        "Trying to create a multi-PI creditor's mandate without a payment instrument should thrown an exception"
      );
    } catch (Exception $ex) {
      $this->assertTrue(true, "This worked as expected");
    }


    // test 2: you not able to create a mandate with the wrong PI
    try {
      $mandate = $this->createMandate(
        [
          'type'                  => self::MANDATE_TYPE_OOFF,
          'creditor_id'           => $custom_creditor_id,
          'payment_instrument_id' => 5,
        ]
      );
      $this->assertFalse(
        true,
        "Trying to create a multi-PI creditor's mandate a wrong payment instrument should thrown an exception"
      );
    } catch (Exception $ex) {
      $this->assertTrue(true, "This worked as expected");
    }


    // test 3: you should be able to create a mandate with PI
    $mandate = $this->createMandate(
      [
        'type'                  => self::MANDATE_TYPE_OOFF,
        'creditor_id'           => $custom_creditor_id,
        'payment_instrument_id' => 1
      ]
    );
    $this->assertNotEmpty(
      $mandate['id'],
      "Creating a multi-PI creditor's mandate with a payment instrument should have worked"
    );
    $contribution = $this->getLatestContributionForMandate($mandate);
    $this->assertEquals(
      1,
      $contribution['payment_instrument_id'],
      "Multi-PI creditor's mandate should set the passed payment instrument (1)"
    );
  }

  /**
   * Test the RCUR for multi-payment-instruments creditor
   */
  public function testMultiPIMandateRCUR()
  {
    $custom_creditor_id = $this->getCustomCreditor(['pi_rcur' => '1,2,3']);

    // test 1: you shouldn't be able to create a mandate without PI
    try {
      $mandate = $this->createMandate(
        [
          'type'        => self::MANDATE_TYPE_RCUR,
          'creditor_id' => $custom_creditor_id,
        ]
      );
      $this->assertFalse(
        true,
        "Trying to create a multi-PI creditor's mandate without a payment instrument should thrown an exception"
      );
    } catch (Exception $ex) {
      $this->assertTrue(true, "This worked as expected");
    }


    // test 2: you not able to create a mandate with the wrong PI
    try {
      $mandate = $this->createMandate(
        [
          'type'                  => self::MANDATE_TYPE_RCUR,
          'creditor_id'           => $custom_creditor_id,
          'payment_instrument_id' => 5,
        ]
      );
      $this->assertFalse(
        true,
        "Trying to create a multi-PI creditor's mandate a wrong payment instrument should thrown an exception"
      );
    } catch (Exception $ex) {
      $this->assertTrue(true, "This worked as expected");
    }


    // test 3: you should be able to create a mandate with PI
    $mandate = $this->createMandate(
      [
        'type'                  => self::MANDATE_TYPE_RCUR,
        'creditor_id'           => $custom_creditor_id,
        'payment_instrument_id' => 1
      ]
    );
    $this->assertNotEmpty(
      $mandate['id'],
      "Creating a multi-PI creditor's mandate with a payment instrument should have worked"
    );

    // NOW: do some testing
    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $mandate      = $this->getMandate($mandate['id']);
    $contribution = $this->getLatestContributionForMandate($mandate);
    $this->assertEquals(1, $contribution['payment_instrument_id'], "Requested payment instrument [1] not set.");

    // close & check
    $transactionGroup = $this->getActiveTransactionGroup(self::MANDATE_TYPE_FRST);
    $this->closeTransactionGroup($transactionGroup['id']);
    $this->executeBatching(self::MANDATE_TYPE_RCUR, "now + 1 month");
    $mandate      = $this->getMandate($mandate['id']);
    $contribution = $this->getLatestContributionForMandate($mandate);
    $this->assertEquals(
      1,
      $contribution['payment_instrument_id'],
      "Requested payment instrument [1] not set in second installment."
    );
  }

  /**
   * Test if disabling OOFF works (for new mandates)
   */
  public function testDisabledOOFF()
  {
    $custom_creditor_id = $this->getCustomCreditor(['pi_ooff' => '']);

    // test 1: you shouldn't be able to create a mandate without PI
    try {
      $mandate = $this->createMandate(
        [
          'type'        => self::MANDATE_TYPE_OOFF,
          'creditor_id' => $custom_creditor_id,
        ]
      );
      $this->assertFalse(
        true,
        "Trying to create a OOFF mandate when those payment instruments are disabled should have thrown an exeption"
      );
    } catch (Exception $ex) {
      $this->assertTrue(true, "This worked as expected");
    }

    // test 1: you shouldn't be able to create a mandate with PI
    try {
      $mandate = $this->createMandate(
        [
          'type'                  => self::MANDATE_TYPE_OOFF,
          'creditor_id'           => $custom_creditor_id,
          'payment_instrument_id' => 5,
        ]
      );
      $this->assertFalse(
        true,
        "Trying to create a OOFF mandate when those payment instruments are disabled should have thrown an exeption"
      );
    } catch (Exception $ex) {
      $this->assertTrue(true, "This worked as expected");
    }
  }

  /**
   * Test if disabling RCUR works (for new mandates)
   */
  public function testDisabledRCUR()
  {
    $custom_creditor_id = $this->getCustomCreditor(['pi_rcur' => '']);

    // test 1: you shouldn't be able to create a mandate without PI
    try {
      $mandate = $this->createMandate(
        [
          'type'        => self::MANDATE_TYPE_RCUR,
          'creditor_id' => $custom_creditor_id,
        ]
      );
      $this->assertFalse(
        true,
        "Trying to create a RCUR mandate when those payment instruments are disabled should have thrown an exeption"
      );
    } catch (Exception $ex) {
      $this->assertTrue(true, "This worked as expected");
    }

    // test 1: you shouldn't be able to create a mandate with PI
    try {
      $mandate = $this->createMandate(
        [
          'type'                  => self::MANDATE_TYPE_RCUR,
          'creditor_id'           => $custom_creditor_id,
          'payment_instrument_id' => 5,
        ]
      );
      $this->assertFalse(
        true,
        "Trying to create a RCUR mandate when those payment instruments are disabled should have thrown an exeption"
      );
    } catch (Exception $ex) {
      $this->assertTrue(true, "This worked as expected");
    }
  }

  /**
   * Test if the change of PIs works for regular mandates
   */
  public function testFrstRcurChange()
  {
    $custom_creditor_id = $this->getCustomCreditor([]);

    $PIs     = CRM_Sepa_Logic_PaymentInstruments::getClassicSepaPaymentInstruments();
    $PI_RCUR = $PIs['RCUR'];
    $PI_FRST = $PIs['FRST'];

    // test 1: you shouldn't be able to create a mandate without PI
    try {
      $mandate = $this->createMandate(
        [
          'type'        => self::MANDATE_TYPE_RCUR,
          'creditor_id' => $custom_creditor_id,
        ]
      );
      $this->assertFalse(
        true,
        "Trying to create a RCUR mandate when those payment instruments are disabled should have thrown an exeption"
      );
    } catch (Exception $ex) {
      $this->assertTrue(true, "This worked as expected");
    }

    // TEST 1:
    // - the status of the mandate should be FRST
    // - the payment instrument of the recurring contribution should be RCUR
    //  - the payment instrument of the contribution should be FRST
    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $mandate      = $this->getMandate($mandate['id']);
    $contribution = $this->getLatestContributionForMandate($mandate);
    $this->assertEquals(
      $PI_FRST,
      $contribution['payment_instrument_id'],
      "Payment instrument for first contribution is not FRST."
    );
    $rcontribution = $this->getRecurringContributionForMandate($mandate);
    $this->assertEquals(
      $PI_RCUR,
      $rcontribution['payment_instrument_id'],
      "Payment instrument for recurring contribution is not RCUR."
    );

    // close group
    $transactionGroup = $this->getActiveTransactionGroup(self::MANDATE_TYPE_FRST);
    $this->closeTransactionGroup($transactionGroup['id']);
    $rcontribution = $this->getRecurringContributionForMandate($mandate);
    $this->assertEquals(
      $PI_RCUR,
      $rcontribution['payment_instrument_id'],
      "Payment instrument for recurring contribution is not RCUR."
    );

    // create the next batch and check again
    $this->executeBatching(self::MANDATE_TYPE_RCUR, "now + 1 month");
    $contribution = $this->getLatestContributionForMandate($mandate);
    $this->assertEquals(
      $PI_RCUR,
      $contribution['payment_instrument_id'],
      "Payment instrument for follow up contribution is not RCUR."
    );
    $rcontribution = $this->getRecurringContributionForMandate($mandate);
    $this->assertEquals(
      $PI_RCUR,
      $rcontribution['payment_instrument_id'],
      "Payment instrument for recurring contribution is not RCUR."
    );

  }
}
