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

require_once 'CiviTest/CiviUnitTestCase.php';

/**
 * FIXME
 */
class CRM_sepa_BatchingTest extends CiviUnitTestCase {
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

  function createContactAndRecurContrib() {
    // create a contact
    $contactId = $this->individualCreate();
    // create a recurring contribution
    $cparams = array(
      'contact_id' => $contactId,
      'frequency_interval' => '1',
      'frequency_unit' => 'month',
      'amount' => 1337.42,
      'contribution_status_id' => 1,
      'start_date' => date("Ymd"),
      'currency' => "EUR",
      'financial_type_id' => 1,
      'cycle_day' => date("d", strtotime("+14 days")),
    );

    $contrib = $this->callAPISuccess("contribution_recur", "create", $cparams);
    $contrib = $contrib["values"][ $contrib["id"] ];

    $result = array("contactId" => $contactId,
                    "contribution" => $contrib);
    return $result;
  }

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

  public function testBatchingWithEmptyParameters() {
     $this->callAPIFailure("SepaAlternativeBatching", "update", array("type" => "INVALIDBATCHINGMODE"));
  }

  public function testBatchingWithInvalidParameters() {
     $this->callAPIFailure("SepaAlternativeBatching", "update", 2142);
  }

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
      "contribution_status_id" => (int) CRM_Core_OptionGroup::getValue('contribution_status', 'In Progress', 'name')
    );
    $this->assertDBCompareValues("CRM_Contribute_DAO_Contribution", array("id" => 1), $searchParams);
  }

  public function testCloseWithEmptyParameters() {
     $this->callAPIFailure("SepaAlternativeBatching", "close", array());
  }

  public function testCloseWithInvalidParameters() {
    $this->callAPIFailure("SepaAlternativeBatching", "close", array("txgroup_id" => "INVALIDTXGID"));
  }

  // Disabled until we fix https://github.com/Project60/sepa_dd/issues/138
  /*public function testReceivedGroup() {
    //$this->assertDBQuery(NULL, "INSERT INTO `civicrm_tests_dev`.`civicrm_option_value` (`id`, `option_group_id`, `label`, `value`, `name`, `grouping`, `filter`, `is_default`, `weight`, `description`, `is_optgroup`, `is_reserved`, `is_active`, `component_id`, `domain_id`, `visibility_id`) VALUES (NULL, '67', 'Received', '6', 'Received', NULL, '0', NULL, '5', NULL, '0', '0', '1', NULL, NULL, NULL);");
    // create a contact
    $contactId = $this->individualCreate();
    // create a recurring contribution
    $txmd5 = md5(date("YmdHis") . "noduplicate1");
    $txref = "SDD-TEST-RCUR-" . $txmd5;
    $cparams = array(
      'contact_id' => $contactId,
      'frequency_interval' => '1',
      'frequency_unit' => 'month',
      'amount' => 1337.42,
      'contribution_status_id' => 1,
      'start_date' => date("Ymd", strtotime("+14 days")),
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
      "date" => date("Y-m-d H:i:s"),
      "creditor_id" => "3",
      "contact_id" => $contactId,
      "iban" => "0000000000000000010001",
      "bic"  => "COLSDE22XXX",
      "creation_date" => date("Y-m-d H:i:s"),
      "entity_table" => "civicrm_contribution_recur",
      "entity_id" => $contrib["id"],
      );

    $this->callAPISuccess("SepaMandate", "create", $apiParams);

    // create another contact
    $contactId = $this->individualCreate();
    // create another recurring contribution
    $txmd5 = md5(date("YmdHis") . "noduplicate2");
    $txref = "SDD-TEST-RCUR-" . $txmd5;
    $cparams = array(
      'contact_id' => $contactId,
      'frequency_interval' => '1',
      'frequency_unit' => 'month',
      'amount' => 543.21,
      'contribution_status_id' => 1,
      'start_date' => date("Ymd", strtotime("+10 days")),
      'currency' => "EUR",
      'financial_type_id' => 1
    );

    $contrib = $this->callAPISuccess("contribution_recur", "create", $cparams);
    $contrib = $contrib["values"][ $contrib["id"] ];

    // create another mandate
    $apiParams = array(
      "type" => "RCUR",
      "reference" => $txmd5,
      "status" => "FRST",
      "source" => "TestSource",
      "date" => date("Y-m-d H:i:s"),
      "creditor_id" => "3",
      "contact_id" => $contactId,
      "iban" => "0000000000000000000110",
      "bic"  => "COLSDE22XXX",
      "creation_date" => date("Y-m-d H:i:s"),
      "entity_table" => "civicrm_contribution_recur",
      "entity_id" => $contrib["id"],
      );

    $this->callAPISuccess("SepaMandate", "create", $apiParams);
    // update txgroup
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type" => "FRST"));
    // close the group
    $this->callAPISuccess("SepaAlternativeBatching", "close", array("txgroup_id"=>1));
    // mark the group as received
    $this->callAPISuccess("SepaAlternativeBatching", "received", array("txgroup_id"=>1));
    // check txgroup attributes
    $searchParams = array(
      "id" => 1,
      "status_id" => (int) CRM_Core_OptionGroup::getValue('batch_status', 'Received', 'name')
    );
    $this->assertDBCompareValues("CRM_Sepa_DAO_SEPATransactionGroup", array("id" => 1), $searchParams);
    // TODO: Second Contribution
  }*/

  public function testReceivedWithEmptyParameters() {
     $this->callAPIFailure("SepaAlternativeBatching", "received", array());
  }

  public function testReceivedWithInvalidParameters() {
    $this->callAPIFailure("SepaAlternativeBatching", "received", array("txgroup_id" => "INVALIDTXGID"));
  }

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
      "contribution_status_id" => (int) CRM_Core_OptionGroup::getValue('contribution_status', 'Completed', 'name')
    );
    $this->assertDBCompareValues("CRM_Contribute_DAO_ContributionRecur", array("id" => 1), $searchParams);
  }

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

  public function testReceivedBeforeClosed() {
    //$this->assertDBQuery(NULL, "INSERT INTO `civicrm_tests_dev`.`civicrm_option_value` (`id`, `option_group_id`, `label`, `value`, `name`, `grouping`, `filter`, `is_default`, `weight`, `description`, `is_optgroup`, `is_reserved`, `is_active`, `component_id`, `domain_id`, `visibility_id`) VALUES (NULL, '67', 'Received', '6', 'Received', NULL, '0', NULL, '5', NULL, '0', '0', '1', NULL, NULL, NULL);");
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

  // test for https://github.com/Project60/sepa_dd/issues/128
  public function testUpdateAfterClosedRCUR() {
    // this test creates 5 contributions
    $contrib_count = 5;

    for ($i=0; $i < $contrib_count; $i++) { 
      $result = $this->createContactAndRecurContrib();

      // 1.1 create a mandate
      $txmd5 = md5(date("YmdHis") . rand(1,100));
      $apiParams = array(
        "type" => "RCUR",
        "reference" => $txmd5,
        "status" => "FRST",
        "source" => "TestSource",
        "date" => date("Y-m-d H:i:s", strtotime("-130 days")),
        "creditor_id" => "3",
        "contact_id" => $result["contactId"],
        "iban" => "0000000000000000010001",
        "bic"  => "COLSDE22XXX",
        "creation_date" => date("Y-m-d H:i:s", strtotime("-130 days")),
        "entity_table" => "civicrm_contribution_recur",
        "entity_id" => $result["contribution"]["id"]
        );

      $this->callAPISuccess("SepaMandate", "create", $apiParams);
    }

    // close the group
    $this->callAPISuccess("SepaAlternativeBatching", "closeended", array("txgroup_id"=>1));

    // update txgroup
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type" => "FRST"));
    $this->callAPISuccess("SepaAlternativeBatching", "update", array("type" => "FRST"));
    
    $this->assertDBQuery(1, 'select count(*) from civicrm_sdd_txgroup;', array());
    $this->assertDBQuery($contrib_count, 'select count(*) from civicrm_contribution_recur;', array());
  }
}