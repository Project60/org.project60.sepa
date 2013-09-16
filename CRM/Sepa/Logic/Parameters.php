<?php

/**
 * Externalises configurable options for the SEPA handling
 * 
 * For customization, provide a CRM_Sepa_Logic_CustomParameters (extends CRM_Sepa_Logic_Parameters) class, 
*   e.g. with an extension
 */
class CRM_Sepa_Logic_Parameters {

  static private $_singleton = NULL;

  /**
   * Return the parameter singleton object. 
   * If a CRM_Sepa_Logic_CustomParameters class exists, it will be instatiated.
   */
  public function getParameters() {
    if ($_singleton==NULL) {
      // unfortunately class_exists() produces fatal errors, and include_once() warnings.
      if (@include_once "CRM/Sepa/Logic/CustomParameters.php") {
        $_singleton = new CRM_Sepa_Logic_CustomParameters();          
      } else {
        // custom class not found... use the defaults in this file
        $_singleton = new CRM_Sepa_Logic_Parameters();
      }
    }
    return $_singleton;
  }



  /**
   * Defines the cycle day, i.e. the day of the month or which the monthly/quarterly/yearly
   * will be scheduled
   *
   * @return type  day of month
   */
  public function getCycleDay() {
    return 8;
  }

  /**
   * Create a new, unique(!) mandate references. 
   * 
   * @param type $ref
   * @param type $type
   * @return type
   */
  public function createMandateReference(&$ref = null, $type = "R") {
    $r = "WMFR-" . date("Y");
    if ($ref) {
      $r .="-" . $ref["entity_id"];
    } else {
      $r .= "-RAND" . sprintf("%08d", rand(0, 999999));
    }
    return $r;
  }


  /**
   * Create a custom transaction message
   * 
   * @param type $mandate  the mandate used for this transaction
   * @return type  message
   */
  public function createTXMessage($mandate) {
    return "thanks";
  }
}

