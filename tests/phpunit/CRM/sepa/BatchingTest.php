<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 SYSTOPIA                       |
| Author: N. Bochan (bochan -at- systopia.de)            |
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

require_once 'BaseTestCase.php';

/**
 * SEPA Unit Tests
 *
 * Batching Algorithm
 *
 */
class CRM_sepa_BatchingTest extends CRM_sepa_BaseTestCase {
  private $tablesToTruncate = array("civicrm_sdd_creditor",
                                    //"civicrm_contact",
                                    "civicrm_contribution",
                                    "civicrm_contribution_recur",
                                    "civicrm_sdd_mandate",
                                    "civicrm_sdd_contribution_txgroup",
                                    "civicrm_sdd_txgroup",
                                    "civicrm_sdd_file",
                                    "civicrm_line_item",
                                    "civicrm_financial_item",
                                    "civicrm_financial_trxn"
                                    );
  private $creditorId = NULL;

  function setUp() {
    parent::setUp();

    // FIXME: there seems to be a bug in civix, call this explicitely until fixed:
    sepa_civicrm_install();

    $this->quickCleanup($this->tablesToTruncate);

    // create a contact
    $this->creditorId = $this->individualCreate();

    // create a creditor
    $this->assertDBQuery(NULL, "INSERT INTO `civicrm_tests_dev`.`civicrm_sdd_creditor` (`id`, `creditor_id`, `identifier`, `name`, `address`, `country_id`, `iban`, `bic`, `mandate_prefix`, `payment_processor_id`, `category`, `tag`, `mandate_active`, `sepa_file_format_id`) VALUES ('3', '%1', 'TESTCREDITORID', 'TESTCREDITOR', '104 Wayne Street', '1082', '0000000000000000000000', 'COLSDE22XXX', 'TEST', '0', 'MAIN', NULL, '1', '1');", array(1 => array($this->creditorId, "Int")));
  }

  function tearDown() {
    error_reporting(E_ALL & ~E_NOTICE);
    $this->quickCleanup($this->tablesToTruncate);
    $this->cleanTempDirs();
    $this->unsetExtensionSystem();
  }


  /**
   * Test update of one-off (single payment) contributions
   *
   * @author niko bochan
   */
  public function testBatchingUpdateOOFF() {
    // create a contact
    $contactId = $this->individualCreate();
    // create a contribution
    $txmd5 = md5(date("YmdHis"));
    $txref = "SDD-TEST-OOFF-" . $txmd5;
    $cparams = array(
      "contact_id" => $contactId,
      "receive_date" => date("YmdHis"),
      "total_amount" => 333.94,
      "currency" => "EUR",
      "financial_type_id" => 1,
      "trxn_id" => $txref,
      "invoice_id" => $txref,
      "source" => "Test",
      "contribution_status_id" => 2,
    );

    $contrib = $this->callAPISuccess("contribution", "create", $cparams);
    $contrib = $contrib["values"][ $contrib["id"] ];

    // create a mandate
    $apiParams = array(
      "type" => "OOFF",
      "reference" => $txmd5,
      "status" => "OOFF",
      "source" => "TestSource",
      "date" => date("Y-m-d H:i:s"),
      "creditor_id" => "3",
      "contact_id" => $contactId,
      "iban" => "0000000000000000000000",
      "bic"  => "COLSDE22XXX",
      "creation_date" => date("Y-m-d H:i:s"),
      "entity_table" => "civicrm_contribution",
      "entity_id" => $contrib["id"],
      );

    $this->callAPISuccess("SepaMandate", "create", $apiParams);

    // create another contact
    $contactId = $this->individualCreate();
    // create another contribution
    $txmd5 = md5(date("YmdHis")."noduplicate");
    $txref = "SDD-TEST-OOFF-" . $txmd5;
    $cparams = array(
      "contact_id" => $contactId,
      "receive_date" => date("YmdHis"),
      "total_amount" => 123.45,
      "currency" => "EUR",
      "financial_type_id" => 1,
      "trxn_id" => $txref,
      "invoice_id" => $txref,
      "source" => "Test",
      "contribution_status_id" => 2,
    );
    $contrib = $this->callAPISuccess("contribution", "create", $cparams);
    $contrib = $contrib["values"][ $contrib["id"] ];
    // create another mandate
    $apiParams = array(
      "type" => "OOFF",
      "reference" => $txmd5,
      "status" => "OOFF",
      "source" => "TestSource",
      "date" => date("Y-m-d H:i:s"),
      "creditor_id" => "3",
      "contact_id" => $contactId,
      "iban" => "0000000000000000000010",
      "bic"  => "COLSDE22XXX",
      "creation_date" => date("Y-m-d H:i:s"),
      "entity_table" => "civicrm_contribution",
      "entity_id" => $contrib["id"]
      );

    $this->callAPISuccess("SepaMandate", "create", $apiParams);

    $result = $this->callAPISuccess("SepaAlternativeBatching", "update", array("type"=>"OOFF"));
    // test whether exactly one txgroup has been created
    $this->assertDBQuery(1, 'select count(*) from civicrm_sdd_txgroup;', array());
    // check txgroup attributes
    $collectionDate = date('Y-m-d', strtotime('+14 days')); // TODO: Use config file instead
    $searchParams = array(
      "id" => 1,
      //"reference" => sprintf("TXG-3-OOFF-%s", $collectionDate), TODO: bug 
      "type" => "OOFF",
      //"collection_date" => sprintf("%s 00:00:00", $collectionDate), TODO: bug
      "latest_submission_date" => sprintf("%s 00:00:00", date('Y-m-d')),
      "created_date" => sprintf("%s 00:00:00", date('Y-m-d')),
      "status_id" => 1,
      "sdd_creditor_id" => 3
    );
    $this->assertDBCompareValues("CRM_Sepa_DAO_SEPATransactionGroup", array("id" => 1), $searchParams);
  }

