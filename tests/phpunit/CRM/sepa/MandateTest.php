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

require_once "BaseTestCase.php";

/**
 * File for SepaMandate.php
 */
class CRM_sepa_MandateTest extends CRM_sepa_BaseTestCase {
  private $tablesToTruncate = array("civicrm_sdd_creditor",
                                    //"civicrm_contact",
                                    "civicrm_contribution",
                                    "civicrm_line_item",
                                    "civicrm_sdd_mandate"
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
   * Test civicrm_api3_sepa_mandate_create (OOFF)
   *
   * @author niko bochan
   */
  public function testCreateGetDeleteOOFF()
  {
    $mandate = $this->createMandate(array('type'=>'OOFF', 'status'=>'OOFF'));

    // test civicrm_api3_sepa_mandate_get
    $mdtest = $this->callAPISuccess("SepaMandate", "get", array("entity_id" => $mandate["id"]));
    $mdtest = $mdtest["values"][$mdtest["id"]];

    foreach ($mandate as $key => $value) {
      $this->assertEquals($mandate[$key], $value);
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
    $mandate = $this->createMandate(array('type'=>'RCUR', 'status'=>'INIT'));

    // test civicrm_api3_sepa_mandate_get
    $mdtest = $this->callAPISuccess("SepaMandate", "get", array("entity_id" => $mandate["id"]));
    $mdtest = $mdtest["values"][$mdtest["id"]];

    foreach ($mdtest as $key => $value) {
      if ($key != 'creation_date' && $key != 'date') {
        $this->assertEquals($mandate[$key], $value);      
      }else{
        $this->assertEquals($mandate[$key], date("YmdHis", strtotime($value)));  
      }
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
      $params['creditor_id'] = $this->getCreditor();

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


  /**
   * Test SepaMandate.creatfull API call
   *
   * @author bjoern -at- systopia.de
   */
  public function testAPICreateFull() {
    $this->assertDBQuery(0, 'select count(*) from civicrm_contribution;', array());
    $this->assertDBQuery(0, 'select count(*) from civicrm_sdd_mandate;', array());
    // get a contact
    $contactId = $this->individualCreate();
    $parameters = array(
      'version'             => 3,
      'type'                => 'OOFF',
      'reference'           => "REFERENCE_COLLISION",
      'contact_id'          => $contactId,
      'financial_type_id'   => 1,
      'total_amount'        => '100.00',
      'start_date'          => date('YmdHis'),
      'receive_date'        => date('YmdHis'),
      'date'                => date('YmdHis'),
      'iban'                => "BE68844010370034",
      'bic'                 => "TESTTEST",
      'creditor_id'         => $this->getCreditor(),
      'is_enabled'          => 1,
    );
    $this->callAPISuccess("SepaMandate", "createfull", $parameters);
    $this->assertDBQuery(1, 'select count(*) from civicrm_contribution;', array());
    $this->assertDBQuery(1, 'select count(*) from civicrm_sdd_mandate;', array());

    // make it fail and check if rollback works
    unset($parameters['is_enabled']);
    $this->callAPIFailure("SepaMandate", "createfull", $parameters);
    $this->assertDBQuery(1, 'select count(*) from civicrm_contribution;', array());
    $this->assertDBQuery(1, 'select count(*) from civicrm_sdd_mandate;', array());
  }

}