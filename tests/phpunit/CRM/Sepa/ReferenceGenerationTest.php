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
 * Tests for reference generation.
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
class CRM_Sepa_ReferenceGenerationTest extends CRM_Sepa_TestBase
{
  /* activates the hook to generate static references */
  protected $static_mandate_reference = NULL;

  public function setUp(): void
  {
    parent::setUp();
    $this->static_mandate_reference = NULL;
  }

  public function tearDown(): void
  {
    $this->static_mandate_reference = NULL;
    parent::tearDown();
  }

  /**
   * Assert that a given mandate reference is valid. \
   * TODO: Should this be moved to CRM_Sepa_TestBase?
   */
  protected function assertValidMandateReference(string $actual, string $message = '')
  {
    $isValid = is_string($actual) &&
        (strlen($actual) > 0) &&
        (strlen($actual) <= 35) &&
        !preg_match("/[^0-9A-Za-z\+\?\/\-\:\(\)\.\,\' ]/", $actual); // There must be no invalid character found.

    $this->assertTrue($isValid, $message);
    return $isValid;
  }

  /**
   * This hook is called before a newly created mandate is written to the DB. \
   * We implement it to test if it works by setting a custom reference.
   * @param array $mandate_parameters The parameters that will be used to create the mandate.
   * @return bool|string Based on op. pre-hooks return a boolean or an error message which aborts the operation.
   */
  public function hook_civicrm_create_mandate(array &$mandate_parameters) {
    if ($this->static_mandate_reference) {
      $mandate_parameters['reference'] = $this->static_mandate_reference;
    }
  }


  /**
   * Test the integrity of an OOFF mandate reference.
   * @see Case_ID R01
   */
  public function testOOFFMandateReference()
  {
    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
      ]
    );

    $this->assertValidMandateReference($mandate['reference'], E::ts('The OOFF mandate reference is invalid.'));
  }

  /**
   * Test the integrity of a RCUR mandate reference.
   * @see Case_ID R02
   */
  public function testRCURMandateReference()
  {
    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_RCUR,
      ]
    );

    $this->assertValidMandateReference($mandate['reference'], E::ts('The RCUR mandate reference is invalid.'));
  }

  /**
   * Test to ensure OOFF reference collisions are detected
   *
   * @see Case_ID R03
   */
  public function testOOFFMandateReferenceCollision()
  {
    // enable usage of static reference
    $this->static_mandate_reference = "OOFF-STATIC-TEST-0001";

    // Use the same contact for every mandate to check reference generation per contact:
    $contactId = $this->createContact();

    // first mandate should work
    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
        'contact_id' => $contactId,
      ]
    );

    // NOW: try creating a second mandate with the _same_ reference
    try {
      $this->createMandate(['type' => self::MANDATE_TYPE_OOFF, 'contact_id' => $contactId,]);
      // this should, of course, fail:
      $this->fail("This should've failed, since we're using the same reference for multiple mandates");

    } catch (Throwable $ex) {
      // ok, it failed - let's check if it's the right failure
      $this->assertStringStartsWith(
        "Failure in api call for SepaMandate createfull:  DB Error: already exists",
        $ex->getMessage(),
        "This should've thrown an 'DB Error: already exists'");
    }
  }

  /**
   * Test to ensure RCUR reference collisions are detected
   *
   * @see Case_ID R03
   */
  public function testRCURMandateReferenceCollision()
  {
    // enable usage of static reference
    $this->static_mandate_reference = "RCUR-STATIC-TEST-0001";

    // Use the same contact for every mandate to check reference generation per contact:
    $contactId = $this->createContact();

    // first mandate should work
    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_RCUR,
        'contact_id' => $contactId,
      ]
    );

    // NOW: try creating a second mandate with the _same_ reference
    try {
      $this->createMandate(['type' => self::MANDATE_TYPE_RCUR, 'contact_id' => $contactId,]);
      // this should, of course, fail:
      $this->fail("This should've failed, since we're using the same reference for multiple mandates");

    } catch (Throwable $ex) {
      // ok, it failed - let's check if it's the right failure
      $this->assertStringStartsWith(
        "Failure in api call for SepaMandate createfull:  DB Error: already exists",
        $ex->getMessage(),
        "This should've thrown an 'DB Error: already exists'");
    }
  }
}