  /**
   * Test update of recurring payments
   *
   * @author niko bochan
   */
  public function testBatchingUpdateRCUR() {
    $result = $this->createContactAndRecurContrib();

    // create a mandate
    $txmd5 = md5(date("YmdHis")."noduplicate1");
    $apiParams = array(
      "type" => "RCUR",
      "reference" => $txmd5,
      "status" => "FRST",
      "source" => "TestSource",
      "date" => date("Y-m-d H:i:s"),
      "creditor_id" => "3",
      "contact_id" => $result["contactId"],
      "iban" => "0000000000000000010001",
      "bic"  => "COLSDE22XXX",
      "creation_date" => date("Y-m-d H:i:s"),
      "entity_table" => "civicrm_contribution_recur",
      "entity_id" => $result["contribution"]["id"],
      );

    $this->callAPISuccess("SepaMandate", "create", $apiParams);

    // create another mandate
    $txmd5 = md5(date("YmdHis")."noduplicate2");
    $apiParams = array(
      "type" => "RCUR",
      "reference" => $txmd5,
      "status" => "FRST",
      "source" => "TestSource",
      "date" => date("Y-m-d H:i:s"),
      "creditor_id" => "3",
      "contact_id" => $result["contactId"],
      "iban" => "0000000000000000000110",
      "bic"  => "COLSDE22XXX",
      "creation_date" => date("Y-m-d H:i:s"),
      "entity_table" => "civicrm_contribution_recur",
      "entity_id" => $result["contribution"]["id"],
      );

    $this->callAPISuccess("SepaMandate", "create", $apiParams);
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type" => "FRST"));

    // test whether exactly one txgroup has been created
    $this->assertDBQuery(1, 'select count(*) from civicrm_sdd_txgroup;', array());
    // check txgroup attributes
    $collectionDate = date('Y-m-d', strtotime('+14 days'));
    $searchParams = array(
      "id" => 1,
      "reference" => sprintf("TXG-3-FRST-%s", $collectionDate),
      "type" => "FRST",
      "collection_date" => sprintf("%s 00:00:00", $collectionDate),
      "created_date" => sprintf("%s 00:00:00", date('Y-m-d')),
      "status_id" => 1,
      "sdd_creditor_id" => 3
    );
    $this->assertDBCompareValues("CRM_Sepa_DAO_SEPATransactionGroup", array("id" => 1), $searchParams);
  }

  /**
   * Try to call update method with invalid batching mode
   *
   * @author niko bochan
   */
  public function testBatchingWithInvalidMode() {
     $this->callAPIFailure("SepaAlternativeBatching", "update", array("type" => "INVALIDBATCHINGMODE"));
  }

  /**
   * Try to call update method with invalid parameters
   *
   * @author niko bochan
   */
  public function testBatchingWithInvalidParameters() {
     $this->callAPIFailure("SepaAlternativeBatching", "update", 2142);
  }

