<?php
require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_BatchForSubmit extends CRM_Core_Page {
  function run() {
    $creditorId = CRM_Utils_Request::retrieve('creditor_id', 'Positive', $_ = null, true);
    CRM_Utils_System::setTitle("Batch pending SDD Transactions for Creditor $creditorId");

    civicrm_api3('SepaSddFile', 'batchforsubmit', array('creditor_id' => $creditorId));

    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/sepa'));
  }
}
