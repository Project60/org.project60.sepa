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
 * Tests for hooks.
 * @group headless
 */
class CRM_Sepa_HookTest extends CRM_Sepa_TestBase
{
  const INSTALLMENT_CREATED_CONTRIBUTION_SOURCE_PREFIX = 'HookTest-';

  /**
   * If true the create_mandate hook will be executed.
   * TODO: Would it be cleaner to have one test file for every hook?
   */
  protected $executeCreateMandateHook = false;
  /**
   * @var int $mandateReferenceCounter A counter for the mandate references to prevent collisions.
   */
  protected $mandateReferenceCounter = 0;
  /**
   * @var string|null $lastMandateReference Contains the last given mandate reference. Null if there is none.
   */
  protected $lastMandateReference = null;

  /**
   * If true the modify_txgroup_reference hook will be executed.
   * TODO: Would it be cleaner to have one test file for every hook?
   */
  protected $executeModifyTxGroupHook = false;
  /**
   * @var int $transactionGroupReferenceCounter A counter for the transaction group references to prevent collisions.
   */
  protected $transactionGroupReferenceCounter = 0;
  /**
   * @var string|null $lastTransactionGroupReference Contains the last given transaction group reference. Null if there is none.
   */
  protected $lastTransactionGroupReference = null;

  /**
   * If true the installment_created hook will be executed.
   * TODO: Would it be cleaner to have one test file for every hook?
   */
  protected $executeInstallmentCreatedHook = false;

  public function setUp(): void
  {
    parent::setUp();

    // Initialise all last references with null so we can easily check if there has been any reference generation happened:
    $this->lastMandateReference = null;
    $this->lastTransactionGroupReference = null;
  }

  public function tearDown(): void
  {
    parent::tearDown();

    // Prevent all hooks from being called after the test has happened:
    $this->executeCreateMandateHook = false;
    $this->executeModifyTxGroupHook = false;
    $this->executeInstallmentCreatedHook = false;
  }

  /**
   * This hook is called before a newly created mandate is written to the DB. \
   * We implement it to test if it works by setting a custom reference.
   * @param array $mandate_parameters The parameters that will be used to create the mandate.
   * @return bool|string Based on op. pre-hooks return a boolean or an error message which aborts the operation.
   */
  public function hook_civicrm_create_mandate(array &$mandate_parameters)
  {
    // Only execute this hook if we are ordered to:
    if (!$this->executeCreateMandateHook)
    {
      return;
    }

    $newReference = 'HookTest-MandateReference-' . $this->mandateReferenceCounter;

    $this->mandateReferenceCounter++;

    $this->lastMandateReference = $newReference;

    $mandate_parameters['reference'] = $newReference;
  }

