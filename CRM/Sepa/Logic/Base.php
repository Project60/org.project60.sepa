<?php

class CRM_Sepa_Logic_Base {

  public static $debugByStatus = 0;
  public static $debugByLog = 1;
  public static $debugLogPath = '';

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
    $a = civicrm_api('SepaMandate', 'getcount', array('version' => 3, 'first_contribution_id' => $bao->id));
    if ($a['count'] == 1) {
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
    $date_part = substr($date_to_adjust, 0, 10);
    if ($days_delta > 0) {
      // adjust for bankdays -> real days
      $delta = floor($days_delta/5)*7 + ($days_delta % 5);
      date_add($date_part, date_interval_create_from_date_string( $delta . ' days'));
      // push forward if on a weekend
      $tinfo = getdate(strtotime($date_part));
      switch ($tinfo['wday']) {
        case 0 : // Sunday
          $date_part = date_add($date_part, date_interval_create_from_date_string('1 days'));
          break;
        case 6 : // Saturday
          $date_part = date_add($date_part, date_interval_create_from_date_string('2 days'));
          break;
         default:
           break;
      }
    } else if ($days_delta < 0) {
      $days_delta = - $days_delta;
      // adjust for bankdays -> real days
      $delta = floor($days_delta/5)*7 + ($days_delta % 5);
      date_sub($date_part, date_interval_create_from_date_string( $delta . ' days'));
      // pull back if on a weekend
      $tinfo = getdate(strtotime($date_part));
      switch ($tinfo['wday']) {
        case 0 : // Sunday
          $date_part = date_sub($date_part, date_interval_create_from_date_string('2 days'));
          break;
        case 6 : // Saturday
          $date_part = date_sub($date_part, date_interval_create_from_date_string('1 days'));
          break;
         default:
           break;
      }
    }
    
    return $date_part;
  }

  /**
   * Debug, used for tracing all kinds of logic for SDD
   * 
   * @param string $message
   * @param string $title
   * @param string $type
   */
  public static function debug($message, $title = '', $type = 'alert') {
    if (self::$debugByStatus) {
      CRM_Core_Session::setStatus($message, $title, $type);
    }
    if (self::$debugByLog && (self::$debugLogPath != '')) {
      $tag = date("Y-m-d G:i:s") . "\t";
      if ($title) $msg = $tag . '*** ' . $title . "\n";
      $msg .= $tag . $message . "\n";
      file_put_contents( self::$debugLogPath, $msg, FILE_APPEND );
    }
  }

  public static function setDebug($byStatus, $byLog, $logFilePath = '') {
    self::$debugByStatus = $byStatus;
    self::$debugByLog = $byLog;
    if ($logFilePath)
      self::$debugLogPath = $logFilePath;
  }

}


