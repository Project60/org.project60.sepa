<?php

class CRM_Sepa_Logic_Base {

  public static $debugByStatus = 1;
  public static $debugByEcho = 1;
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
   * isSDD: return true if the payment instrument used by this contribution is 
   * one of the SDD ones
   */
  public static function isSDD($contrib) {
    $payment_instrument_id = $contrib["payment_instrument_id"];
    $name = CRM_Core_OptionGroup::getValue('payment_instrument', $payment_instrument_id, 'id', 'String', 'name');
    switch ($name) {
      case 'FRST' :
      case 'RCUR' :
      case 'OOFF' :
        return true;
    }
    return false;
  }

  /**
   * Determine the type of contribution from the mandate
   * @deprecated in favour of using the contribution's payment instrument id
   *
   * @param CRM_Contribute_BAO_Contribution $bao
   * @return string
   */
  public static function getSDDType(CRM_Contribute_BAO_Contribution $bao) {
    die('<br>getSDDType is <b>deprecated</b> : caller should use detection based on pmt instrument id');
    $a = civicrm_api('SepaMandate', 'getcount', array('version' => 3, 'first_contribution_id' => $bao->id));
    if ($a['count'] == 1) {
      // check OOFF in function of mandate
      return 'FRST';
    }
    return 'RCUR';
  }

  /**
   * Adjust this date by a number of bank working days. 
   * 
   * @param type $date_to_adjust
   * @param type $days_delta
   * @return type
   */
  public static function adjustBankDays($date_to_adjust, $days_delta) {

    $days = array( 1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday', 5=>'Friday');
//absolutely broken, right now do nothing is better
    $date_part = substr($date_to_adjust, 0, 10);
    //return $date_part;

    // convert to date
    $date_ta = date_create($date_part);
    $tinfo = getdate(strtotime($date_part));
    $wday = $tinfo['wday'];   // 0 is Sunday, 6 is Saturday
    
    if ($days_delta > 0) {
      // echo ' moving forward from ', $date_part, ' (', $wday, ')';
      // adjust for bankdays -> real days
      $delta = floor($days_delta / 5) * 7 + ($days_delta % 5);
      // adjust for first weekend jump, if today's weekday + delta would land in a weekend (6 or 7/0)
      if ($wday + $delta > 6) $delta += 2; 
      else if ($wday + $delta == 6) $delta += 1;
      $date_ta = date_add($date_ta, date_interval_create_from_date_string($delta . ' days'));
      //echo ' +', $delta, ' days to become ', date_format($date_ta,'Y-m-d');
      // push forward if on a weekend
      $tinfo = getdate(strtotime(date_format($date_ta,'Y-m-d')));
      switch ($tinfo['wday']) {
        case 0 : // Sunday
          $date_ta = date_add($date_ta, date_interval_create_from_date_string('1 days'));
          //echo ' -- on a Sunday so adding 1 day to become ', date_format($date_ta,'Y-m-d');
          break;
        case 6 : // Saturday
          $date_ta = date_add($date_ta, date_interval_create_from_date_string('2 days'));
          //echo ' -- on a Saturday so adding 2 days to become ', date_format($date_ta,'Y-m-d');
          break;
        default:
          //echo ' -- on ', $days[$tinfo['wday']];
          break;
      }
    } else if ($days_delta < 0) {
      //echo ' moving backward from ', $date_part;
      $days_delta = - $days_delta;
      // adjust for bankdays -> real days
      $delta = floor($days_delta / 5) * 7 + ($days_delta % 5);
      if ($wday - $delta < 0) $delta += 1; 
      else if ($wday - $delta == 0) $delta += 2;
      $date_ta = date_sub($date_ta, date_interval_create_from_date_string($delta . ' days'));
      //echo ' -', $delta, ' days to become ', date_format($date_ta,'Y-m-d');
      // pull back if on a weekend
      $tinfo = getdate(strtotime(date_format($date_ta,'Y-m-d')));
      switch ($tinfo['wday']) {
        case 0 : // Sunday
          $date_ta = date_sub($date_ta, date_interval_create_from_date_string('2 days'));
          //echo ' -- on a Sunday so subtracting 2 days to become ', date_format($date_ta,'Y-m-d');
          break;
        case 6 : // Saturday
          $date_ta = date_sub($date_ta, date_interval_create_from_date_string('1 days'));
          //echo ' -- on a Saturday so subtracting 1 day to become ', date_format($date_ta,'Y-m-d');
          break;
        default:
          //echo ' -- on ', $days[$tinfo['wday']];
          break;
      }
    }

    return date_format($date_ta,'Y-m-d');
  }

  /**
   * Debug, used for tracing all kinds of logic for SDD
   * 
   * @param string $message
   * @param string $title
   * @param string $type
   */
  public static function debug($message, $title = '', $type = 'alert') {
    if (self::$debugByEcho) {
      echo '<br/><b>', $title, '</b> ', $message;
    }
    if (self::$debugByStatus) {
      CRM_Core_Session::setStatus($message, $title, $type);
    }
    if (self::$debugByLog && (self::$debugLogPath != '')) {
      $tag = date("Y-m-d G:i:s") . "\t";
      if ($title)
        $msg = $tag . '*** ' . $title . "\n";
      $msg .= $tag . $message . "\n";
      file_put_contents(self::$debugLogPath, $msg, FILE_APPEND);
    }
  }

  public static function setDebug($byStatus, $byLog, $logFilePath = '', $byEcho = false) {
    self::$debugByStatus = $byStatus;
    self::$debugByLog = $byLog;
    self::$debugByEcho = $byEcho;
    if ($logFilePath)
      self::$debugLogPath = $logFilePath;
  }

  /**
   * Convert string in UTF-8 encoding to the restricted SEPA charset.
   *
   * Although SEPA files are technically UTF-8 XML files --
   * which could carry any possible Unicode/UCS codepoint --
   * the *actually* allowed characters form a very restricted set
   * (see SEPA C2B Implementation Guidelines, chapter 1.4),
   * which is a subset of ASCII.
   *
   * (Basically the least common denominator of all legacy banking systems across Europe...)
   *
   * This function converts characters outside this charset to allowed characters,
   * trying to approximate the desired characters as well as possible.
   * (It probably doesn't fully follow the official recommendations --
   * but should be close enough...)
   *
   * Note: This function has a type check, so it's safe to call on non-string input values too.
   * Thus it can be safely invoked on a whole array (possibly containing non-string elements)
   * using array_map().
   *
   * @param string $string The input UTF-8 string to convert
   * @return string The input converted to SEPA charset
   */
  public static function utf8ToSEPA($string) {
    if (!is_string($string)) {
      return $string;
    }

    // Replace any non-ASCII characters
    if (function_exists("iconv")) {
      /*
       * iconv() transliteration only works when an explicit UTF-8 locale is set.
       * (Otherwise, we just get '?' for every non-ASCII character...)
       *
       * Ideally, we should use an appropriate locale for our country,
       * to get the best possible transliterations.
       * However, 'en_US' seems a good default,
       * as it is most likely to be installed everywhere,
       * and the transliterations it provides appear to be acceptable in most cases.
       */
      setlocale(LC_CTYPE, 'en_US.utf8');
      $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
    }

    // '&' should be replaced by '+' according to the official recommendation.
    $string = str_replace('&', '+', $string);

    // Any other characters outside the valid set should be replaced by '.'.
    $string = preg_replace('%[^[:alnum:]/?:().,\' +-]%', '.', $string);

    return $string;
  }
}