  /**
   * Test group closing
   *
   * @author niko bochan
   */
  public function testCloseGroup() {
    // create a contact
    $contactId = $this->individualCreate();
    // create a contribution
    $txmd5 = md5(date("YmdHis"));
    $txref = "SDD-TEST-OOFF-" . $txmd5;
    $cparams = array(
      "contact_id" => $contactId,
      "receive_date" => date("YmdHis"),
      "total_amount" => 333.94,
      "currency" => "EUR",
      "financial_type_id" => 1,
      "trxn_id" => $txref,
      "invoice_id" => $txref,
      "source" => "Test",
      "contribution_status_id" => 2,
    );

    $contrib = $this->callAPISuccess("contribution", "create", $cparams);
    $contrib = $contrib["values"][ $contrib["id"] ];

    // create a mandate
    $apiParams = array(
      "type" => "OOFF",
      "reference" => $txmd5,
      "status" => "OOFF",
      "source" => "TestSource",
      "date" => date("Y-m-d H:i:s"),
      "creditor_id" => "3",
      "contact_id" => $contactId,
      "iban" => "0000000000000000000000",
      "bic"  => "COLSDE22XXX",
      "creation_date" => date("Y-m-d H:i:s"),
      "entity_table" => "civicrm_contribution",
      "entity_id" => $contrib["id"],
      );

    $this->callAPISuccess("SepaMandate", "create", $apiParams);
    // update txgroup
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type"=>"OOFF"));
    // close the group
    $this->callAPISuccess("SepaAlternativeBatching", "close", array("txgroup_id"=>1));
    // check txgroup attributes
    $searchParams = array(
      "id" => 1,
      "status_id" => 2 // the group should be closed
    );
    $this->assertDBCompareValues("CRM_Sepa_DAO_SEPATransactionGroup", array("id" => 1), $searchParams);
    // check whether the contribution has been marked as "in progress"
    $searchParams = array(
      "id" => 1,
      "contribution_status_id" => (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'In Progress')
    );
    $this->assertDBCompareValues("CRM_Contribute_DAO_Contribution", array("id" => 1), $searchParams);
  }

  /**
   * Try to call close method with empty parameters
   *
   * @author niko bochan
   */
  public function testCloseWithEmptyParameters() {
     $this->callAPIFailure("SepaAlternativeBatching", "close", array());
  }

  /**
   * Try to close an invalid group
   *
   * @author niko bochan
   */
  public function testCloseWithInvalidParameters() {
    $this->callAPIFailure("SepaAlternativeBatching", "close", array("txgroup_id" => "INVALIDTXGID"));
  }

