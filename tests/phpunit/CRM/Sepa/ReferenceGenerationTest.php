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
  /**
   * Assert that a given mandate reference is valid. \
   * TODO: Should this be moved to CRM_Sepa_TestBase?
   */
  protected function assertValidMandateReference(string $actual, string $message = '')
  {
    // TODO: The following is inefficient and should only initialised once.
    //       PHPUnit uses globals for this, a singleton could be an alternative.
    $constraint = new CRM_Sepa_Constraints_MandateReferenceIsValid();

    $this->assertThat($actual, $constraint, $message);
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
   * Test that there are no collisions in a greater amount of OOFF mandates.
   * @see Case_ID R03
   */
  public function testOOFFMandateReferenceCollision()
  {
    self::markTestIncomplete('FIXME: Test OOFFMandateReferenceCollision for test case R03 is incomplete.');

    $referenceMap = [];

    // Use the same contact for every mandate to check reference generation per contact:
    $contactId = $this->createContact();

    for ($i = 0; $i <= 101; $i++) // Test more than hundred for a possible counter overflow after 99.
    {
      $mandate = $this->createMandate(
        [
          'type' => self::MANDATE_TYPE_OOFF,
          'contact_id' => $contactId,
        ]
      );

      $reference = $mandate['reference'];

      $this->assertValidMandateReference($reference, E::ts("The OOFF mandate reference with index $i is invalid."));

      $referenceIsDuplucate = array_key_exists($reference, $referenceMap);

      $this->assertFalse($referenceIsDuplucate, E::ts("The OOFF mandate reference with index $i is duplicate."));

      $referenceMap[$reference] = 1;
    }

    // TODO: Add varying financial types.
    // TODO: Add varying campaigns.
  }

  /**
   * Test that there are no collisions in a greater amount of RCUR mandates.
   * @see Case_ID R04
   */
  public function testRCURMandateReferenceCollision()
  {
    self::markTestIncomplete('FIXME: Test RCURMandateReferenceCollision for test case R04 is incomplete.');

    $referenceMap = [];

    // Use the same contact for every mandate to check reference generation per contact:
    $contactId = $this->createContact();

    for ($i = 0; $i <= 101; $i++) // Test more than hundred for a possible counter overflow after 99.
    {
      $mandate = $this->createMandate(
        [
          'type' => self::MANDATE_TYPE_RCUR,
          'contact_id' => $contactId,
        ]
      );

      $reference = $mandate['reference'];

      $this->assertValidMandateReference($reference, E::ts("The RCUR mandate reference with index $i is invalid."));

      $referenceIsDuplucate = array_key_exists($reference, $referenceMap);

      $this->assertFalse($referenceIsDuplucate, E::ts("The RCUR mandate reference with index $i is duplicate."));

      $referenceMap[$reference] = 1;
    }

    // TODO: Add varying financial types.
    // TODO: Add varying campaigns.
  }
}
