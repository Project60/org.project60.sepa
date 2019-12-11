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

use Civi\Test\Api3TestTrait;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * FIXME - Add test description.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Sepa_TestBase extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface, TransactionalInterface
{
  use Api3TestTrait {
    callAPISuccess as protected traitCallAPISuccess;
  }

  protected const TEST_IBAN = 'DE02370501980001802057';

  protected $testCreditorId;

  public function setUpHeadless(): Civi\Test\CiviEnvBuilder
  {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
    return \Civi\Test::headless()
        ->installMe(__DIR__)
        ->install('org.project60.banking')
        ->apply();
  }

  public function setUp(): void
  {
    $this->testCreditorId = $this->setUpCreditor();

    parent::setUp();
  }

  public function tearDown(): void
  {
    parent::tearDown();
  }

  /**
   * Remove 'xdebug' result key set by Civi\API\Subscriber\XDebugSubscriber
   *
   * This breaks some tests when xdebug is present, and we don't need it.
   *
   * @param $entity
   * @param $action
   * @param $params
   * @param null $checkAgainst
   *
   * @return array|int
   */
  protected function callAPISuccess(string $entity, string $action, array $params, $checkAgainst = NULL)
  {
    $result = $this->traitCallAPISuccess($entity, $action, $params, $checkAgainst);
    if (is_array($result)) {
      unset($result['xdebug']);
    }
    return $result;
  }

  /**
   * Create a contact and return it's ID.
   * @return string The Id of the created contact.
   */
  protected function createContact(): string
  {
    $contact = $this->callAPISuccess(
      'Contact',
      'create',
      [
        'contact_type' => 'Individual',
        'email' => 'unittests@sepa.project60.org',
      ]
    );

    $contactId = $contact['id'];

    return $contactId;
  }

  /**
   * Set up a test creditor and return it's ID.
   */
  private function setUpCreditor(): string
  {
    // fetch the test creditor
    $creditorId = $this->callAPISuccessGetValue(
      'SepaCreditor',
      [
        'return' => 'id',
      ]
    );

    // Make sure the test creditor has all needed configs:
    $this->callAPISuccess(
      'SepaCreditor',
      'create',
      [
        'id' => $creditorId,
        'creditor_type' => 'SEPA',
        'uses_bic' => false,
        'currency'  => 'EUR',
        'category' => null, // It must NOT be a test creditor!
      ]
    );

    // Set the creditor as default:
    CRM_Sepa_Logic_Settings::setSetting('batching_default_creditor', $creditorId);

    return $creditorId;
  }
}
