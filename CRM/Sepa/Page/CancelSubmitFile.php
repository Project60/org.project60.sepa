<?php
require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_CancelSubmitFile extends CRM_Core_Page {
  function run() {
    $fileId = CRM_Utils_Request::retrieve('file_id', 'Positive', $_ = null, true);
    CRM_Utils_System::setTitle("Unbatch Transactions from all Groups in File $fileId");

    civicrm_api3('SepaSddFile', 'cancelsubmit', array('id' => $fileId));

    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/sepa'));
  }
}
