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
 * Tests for BIC verification.
 *
 * @group headless
 */
class CRM_Sepa_VerifyBicTest extends CRM_Sepa_TestBase
{
  protected const TEST_BIC_SHORT = 'COLSDE33';
  protected const TEST_BIC_TEST_CODE = 'ABCDDE30XXX'; // 8th digit is a zero, meaning this is a test BIC.
  protected const TEST_BIC_PASSIVE_MEMBER_CODE = 'ABCDDE31XXX'; // 8th digit is a one, meaning the owner of the BIC is passive SWIFT member.
  protected const TEST_BIC_TRANSACTION_COST_CODE = 'ABCDDE32XXX'; // 8th digit is a two, marking that the receiver pays the transaction cost.
  protected const TEST_BIC_INVALID_LOCATION = 'BELADE0EXXX'; // 7th digit must not be zero or one.
  protected const TEST_BIC_WRONG_FOR_IBAN = 'BELADEBEXXX';
  protected const TEST_BIC_NONEXISTENT = 'ABCDDE33XXX'; // Correct format but does not exist.
  protected const TEST_BIC_INCORRECT = 'INCORRECT';

  public function setUp(): void
  {
    $this->setCreditorConfiguration('uses_bic', 'true');

    parent::setUp();
  }

  /**
   * Test that a valid BIC works.
   * @see Case_ID V01
   */
  public function testValidBic()
  {
    $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
        'iban' => self::TEST_IBAN,
        'bic' => self::TEST_BIC,
      ]
    );
  }

  /**
   * Test that a short BIC works.
   * @see Case_ID V01
   */
  public function testShortBic()
  {
    $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
        'iban' => self::TEST_IBAN,
        'bic' => self::TEST_BIC_SHORT,
      ]
    );
  }

  /**
   * Test that a test BIC works.
   * @see Case_ID V01
   */
  public function testTestBic()
  {
    $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
        'iban' => self::TEST_IBAN,
        'bic' => self::TEST_BIC_TEST_CODE,
      ]
    );
  }

  /**
   * Test that the BIC of a passive SWIFT member works.
   * @see Case_ID V01
   */
  public function testPassiveSwiftMemberBic()
  {
    $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
        'iban' => self::TEST_IBAN,
        'bic' => self::TEST_BIC_PASSIVE_MEMBER_CODE,
      ]
    );
  }

  /**
   * Test that a BIC with a marking that the receiver pays the transaction cost works.
   * @see Case_ID V01
   */
  public function testReceiverPaysTransactionCostBic()
  {
    $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
        'iban' => self::TEST_IBAN,
        'bic' => self::TEST_BIC_TRANSACTION_COST_CODE,
      ]
    );
  }

  /**
   * Test that a BIC with an invalid location code fails.
   * @see Case_ID V01
   */
  public function testInvalidLocationBic()
  {
    $this->assertException(
      PHPUnit_Framework_ExpectationFailedException::class,
      function ()
      {
        $this->createMandate(
          [
            'type' => self::MANDATE_TYPE_OOFF,
            'iban' => self::TEST_IBAN,
            'bic' => self::TEST_BIC_INVALID_LOCATION,
          ]
        );
      },
      E::ts('Invalid BIC location detection fails!')
    );
  }

  /**
   * Test that a BIC not matching the IBAN but being correct and real fails.
   * @see Case_ID V01
   */
  public function disabled_testWrongBicForIbanFails()
  {
    self::markTestSkipped('FIXME: Test fails because the Sepa extension does only verify that BICs have a correct format.');

    $this->assertException(
      PHPUnit_Framework_ExpectationFailedException::class,
      function ()
      {
        $this->createMandate(
          [
            'type' => self::MANDATE_TYPE_OOFF,
            'iban' => self::TEST_IBAN,
            'bic' => self::TEST_BIC_WRONG_FOR_IBAN,
          ]
        );
      },
      E::ts('Wrong BIC for IBAN detection fails!')
    );
  }

  /**
   * Test that a BIC with correct format but not in use fails.
   * @see Case_ID V01
   */
  public function disabled_testNonexistentBic()
  {
    self::markTestSkipped('FIXME: Test fails because the Sepa extension does only verify that BICs have a correct format.');

    $this->assertException(
      PHPUnit_Framework_ExpectationFailedException::class,
      function ()
      {
        $this->createMandate(
          [
            'type' => self::MANDATE_TYPE_OOFF,
            'iban' => self::TEST_IBAN,
            'bic' => self::TEST_BIC_NONEXISTENT,
          ]
        );
      },
      E::ts('Nonexistent BIC detection fails!')
    );
  }

  /**
   * Test that an incorrect BIC fails.
   * @see Case_ID V01
   */
  public function testIncorrectBicFails()
  {
    $this->assertException(
      PHPUnit_Framework_ExpectationFailedException::class,
      function ()
      {
        $this->createMandate(
          [
            'type' => self::MANDATE_TYPE_OOFF,
            'iban' => self::TEST_IBAN,
            'bic' => self::TEST_BIC_INCORRECT,
          ]
        );
      },
      E::ts('Incorrect BIC detection fails!')
    );
  }

  /**
   * Test that a PSP creator accepts incorrect BICs.
   * @see Case_ID V02
   */
  public function testPspCreatorAcceptsIncorrectBic()
  {
    $this->setCreditorConfiguration('creditor_type', 'PSP');

    $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
        'iban' => self::TEST_IBAN,
        'bic' => self::TEST_BIC_INCORRECT,
      ]
    );
  }
}
