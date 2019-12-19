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

  protected const MANDATE_TYPE_OOFF = 'OOFF';
  protected const MANDATE_TYPE_RCUR = 'RCUR';
  protected const MANDATE_TYPE_FRST = 'FRST';

  protected const MANDATE_STATUS_SENT = 'SENT';
  protected const MANDATE_STATUS_INVALID = 'INVALID';

  protected $testCreditorId;

  #region PHPUnit Framework implementation

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

  #endregion

  #region Set up and tear down functions

  /**
   * Set up a test creditor and return it's ID.
   */
  private function setUpCreditor(): string
  {
    // Fetch the test creditor:
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

  #endregion

  #region Test helpers

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

  protected function assertException(string $exceptionType, callable $function, string $message = '')
  {
    $thrown = null;
    try
    {
      $function();
    }
    catch (Exception $e)
    {
      $thrown = $e;
    }

    $constraint = new CRM_Sepa_Constraints_ExceptionThrown($exceptionType);
    $this->assertThat($thrown, $constraint, $message);
  }

  /**
   * Assert that two date strings or date and time strings have the same date.
   */
  protected function assertSameDate(string $expectedDateOrDateTime, string $actualDateOrDateTime, string $message = ''): void
  {
    $lengthOfDate = 8; // 4 (the year) + 2 (the month) + 2 (the day) NOTE: This will break in the year 10000.

    $cleanedDateA = preg_replace('/[^0-9]/', '', $expectedDateOrDateTime); // Remove everything that is not a number.
    $cleanedDateB = preg_replace('/[^0-9]/', '', $actualDateOrDateTime);

    $dateA = substr($cleanedDateA, 0, $lengthOfDate);
    $dateB = substr($cleanedDateB, 0, $lengthOfDate);

    $this->assertSame($dateA, $dateB, $message);
  }

  #endregion

  #region General helpers

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

  #endregion

  #region Sepa helpers

  /**
   * Set a configuration/setting for the creditor in use.
   * @param string $key The settings key.
   * @param mixed $value The settings value.
   */
  protected function setCreditorConfiguration(string $key, $value): void
  {
    // Fetch the active/default creditor:
    $creditorId = $this->callAPISuccessGetValue(
      'SepaCreditor',
      [
        'return' => 'id',
      ]
    );

    // Set the creditor's config:
    $this->callAPISuccess(
      'SepaCreditor',
      'create',
      [
        'id' => $creditorId,
        $key => $value,
      ]
    );
  }

  /**
   * Create a mandate.
   * @param string $mandateType The type of the mandate, possible values can be found in the class constants as "MANDATE_TYPE_X".
   * @param string $collectionDate A string parsable by strtotime to set the (first) collection date.
   * @return array The mandate.
   */
  protected function createMandate(string $mandateType, string $collectionDate = 'now'): array
  {
    $parameters = [
      'contact_id' => $this->createContact(),
      'type' => $mandateType,
      'iban' => self::TEST_IBAN,
      'amount' => 8,
      'financial_type_id' => 1,
    ];

    $collectionDate = date('Y-m-d', strtotime($collectionDate));

    if ($mandateType == self::MANDATE_TYPE_OOFF)
    {
      $parameters['receive_date'] = $collectionDate;
    }
    else
    {
      $parameters['frequency_unit'] = 'month';
      $parameters['frequency_interval'] = 1;
      $parameters['start_date'] = $collectionDate;
    }

    $result = $this->callAPISuccess(
      'SepaMandate',
      'createfull',
      $parameters
    );

    $mandateId = $result['id'];
    $mandate = $result['values'][$mandateId];

    return $mandate;
  }

  /**
   * Terminate a mandate.
   * @param array $mandate The mandate to terminate.
   */
  protected function terminateMandate(array $mandate): void
  {
    $this->callAPISuccess(
      'SepaMandate',
      'terminate',
      [
        'mandate_id' => $mandate['id'],
      ]
    );
  }

  /**
   * Execute batching for mandates, resulting in the creation of a group.
   * @param string $type The type of the mandates to batch, possible values can be found in the class constants as "MANDATE_TYPE_X".
   * @param string $nowOverwrite A string parsable by strtotime to overwrite the current time used for batching.
  */
  protected function executeBatching(string $type, string $nowOverwrite = 'now'): void
  {
    $this->callAPISuccess(
      'SepaAlternativeBatching',
      'update',
      [
        'type' => $type,
        'now' => $nowOverwrite,
      ]
    );
  }

  /**
   * Close a transaction group of the given ID.
   */
  protected function closeTransactionGroup(string $groupId): void
  {
    $this->callAPISuccess(
      'SepaAlternativeBatching',
      'close',
      [
        'txgroup_id' =>  $groupId,
      ]
    );
  }

  /**
   * Close a list of transaction groups.
   */
  protected function closeTransactionGroups(array $groups): void
  {
    // NOTE: The Sepa API does not support "IN" statements. That's why we have
    //       to call the API once for every transaction group...

    foreach ($groups as $group)
    {
      $this->closeTransactionGroup($group['id']);
    }
  }

  /**
   * Get a mandate by it's ID.
   */
  protected function getMandate(string $mandateId): array
  {
    $mandate = $this->callAPISuccessGetSingle(
      'SepaMandate',
      [
        'id' => $mandateId,
      ]
    );

      return $mandate;
  }

  /**
   * Get the contribution for a given mandate.
   * @param array $mandate The mandate to get the contribution for.
   */
  protected function getContributionForMandate(array $mandate): array
  {
    $mandateType = $mandate['type'];

    // Check if the mandate type is supported:
    if (!in_array($mandateType, [self::MANDATE_TYPE_OOFF, self::MANDATE_TYPE_RCUR, self::MANDATE_TYPE_FRST]))
    {
      throw new Exception('For this mandate type can no contribution be determined.');
    }

    $contributionEntity = $mandateType == self::MANDATE_TYPE_OOFF ? 'Contribution' : 'ContributionRecur';

    $contributionId = $mandate['entity_id'];

    $contribution = $this->callAPISuccessGetSingle(
      $contributionEntity,
      [
        'id' => $contributionId,
      ]
    );

    return $contribution;
  }

  /**
   * Get the only active transaction group.
   * @param string $type The mandate type of the group to search for, possible values can be found in the class constants as "MANDATE_TYPE_X".
   * @return array The transaction group.
   */
  protected function getActiveTransactionGroup(string $type): array
  {
    $group = $this->callAPISuccessGetSingle(
      'SepaTransactionGroup',
      [
        'type' => $type,
        'status_id' => 1,
      ]
    );

    return $group;
  }

  /**
   * Get all active transaction groups.
   * @param string $type The mandate type of the groups to search for, possible values can be found in the class constants as "MANDATE_TYPE_X".
   * @return array[] The list of transaction groups.
   */
  protected function getActiveTransactionGroups(string $type): array
  {
    $result = $this->callAPISuccess(
      'SepaTransactionGroup',
      'get',
      [
        'type' => $type,
        'status_id' => 1,
      ]
    );

    $groups = $result['values'];

    return $groups;
  }

  /**
   * Get a transaction group by ID.
   * @return array The transaction group.
   */
  protected function getTransactionGroup(string $groupId): array
  {
    $group = $this->callAPISuccessGetSingle(
      'SepaTransactionGroup',
      [
        'id' => $groupId,
      ]
    );

    return $group;
  }

  /**
   * Get the transaction group that is associated with the given contribution.
   * @return array The transaction group.
   */
  protected function getTransactionGroupForContribution(array $contribution): array
  {
    $contributionId = $contribution['id'];

    $groupId = $this->callAPISuccessGetValue(
      'SepaContributionGroup',
      [
        'return' => 'txgroup_id',
        'contribution_id' => $contributionId,
      ]
    );

    $group = $this->getTransactionGroup($groupId);

    return $group;
  }

  #endregion

}
