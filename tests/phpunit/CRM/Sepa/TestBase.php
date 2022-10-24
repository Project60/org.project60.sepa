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

use CRM_Sepa_ExtensionUtil as E;

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
class CRM_Sepa_TestBase extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface
{
  use Api3TestTrait {
    callAPISuccess as protected traitCallAPISuccess;
  }

  const TEST_IBAN = 'DE02370501980001802057';
  const TEST_BIC = 'COLSDE33XXX';

  const MANDATE_TYPE_OOFF = 'OOFF';
  const MANDATE_TYPE_RCUR = 'RCUR';
  const MANDATE_TYPE_FRST = 'FRST';  // fixme: this is *NOT* a mandate _type_ but only a mandate status
                                     // (while RCUR and OOFF are mandate types)

  const MANDATE_STATUS_SENT = 'SENT';
  const MANDATE_STATUS_INVALID = 'INVALID';
  const MANDATE_STATUS_COMPLETE = 'COMPLETE';

  // TODO: Move the following constants to a better place (or get them dynamically from Civi):
  const CONTRIBUTION_STATUS_COMPLETED = '1';
  const CONTRIBUTION_STATUS_PENDING = '2';
  const CONTRIBUTION_STATUS_CANCELLED = '3';
  const CONTRIBUTION_STATUS_FAILED = '4';
  const CONTRIBUTION_STATUS_IN_PROGRESS = '5';
  //const CONTRIBUTION_STATUS_OVERDUE = '6';
  const CONTRIBUTION_STATUS_REFUNDED = '7';
  const CONTRIBUTION_STATUS_PARTIALLY_PAID = '8';
  const CONTRIBUTION_STATUS_PENDING_REFUND = '9';
  const CONTRIBUTION_STATUS_CHARGEBACK = '10';
  const CONTRIBUTION_STATUS_TEMPLATE = '11';

  // TODO: Move the following constants to a better place (or get them dynamically from Civi):
  const RECURRING_CONTRIBUTION_STATUS_COMPLETED = '1';
  const RECURRING_CONTRIBUTION_STATUS_PENDING = '2';
  const RECURRING_CONTRIBUTION_STATUS_CANCELLED = '3';
  const RECURRING_CONTRIBUTION_STATUS_FAILED = '4';
  const RECURRING_CONTRIBUTION_STATUS_IN_PROGRESS = '5';
  const RECURRING_CONTRIBUTION_STATUS_OVERDUE = '6';
  const RECURRING_CONTRIBUTION_STATUS_PROCESSING = '7';
  const RECURRING_CONTRIBUTION_STATUS_FAILING = '8';

  // TODO: Move the following constants to a better place (or get them dynamically from Civi):
  const BATCH_STATUS_OPEN = '1';
  const BATCH_STATUS_CLOSED = '2';
  const BATCH_STATUS_DATA_ENTRY = '3';
  const BATCH_STATUS_REOPENED = '4';
  const BATCH_STATUS_EXPORTED = '5';
  const BATCH_STATUS_RECEIVED = '6';
  const PAYMENT_INSTRUMENT_FRST = '6';
  const PAYMENT_INSTRUMENT_RCUR = '7';

  protected $testCreditorId;

  #region PHPUnit Framework implementation

  public function setUpHeadless(): Civi\Test\CiviEnvBuilder
  {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
    return \Civi\Test::headless()
        ->installMe(__DIR__)
        ->apply();
  }

