<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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


class CRM_Sepa_Logic_Verification {

  /**
   * Verifies if the given IBAN is formally correct
   *
   * @param iban  string, IBAN candidate
   *
   * @return NULL if given IBAN is valid, localized error message otherwise
   */
  static function verifyIBAN($iban) {
    // We only accept uppecase characters and numerals (machine format)
    // see https://github.com/Project60/org.project60.sepa/issues/246
    if (!preg_match("/^[A-Z0-9]+$/", $iban)) {
      return ts("IBAN is not correct", array('domain' => 'org.project60.sepa'));
    }

    if (verify_iban($iban)) {
      return NULL;
    } else {
      return ts("IBAN is not correct", array('domain' => 'org.project60.sepa'));
    }
  }

  /**
   * Generates an anonymised version of the given IBAN
   *
   * @param $iban  string, IBAN candidate
   *
   * @return string anonymised IBAN
   */
  static function anonymiseIBAN($iban, $placeholder='X') {
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
   * Verifies if the given BIC is formally correct
   *
   * @param bic  string, BIC candidate
   *
   * @return NULL if given BIC is valid, localized error message otherwise
   */
  static function verifyBIC($bic) {
    if (preg_match("/^[A-Z]{6,6}[A-Z2-9][A-NP-Z0-9]([A-Z0-9]{3,3}){0,1}$/", $bic)) {
      return NULL;
    } else {
      return ts("BIC is not correct", array('domain' => 'org.project60.sepa'));
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
}
