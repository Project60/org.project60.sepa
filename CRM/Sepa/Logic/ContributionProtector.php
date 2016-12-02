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

require_once 'api/Wrapper.php';

/**
 * This wrapper will prevent users from deleting contributions
 *   or recurring contributions that are connected to a mandate
 */
class CRM_Sepa_Logic_ContributionProtector implements API_Wrapper {

  /**
   * Make sure nobody deletes stuff
   */
  public function fromApiInput($apiRequest) {
    // error_log(json_encode($apiRequest));
    if ($apiRequest['action'] == 'delete') {
      $error = FALSE;
      if ($apiRequest['entity'] == 'Contribution') {
        $error = self::isProtected($apiRequest['params']['id'], 'civicrm_contribution');
      } elseif ($apiRequest['entity'] == 'ContributionRecur') {
        $error = self::isProtected($apiRequest['params']['id'], 'civicrm_contribution_recur');
      }

      if ($error) {
        throw new API_Exception($error);
      }
    }

    return $apiRequest;
  }
 
   /**
   * alter the result before returning it to the caller.
   */
  public function toApiOutput($apiRequest, $result) {
    // nothing to do here
    return $result;
  }

  /**
   * Check if the given entity is protected by CiviSEPA,
   *  which usually means that it's connected to a SepaMandate
   * 
   * @return Error message if it is protected, FALSE otherwise
   */
  public static function isProtected($entity_id, $entity_table) {
    $entity_id = (int) $entity_id;
    if ($entity_id) {
      if ($entity_table == 'civicrm_contribution' || $entity_table == 'civicrm_contribution_recur') {
        $protected = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_sdd_mandate WHERE entity_id={$entity_id} AND entity_table='{$entity_table}';");
        if ($protected) {
          // TODO: use ts() parameters
          return sprintf(ts("You cannot delete this contribution because it is connected to SEPA mandate [%s]. Delete the mandate instead!", array('domain' => 'org.project60.sepa')), $protected);
        }
      }
    }
    return FALSE;
  }
}
