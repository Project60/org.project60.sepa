<?php

class CRM_Sepa_Logic_Base {

  /**
   * Determine whether there is a mandate attached, so it's a SDD TX
   * @deprecated I think
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

  /**
   * isSDD is a new version, shorter now that we have the payment instrument set
   */
  public static function isSDD($contrib) {
    return ($contrib["payment_instrument_id"] == CRM_Core_OptionGroup::getValue('payment_instrument', 'SEPADD', 'name', 'String', 'id'));
  }

  /**
   * Determine the type of contribution from the mandate
   * 
   * @param CRM_Contribute_BAO_Contribution $bao
   * @return string
   */
  public static function getSDDType(CRM_Contribute_BAO_Contribution $bao) {
    $a = civicrm_api('SepaMandate', 'getsingle', array('version' => 3, 'first_contribution_id' => $bao->id));
    if ($a['count']) {
      // check OOFF in function of mandate
      return 'FRST';
    }
    return 'RCUR';
  }

  /**
   * Adjust this date by a number of bank working days. For now, do nothing.
   * 
   * @param type $date_to_adjust
   * @param type $days_delta
   * @return type
   */
  public static function adjustBankDays($date_to_adjust, $days_delta) {
    $date_part = substr($date_to_adjust,0,10);
    return $date_part;
  }

}
