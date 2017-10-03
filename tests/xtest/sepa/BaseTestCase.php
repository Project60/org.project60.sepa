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

require_once 'CiviUnitXtestCase.php';

class SEPA_BaseTestCase extends CiviUnitXtestCase {

  // ############################################################################
  //                              Helper functions
  // ############################################################################

  /**
   * create a mandate
   *
   * @author endres -at- systopia.de
   * @return array with mandate data
   */
  function createMandate($mandate_parms = array(), $contrib_parms = array()) {
    // read the payment instrument ids
    $payment_instrument_OOFF = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'OOFF');
    $this->assertNotEmpty($payment_instrument_OOFF, "Could not find the 'OOFF' payment instrument.");
    $payment_instrument_RCUR = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'RCUR');
    $this->assertNotEmpty($payment_instrument_RCUR, "Could not find the 'RCUR' payment instrument.");
    $contribution_status_pending = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
    $this->assertNotEmpty($contribution_status_pending, "Could not find the 'Pending' contribution status.");

    $mode = empty($mandate_parms['type'])?'OOFF':$mandate_parms['type'];
    $this->assertTrue(($mode=='OOFF' || $mode=='RCUR'), "Mandat can only be of type 'OOFF' or 'RCUR'!");
    $contribution_entity = ($mode=='OOFF')?'Contribution':'ContributionRecur';
    $contribution_table  = ($mode=='OOFF')?'civicrm_contribution':'civicrm_contribution_recur';

    // create a contribution
    $create_contribution = array(
      'contact_id'              => empty($contrib_parms['contact_id'])?$this->individualCreate():$contrib_parms['contact_id'],
      'financial_type_id'       => empty($contrib_parms['financial_type_id'])?1:$contrib_parms['financial_type_id'],
      'currency'                => empty($contrib_parms['currency'])?'EUR':$contrib_parms['currency'],
      'contribution_status_id'  => empty($contrib_parms['contribution_status_id'])?$contribution_status_pending:$contrib_parms['contribution_status_id'],
      'is_test'                 => empty($contrib_parms['is_test'])?0:$contrib_parms['is_test'],
    );
    if ($mode=='RCUR') {
      $create_contribution['payment_instrument_id'] = $payment_instrument_RCUR;
      $create_contribution['amount'] =
          empty($contrib_parms['amount'])?'6.66':$contrib_parms['amount'];
      $create_contribution['start_date'] =
          empty($contrib_parms['start_date'])?date("Ymd"):$contrib_parms['start_date'];
      $create_contribution['end_date'] =
          empty($contrib_parms['end_date'])?NULL:$contrib_parms['end_date'];
      $create_contribution['frequency_interval'] =
          empty($contrib_parms['frequency_interval'])?1:$contrib_parms['frequency_interval'];
      $create_contribution['frequency_unit'] =
          empty($contrib_parms['frequency_unit'])?'month':$contrib_parms['frequency_unit'];
      $create_contribution['cycle_day'] =
          empty($contrib_parms['cycle_day'])?date("d", strtotime("+14 days")):$contrib_parms['cycle_day'];
    } else {
      $create_contribution['payment_instrument_id'] = $payment_instrument_OOFF;
      $create_contribution['total_amount'] =
          empty($contrib_parms['total_amount'])?'6.66':$contrib_parms['total_amount'];
      $create_contribution['receive_date'] =
        empty($contrib_parms['receive_date'])?date('YmdHis'):$contrib_parms['receive_date'];
    }
    $contribution = $this->callAPISuccess($contribution_entity, "create", $create_contribution);


    // create a mandate
    $create_mandate = array(
      "type"          => empty($mandate_parms['type'])?'OOFF':$mandate_parms['type'],
      "status"        => empty($mandate_parms['status'])?'INIT':$mandate_parms['status'],
      "reference"     => empty($mandate_parms['reference'])?md5(microtime()):$mandate_parms['reference'],
      "source"        => empty($mandate_parms['source'])?"TestSource":$mandate_parms['source'],
      "date"          => empty($mandate_parms['date'])?date("YmdHis"):$mandate_parms['date'],
      "creditor_id"   => empty($mandate_parms['creditor_id'])?$this->getCreditor():$mandate_parms['creditor_id'],
      "contact_id"    => empty($mandate_parms['contact_id'])?$create_contribution['contact_id']:$mandate_parms['contact_id'],
      "iban"          => empty($mandate_parms['iban'])?"DE89370400440532013000":$mandate_parms['iban'],
      "bic"           => empty($mandate_parms['bic'])?"INGDDEFFXXX":$mandate_parms['bic'],
      "creation_date" => empty($mandate_parms['creation_date'])?date("YmdHis"):$mandate_parms['creation_date'],
      "entity_table"  => $contribution_table,
      "entity_id"     => $contribution['id'],
    );
    $mandate = $this->callAPISuccess("SepaMandate", "create", $create_mandate);

    return $mandate['values'][$mandate['id']];
  }

  /**
   * get a creditor. If none exists, create one.
   *
   * @author endres -at- systopia.de
   * @return creditor_id
   */
  function getCreditor() {
    $creditors = $this->callAPISuccess("SepaCreditor", "get", array());
    if ($creditors['count']==0) {
      // none there: create...
      $this->assertDBQuery(NULL, "INSERT INTO `civicrm_sdd_creditor` (`id`, `creditor_id`, `identifier`, `name`, `address`, `country_id`, `iban`, `bic`, `mandate_prefix`, `payment_processor_id`, `category`, `tag`, `mandate_active`, `sepa_file_format_id`) VALUES ('3', '%1', 'TESTCREDITORID', 'TESTCREDITOR', '104 Wayne Street', '1082', '0000000000000000000000', 'COLSDE22XXX', 'TEST', '0', 'MAIN', NULL, '1', '1');", array(1 => array($this->creditorId, "Int")));
      // and try again
      $creditors = $this->callAPISuccess("SepaCreditor", "get", array());
    }

    // make sure, there is at least one creditor...
    $this->assertGreaterThan(0, $creditors['count'], "Something went wrong, creditor could not be created.");

    // return the id of the first entry in the values array
    $first_creditor = reset($creditors['values']);
    return $first_creditor['id'];
  }

  /**
   * get a contact and recurring contribution
   *
   * @author niko bochan
   * @return contactId, contribution
   */
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

}
