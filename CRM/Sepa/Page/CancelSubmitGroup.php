<?php
require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_CancelSubmitGroup extends CRM_Core_Page {
  function run() {
    $txgroupId = CRM_Utils_Request::retrieve('txgroup_id', 'Positive', $_ = null, true);
    CRM_Utils_System::setTitle("Unbatch Transactions from Group $txgroupId");

    civicrm_api3('SepaTransactionGroup', 'cancelsubmit', array('id' => $txgroupId));

    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/sepa'));
  }
}
