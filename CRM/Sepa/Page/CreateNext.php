<?php
require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_CreateNext extends CRM_Core_Page {
  function run() {
    set_time_limit(0); /* This action can take quite long... */

    #$creditorId = CRM_Utils_Request::retrieve('creditor_id', 'Positive', $_ = null, true);
    #CRM_Utils_System::setTitle("Create upcoming Recurring Contribution installments for Creditor $creditorId");
    #
    #civicrm_api3('SepaContributionGroup', 'createnext', array('creditor_id' => $creditorId));
    civicrm_api3('SepaContributionGroup', 'createnext', array());

    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/sepa'));
  }
}
