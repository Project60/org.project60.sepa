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

require_once 'sepa/BaseTestCase.php';

/**
 * File for SepaMandate.php
 */
class CRM_sepa_MandateTest extends SEPA_BaseTestCase {

  function setUp() {
    parent::setUp();
  }

  function tearDown() {
    parent::tearDown();
  }

  /**
   * Test civicrm_api3_sepa_mandate_create (OOFF)
   *
   * @author niko bochan
   */
  public function testCreateGetDeleteOOFF()
  {
    // backup the current mandate count
    $mandateCount = CRM_Core_DAO::singleValueQuery('select count(*) from civicrm_sdd_mandate;', array());
    // create a new one
    $mandate = $this->createMandate(array('type'=>'OOFF', 'status'=>'OOFF'));

    // test civicrm_api3_sepa_mandate_get
    $mdtest = $this->callAPISuccess("SepaMandate", "get", array("id" => $mandate["id"]));
    $mdtest = $mdtest["values"][$mdtest["id"]];

    foreach ($mandate as $key => $value) {
      $this->assertEquals($mandate[$key], $value);
    }

    // test civicrm_api3_sepa_mandate_delete
    $this->callAPISuccess("SepaMandate", "delete", array("id" => $mandate["id"]));
    // compare count to previously count
    $this->assertDBQuery($mandateCount, 'select count(*) from civicrm_sdd_mandate;', array());
  }

  /**
   * Test civicrm_api3_sepa_mandate_create (RCUR)
   *
   * @author niko bochan
   */
  public function testCreateGetDeleteRCUR()
  {
    // backup the current mandate count
    $mandateCount = CRM_Core_DAO::singleValueQuery('select count(*) from civicrm_sdd_mandate;', array());
    // create a new one
    $mandate = $this->createMandate(array('type'=>'RCUR', 'status'=>'INIT'));

    // test civicrm_api3_sepa_mandate_get
    $mdtest = $this->callAPISuccess("SepaMandate", "get", array("id" => $mandate["id"]));
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
    $this->assertDBQuery($mandateCount, 'select count(*) from civicrm_sdd_mandate;', array());
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
      $mdtest = $this->callAPISuccess("SepaMandate", "get", array("id" => $dao->id));
      $mdtest = $mdtest["values"][$mdtest["id"]];

      foreach ($params as $key => $value) {
        $this->assertEquals($params[$key], $value);
      }

  }


  /**
   * Test SepaMandate.creatfull API call
   *
   * @author endres -at- systopia.de
   */
  public function testAPICreateFull() {
    // backup the current mandate- and contribution count
    $mandateCount = CRM_Core_DAO::singleValueQuery('select count(*) from civicrm_sdd_mandate;', array());
    $contribCount = CRM_Core_DAO::singleValueQuery('select count(*) from civicrm_contribution;', array());
    // get a contact
    $contactId = $this->individualCreate();
    $parameters = array(
      'version'             => 3,
      'type'                => 'OOFF',
      'reference'           => "REFERENCE_COLLISION039",
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
    $this->assertDBQuery($contribCount+1, 'select count(*) from civicrm_contribution;', array());
    $this->assertDBQuery($mandateCount+1, 'select count(*) from civicrm_sdd_mandate;', array());

    // make it fail and check if rollback works
    unset($parameters['is_enabled']);
    $this->callAPIFailure("SepaMandate", "createfull", $parameters);
    $this->assertDBQuery($contribCount+1, 'select count(*) from civicrm_contribution;', array());
    $this->assertDBQuery($mandateCount+1, 'select count(*) from civicrm_sdd_mandate;', array());
  }

  /**
   * Test civicrm_api3_sepa_mandate_create with default creditor
   *
   * @author niko bochan
   */
  public function testCreateWithDefaultCreditor()
  {
    $contactId = $this->individualCreate();
    $contribution = $this->callAPISuccess("Contribution", "create", array(
      'version'             => 3,
      'financial_type_id'   => 1,
      'contribution_status_id' => 2,
      'total_amount'        => '100.00',
      'currency'            => 'EUR',
      'contact_id'          => $contactId,
    ));
    $create_data = array(
      'version'             => 3,
      'type'                => 'OOFF',
      'status'              => 'INIT',
      'entity_id'           => $contribution['id'],
      'entity_table'        => 'civicrm_contribution',
      'contact_id'          => $contactId,
      'start_date'          => date('YmdHis'),
      'receive_date'        => date('YmdHis'),
      'date'                => date('YmdHis'),
      'iban'                => "BE68844010370034",
      'bic'                 => "TESTTEST",
      'is_enabled'          => 1,
      );

    // set default creditor
    $creditor_id = $this->getCreditor();
    CRM_Sepa_Logic_Settings::setSetting('batching_default_creditor', $creditor_id);
    $create_data['creditor_id'] = $creditor_id;
    // this should work, since no creditor_id is set BUT the default creditor is
    $this->callAPISuccess("SepaMandate", "create", $create_data);

    // set bad default creditor
    CRM_Sepa_Logic_Settings::setSetting('batching_default_creditor', '999');
    unset($create_data['creditor_id']);
    $this->assertEquals('999', CRM_Sepa_Logic_Settings::getSetting('batching_default_creditor'));

    // this should fail, since no creditor_id is set and the default creditor is bad
    $this->callAPIFailure("SepaMandate", "create", $create_data);
  }
}