  /**
   * Test if groups are marked correctly as received
   *
   * @author niko bochan
   */
  public function testReceivedGroup() {
    $this->createMandate(array('type'=>'RCUR', 'status'=>'FRST'));
    $this->createMandate(array('type'=>'RCUR', 'status'=>'FRST'));

    // update txgroup
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type" => "RCUR"));
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type" => "FRST"));
    // close the group
    $this->callAPISuccess("SepaAlternativeBatching", "close", array("txgroup_id"=>1));
    // mark the group as received
    $this->callAPISuccess("SepaAlternativeBatching", "received", array("txgroup_id"=>1));
    // check txgroup attributes
    $searchParams = array(
      "id" => 1,
      "status_id" => (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Received')
    );
    $this->assertDBCompareValues("CRM_Sepa_DAO_SEPATransactionGroup", array("id" => 1), $searchParams);
  }

  /**
   * Try to call API with empty parameters
   *
   * @author niko bochan
   */
  public function testReceivedWithEmptyParameters() {
     $this->callAPIFailure("SepaAlternativeBatching", "received", array());
  }

  /**
   * Try to set an invalid group to received
   *
   * @author niko bochan
   */
  public function testReceivedWithInvalidParameters() {
    $this->callAPIFailure("SepaAlternativeBatching", "received", array("txgroup_id" => "INVALIDTXGID"));
  }

  /**
   * Test if ended/old groups are closed
   *
   * @author niko bochan
   */
  public function testCloseEndedGroup() {
    // create a contact
    $contactId = $this->individualCreate();
    // create a recurring contribution
    $txmd5 = md5(date("YmdHis"));
    $cparams = array(
      'contact_id' => $contactId,
      'frequency_interval' => '1',
      'frequency_unit' => 'month',
      'amount' => 1337.42,
      'contribution_status_id' => 1,
      'start_date' => date("Ymd", strtotime("-100 days")),
      'end_date' => date("Ymd", strtotime("-50 days")),
      'currency' => "EUR",
      'financial_type_id' => 1
    );

    $contrib = $this->callAPISuccess("contribution_recur", "create", $cparams);
    $contrib = $contrib["values"][ $contrib["id"] ];

    // create a mandate
    $apiParams = array(
      "type" => "RCUR",
      "reference" => $txmd5,
      "status" => "FRST",
      "source" => "TestSource",
      "date" => date("Y-m-d H:i:s", strtotime("-110 days")),
      "creditor_id" => "3",
      "contact_id" => $contactId,
      "iban" => "0000000000000000010001",
      "bic"  => "COLSDE22XXX",
      "creation_date" => date("Y-m-d H:i:s", strtotime("-110 days")),
      "entity_table" => "civicrm_contribution_recur",
      "entity_id" => $contrib["id"]
      );

    $this->callAPISuccess("SepaMandate", "create", $apiParams);

    // update txgroup
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type" => "FRST"));
    // close the group
    $this->callAPISuccess("SepaAlternativeBatching", "closeended", array("txgroup_id"=>1));
    // Check whether the mandate has been closed
    $searchParams = array(
      "id" => 1,
      "status" => 'COMPLETE'
    );
    $this->assertDBCompareValues("CRM_Sepa_DAO_SEPAMandate", array("id" => 1), $searchParams);
    // Check whether contribution has been flagged as ended
    $searchParams = array(
      "id" => 1,
      "contribution_status_id" => (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed')
    );
    $this->assertDBCompareValues("CRM_Contribute_DAO_ContributionRecur", array("id" => 1), $searchParams);
  }

  /**
   * Test support of multiple creditors
   *
   * @author niko bochan
   */
  public function testMultipleCreditors() {
    // create a contact
    $secondCreditorId = $this->individualCreate();
    // create a creditor
    $this->assertDBQuery(NULL, "INSERT INTO `civicrm_tests_dev`.`civicrm_sdd_creditor` (`id`, `creditor_id`, `identifier`, `name`, `address`, `country_id`, `iban`, `bic`, `mandate_prefix`, `payment_processor_id`, `category`, `tag`, `mandate_active`, `sepa_file_format_id`) VALUES ('4', '%1', 'TESTCREDITORID', 'TESTCREDITOR', '108 Wayne Street', '1082', '0000000000000000000000', 'COLSDE44XXX', 'TEST', '0', 'MAIN', NULL, '1', '1');", array(1 => array($secondCreditorId, "Int")));
  
    $result = $this->createContactAndRecurContrib();

    // create a mandate
    $txmd5 = md5(date("YmdHis") . "noduplicate1");
    $apiParams = array(
      "type" => "RCUR",
      "reference" => $txmd5,
      "status" => "FRST",
      "source" => "TestSource",
      "date" => date("Y-m-d H:i:s"),
      "creditor_id" => "4",
      "contact_id" => $result["contactId"],
      "iban" => "0000000000000000010001",
      "bic"  => "COLSDE22XXX",
      "creation_date" => date("Y-m-d H:i:s"),
      "entity_table" => "civicrm_contribution_recur",
      "entity_id" => $result["contribution"]["id"],
      );

    $this->callAPISuccess("SepaMandate", "create", $apiParams);

    $result = $this->createContactAndRecurContrib();

    // create another mandate
    $txmd5 = md5(date("YmdHis") . "noduplicate2");
    $apiParams = array(
      "type" => "RCUR",
      "reference" => $txmd5,
      "status" => "FRST",
      "source" => "TestSource",
      "date" => date("Y-m-d H:i:s"),
      "creditor_id" => "3",
      "contact_id" => $result["contribution"]["id"],
      "iban" => "0000000000000000000110",
      "bic"  => "COLSDE22XXX",
      "creation_date" => date("Y-m-d H:i:s"),
      "entity_table" => "civicrm_contribution_recur",
      "entity_id" => $result["contribution"]["id"],
      );

    $this->callAPISuccess("SepaMandate", "create", $apiParams);
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type" => "FRST"));

    // test whether exactly one txgroup has been created
    $this->assertDBQuery(2, 'select count(*) from civicrm_sdd_txgroup;', array());
  }

  /**
   * Test whether there is an error returned when we set a
   * txgroup status to 'received' before closing the group
   *
   * @author niko bochan
   */
  public function testReceivedBeforeClosed() {
    $result = $this->createContactAndRecurContrib();

    // create a mandate
    $txmd5 = md5(date("YmdHis") . "noduplicate1");
    $apiParams = array(
      "type" => "RCUR",
      "reference" => $txmd5,
      "status" => "FRST",
      "source" => "TestSource",
      "date" => date("Y-m-d H:i:s"),
      "creditor_id" => "3",
      "contact_id" => $result["contactId"],
      "iban" => "0000000000000000010001",
      "bic"  => "COLSDE22XXX",
      "creation_date" => date("Y-m-d H:i:s"),
      "entity_table" => "civicrm_contribution_recur",
      "entity_id" => $result["contribution"]["id"],
      );

    $this->callAPISuccess("SepaMandate", "create", $apiParams);

    // create another contact
    $result = $this->createContactAndRecurContrib();

    // create another mandate
    $txmd5 = md5(date("YmdHis") . "noduplicate2");
    $apiParams = array(
      "type" => "RCUR",
      "reference" => $txmd5,
      "status" => "FRST",
      "source" => "TestSource",
      "date" => date("Y-m-d H:i:s"),
      "creditor_id" => "3",
      "contact_id" => $result["contactId"],
      "iban" => "0000000000000000010001",
      "bic"  => "COLSDE22XXX",
      "creation_date" => date("Y-m-d H:i:s"),
      "entity_table" => "civicrm_contribution_recur",
      "entity_id" => $result["contribution"]["id"],
      );

    $this->callAPISuccess("SepaMandate", "create", $apiParams);
    // update txgroup
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type" => "FRST"));
    // mark the group as received
    $this->callAPIFailure("SepaAlternativeBatching", "received", array("txgroup_id"=>1));
  }

  /**
   * Test if an update after closing a group works correctly
   *
   * @see https://github.com/Project60/sepa_dd/issues/128
   * @author niko bochan
   */
  public function testUpdateAfterClosedRCUR() {
    $this->createMandate(array('type'=>'RCUR', 'status'=>'FRST'));
    $this->createMandate(array('type'=>'RCUR', 'status'=>'FRST'));

    // close the group
    $this->callAPISuccess("SepaAlternativeBatching", "closeended", array("txgroup_id"=>1));

    // update txgroup
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type" => "FRST"));
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type" => "RCUR"));
    
    $this->assertDBQuery(1, 'select count(*) from civicrm_sdd_txgroup;', array());
    $this->assertDBQuery(2, 'select count(*) from civicrm_contribution_recur;', array());
  }


  /**
   * Test if the correct payment instrument is used throughout the RCUR status changes
   * 
   * @see https://github.com/Project60/sepa_dd/issues/124
   * @author endres -at- systopia.de
   */
  public function testCorrectPaymentInstrumentSet() {
    // read the payment instrument ids  
    $payment_instrument_FRST = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'FRST');
    $this->assertNotEmpty($payment_instrument_FRST, "Could not find the 'FRST' payment instrument.");
    $payment_instrument_RCUR = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'RCUR');
    $this->assertNotEmpty($payment_instrument_RCUR, "Could not find the 'RCUR' payment instrument.");

    // create a contact
    $result = $this->createContactAndRecurContrib();

    // create a mandate
    $apiParams = array(
      "type" => "RCUR",
      "status" => "FRST",
      "reference" => md5(microtime()),
      "source" => "TestSource",
      "date" => date("Y-m-d H:i:s"),
      "creditor_id" => $this->getCreditor(),
      "contact_id" => $result["contactId"],
      "iban" => "0000000000000000010001",
      "bic"  => "COLSDE22XXX",
      "creation_date" => date("Y-m-d H:i:s"),
      "entity_table" => "civicrm_contribution_recur",
      "entity_id" => $result["contribution"]["id"],
      );
    $mandate = $this->callAPISuccess("SepaMandate", "create", $apiParams);

    // check the batching creates a contribution with ther right payment instrument
    $sql = "select count(*) from civicrm_contribution where payment_instrument_id = '%1';";
    $this->assertDBQuery(0, $sql, array(1 => array($payment_instrument_FRST, 'Integer'))); // "There is already a payment in the DB. Weird"
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type" => "FRST"));
    $this->assertDBQuery(1, $sql, array(1 => array($payment_instrument_FRST, 'Integer'))); // "Batching has not created a correct payment."

    // now change the status of the mandate to 'RCUR'
    // FIXME: do this via the closegroup API
    $this->callAPISuccess("SepaMandate", "create", array('id' => $mandate['id'], 'status' => 'RCUR'));

    // again: check the batching creates a contribution with ther right payment instrument
    $this->assertDBQuery(0, $sql, array(1 => array($payment_instrument_RCUR, 'Integer'))); // "There is already a payment in the DB. Weird"
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type" => "RCUR"));
    $this->assertDBQuery(1, $sql, array(1 => array($payment_instrument_RCUR, 'Integer'))); // "Batching has not created a correct payment."
  }

