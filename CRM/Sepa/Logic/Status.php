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

use CRM_Sepa_ExtensionUtil as E;

/**
 * This class provide functions to translate the SEPA mandate status
 * into the following, more 'human readable' ones:
 * 'Not activated', 'Ready', 'In Use', 'Completed', 'Suspended'
 */
class CRM_Sepa_Logic_Status {

  /**
   * translate the DB status tags to a human readable one.
   *
   * @param $manadate_status  the status as in the DB
   * @param $localise         return the ts'ed version of the value
   */
  // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
  public static function translateMandateStatus($mandate_status, $localise = FALSE) {
    switch ($mandate_status) {

      case 'INIT':
        return ($localise ? E::ts('Not activated') : 'Not activated');

      case 'FRST':
      case 'OOFF':
        return ($localise ? E::ts('Ready') : 'Ready');

      case 'RCUR':
      case 'SENT':
        return ($localise ? E::ts('In Use') : 'In Use');

      case 'COMPLETE':
        return ($localise ? E::ts('Completed') : 'Completed');

      case 'ONHOLD':
        return ($localise ? E::ts('Suspended') : 'Suspended');

      case 'PARTIAL':
        return ($localise ? E::ts('Incomplete Donation') : 'Incomplete Donation');

      case 'INVALID':
      default:
        return ($localise ? E::ts('Error') : 'Error');
    }
  }

  /**
   * Translates human readable status to the ones used in the DB
   * CAUTION: This only works for UNLOCALISED strings
   *
   * @param $status       the status as in the DB
   * @param $mandate_type the mandate type ('RCUR' or 'OOFF').
   *
   * @return string|array  if the mandate type is given, it will only return on status as a string
   *   if it's empty, it will always return multiple statuses
   */
  // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
  public static function translateToMandateStatus($status, $mandate_type = NULL) {
    switch ($status) {

      case 'Not activated':
        return ($mandate_type ? 'INIT' : ['INIT']);

      case 'Ready':
        if ($mandate_type == 'OOFF') {
          return 'OOFF';
        }
        elseif ($mandate_type == 'RCUR') {
          return 'FRST';
        }
        else {
          return ['FRST', 'OOFF'];
        }

      case 'In Use':
        if ($mandate_type == 'OOFF') {
          return 'SENT';
        }
        elseif ($mandate_type == 'RCUR') {
          return 'RCUR';
        }
        else {
          return ['SENT', 'RCUR'];
        }

      case 'Completed':
        return ($mandate_type ? 'COMPLETE' : ['COMPLETE']);

      case 'Suspended':
        return ($mandate_type ? 'ONHOLD' : ['ONHOLD']);

      case 'Incomplete Donation':
        return ($mandate_type ? 'PARTIAL' : ['PARTIAL']);

      case 'Error':
      default:
        return ($mandate_type ? 'INVALID' : ['INVALID']);
    }
  }

  /**
   * get a mapping of the not localised human readable status
   * to the localised one, as can be used by dropdowns
   */
  public static function getStatusSelectorOptions($excludePartials = FALSE) {
    $list = [
      'Not activated'       => E::ts('Not activated'),
      'Ready'               => E::ts('Ready'),
      'In Use'              => E::ts('In Use'),
      'Completed'           => E::ts('Completed'),
      'Suspended'           => E::ts('Suspended'),
      'Error'               => E::ts('Error'),
    ];

    if (!$excludePartials) {
      $list['Incomplete Donation'] = E::ts('Incomplete Donation');
    }
    return $list;
  }

}
