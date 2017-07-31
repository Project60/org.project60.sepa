<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 TTTP                           |
| Author: X+                                             |
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
 * Extend ContributionRecur API
 *
 * @package CiviCRM_SEPA
 *
 */

function civicrm_api3_contribution_recur_getfull ($params) {
  $sql= "select recur.id,recur.contact_id, date(start_date) as start_date, create_date, amount, count(recur.id) as nb_contribution, SUM(contrib.total_amount) as total from civicrm_contribution_recur as recur, civicrm_contribution contrib where contribution_recur_id = recur.id AND recur.contribution_status_id = 1 group by recur.id order by nb_contribution, start_date";
  $dao = CRM_Core_DAO::executeQuery($sql);
  $dao = CRM_Core_DAO::executeQuery($sql);
  $values = array();
  while ($dao->fetch()) {
    $values[] = $dao->toArray();
  }
  return civicrm_api3_create_success($values, $params, NULL, NULL, $dao);

}