  /**
   * See if the status change from FRST to RCUR works correctly
   * 
   * @see https://github.com/Project60/sepa_dd/issues/128
   * @author endres -at- systopia.de
   */
  public function testFRSTtoRCURswitch() {
    // select cycle day so that the submission would be due today
    $frst_notice = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', 'batching_FRST_notice');
    $this->assertNotEmpty($frst_notice, "No FRST notice period specified!");
    CRM_Core_BAO_Setting::setItem($frst_notice, 'SEPA Direct Debit Preferences', 'batching_RCUR_notice');
    $rcur_notice = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', 'batching_RCUR_notice');
    $this->assertNotEmpty($rcur_notice, "No RCUR notice period specified!");
    $this->assertEquals($frst_notice, $rcur_notice, "Notice periods should be the same.");
    $cycle_day = date("d", strtotime("+$frst_notice days"));

    // also, horizon mustn't be big enough to create another contribution
    CRM_Core_BAO_Setting::setItem(27, 'SEPA Direct Debit Preferences', 'batching_RCUR_horizon');
    $rcur_horizon = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', 'batching_RCUR_horizon');

    // 1) create a FRST mandate, due for collection right now
    $mandate = $this->createMandate(array('type'=>'RCUR', 'status'=>'FRST'), array('cycle_day' => $cycle_day));
    $mandate_before_batching = $this->callAPISuccess("SepaMandate", "getsingle", array("id" => $mandate['id']));
    $this->assertTrue(($mandate_before_batching['status']=='FRST'), "Mandate was not created in the correct status.");

    // 2) call batching
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type" => "FRST"));
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type" => "RCUR"));


    // 3) find and check the created contribution
    $contribution_recur_id = $mandate['entity_id'];
    $this->assertNotEmpty($contribution_recur_id, "No entity set in mandate");
    $contribution_id = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_contribution WHERE contribution_recur_id=$contribution_recur_id;");
    $this->assertNotEmpty($contribution_id, "Couldn't find created contribution.");    
    $this->assertDBQuery(1, "SELECT count(id) FROM civicrm_contribution WHERE contribution_recur_id=$contribution_recur_id;");

    // 4) close the group
    $this->assertDBQuery(1, "SELECT count(txgroup_id) FROM civicrm_sdd_contribution_txgroup WHERE contribution_id=$contribution_id;");
    $txgroup_id = CRM_Core_DAO::singleValueQuery("SELECT txgroup_id FROM civicrm_sdd_contribution_txgroup WHERE contribution_id=$contribution_id;");
    $this->assertNotEmpty($txgroup_id, "Contribution was not added to a group!");
    $txgroup = $this->callAPISuccess("SepaTransactionGroup", "getsingle", array("id" => $txgroup_id));
    $latest_submission_date = substr($txgroup['latest_submission_date'], 0, 10);
    $this->assertEquals(date('Y-m-d'), $latest_submission_date, "The group should be due today! Check test configuration!");
    $this->callAPISuccess("SepaAlternativeBatching", "close", array("txgroup_id" => $txgroup_id));

    // 5) check if contribution and mandate are in the correct state (RCUR)
    $mandate_after_batching = $this->callAPISuccess("SepaMandate", "getsingle", array("id" => $mandate['id']));
    $this->assertTrue(($mandate_after_batching['status']=='RCUR'), "Mandate was not switched to status 'RCUR' after group was closed");
    $contribution = $this->callAPISuccess("Contribution", "getsingle", array("id" => $contribution_id));
    $this->assertEquals('FRST', $contribution['contribution_payment_instrument'], "Created contribution does not have payment instrument 'FRST'!");

    // uncomment this, if you want to provoke an error like https://github.com/Project60/sepa_dd/issues/128
    //$this->assertDBQuery(0, "UPDATE civicrm_sdd_mandate SET first_contribution_id=NULL WHERE id=".$mandate['id'].";");
    
    // 5) call batching again
    $group_count_before = CRM_Core_DAO::singleValueQuery("SELECT COUNT(id) FROM civicrm_sdd_txgroup;");
    $contribution_count_before = CRM_Core_DAO::singleValueQuery("SELECT COUNT(id) FROM civicrm_contribution;");
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type" => "FRST"));
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type" => "RCUR"));

    // 6) check that there NO new group and no new contribution has been created
    $group_count_after = CRM_Core_DAO::singleValueQuery("SELECT COUNT(id) FROM civicrm_sdd_txgroup;");
    $contribution_count_after = CRM_Core_DAO::singleValueQuery("SELECT COUNT(id) FROM civicrm_contribution;");
    $this->assertEquals($contribution_count_before, $contribution_count_after, "A new contribution has been created, but shouldn't have!");
    $this->assertEquals($group_count_before, $group_count_after, "A new group has been created, but shouldn't have!");
    $this->assertDBQuery(1, "SELECT count(id) FROM civicrm_contribution WHERE contribution_recur_id=$contribution_recur_id;");    
  }  

