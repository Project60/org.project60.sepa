<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 SYSTOPIA                       |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

require_once 'packages/php-iban-1.4.0/php-iban.php';

use CRM_Sepa_ExtensionUtil as E;


class CRM_Sepa_Logic_Verification {

  /**
   * Will format the given string
   *
   * @param $iban  string, IBAN candidate
   * @param $type  creditor_type, SEPA or PSP
   *
   * @return formatted IBAN
   */
  public static function formatIBAN($iban, $type = 'SEPA') {
    switch ($type) {
      case 'SEPA':
        $iban = trim($iban);
        $iban = strtoupper($iban);
        $iban = str_replace(' ', '', $iban);
        return $iban;

      default:
      case 'PSP':
        return trim($iban);
    }
  }


  /**
   * Verifies if the given IBAN is formally correct
   *
   * @param iban  string, IBAN candidate
   *
   * @return NULL if given IBAN is valid, localized error message otherwise
   */
  public static function verifyIBAN($iban, $type = 'SEPA') {
    // first: check if blacklisted (#540)
    if (self::isIbanBlacklisted($iban)) {
      return E::ts("IBAN is blacklisted");
    }

    switch ($type) {
      case 'SEPA':
        // We only accept uppecase characters and numerals (machine format)
        // see https://github.com/Project60/org.project60.sepa/issues/246
        if (!preg_match("/^[A-Z0-9]+$/", $iban)) {
          return E::ts("IBAN is not correct");
        }
        if (preg_match("/^IBAN/", $iban)) {
          return E::ts("Please remove leading IBAN");
        }
        if (!verify_iban($iban)) {
          return E::ts("IBAN is not correct");
        }
        break;

      default:
      case 'PSP':
        if (!preg_match("#^[a-zA-Z0-9_\/\-=+]+$#", $iban)) {
          return E::ts("Invalid PSP Code");
        }
    }
    // all clear
    return NULL;
  }

  /**
   * Check if this IBAN is blacklisted
   *
   * @param $iban string IBAN to check
   * @return boolean
   */
  public static function isIbanBlacklisted($iban) {
    static $blacklist = NULL;
    if ($blacklist === NULL) {
      // we have to check whether the group exists first, getOptionValuesAssocArrayFromName doesn't do that
      $blacklist = [];
      $query = civicrm_api3('OptionValue', 'get', [
          'option_group_id' => 'iban_blacklist',
          'option.limit'    => 0,
          'return'          => 'value'
      ]);
      foreach ($query['values'] as $value) {
        $blacklist[$value['value']] = 1;
      }
    }
    return isset($blacklist[$iban]);
  }

  /**
   * Verifies if the given mandate reference is formally correct
   *
   * @param $reference string reference candidate
   *
   * @return NULL if given reference is valid, localized error message otherwise
   */
  public static function verifyReference($reference, $type = 'SEPA') {
    switch ($type) {
      case 'SEPA':
        // official guidelines say this:
        if (!preg_match("#^[A-Za-z0-9+?/\-:()., ']+$#", $reference)) {
          return E::ts("Illegal characters detected");
        }

        // we don't want weird numbers starting/ending with special characters
        if (preg_match("#^[+?/\-:().,' ]#", $reference)) {
          return E::ts("Don't start with special characters");
        }
        if (preg_match("#[+?/\-:().,' ]$#", $reference)) {
          return E::ts("Don't end with special characters");
        }
        break;

      default:
      case 'PSP':
        // similar to SEPA, but with extra '_'
        if (!preg_match("#^[A-Za-z0-9+?/\-:()., _']+$#", $reference)) {
          return E::ts("Illegal characters detected");
        }

        // we don't want weird numbers starting/ending with special characters
        if (preg_match("#^[+?/\-:().,']#", $reference)) {
          return E::ts("Don't start with special characters");
        }
        if (preg_match("#[+?/\-:().,']$#", $reference)) {
          return E::ts("Don't end with special characters");
        }
    }
    // all clear
    return NULL;
  }

  /**
   * Generates an anonymised version of the given IBAN
   *
   * @param $iban  string, IBAN candidate
   *
   * @return string anonymised IBAN
   */
  static function anonymiseIBAN($iban, $placeholder='X', $type = 'SEPA') {
    if (empty($iban)) {
      return $iban;
    }

    // calculate the amount of unchanged characters at the beginning and the end.
    //   while anonymising at least 2/3 of the string
    $reveal_count = min(4, strlen($iban) / 3);
    $anonymised_count = strlen($iban) - 2 * $reveal_count;

    // compile anonymised string
    return substr($iban, 0, $reveal_count) . str_repeat($placeholder, $anonymised_count) . substr($iban, (strlen($iban)-$reveal_count), $reveal_count);
  }

  /**
   * Convert the given string to the SEPA character set (see SEPA-520)
   *
   * @param $string string given value
   * @return string converted string
   */
  public static function convert2SepaCharacterSet($string) {
    if (!isset($string)) {
      return $string;
    }

    // try to convert the name into transliterated ASCII
    if (function_exists("iconv")){
      $string = iconv("UTF-8", "ASCII//TRANSLIT", $string);
    }

    // replace the remaining characters with '?'
    $string = preg_replace("/[^ 0-9a-zA-Z':?,\-(+.)\/\"]/", '?', $string);
    return $string;
  }


  /**
   * Form rule wrapper for ::verifyIBAN
   */
  static function rule_valid_IBAN($value) {
    if (self::verifyIBAN($value)===NULL) {
      return 1;
    } else {
      return 0;
    }
  }

  /**
   * Form rule wrapper for ::verifyIBAN
   */
  static function rule_valid_PSP_Code($value) {
    if (self::verifyIBAN($value, 'PSP')===NULL) {
      return 1;
    } else {
      return 0;
    }
  }

  /**
   * Verifies if the given BIC is formally correct
   *
   * @param bic  string, BIC candidate
   *
   * @return NULL if given BIC is valid, localized error message otherwise
   */
  static function verifyBIC($bic, $type = 'SEPA') {
    switch ($type) {
      case 'SEPA':
        if (preg_match("/^[A-Z]{6,6}[A-Z2-9][A-NP-Z0-9]([A-Z0-9]{3,3}){0,1}$/", $bic)) {
          return NULL;
        } else {
          return E::ts("BIC is not correct");
        }

      default:
      case 'PSP':
        if (preg_match("/^[a-zA-Z0-9_\/\-=+]{1,25}$/", $bic)) {
          return NULL;
        } else {
          return E::ts("PSP/BIC is not correct");
        }
      }
  }

  /**
   * Form rule wrapper for ::verifyBIC
   */
  static function rule_valid_BIC($value) {
    if (self::verifyBIC($value)===NULL) {
      return 1;
    } else {
      return 0;
    }
  }

  /**
   * Form rule wrapper for ::verifyBIC
   */
  static function rule_valid_PSP_BIC($value) {
    if (self::verifyBIC($value, 'PSP')===NULL) {
      return 1;
    } else {
      return 0;
    }
  }
}
