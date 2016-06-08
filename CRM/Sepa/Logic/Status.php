<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2016 SYSTOPIA                       |
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
  public static function translateMandateStatus($mandate_status, $localise = FALSE) {
    switch ($mandate_status) {

      case 'INIT':
        return ($localise?ts("Not activated", array('domain' => 'org.project60.sepa')):"Not activated");

      case 'FRST':
      case 'OOFF':
        return ($localise?ts("Ready", array('domain' => 'org.project60.sepa')):"Ready");

      case 'RCUR':
      case 'SENT':
        return ($localise?ts("In Use", array('domain' => 'org.project60.sepa')):"In Use");

      case 'COMPLETE':
        return ($localise?ts("Completed", array('domain' => 'org.project60.sepa')):"Completed");

      case 'ONHOLD':
        return ($localise?ts("Suspended", array('domain' => 'org.project60.sepa')):"Suspended");

      case 'PARTIAL':
        return ($localise?ts("Incomplete Donation", array('domain' => 'org.project60.sepa')):"Incomplete Donation");

      case 'INVALID':
      default:
        return ($localise?ts("Error", array('domain' => 'org.project60.sepa')):"Error");
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
   *                        if it's empty, it will always return multiple statuses 
   */
  public static function translateToMandateStatus($status, $mandate_type = NULL) {
    switch ($status) {
      
      case "Not activated":
        return ($mandate_type?'INIT':array('INIT'));

      case "Ready":
        if ($mandate_type == 'OOFF') {
          return 'OOFF';
        } elseif ($mandate_type == 'RCUR') {
          return 'FRST';
        } else {
          return array('FRST', 'OOFF');
        }

      case "In Use":
        if ($mandate_type == 'OOFF') {
          return 'SENT';
        } elseif ($mandate_type == 'RCUR') {
          return 'RCUR';
        } else {
          return array('SENT', 'RCUR');
        }
      
      case "Completed":
        return ($mandate_type?'COMPLETE':array('COMPLETE'));

      case "Suspended":
        return ($mandate_type?'ONHOLD':array('ONHOLD'));

      case "Incomplete Donation":
        return ($mandate_type?'PARTIAL':array('PARTIAL'));

      case "Error":
      default:
        return ($mandate_type?'INVALID':array('INVALID'));
    }
  }


  /**
   * get a mapping of the not localised human readable status
   * to the localised one, as can be used by dropdowns
   */
  public static function getStatusSelectorOptions($excludePartials = FALSE) {
    $list = array(
      "Not activated"       => ts("Not activated", array('domain' => 'org.project60.sepa')),
      "Ready"               => ts("Ready", array('domain' => 'org.project60.sepa')),
      "In Use"              => ts("In Use", array('domain' => 'org.project60.sepa')),
      "Completed"           => ts("Completed", array('domain' => 'org.project60.sepa')),
      "Suspended"           => ts("Suspended", array('domain' => 'org.project60.sepa')),
      // "Incomplete Donation" => ts("Incomplete Donation", array('domain' => 'org.project60.sepa')),
      "Error"               => ts("Error", array('domain' => 'org.project60.sepa')),
    );

    if (!$excludePartials) {
      $list["Incomplete Donation"] = ts("Incomplete Donation", array('domain' => 'org.project60.sepa'));
    }
    return $list;
  }
}
