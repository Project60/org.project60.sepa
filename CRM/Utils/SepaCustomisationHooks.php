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

/**
 * defines customization hooks
 *
 * @package CiviCRM_SEPA
 *
 */


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
   * @param array $mandate_parameters the parameters that will be used to create the mandate.
   *
   * @return mixed             based on op. pre-hooks return a boolean or
   *                           an error message which aborts the operation
   * @access public
   */
  static function create_mandate(&$mandate_parameters) {
    $names = ['mandate_parameters'];
    return CRM_Utils_Hook::singleton()->invoke($names, $mandate_parameters, self::$null, self::$null, self::$null, self::$null, self::$null, 'civicrm_create_mandate');
  }

  /**
   * This hook is called when a new transaction group is generated
   *
   * The default implementation is "TXG-{$creditor_id}-{$mode}-{$collection_date}"
   *
   * Be aware the the reference has to be unique. You will have to use suffixes
   *  if your preferred reference is already in use.
   *
   * @param $reference        string  currently proposed reference (max. 35 characters!)
   * @param $collection_date  string  scheduled collection date
   * @param $mode             string  SEPA mode (OOFF, RCUR, FRST, RTRY)
   * @param $creditor_id      string  SDD creditor ID
   *
   * @access public
   */
  static function modify_txgroup_reference(&$reference, $creditor_id, $mode, $collection_date, $financial_type_id) {
    $names = ['reference', 'creditor_id', 'mode', 'collection_date', 'financial_type_id'];
    return CRM_Utils_Hook::singleton()->invoke($names, $reference, $creditor_id, $mode, $collection_date, $financial_type_id, self::$null, 'civicrm_modify_txgroup_reference');
  }


  /**
   * This hook is called when the PAIN.008 XML is being generated.
   *
   * You can implement this hook to generate a custom message to your
   *  debtor, even an individualised one (like "Thank you, Hans!")
   *
   * @param string $txmessage    the message that will go with the transaction. Modify or extend
   * @param array  $cinfo        some information on the mandate/contribution
   * @param array  $creditor     the creditor involved
   *
   * @access public
   */
  static function modify_txmessage(&$txmessage, $contribution, $creditor) {
    $names = ['txmessage', 'contribution', 'creditor'];
    return CRM_Utils_Hook::singleton()->invoke($names, $txmessage, $contribution, $creditor, self::$null, self::$null, self::$null, 'civicrm_modify_txmessage');
  }

  /**
   * This hook is called when the PAIN.008 XML is being generated.
   *
   * You can implement this hook to generate a custom EndToEndId
   *
   * @param string $end2endID    the PAIN EndToEndId being used for this transaction
   * @param array  $cinfo        some information on the mandate/contribution
   * @param array  $creditor     the creditor involved
   *
   * @access public
   */
  static function modify_endtoendid(&$end2endID, $contribution, $creditor) {
    $names = ['end2endID', 'contribution', 'creditor'];
    return CRM_Utils_Hook::singleton()->invoke($names, $end2endID, $contribution, $creditor, self::$null, self::$null, self::$null, 'civicrm_modify_endtoendid');
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
    $names = ['rcontribId', 'rcontrib'];
    return CRM_Utils_Hook::singleton()->invoke($names, $rcontribId, $rcontrib, self::$null, self::$null, self::$null, self::$null, 'civicrm_mend_rcontrib');
  }

  /**
   * This hook is called by the alternativeBatching:
   *  you can set a custom collection date for a rcurring contribution.
   *  For example you can use this hook when you mandate is connected to a yearly membership from January to December.
   *  And when a new member signs up in October. You want to collect that money in october and the membership will end on 31st of December.
   *  So the next collection is in January.
   *
   * @param string $next_collection_date  the calculated collection date (format: "YYYY-MM-DD").
   * @param array  $data array with data (such as mandate_id, mandate_entity_id for contribution recur id).
   *
   * @access public
   */
  static function alter_next_collection_date(&$next_collection_date, $data) {
    $names = ['next_collection_date', 'data'];
    return CRM_Utils_Hook::singleton()->invoke($names, $next_collection_date, $data, self::$null, self::$null, self::$null, self::$null, 'civicrm_alter_next_collection_date');
  }

  /**
   * This hook is called by the alternativeBatching:
   *  to avoid using a collection date that is not accepted by the bank, e.g. holidays,
   *  this hook lets you alter the calculated collection date string (format: "YYYY-MM-DD").
   *  You should _only_ defer the date by a few days!
   *
   * @param string $collection_date  the calculated collection date (format: "YYYY-MM-DD").
   * @param array  $creditor_id      the creditor involved
   *
   * @access public
   */
  static function defer_collection_date(&$collection_date, $creditor_id) {
    $names = ['collection_date', 'creditor_id'];
    return CRM_Utils_Hook::singleton()->invoke($names, $collection_date, $creditor_id, self::$null, self::$null, self::$null, self::$null, 'civicrm_defer_collection_date');
  }


  /**
   * This hook is called by the batching alogrithm:
   *  whenever a new installment has been created for a given RCUR mandate
   *  this hook is called so you can modify the resulting contribution,
   *  e.g. connect it to a membership, or copy custom fields
   *
   * be aware the newly created contribution is still 'Pending', it might NOT be
   * issued to the bank.
   *
   * @param array  $mandate_id             the CiviSEPA mandate entity
   * @param array  $contribution_recur_id  the recurring contribution connected to the mandate
   * @param array  $contribution_id        the newly created contribution
   *
   * @access public
   */
  static function installment_created($mandate_id, $contribution_recur_id, $contribution_id) {
    $names = ['mandate_id', 'contribution_recur_id', 'contribution_id'];
    return CRM_Utils_Hook::singleton()->invoke($names, $mandate_id, $contribution_recur_id, $contribution_id, self::$null, self::$null, self::$null, 'civicrm_installment_created');
  }
}
