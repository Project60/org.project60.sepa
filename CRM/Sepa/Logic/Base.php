<?php

class CRM_Sepa_Logic_Base {

  /**
   * etermine whethere there is a mandate attached, so it's a SDD TX
   * 
   * @param type $id
   */
  static function isSDDContribution($contrib) {
    // get contrib page info
    $a = civicrm_api('ContributionPage', 'getsingle', array('version' => 3, 'id' => $contrib->contribution_page_id));
    if (!array_key_exists("payment_processor", $a)) {
      CRM_Core_Error::fatal(ts("Can't find contribution page for this contribution"));
    }

    // get pp info
    $a = civicrm_api('PaymentProcessor', 'getsingle', array('version' => 3, 'id' => $a['payment_processor']));
    if (!array_key_exists("class_name", $a)) {
      CRM_Core_Error::fatal(ts("Can't find payment processor for this contribution page"));
    }

    if ($a['class_name'] == 'Payment_SEPA_DD')
      return true;

    return false;
  }

}