  /**
   * Make sure a lost group will not be deleted
   * 
   * @see https://github.com/Project60/sepa_dd/issues/...
   * @author endres -at- systopia.de
   */
  public function testLostGroup() {
    // 1) create a payment and select cycle day so that the submission would be due today
    $frst_notice = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', 'batching_FRST_notice');
    $this->assertNotEmpty($frst_notice, "No FRST notice period specified!");
    CRM_Core_BAO_Setting::setItem('SEPA Direct Debit Preferences', 'batching_RCUR_notice', $frst_notice);
    $rcur_notice = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', 'batching_RCUR_notice');
    $this->assertNotEmpty($rcur_notice, "No RCUR notice period specified!");
    $this->assertEquals($frst_notice, $rcur_notice, "Notice periods should be the same.");
    $cycle_day = date("d", strtotime("+$frst_notice days"));
   
    // 2) create a FRST mandate, due for collection right now
    $mandate = $this->createMandate(array('type'=>'RCUR', 'status'=>'FRST'), array('cycle_day' => $cycle_day));
    $mandate_before_batching = $this->callAPISuccess("SepaMandate", "getsingle", array("id" => $mandate['id']));
    $this->assertTrue(($mandate_before_batching['status']=='FRST'), "Mandate was not created in the correct status.");

    // 3) batch and find and check the created contribution
    CRM_Sepa_Logic_Batching::updateRCUR($mandate['creditor_id'], 'FRST');
    $contribution_recur_id = $mandate['entity_id'];
    $this->assertNotEmpty($contribution_recur_id, "No entity set in mandate");
    $contribution_id = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_contribution WHERE contribution_recur_id=$contribution_recur_id;");
    $this->assertNotEmpty($contribution_id, "Couldn't find created contribution.");    
    $this->assertDBQuery(1, "SELECT count(id) FROM civicrm_contribution WHERE contribution_recur_id=$contribution_recur_id;");
    $this->assertDBQuery(1, "SELECT count(txgroup_id) FROM civicrm_sdd_contribution_txgroup WHERE contribution_id=$contribution_id;");
    $txgroup_id = CRM_Core_DAO::singleValueQuery("SELECT txgroup_id FROM civicrm_sdd_contribution_txgroup WHERE contribution_id=$contribution_id;");
    $txgroup = $this->callAPISuccess("SepaTransactionGroup", "getsingle", array("id" => $txgroup_id));
    $latest_submission_date = explode(' ', $txgroup['latest_submission_date']);
    $this->assertEquals(date('Y-m-d'), $latest_submission_date[0], "Something went wrong, this group should be due today!");

    // 4) set the grace period to 7 and virtually execute batching for the day after tomorrow
    //      => the group should be retained
    CRM_Sepa_Logic_Settings::setSetting('batching.RCUR.grace', '7');
    $grace_period = (int) CRM_Sepa_Logic_Settings::getSetting("batching.RCUR.grace", $mandate['creditor_id']);
    $this->assertEquals(7, $grace_period, "Setting the grace period failed!");
    CRM_Sepa_Logic_Batching::updateRCUR($mandate['creditor_id'], 'FRST', date('Y-m-d', strtotime("+3 day")));
    $txgroups = $this->callAPISuccess("SepaTransactionGroup", "get", array("id" => $txgroup_id));
    $this->assertEquals(1, $txgroups['count'], "transaction group was deleted!");

    // 5) set the grace period to 0 and virtually execute batching for the day after tomorrow
    //      => the group should be deleted
    CRM_Sepa_Logic_Settings::setSetting('batching.RCUR.grace', '0');
    $grace_period = (int) CRM_Sepa_Logic_Settings::getSetting("batching.RCUR.grace", $mandate['creditor_id']);
    $this->assertEquals(0, $grace_period, "Setting the grace period failed!");
    CRM_Sepa_Logic_Batching::updateRCUR($mandate['creditor_id'], 'FRST', date('Y-m-d', strtotime("+3 day")));
    $txgroups = $this->callAPISuccess("SepaTransactionGroup", "get", array("id" => $txgroup_id));
    $this->assertEquals(0, $txgroups['count'], "transaction group was not deleted!");
  }

