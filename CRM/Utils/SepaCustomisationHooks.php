<?php

/**
 * Defines the hooks that allow the customisation of SEPA related data
 */
class CRM_Utils_SepaCustomisationHooks {

  static $null = NULL;

  /**
   * This hook is called before a newly created mandate is written to the DB
   *
   * You can implement this hook e.g. to modify the mandate reference ($parameters['reference'])
   *
   * @param object $parameters the parameters that will be used to create the mandate.
   *
   * @return mixed             based on op. pre-hooks return a boolean or
   *                           an error message which aborts the operation
   * @access public
   */
  static function create_mandate(&$mandate_parameters) {
    return CRM_Utils_Hook::singleton()->invoke(1, $mandate_parameters, self::$null, self::$null, self::$null, self::$null, 'civicrm_create_mandate');
  }


  /**
   * This hook is called when the PAIN.008 XML is being generated.
   *
   * You can implement this hook to generate a custom message to your
   *  debtor, even an individualised one (like "Thank you, Hans!")
   *
   * @param string $txmessage    the message that will go with the transaction. Modify or extend
   * @param array  $contribution the contribution that is being debited
   * @param array  $creditor the the creditor involved
   *
   * @access public
   */
  static function modify_txmessage(&$txmessage, $contribution, $creditor) {
    return CRM_Utils_Hook::singleton()->invoke(3, $txmessage, $contribution, $creditor, self::$null, self::$null, 'civicrm_modify_txmessage');
  }


  /**
   * This hook is called when a new mandate is created. It gives you the
   *  opportunity to change things like the cycle date
   *
   * @param string $rcontribId  the Id of the recurring contribtution, that is connected to the mandate
   * @param array  $rcontrib    the recurring contribtution object, that is connected to the mandate
   *
   * @access public
   */
  static function mend_rcontrib($rcontribId, &$rcontrib) {
    return CRM_Utils_Hook::singleton()->invoke(2, $rcontribId, $rcontrib, self::$null, self::$null, self::$null, 'civicrm_mend_rcontrib');
  }
}