  public function setUp(): void
  {
    parent::setUp();
    $this->testCreditorId = $this->setUpCreditor();

    // TODO: Should we make sure that there are no mandates and groups open?
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
    // make sure there is at least one...
    CRM_Sepa_BAO_SEPACreditor::addDefaultCreditorIfMissing();

    // Fetch the test creditor:
    $creditorId = $this->callAPISuccessGetValue(
      'SepaCreditor',
      [
        'return' => 'id',
      ]
    );

    // Make sure the test creditor has all needed configs:
    $classic_payment_instrument_ids = CRM_Sepa_Logic_PaymentInstruments::getClassicSepaPaymentInstruments();
    $this->callAPISuccess(
      'SepaCreditor',
      'create',
      [
        'id' => $creditorId,
        'creditor_type' => 'SEPA',
        'uses_bic' => false,
        'currency'  => 'EUR',
        'category' => null, // It must NOT be a test creditor!
        'pi_ooff' => $classic_payment_instrument_ids['OOFF'],
        'pi_rcur' => "{$classic_payment_instrument_ids['FRST']}-{$classic_payment_instrument_ids['RCUR']}",
      ]
    );

    // Set the creditor as default:
    CRM_Sepa_Logic_Settings::setGenericSetting($creditorId, 'batching_default_creditor');

    // set some basic config
    CRM_Sepa_Logic_Settings::setGenericSetting('2',  'batching.OOFF.notice');
    CRM_Sepa_Logic_Settings::setGenericSetting('20', 'batching.OOFF.horizon');

    CRM_Sepa_Logic_Settings::setGenericSetting('2',  'batching.FRST.notice');
    CRM_Sepa_Logic_Settings::setGenericSetting('2',  'batching.RCUR.notice');
    CRM_Sepa_Logic_Settings::setGenericSetting('20', 'batching.RCUR.horizon');
    CRM_Sepa_Logic_Settings::setGenericSetting('1',  'batching.RCUR.grace');

    return $creditorId;
  }

  #endregion

  #region Test helpers

