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

require_once "CiviTest/CiviUnitTestCase.php";

/**
 * File for SepaMandate.php
 */
class CRM_sepa_MandateTest extends CiviUnitTestCase {
  private $tablesToTruncate = array("civicrm_sdd_creditor",
                                    //"civicrm_contact",
                                    "civicrm_contribution",
                                    "civicrm_sdd_mandate"
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

  /**
   * Test civicrm_api3_sepa_mandate_create (OOFF)
   *
   * @author niko bochan
   */
  public function testCreateGetDeleteOOFF()
  {
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

    // test contribution
    foreach ($cparams as $key => $value) {
      $this->assertEquals($contrib[$key], $value);
    }

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

    $result = $this->callAPISuccess("SepaMandate", "create", $apiParams);
    $mandate = $result["values"][1];

    // test civicrm_api3_sepa_mandate_get
    $mdtest = $this->callAPISuccess("SepaMandate", "get", array("entity_id" => $mandate["id"]));
    $mdtest = $mdtest["values"][$mdtest["id"]];

    foreach ($apiParams as $key => $value) {
      $this->assertEquals($mdtest[$key], $value);
    }

    // test civicrm_api3_sepa_mandate_delete
    $this->callAPISuccess("SepaMandate", "delete", array("id" => $mandate["id"]));
    $this->assertDBQuery(0, 'select count(*) from civicrm_sdd_mandate;', array());
  }

  /**
   * Test civicrm_api3_sepa_mandate_create (RCUR)
   *
   * @author niko bochan
   */
  public function testCreateGetDeleteRCUR() 
  {
    // create a contact
    $contactId = $this->individualCreate();
    // create a recurring contribution
    $txmd5 = md5(date("YmdHis"));
    $txref = "SDD-TEST-RCUR-" . $txmd5;
    $cparams = array(
      'contact_id' => $contactId,
      'frequency_interval' => '1',
      'frequency_unit' => 'month',
      'amount' => 1337.42,
      'contribution_status_id' => 1,
      'start_date' => date("Ymd")."000000",
      'currency' => "EUR",
      'trxn_id' => $txref,
    );

    $contrib = $this->callAPISuccess("contribution_recur", "create", $cparams);
    $contrib = $contrib["values"][ $contrib["id"] ];

    // test contribution
    foreach ($cparams as $key => $value) {
      $this->assertEquals($contrib[$key], $value);
    }

    // create a mandate
    $apiParams = array(
      "type" => "RCUR",
      "reference" => $txmd5,
      "status" => "INIT",
      "source" => "TestSource",
      "date" => date("Y-m-d H:i:s"),
      "creditor_id" => "3",
      "contact_id" => $contactId,
      "iban" => "0000000000000000000000",
      "bic"  => "COLSDE22XXX",
      "creation_date" => date("Y-m-d H:i:s"),
      "entity_table" => "civicrm_contribution_recur",
      "entity_id" => $contrib["id"],
      );

    $result = $this->callAPISuccess("SepaMandate", "create", $apiParams);
    $mandate = $result["values"][1];

    // test civicrm_api3_sepa_mandate_get
    $mdtest = $this->callAPISuccess("SepaMandate", "get", array("entity_id" => $mandate["id"]));
    $mdtest = $mdtest["values"][$mdtest["id"]];

    foreach ($apiParams as $key => $value) {
      $this->assertEquals($mdtest[$key], $value);
    }

    // test civicrm_api3_sepa_mandate_delete
    $this->callAPISuccess("SepaMandate", "delete", array("id" => $mandate["id"]));
    $this->assertDBQuery(0, 'select count(*) from civicrm_sdd_mandate;', array());
  }

  /**
   * Test civicrm_api3_sepa_mandate_create with empty parameters
   *
   * @author niko bochan
   */
  public function testCreateWithEmptyParameters()
  {
      $this->callAPIFailure("SepaMandate", "create", array());
  }

  /**
   * Test civicrm_participant_create with invalid parameter type.
   *
   * @author niko bochan
   */
  public function testCreateWithInvalidParamsType()
  {
      $this->callAPIFailure("SepaMandate", "create", "invalid type");
  }

   /**
   * Test civicrm_api3_sepa_mandate_get with invalid parameter type.
   *
   * @author niko bochan
   */
  public function testGetWithInvalidParamsType()
  {
      $this->callAPIFailure("SepaMandate", "get", array("entity_id" => "invalid type"));
  }

  /**
   * Test civicrm_api3_sepa_mandate_delete with empty parameters
   *
   * @author niko bochan
   */
  public function testDeleteWithEmptyParameters()
  {
      $this->callAPIFailure("SepaMandate", "delete", array());
  }

  /**
   * Test civicrm_api3_sepa_mandate_delete with invalid parameter type.
   *
   * @author niko bochan
   */
  public function testDeleteWithInvalidParamsType()
  {
      $this->callAPIFailure("SepaMandate", "delete", "invalid type");
  }

  /**
   * Test CRM_Sepa_BAO_SEPAMandate::add()
   *
   * @author niko bochan
   */
  public function testCreateUsingBAO() {
      // create a new contact
      $contactId = $this->individualCreate();

      // create a recurring contribution
      $txmd5 = md5(date("YmdHis".rand(1,100)));
      $txref = "SDD-TEST-RCUR-" . $txmd5;
      $cparams = array(
        'contact_id' => $contactId,
        'frequency_interval' => '1',
        'frequency_unit' => 'month',
        'amount' => 123.42,
        'contribution_status_id' => 1,
        'start_date' => date("Ymd")."000000",
        'currency' => "EUR",
        'trxn_id' => $txref,
      );

      $contrib = $this->callAPISuccess("contribution_recur", "create", $cparams);
      $contrib = $contrib["values"][ $contrib["id"] ];
      
      // mandate parameters array
      $params = array();
      $params['status'] = "FRST";
      $params['is_enabled'] = 1; 
      $params['version'] = 3;
      $params['debug'] = 1;
      $params['contact_id'] = $contactId;
      $params['source'] = "TestSource"; 
      $params['entity_table'] = "civicrm_contribution_recur"; 
      $params['entity_id'] = $contrib;
      $params['creation_date'] = "20140722092142"; 
      $params['validation_date'] = "20140722092142";
      $params['date'] = "20140722092142";
      $params['iban'] = "BE68844010370034"; 
      $params['bic'] = "TESTTEST"; 
      $params['type'] = "RCUR";
      $params['creditor_id'] = 3;

      $dao = CRM_Sepa_BAO_SEPAMandate::add($params);
      
      // close the mandate
      CRM_Sepa_BAO_SEPAMandate::terminateMandate($dao->id, date("Y-m-d"), "Test");

      // get the mandate via API and test it against the parameters array
      $mdtest = $this->callAPISuccess("SepaMandate", "get", array("entity_id" => $dao->id));
      $mdtest = $mdtest["values"][$mdtest["id"]];

      foreach ($params as $key => $value) {
        $this->assertEquals($params[$key], $value);
      }

  }

}