<?php

class CRM_Sepa_Logic_Base {

  public static $debugByStatus = 0;
  public static $debugByEcho = 0;
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
    $name = CRM_Core_OptionGroup::getValue('payment_instrument', $payment_instrument_id, 'value', 'String', 'name');
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
   * Advance given date by a number of inter-bank working days, according to TARGET calendar.
   *
   * If the given date is not on a banking day itself,
   * counting starts with the next banking day as day 0.
   * 
   * @param string $date_to_adjust
   * @param integer $days_delta
   * @return string
   */
  public static function adjustBankDays($date_to_adjust, $days_delta) {
    $startDate = date_create_from_format("!Y-m-d", $date_to_adjust, new DateTimeZone('UTC'));
    if (!($startDate instanceof DateTime)) {
      throw new CRM_Exception(sprintf('Failed parsing date "%s": %s', $date_to_adjust, print_r(date_get_last_errors(), true)));
    }

    $weekend = array('Sat', 'Sun');
    $fixedHolidays = array(
      '01-01', /* New Year's Day */
      '05-01', /* Labour Day */
      '12-25', /* Christmas Day */
      '12-26', /* Christmas Holiday */
    );

    for ($nextDate = $startDate, $bankDays = -1; $bankDays < $days_delta; $nextDate->modify('+1 day')) {
      $date = clone $nextDate;

      $year = $date->format('Y');
      $easterDays = easter_days($year);
      $variableHolidays = array(
        date('Y-m-d', strtotime("$year-03-19 +$easterDays days")), /* Good Friday */
        date('Y-m-d', strtotime("$year-03-22 +$easterDays days")), /* Easter Monday */
      );

      if (
        !in_array($date->format('D'), $weekend)
        && !in_array($date->format('m-d'), $fixedHolidays)
        && !in_array($date->format('Y-m-d'), $variableHolidays)
      ) {
        ++$bankDays;
      }
    }

    return date_format($date, 'Y-m-d');
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

  public static function countPeriods($fromDate, $toDate, $frequencyUnit, $frequencyInterval) {
    $origTimezone = date_default_timezone_get();
    date_default_timezone_set('UTC');
    $diff = date_diff($fromDate, $toDate);
    date_default_timezone_set($origTimezone);

    switch ($frequencyUnit) {
      case 'year': $units = $diff->y; break;
      case 'month': $units = 12 * $diff->y + $diff->m; break;
      case 'week': $units = floor($diff->days / 7); break;
      case 'day': $units = $diff->days; break;
      default: throw new Exception("Unknown frequency unit: $frequencyUnit");
    }
    $signedUnits = $units * ($diff->inverse ? -1 : 1);
#echo('<pre>'.print_r($fromDate, true).print_r($toDate, true).print_r($diff, true).print_r($signedUnits, true).'</pre>'); #DEBUG
    return floor($signedUnits / $frequencyInterval);
  }

  public static function addPeriods($startDate, $periods, $frequencyUnit, $frequencyInterval, $monthWrapPolicy) {
    $dueDate = date_add(clone $startDate, DateInterval::createFromDateString($periods * $frequencyInterval . $frequencyUnit));

    if (in_array($frequencyUnit, array('month', 'year')) && $dueDate->format('d') != $startDate->format('d')) { /* Month wrapped. */
      $wrapDays = $dueDate->format('d');
      switch ($monthWrapPolicy) {
        case 'PRE':
          $dueDate->modify("-$wrapDays days");
          break;
        case 'POST':
          $dueDate->modify("-$wrapDays days +1 day");
          break;
      }
    }

    return $dueDate;
  }

  public static function getSequenceNumberField() {
    return 'custom_' . civicrm_api3('CustomField', 'getvalue', array('name' => 'sdd_contribution_sequence_number', 'return' => 'id'));
  }
}

