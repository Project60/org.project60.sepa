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
 * Tests for IBAN verification.
 *
 * @group headless
 */
class CRM_Sepa_VerifyIbanTest extends CRM_Sepa_TestBase
{
  const TEST_IBAN_2 = 'DE02100100100006820101';
  const TEST_IBAN_INCORRECT_CONTENT = 'DE12300105171814696324';
  const TEST_IBAN_INCORRECT_BANK_CODE = 'DE02470501980001802057';
  const TEST_IBAN_INCORRECT_CHECKSUM = 'DE03370501980001802057';
  const TEST_IBAN_INCORRECT_LENGTH = 'DE1250010517414168455';
  const TEST_IBAN_INCORRECT_CHAR = 'DE1250010517414168aä🙂';

  // CiviSEPA doesn't test for German internal account number verification
  // protected const TEST_IBAN_INCORRECT_ACCOUNT_NUMBER = 'DE35500105171814696323';

  /**
   * Test that a valid IBAN works.
   * @see Case_ID V03
   */
  public function testValidIban()
  {
    $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
        'iban' => self::TEST_IBAN,
      ]
    );
  }


  /**
   * Test that an IBAN with incorrect content (but correct format) fails.
   * @see Case_ID V03
   */
  public function testIncorrectIbanFails()
  {
    $this->assertException(
      PHPUnit_Framework_ExpectationFailedException::class,
      function ()
      {
        $this->createMandate(
          [
            'type' => self::MANDATE_TYPE_OOFF,
            'iban' => self::TEST_IBAN_INCORRECT_CONTENT,
          ]
        );
      },
      E::ts('Incorrect IBAN detection fails!')
    );
  }

  /**
   * Test that an IBAN with incorrect bank code fails.
   * @see Case_ID V03
   */
  public function testIncorrectBankCodeFails()
  {
    $this->assertException(
      PHPUnit_Framework_ExpectationFailedException::class,
      function ()
      {
        $this->createMandate(
          [
            'type' => self::MANDATE_TYPE_OOFF,
            'iban' => self::TEST_IBAN_INCORRECT_BANK_CODE,
          ]
        );
      },
      E::ts('Incorrect bank code detection fails!')
    );
  }

  /**
   * Test that an IBAN with an incorrect checksum fails.
   * @see Case_ID V03
   */
  public function testIncorrectChecksumFails()
  {
    $this->assertException(
      PHPUnit_Framework_ExpectationFailedException::class,
      function ()
      {
        $this->createMandate(
          [
            'type' => self::MANDATE_TYPE_OOFF,
            'iban' => self::TEST_IBAN_INCORRECT_CHECKSUM,
          ]
        );
      },
      E::ts('Incorrect IBAN checksum detection fails!')
    );
  }

  /**
   * Test that an IBAN with incorrect length fails.
   * @see Case_ID V03
   */
  public function testIncorrectLengthFails()
  {
    $this->assertException(
      PHPUnit_Framework_ExpectationFailedException::class,
      function ()
      {
        $this->createMandate(
          [
            'type' => self::MANDATE_TYPE_OOFF,
            'iban' => self::TEST_IBAN_INCORRECT_LENGTH,
          ]
        );
      },
      E::ts('Incorrect IBAN length detection fails!')
    );
  }

  /**
   * Test that an IBAN with forbidden chars fails.
   * @see Case_ID V03
   */
  public function testIncorrectCharFails()
  {
    $this->assertException(
      PHPUnit_Framework_ExpectationFailedException::class,
      function ()
      {
        $this->createMandate(
          [
            'type' => self::MANDATE_TYPE_OOFF,
            'iban' => self::TEST_IBAN_INCORRECT_CHAR,
          ]
        );
      },
      E::ts('Incorrect char in IBAN detection fails!')
    );
  }

  /**
   * Test that a PSP creator accepts incorrect IBANs.
   * @see Case_ID V04
   */
  public function testPspCreatorAcceptsIncorrectIban()
  {
    $this->setCreditorConfiguration('creditor_type', 'PSP');

    $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
        'iban' => self::TEST_IBAN_INCORRECT_CONTENT,
      ]
    );
  }

  /**
   * Test that an IBAN put on the blocklist will fail.
   * @see Case_ID V05
   */
  public function testBlocklistedIbanFails()
  {
    $this->addIbanToBlocklist(self::TEST_IBAN);

    // should be using $this->assertException but there's something off here
    try {
      $mandate = $this->createMandate(
        [
          'type' => self::MANDATE_TYPE_OOFF,
          'iban' => self::TEST_IBAN,
        ]
      );
      $this->fail(E::ts('Blocklistet IBAN should fail but did not!'));
    } catch (Exception $ex) {
      // this is expected
    }
  }

  /**
   * This will test if a valid IBAN works when there is another IBAN on the blocklist. \
   * NOTE: In the default settings of the Sepa extension there is a test entry on the blocklist, \
   *       so technically this is not necessary as testValidIban does the same; but this \
   *       test will go sure in the case someone removes the default test entry.
   * @see Case_ID V05
   */
  public function testValidIbanWhenOtherIbanIsBlocklisted()
  {
    $this->addIbanToBlocklist(self::TEST_IBAN_2);

    $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
        'iban' => self::TEST_IBAN,
      ]
    );
  }
}