  /**
   * See if the is_test flag is properly passed on through batching
   * 
   * @see https://github.com/Project60/sepa_dd/issues/114
   * @author endres -at- systopia.de
   */
  public function testTestMandates() {
    $frst_notice = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', 'batching_FRST_notice');
    $this->assertNotEmpty($frst_notice, "No FRST notice period specified!");
    CRM_Core_BAO_Setting::setItem('SEPA Direct Debit Preferences', 'batching_RCUR_notice', $frst_notice);
    $rcur_notice = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', 'batching_RCUR_notice');
    $this->assertNotEmpty($rcur_notice, "No RCUR notice period specified!");
    $this->assertEquals($frst_notice, $rcur_notice, "Notice periods should be the same.");
    $cycle_day = date("d", strtotime("+$frst_notice days"));
   
    // 2) create a RCUR mandate, due for collection right now
    $mandate = $this->createMandate(array('type'=>'RCUR', 'status'=>'FRST'), array('cycle_day' => $cycle_day));
    CRM_Sepa_Logic_Batching::updateRCUR($mandate['creditor_id'], 'FRST');
    $rcontrib = $this->callAPISuccess("ContributionRecur", "getsingle", array("id" => $mandate['entity_id']));
    $this->assertEquals('0', $rcontrib['is_test'], "Test flag should not be set!");
    $contrib = $this->callAPISuccess("Contribution", "getsingle", array("contribution_recur_id" => $rcontrib['id']));
    $this->assertEquals('0', $contrib['is_test'], "Test flag should not be set!");
    
    // 3) create a RCUR TEST mandate, due for collection right now
    $ccount = CRM_Core_DAO::singleValueQuery("SELECT count(id) FROM civicrm_contribution;");
    $tccount = CRM_Core_DAO::singleValueQuery("SELECT count(id) FROM civicrm_contribution WHERE is_test=1;");
    $mandate = $this->createMandate(array('type'=>'RCUR', 'status'=>'FRST'), array('is_test' => 1, 'cycle_day' => $cycle_day));
    CRM_Sepa_Logic_Batching::updateRCUR($mandate['creditor_id'], 'FRST');
    $this->assertDBQuery($ccount+1, "SELECT count(id) FROM civicrm_contribution;");
    $rcontrib = $this->callAPISuccess("ContributionRecur", "getsingle", array("id" => $mandate['entity_id']));
    $this->assertEquals('1', $rcontrib['is_test'], "Test flag should be set!");
    // cannot use API for Contribution, wouldn't load test contributions    
    $this->assertDBQuery($tccount+1, "SELECT count(id) FROM civicrm_contribution WHERE is_test=1;");

    // 4) create a OOFF TEST mandate
    $mandate = $this->createMandate(array('type'=>'OOFF', 'status'=>'INIT'), array('is_test' => 1));
    CRM_Sepa_Logic_Batching::updateRCUR($mandate['creditor_id'], 'FRST');
    $contrib = $this->callAPISuccess("Contribution", "getsingle", array("id" => $mandate['entity_id']));
    $this->assertEquals('1', $contrib['is_test'], "Test flag should be set!");
  }

