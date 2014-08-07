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
    if (verify_iban($_REQUEST['iban'])) {
      return NULL;
    } else {
      return ts("IBAN is not correct");
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
      return ts("BIC is not correct");
    }
  }
}