  /**
   * This allows test to create their own creditor instance,
   *    that's different from the main creditor
   *
   * @param array $params
   *   a list of parameters that are to be different from the default creditor
   *
   * @return integer
   *   new creditor ID
   */
  protected function getCustomCreditor($params)
  {
    // load the default creditor
    $creditor_template = $this->callAPISuccess('SepaCreditor', 'getsingle', ['id' => $this->testCreditorId]);
    unset($creditor_template['id']);
    unset($creditor_template['creditor_id']);
    $creditor_template['name'] = "creditor-" . CRM_Core_DAO::singleValueQuery("SELECT MAX(id) + 2 FROM civicrm_sdd_creditor");

    // add custom data
    foreach ($params as $key => $value) {
      $creditor_template[$key] = $value;
    }
    $creditor = $this->callAPISuccess('SepaCreditor', 'create', $creditor_template);
    return $creditor['id'];
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
   * Assert that an exception of a specific type (or it's child classes) is thrown when calling a function.
   */
  protected function assertException(string $exceptionType, callable $function, string $message = '')
  {
    // convert to phpunit6 exception types
    $exceptionType = preg_replace('/_/', '\\', $exceptionType);
    try
    {
      $function();
    }
    catch (Exception $e)
    {
      if ($e instanceof $exceptionType) {
        return;
      } else{
        $this->fail($message);
      }
    }
    $this->fail($message);
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
   * Set a generic configuration/setting
   * @param string $key The settings key.
   * @param mixed $value The settings value.
   */
  protected function setSepaConfiguration(string $key, $value): void
  {
    CRM_Sepa_Logic_Settings::setSetting($value, $key);
  }



  /**
   * Add an IBAN to the blocklist.
   * @param string $iban The IBAN.
   */
  protected function addIbanToBlocklist(string $iban): void
  {
    $existsAlready = $this->callAPISuccessGetCount(
      'OptionValue',
      [
        'option_group_id' => 'iban_blacklist',
        'value' => $iban,
      ]
    );

    if (!$existsAlready)
    {
      $this->callAPISuccess(
        'OptionValue',
        'create',
        [
          'option_group_id' => 'iban_blacklist',
          'value' => $iban,
          'label' => 'Test-' . $iban,
        ]
      );
    }
  }

  /**
   * Create a mandate.
   * @param string $mandateType The type of the mandate, possible values can be found in the class constants as "MANDATE_TYPE_X".
   * @param string $collectionDate A string parsable by strtotime to set the (first) collection date.
   * @return array The mandate.
   */
  protected function createMandate(array $parameters, string $collectionDate = 'now'): array
  {
    // default parameters
    $parameters['contact_id']        = array_key_exists('contact_id', $parameters)        ? $parameters['contact_id']        : $this->createContact();
    $parameters['iban']              = array_key_exists('iban', $parameters)              ? $parameters['iban']              : self::TEST_IBAN;
    $parameters['amount']            = array_key_exists('amount', $parameters)            ? $parameters['amount']            : 8;
    $parameters['financial_type_id'] = array_key_exists('financial_type_id', $parameters) ? $parameters['financial_type_id'] : 1;
    $parameters['creditor_id']       = array_key_exists('creditor_id', $parameters)       ? $parameters['creditor_id']       : CRM_Sepa_Logic_Settings::defaultCreditor()->id;
    $parameters['account_holder']    = array_key_exists('account_holder', $parameters)    ? $parameters['account_holder']    : "";

    if ($parameters['type'] == self::MANDATE_TYPE_OOFF)
    {
      $parameters['receive_date'] = array_key_exists('receive_date', $parameters) ? $parameters['receive_date'] : $collectionDate;
    }
    else
    {
      $parameters['start_date']         = array_key_exists('start_date', $parameters)         ? $parameters['start_date']         : $collectionDate;
      $parameters['frequency_unit']     = array_key_exists('frequency_unit', $parameters)     ? $parameters['frequency_unit']     : 'month';
      $parameters['frequency_interval'] = array_key_exists('frequency_interval', $parameters) ? $parameters['frequency_interval'] : 1;

      // set the cycle day to the next possible collection date
      $frst_notice_days = (int) CRM_Sepa_Logic_Settings::getSetting("batching.FRST.notice", $parameters['creditor_id']);
      $earliest_start_date  = date('Y-m-d', strtotime("now + {$frst_notice_days} days"));
      $requested_start_date = date('Y-m-d', strtotime($collectionDate));
      if ($requested_start_date < $earliest_start_date) {
        $parameters['cycle_day'] = date('j', strtotime($earliest_start_date));
      } else {
        $parameters['cycle_day'] = date('j', strtotime($requested_start_date));
      }
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
   * @param string $endDate A string parsable by strtotime. If it differs from 'today' only the end date is set and the mandate not terminated.
   */
  protected function terminateMandate(array $mandate, string $endDate = 'today'): void
  {
    $this->callAPISuccess(
      'SepaMandate',
      'terminate',
      [
        'mandate_id' => $mandate['id'],
        'end_date' => $endDate,
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
   * Get the latest contribution for a given mandate.
   *
   * @param array $mandate The mandate to get the contribution for.
   *
   * @return array|null contribution data
   */
  protected function getLatestContributionForMandate(array $mandate, $can_be_null = false)
  {
    $mandateType = $mandate['type'];

    // Check if the mandate type is supported:
    if (!in_array($mandateType, [self::MANDATE_TYPE_OOFF, self::MANDATE_TYPE_RCUR, self::MANDATE_TYPE_FRST]))
    {
      throw new Exception('For this mandate type can no contribution be determined.');
    }

    $contribution = null;

    if ($mandateType == self::MANDATE_TYPE_OOFF)
    {
      // If it is an OOFF mandate, we simply have the contribution ID given in the mandate's entity_id.

      $contribution = $this->callAPISuccessGetSingle(
        'Contribution',
        [
          'id' => $mandate['entity_id'],
        ]
      );
    }
    else if (in_array($mandateType, [self::MANDATE_TYPE_RCUR, self::MANDATE_TYPE_FRST]))
    {
      // If it is an RCUR/FRST mandate, we need to get the contribution from the recurring contribution given in the mandate's entity_id.
      // There could be multiple contributions attached, so we return the latest one.

      $contributions = $this->callAPISuccess(
        'Contribution', 'get',
        [
          'contribution_recur_id' => $mandate['entity_id'],
          'options' =>
            [
              'sort' => 'id DESC',
              'limit' => 1,
            ],
        ]
      );

      if ($contributions['count'] == 0) {
        $contribution = NULL;
      } else {
        $contribution = reset($contributions['values']);
      }

    }
    else
    {
      throw new Exception('For this mandate type can no contribution be determined.');
    }

    if (!$can_be_null) {
      $this->assertNotNull($contribution, E::ts('This mandate has no contribution, even though there should be one.'));
    }

    return $contribution;
  }

  /**
   * Get the recurring contribution for a given RCUR/FRST mandate.
   * @param array $mandate The RCUR or FRST mandate to get the contribution for.
   */
  protected function getRecurringContributionForMandate(array $mandate): array
  {
    // Only RCUR/FRST mandates are allowed:
    if (!in_array($mandate['type'], [self::MANDATE_TYPE_RCUR, self::MANDATE_TYPE_FRST]))
    {
       throw new Exception('For this mandate type can no recurring contribution be determined.');
    }

    $recurringContribution = $this->callAPISuccessGetSingle(
      'ContributionRecur',
      [
        'id' => $mandate['entity_id'],
      ]
    );

    return $recurringContribution;
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