  /**
   * Test update of recurring payments after a deferred submission
   *
   * @author Björn Endres
   * @see https://github.com/Project60/sepa_dd/issues/190
   */
  public function testRCURGracePeriod_190() {
    $rcur_notice = 6;
    CRM_Sepa_Logic_Settings::setSetting('batching.RCUR.notice', $rcur_notice);    
    CRM_Sepa_Logic_Settings::setSetting('batching.RCUR.grace', 2 * $rcur_notice);
    $contactId = $this->individualCreate();
    $collection_date = strtotime("+1 days");
    $deferred_collection_date = strtotime("+$rcur_notice days");

    // count the existing contributions
    $count = $this->callAPISuccess("Contribution", "getcount", array('version' => 3));

    // create a mandate, that's already late
    $parameters = array(
      'version'             => 3,
      'type'                => 'RCUR',
      'status'              => 'RCUR',
      'contact_id'          => $contactId,
      'financial_type_id'   => 1,
      'amount'              => '6.66',
      'start_date'          => date('YmdHis'),
      'date'                => date('YmdHis'),
      'cycle_day'           => date('d', $collection_date),
      'frequency_interval'  => 1,
      'frequency_unit'      => 'month',
      'iban'                => "BE68844010370034",
      'bic'                 => "TESTTEST",
      'creditor_id'         => $this->getCreditor(),
      'is_enabled'          => 1,
    );
    $this->callAPISuccess("SepaMandate", "createfull", $parameters);

    // batch it
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type" => "RCUR", 'version' => 3));

    // check contributions count again
    $newcount = $this->callAPISuccess("Contribution", "getcount", array('version' => 3));
    $this->assertEquals($count+1, $newcount, "A contribution should have been created!");

    // adjust collection date, close the group and thus modify the contribution's receive date
    $txgroup = $this->callAPISuccess("SepaTransactionGroup", "getsingle", array('version' =>3));
    CRM_Sepa_BAO_SEPATransactionGroup::adjustCollectionDate($txgroup['id'], date('Y-m-d', $deferred_collection_date));
    CRM_Sepa_Logic_Group::close($txgroup['id']);

    // batch again
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type" => "RCUR", 'version' => 3));

    // verify, that NO new contribution is created
    $newcount = $this->callAPISuccess("Contribution", "getcount", array('version' => 3));
    $this->assertEquals($count+1, $newcount, "Yet another contribution has been created. Issue #190 still active!");
  }
}
