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
class CRM_Sepa_SettingsTest extends CRM_Sepa_TestBase
{


  /**
   * Test a simple set/retrieve setting
   */
  public function testGenericSetRetrieve()
  {
    $old_value = CRM_Sepa_Logic_Settings::getGenericSetting('batching.FRST.notice');
    $new_value = rand(1, 364);

    // test setting
    CRM_Sepa_Logic_Settings::setGenericSetting($new_value, 'batching.FRST.notice');
    $current_value = CRM_Sepa_Logic_Settings::getGenericSetting('batching.FRST.notice');
    $this->assertEquals($new_value, $current_value, E::ts("set/getGenericSetting doesn't work"));

    // restore setting
    CRM_Sepa_Logic_Settings::setGenericSetting($old_value, 'batching.FRST.notice');
    $current_value = CRM_Sepa_Logic_Settings::getGenericSetting('batching.FRST.notice');
    $this->assertEquals($old_value, $current_value, E::ts("set/getGenericSetting doesn't work"));
  }

  /**
   * Test a simple set/retrieve setting
   */
  public function testSimpleSetRetrieve()
  {
    $old_value = CRM_Sepa_Logic_Settings::getSetting('batching.FRST.notice');
    $new_value = rand(1, 364);

    // test setting
    CRM_Sepa_Logic_Settings::setSetting($new_value, 'batching.FRST.notice');
    $current_value = CRM_Sepa_Logic_Settings::getSetting('batching.FRST.notice');
    $this->assertEquals($new_value, $current_value, E::ts("set/getSetting doesn't work"));

    // restore setting
    CRM_Sepa_Logic_Settings::setSetting($old_value, 'batching.FRST.notice');
    $current_value = CRM_Sepa_Logic_Settings::getSetting('batching.FRST.notice');
    $this->assertEquals($old_value, $current_value, E::ts("set/getSetting doesn't work"));
  }

  /**
   * Test a simple set/retrieve setting
   */
  public function testSetRetrieveOverride()
  {
    $old_value = CRM_Sepa_Logic_Settings::getSetting('batching.FRST.notice');
    $new_general_value  = rand(1, 364);
    $new_creditor_value = $new_general_value + 1;
    $creditor_id = rand(1, 4);

    // test setting
    CRM_Sepa_Logic_Settings::setSetting($new_general_value, 'batching.FRST.notice');
    CRM_Sepa_Logic_Settings::setSetting($new_creditor_value, 'batching.FRST.notice', $creditor_id);
    $this->assertEquals($new_general_value, CRM_Sepa_Logic_Settings::getSetting('batching.FRST.notice'), E::ts("set/getSetting doesn't work"));
    $this->assertEquals($new_general_value, CRM_Sepa_Logic_Settings::getSetting('batching.FRST.notice', 0), E::ts("set/getSetting doesn't work"));
    $this->assertEquals($new_creditor_value, CRM_Sepa_Logic_Settings::getSetting('batching.FRST.notice',$creditor_id), E::ts("set/getSetting doesn't work"));

    // restore setting
    CRM_Sepa_Logic_Settings::setSetting($old_value, 'batching.FRST.notice');
  }


  /**
   * Test a simple set/retrieve setting
   */
  public function testDefaultCreditor()
  {
    $default_creditor = CRM_Sepa_Logic_Settings::defaultCreditor();
    $this->assertNotNull($default_creditor, "Default creditor is not available");
    $this->assertNotNull($default_creditor->id, "Default creditor is broken");
  }

}
