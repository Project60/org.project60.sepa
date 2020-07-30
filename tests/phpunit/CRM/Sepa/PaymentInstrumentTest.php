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
 * Testing:
 *  payment instruments in a traditional SEPA mandate live cycle (OOFF / RCUR)
 *  payment instruments with customised payemnt instruments
 *    - unique payment instruments
 *    - multiple payment instruments
 *    - no ooff (disabled)
 *    - no rcur (disabled)
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
    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
      ]
    );
    $contribution = $this->getLatestContributionForMandate($mandate);

    // we're expecting the OOFF payment instrument
    $this->assertNotEmpty($sdd_instruments['OOFF'], "Couldn't find OOFF payment instrument");
    $this->assertEquals($sdd_instruments['OOFF'], $contribution['payment_instrument_id'], "Traditional OOFF mandate's contribution should have payment instrument OOFF");
  }

  /**
   * Test the payment instruments of a traditional OOFF mandate
   */
  public function testTraditionalMandateRCUR()
  {
    $sdd_instruments = CRM_Sepa_Logic_PaymentInstruments::getClassicSepaPaymentInstruments();
    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_RCUR,
      ]
    );

    // batch & check
    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $mandate = $this->getMandate($mandate['id']);
    $contribution = $this->getLatestContributionForMandate($mandate);
    $this->assertNotEmpty($sdd_instruments['FRST'], "Couldn't find FRST payment instrument");
    $this->assertEquals($sdd_instruments['FRST'], $contribution['payment_instrument_id'], "Traditional RCUR mandate's first contribution should have payment instrument FRST");

    // close & check
    $transactionGroup = $this->getActiveTransactionGroup(self::MANDATE_TYPE_FRST);
    $this->closeTransactionGroup($transactionGroup['id']);
    $this->executeBatching(self::MANDATE_TYPE_RCUR, "now + 1 month");
    $mandate = $this->getMandate($mandate['id']);
    $contribution = $this->getLatestContributionForMandate($mandate);
    $this->assertNotEmpty($sdd_instruments['RCUR'], "Couldn't find RCUR payment instrument");
    $this->assertEquals($sdd_instruments['RCUR'], $contribution['payment_instrument_id'], "Traditional RCUR mandate's follow-up contribution should have payment instrument RCUR");
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
      $this->assertFalse(true, "Trying to create a multi-PI creditor's mandate without a payment instrument should thrown an exception");
    } catch (Exception $ex) {
      $this->assertTrue(true, "This worked as expected");
    }


    // test 2: you not able to create a mandate with the wrong PI
    try {
      $mandate = $this->createMandate(
        [
          'type'        => self::MANDATE_TYPE_OOFF,
          'creditor_id' => $custom_creditor_id,
          'payment_instrument_id' => 5,
        ]
      );
      $this->assertFalse(true, "Trying to create a multi-PI creditor's mandate a wrong payment instrument should thrown an exception");
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
    $this->assertNotEmpty($mandate['id'], "Creating a multi-PI creditor's mandate with a payment instrument should have worked");
    $contribution = $this->getLatestContributionForMandate($mandate);
    $this->assertEquals(1, $contribution['payment_instrument_id'], "Multi-PI creditor's mandate should set the passed payment instrument (1)");
  }
}
