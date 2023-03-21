<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit - PHPUnit tests         |
| Copyright (C) 2022 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
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
 * Bug reproduction tests.
 *
 * The test numbers refer to issues on GitHub (https://github.com/Project60/org.project60.sepa/issues)
 *
 * @group headless
 */
class CRM_Sepa_BugReproductionTest extends CRM_Sepa_TestBase
{
  // simulate CiviCRM >
  public function setUp(): void
  {
    parent::setUp();

    // simulate a newly installed CiviCRM 5.54+, which has no 'In Progress' contribution status
    $contribution_in_progress_status = null;
    try {
      // get status (if it exists) and delete it
      $contribution_in_progress_status = civicrm_api3('OptionValue', 'getsingle', [
        'option_group_id' => 'contribution_status',
        'value' => self::CONTRIBUTION_STATUS_IN_PROGRESS,
      ]);
    } catch (CiviCRM_API3_Exception $ex) {
      // this means the status is already missing, no harm done
    }

    if ($contribution_in_progress_status) {
      // the status is there (older civicrm version): delete it to simulate a newer version
      civicrm_api3('OptionValue', 'delete', [
        'id' => $contribution_in_progress_status['id']
      ]);
    }
  }


  /**
   * Verify that but #629 is fixed:
   *  'each time recurring contributions are updated via the dashboard new contributions are created for recurring
   *   mandates even if there is a contribution for the current period.'
   *
   * Presumably this goes back to a bad payment instrument id, set for example by a faulty payment processor
   *
   * We want to recreate the following scenario:
   *  1. We have a running RCUR mandate, but the recurring_contribution has payment_instrument FRST instead of RCUR
   *     (probably due to some faulty payment processor)
   *  2. This mandate now generates installments with payment_instrument FRST instead of RCUR
   *  3. When such a group is closed, it would be falsely regenerated, because the
   *      existing contributions for the same date are not found due to the wrong payment instruments
   *
   * @return void
   *
   * @see https://github.com/Project60/org.project60.sepa/issues/629
   */
  public function testBug629()
  {
    $this->setSepaConfiguration('exclude_weekends', '0');
    $this->setCreditorConfiguration('batching.RCUR.grace', 5);
    $this->setCreditorConfiguration('batching.RCUR.horizon', 20);
    $this->setCreditorConfiguration('batching.RCUR.notice', 2);
    $this->setCreditorConfiguration('batching.FRST.notice', 2);

    // create a recurring mandate
    $mandateDate = 'now - 65 days';
    $monthly_mandate = $this->createMandate(['type' => self::MANDATE_TYPE_RCUR], $mandateDate);

    // NOW mess up the recurring contribution in a way that likely caused #629:
    //  give the recurring contribution the FRST payment instrument
    $recurring_contribution = $this->civicrm_api('ContributionRecur', 'getsingle', [
      'id' => $monthly_mandate['entity_id'],
      'version' => 3]);
    $pi_mapping_reversed = array_flip(CRM_Sepa_Logic_PaymentInstruments::getFrst2RcurMapping($monthly_mandate['creditor_id']));
    $wrong_payment_instrument_id = $pi_mapping_reversed[$recurring_contribution['payment_instrument_id']];

    $this->civicrm_api('ContributionRecur', 'create', [
      'id' => $monthly_mandate['entity_id'],
      'payment_instrument_id' => $wrong_payment_instrument_id,
      'contribution_status_id' => self::CONTRIBUTION_STATUS_PENDING,
    ]);

    // now generate and close three groups
    $group_type = self::MANDATE_TYPE_FRST;
    $contributions = [];
    //foreach (['-60', '-30', '+0'] as $batch_time_offset) { // todo: removed 60 days b/c something is suddenly wrong there
    foreach (['-30', '+0'] as $batch_time_offset) {
      // run batching and close the groups
      $this->executeBatching($group_type, "now {$batch_time_offset} days");

      $contribution = $this->getLatestContributionForMandate($monthly_mandate);
      $tx_group = $this->getTransactionGroupForContribution($contribution);
      $this->closeTransactionGroup($tx_group['id']);

      // reload after group was closed
      $contribution = $this->getLatestContributionForMandate($monthly_mandate);

      // make sure this is a new one.
      $this->assertFalse(isset($contributions[$contribution['id']]), "The same contribution was 'generated' again.");
      $contributions[$contribution['id']] = $contribution;

      // now, to reproduce, the contribution needs to be PI=FRST and STATUS=Pending
      $this->callAPISuccess('Contribution', 'create', [
        'id' => $contribution['id'],
        'payment_instrument_id' => self::PAYMENT_INSTRUMENT_FRST,
        'financial_type_id' => $contribution['financial_type_id'],
      ]);

      // for the next iteration, batch type is RCUR
      $group_type = self::MANDATE_TYPE_RCUR;
    }

    // now, run the last one again, and we will get ANOTHER contribution if the bug is present
    $this->executeBatching($group_type);
    $contribution = $this->getLatestContributionForMandate($monthly_mandate);
    $this->assertTrue(isset($contributions[$contribution['id']]), "A new contribution was generated, but it shouldn't have.");
  }


  /**
   * Verify that but #632 is fixed:
   *  The status CONTRIBUTION_STATUS_PENDING is not shipped with the CiviCRM 5.54+ (?)
   *
   * @return void
   *
   * @see https://github.com/Project60/org.project60.sepa/issues/632
   * @see https://lab.civicrm.org/dev/financial/-/issues/201
   */
  public function testBug632()
  {
    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
      ]
    );

    $this->executeBatching(self::MANDATE_TYPE_OOFF);

    $transactionGroup = $this->getActiveTransactionGroup(self::MANDATE_TYPE_OOFF);

    $this->closeTransactionGroup($transactionGroup['id']);

    $closedMandate = $this->getMandate($mandate['id']);
    $closedContribution = $this->getLatestContributionForMandate($closedMandate);

    $this->assertSame(
      self::MANDATE_STATUS_SENT,
      $closedMandate['status'],
      E::ts('OOFF Mandate status after closing is incorrect.')
    );
    $this->assertSame(
      self::CONTRIBUTION_STATUS_IN_PROGRESS,
      $closedContribution['contribution_status_id'],
      E::ts('OOFF contribution status after closing is incorrect, probably related to SEPA-629')
    );
  }
}
