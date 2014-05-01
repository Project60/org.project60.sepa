<?php
require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_BatchingAction extends CRM_Core_Page {
  function run() {
    /* Use '_action', because plain 'action' is a magic value subjected to special handling. */
    $action = CRM_Utils_Request::retrieve('_action', 'String', $_ = null, true);
    switch ($action) {
      case 'batch_for_submit':
        $creditorId = CRM_Utils_Request::retrieve('creditor_id', 'Positive', $_ = null, true);
        CRM_Utils_System::setTitle("Batch pending SDD Transactions for Creditor $creditorId");

        civicrm_api3('SepaSddFile', 'batchforsubmit', array('creditor_id' => $creditorId));
        break;
      case 'cancel_submit_file':
        $fileId = CRM_Utils_Request::retrieve('file_id', 'Positive', $_ = null, true);
        CRM_Utils_System::setTitle("Unbatch Transactions from all Groups in File $fileId");

        civicrm_api3('SepaSddFile', 'cancelsubmit', array('id' => $fileId));
        break;
      case 'cancel_submit_group':
        $txgroupId = CRM_Utils_Request::retrieve('txgroup_id', 'Positive', $_ = null, true);
        CRM_Utils_System::setTitle("Unbatch Transactions from Group $txgroupId");

        civicrm_api3('SepaTransactionGroup', 'cancelsubmit', array('id' => $txgroupId));
        break;
      default:
        throw new Exception("Unknown action '$action'.");
    }

    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/sepa'));
  }
}
