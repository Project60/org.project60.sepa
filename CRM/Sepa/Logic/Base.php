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
      return false; //payment recorded from the backoffice probably
    }

    // get pp info
    $a = civicrm_api('PaymentProcessor', 'getsingle', array('version' => 3, 'id' => $a['payment_processor']));
    if (!array_key_exists("class_name", $a)) {
      return false; // not sure what happened, but at least we know it's not a SDD
    }

    if ($a['class_name'] == 'Payment_SEPA_DD')
      return true;

    return false;
  }
  
}