  /**
   * Test create OOFF mandate with custom reference. \
   * TODO: Check if this is everything needed to fulfil the requirements stated in the test description.
   * @see Case_ID H01
   */
  public function testOOFFCustomMandateReference(): void
  {
    $this->executeCreateMandateHook = true;

    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
      ]
    );

    $this->assertNotNull($this->lastMandateReference, E::ts('The create_mandate hook has not been called.'));
    $this->assertSame(
      $this->lastMandateReference,
      $mandate['reference'],
      E::ts('The mandate reference is not the generated one.')
    );
  }

  /**
   * Test create RCUR mandate with custom reference. \
   * TODO: Check if this is everything needed to fulfil the requirements stated in the test description.
   * @see Case_ID H02
   */
  public function testRCURCustomMandateReference(): void
  {
    $this->executeCreateMandateHook = true;

    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_RCUR,
      ]
    );

    $this->assertNotNull($this->lastMandateReference, E::ts('The create_mandate hook has not been called.'));
    $this->assertSame(
      $this->lastMandateReference,
      $mandate['reference'],
      E::ts('The mandate reference is not the generated one.')
    );
  }

  /**
   * This hook is called when a new transaction group is generated. \
   * We implement it to test if it works by setting a custom reference.
   * @param string $reference The currently proposed reference (max. 35 characters).
   * @param string $collection_date The scheduled collection date.
   * @param string $mode The SEPA mode (OOFF, RCUR, FRST, RTRY).
   * @param string $creditor_id The SDD creditor ID.
   */
  function hook_civicrm_modify_txgroup_reference(string &$reference, string $creditor_id, string $mode, string $collection_date): void
  {
    // Only execute this hook if we are ordered to:
    if (!$this->executeModifyTxGroupHook)
    {
      return;
    }

    // NOTE: The reference has a length limitation (currently 35 characters). With the set prefix there are nine characters
    //       left for a billion possible references. This must be noted if the prefix size is increased.
    $newReference = 'HookTest-TxGroupReference-' . $this->transactionGroupReferenceCounter;

    $this->transactionGroupReferenceCounter++;

    $this->lastTransactionGroupReference = $newReference;

    $reference = $newReference;
  }

  /**
   * Test create OOFF mandate with coustom transaction group reference.
   * @see Case_ID H03
   */
  public function testOOFFCustomGroupReference(): void
  {
    $this->executeModifyTxGroupHook = true;

    $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
      ]
    );

    $this->executeBatching(self::MANDATE_TYPE_OOFF);

    $transactionGroup = $this->getActiveTransactionGroup(self::MANDATE_TYPE_OOFF);

    $this->assertNotNull(
      $this->lastTransactionGroupReference,
      E::ts('The modify_txgroup_reference hook has not been called.')
    );
    $this->assertSame(
      $this->lastTransactionGroupReference,
      $transactionGroup['reference'],
      E::ts('The transaction group reference is not the generated one.')
    );
  }

  /**
   * Test create RCUR mandate with coustom transaction group reference.
   * @see Case_ID H04
   */
  public function testRCURCustomGroupReference(): void
  {
    $this->executeModifyTxGroupHook = true;

    $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_RCUR,
      ]
    );

    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $this->executeBatching(self::MANDATE_TYPE_RCUR);

    $transactionGroup = $this->getActiveTransactionGroup(self::MANDATE_TYPE_FRST);

    $this->assertNotNull(
      $this->lastTransactionGroupReference,
      E::ts('The modify_txgroup_reference hook has not been called.')
    );
    $this->assertSame(
      $this->lastTransactionGroupReference,
      $transactionGroup['reference'],
      E::ts('The transaction group reference is not the generated one.')
    );
  }

  /**
   * This hook is called by the batching algorithm: \
   * Whenever a new installment has been created for a given RCUR mandate this hook is called so you can modify \
   * the resulting contribution, e.g. connect it to a membership, or copy custom fields. \
   * We implement this hook to test if it is called correctly. For this we set the contribution's source to the mandate's reference. \
   * FIXME: In the sepacustom example extension the three parameters are marked as array, which is wrong and should be fixed.
   * @param string $mandate_id The CiviSEPA mandate entity ID.
   * @param string $contribution_recur_id The recurring contribution connected to the mandate.
   * @param string $contribution_id The newly created contribution.
   */
  function hook_civicrm_installment_created(string $mandate_id, string $contribution_recur_id, string $contribution_id): void
  {
    // Only execute this hook if we are ordered to:
    if (!$this->executeInstallmentCreatedHook)
    {
      return;
    }

    $mandateReference = $this->callAPISuccessGetValue(
      'SepaMandate',
      [
        'return' => 'reference',
      ]
    );

    $this->callAPISuccess(
      'Contribution',
      'create',
      [
        'id' => $contribution_id,
        'contribution_source' => self::INSTALLMENT_CREATED_CONTRIBUTION_SOURCE_PREFIX . $mandateReference,
      ]
    );
  }

  /**
   * Test the installment created hook.
   * @see Case_ID H08
   */
  public function testInstallmentCreated(): void
  {
    $this->executeInstallmentCreatedHook = true;

    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_RCUR,
      ]
    );

    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $this->executeBatching(self::MANDATE_TYPE_RCUR);

    $contribution = $this->getLatestContributionForMandate($mandate);

    $excepted = self::INSTALLMENT_CREATED_CONTRIBUTION_SOURCE_PREFIX . $mandate['reference'];

    $this->assertSame(
      $excepted,
      $contribution['contribution_source'],
      E::ts('The installment_created hook has not been called (correctly).')
    );
  }
}
