<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 TTTP                           |
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
 * File for the CiviCRM sepa_transaction_group business logic
 *
 * @package CiviCRM_SEPA
 *
 */


/**
 * Class contains  functions for Sepa mandates
 */
class CRM_Sepa_BAO_SEPATransactionGroup extends CRM_Sepa_DAO_SEPATransactionGroup {


  /**
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return object       CRM_Core_BAO_SEPATransactionGroup object on success, null otherwise
   * @access public
   * @static
   */
  static function add(&$params) {
    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'SepaTransactionGroup', CRM_Utils_Array::value('id', $params), $params);

    $dao = new CRM_Sepa_DAO_SEPATransactionGroup();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'SepaTransactionGroup', $dao->id, $dao);
    return $dao;
  }

  function generateXML ($id = null) {
    $template = CRM_Core_Smarty::singleton();
    if ($id) {
      $this->id=$id;
    }
    if (empty ($this->id)) {
      CRM_Core_Error::fatal("missing id of the transaction group");
    }
    $r=array();
    $this->total=0;
    $this->nbtransactions=0;

    $group = civicrm_api3("SepaTransactionGroup","getsingle", array('id' => $this->id));
    if ($group['type'] == 'RTRY') {
      // RTRY groups (repeated collection attempt of failed debits) still have to be RCUR in the file
      $group['type'] = 'RCUR';
    }

    $creditor_id = $group["sdd_creditor_id"];
    $creditor    = civicrm_api3('SepaCreditor', 'getsingle', array('id' => $creditor_id));
    if (!empty($creditor['country_id'])) {
      $creditor['ctry'] = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Country', $creditor['country_id'], 'iso_code');
    }
    $format      = CRM_Sepa_Logic_Format::getFormatForCreditor($creditor_id);
    $template->assign('group',    $group);
    $template->assign('creditor', $creditor);

    $queryParams= array (1=>array($this->id, 'Positive'));
    $query="
      SELECT
        c.id AS cid,
        c.id AS contribution_id,
        civicrm_contact.display_name,
        invoice_id,
        currency,
        total_amount,
        receive_date,
        contribution_recur_id,
        contribution_status_id,
        a.street_address,
        a.postal_code,
        a.city,
        c.invoice_id,
        c.currency,
        c.total_amount,
        c.receive_date,
        c.contribution_recur_id,
        c.contribution_status_id,
        mandate.*
      FROM civicrm_contribution AS c
      JOIN civicrm_sdd_contribution_txgroup AS g ON g.contribution_id=c.id
      JOIN civicrm_sdd_mandate AS mandate ON mandate.id = IF(c.contribution_recur_id IS NOT NULL,
        (SELECT id FROM civicrm_sdd_mandate WHERE entity_table = 'civicrm_contribution_recur' AND entity_id = c.contribution_recur_id),
        (SELECT id FROM civicrm_sdd_mandate WHERE entity_table = 'civicrm_contribution' AND entity_id = c.id)
      )
      JOIN civicrm_contact ON c.contact_id = civicrm_contact.id
      LEFT JOIN civicrm_address a ON c.contact_id = a.contact_id AND a.is_primary = 1
      WHERE g.txgroup_id = %1
        AND c.contribution_status_id != 3
        AND mandate.is_enabled = true
      GROUP BY c.id"; //and not cancelled

    CRM_Core_DAO::disableFullGroupByMode();
    $contrib = CRM_Core_DAO::executeQuery($query, $queryParams);
    CRM_Core_DAO::reenableFullGroupByMode();

    setlocale(LC_CTYPE, 'en_US.utf8');
    //dear dear, it might work, but seems to be highly dependant of the system running it, without any way to know what is available, or if the setting was done properly #phpeature

    while ($contrib->fetch()) {
      $t         = $contrib->toArray();
      $t['id']   = $t['cid'];  // see https://github.com/Project60/org.project60.sepa/issues/385

      // SEPA-specific modifications and information on the IBAN.
      if ($creditor['creditor_type'] == 'SEPA') {
        $t["iban"] = str_replace(array(' ','-'), '', $t["iban"]);
        $t['ctry'] = substr($t["iban"], 0, 2);
      }

      // make some fields comply with SEPA standards
      if (!empty($t["account_holder"])) {
        $t["display_name"] = CRM_Sepa_Logic_Verification::convert2SepaCharacterSet($t["account_holder"]);
      } else {
        $t["display_name"] = CRM_Sepa_Logic_Verification::convert2SepaCharacterSet($t["display_name"]);
      }
      $t["street_address"] = CRM_Sepa_Logic_Verification::convert2SepaCharacterSet($t["street_address"]);
      $t["postal_code"]    = CRM_Sepa_Logic_Verification::convert2SepaCharacterSet($t["postal_code"]);
      $t["city"]           = CRM_Sepa_Logic_Verification::convert2SepaCharacterSet($t["city"]);

      // create an individual transaction message
      $t["message"] = CRM_Sepa_Logic_Settings::getTransactionMessage($t, $creditor, $this->id);

      // create an individual EndToEndId
      $end2endID = $t['id']; // that's the old default
      CRM_Utils_SepaCustomisationHooks::modify_endtoendid($end2endID, $t, $creditor);
      $t["end2endID"] = $end2endID;

      // let the format extend the transaction record
      $format->extendTransaction($t, $creditor_id);

      $r[] = $t;
      if ($creditor_id == null) {
        $creditor_id = $contrib->creditor_id;
      } elseif ($contrib->creditor_id == null) { // it shouldn't happen.
        $contrib->creditor_id = $creditor_id;
      } elseif ($creditor_id != $contrib->creditor_id){
        throw new Exception("Mixed creditors ({$creditor_id} != {$contrib->creditor_id}) in the group - contribution {$contrib->id}");
      }
      $this->total += $contrib->total_amount;
      $this->nbtransactions++;
    }
    $template->assign("total", number_format($this->total, 2, '.', '')); // SEPA-432: two-digit decimals
    $template->assign("nbtransactions", $this->nbtransactions);
    $template->assign("contributions", $r);

    // load file format class
    $fileFormatName = CRM_Core_PseudoConstant::getName('CRM_Sepa_BAO_SEPACreditor', 'sepa_file_format_id', $creditor['sepa_file_format_id']);
    $fileFormat = CRM_Sepa_Logic_Format::loadFormatClass($fileFormatName);
    $fileFormat->assignExtraVariables($template);

    // render file
    $content  = $template->fetch($fileFormat->getHeaderTpl());
    $content .= $template->fetch($fileFormat->getDetailsTpl());
    $content .= $template->fetch($fileFormat->getFooterTpl());
    return $fileFormat->characterEncode($content);
  }


  /**
   * This method will create the SDD file for the given group
   *
   * @param txgroup_id  the transaction group for which the file should be created
   * @param override    if true, will override an already existing file and create a new one
   *
   * @return int id of the sepa file entity created, or an error message string
   */
  static function createFile($txgroup_id, $override = false) {
    try {
      $txgroup = \Civi\Api4\SepaTransactionGroup::get(TRUE)
        ->addWhere('id', '=', $txgroup_id)
        ->execute()
        ->single();
    }
    catch (CRM_Core_Exception $exception) {
      return "Cannot find transaction group ".$txgroup_id;
    }

    // get file format
    $format = CRM_Sepa_Logic_Format::getFormatForCreditor($txgroup['sdd_creditor_id']);

    $creditor = civicrm_api ("SepaCreditor", "getsingle", array("sequential"=>1, "version"=>3, "id"=>$txgroup["sdd_creditor_id"]));
    // TODO: grouping: $fileFormatGrouping = CRM_Sepa_CustomData::getOptionValue('sepa_file_format', $creditor['sepa_file_format_id'], 'value', 'String', 'grouping');

    if ($override || (!isset($txgroup['sdd_file_id']) || !$txgroup['sdd_file_id'])) {
      // find an available txgroup reference
      // TODO: grouping: $available_name = $name = "SDD".strtoupper($fileFormatGrouping)."-".$txgroup['reference'];
      $available_name = $name = $format->getFileReference($txgroup);
      $counter = 1;
      $test_sql = "SELECT id FROM civicrm_sdd_file WHERE reference='%s';";
      while (CRM_Core_DAO::executeQuery(sprintf($test_sql, $available_name))->fetch()) {
        // i.e. available_name is already taken, modify it
        $available_name = $name.'--'.$counter;
        $counter += 1;
        if ($counter>1000) {
          return "Cannot create file! Unable to find an available file reference.";
        }
      }

      $group_status_id_closed = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Closed');

      // now that we found an available reference, create the file
      $sepa_file = civicrm_api('SepaSddFile', 'create', array(
            'version'                 => 3,
            'reference'               => $available_name,
            /// TODO: grouping: 'filename'                => $fileFormatGrouping ? $available_name.'.'.$fileFormatGrouping : $available_name.'.xml',
            'filename'                => $format->getFilename($available_name),
            'latest_submission_date'  => $txgroup['latest_submission_date'],
            'created_date'            => date('YmdHis'),
            'created_id'              => CRM_Core_Session::singleton()->get('userID'),
            'status_id'               => $group_status_id_closed)
        );
      if (isset($sepa_file['is_error']) && $sepa_file['is_error']) {
        return sprintf(ts("Cannot create file! Error was: '%s'", array('domain' => 'org.project60.sepa')), $sepa_file['error_message']);
      } else {

        // update the txgroup object
          $result = civicrm_api('SepaTransactionGroup', 'create', array(
                'id'                      => $txgroup_id,
                'sdd_file_id'             => $sepa_file['id'],
                'version'                 => 3));
          if (isset($result['is_error']) && $result['is_error']) {
            sprintf(ts("Cannot update transaction group! Error was: '%s'", array('domain' => 'org.project60.sepa')), $result['error_message']);
          }


        return $sepa_file['id'];
      }
    }
  }

  /**
   * This method will adjust the collection date,
   *   so it can still be submitted by the give submission date
   *
   * @param txgroup_id              the transaction group for which the file should be created
   * @param latest_submission_date  the date when it should be submitted
   *
   * @return an update array with the txgroup or a string with an error message
   */
  static function adjustCollectionDate($txgroup_id, $latest_submission_date) {
    $txgroup = \Civi\Api4\SepaTransactionGroup::get(TRUE)
      ->addWhere('id', '=', $txgroup_id)
      ->execute()
      ->single();
    if ($txgroup['type'] == 'RTRY') {
      $txgroup['type'] = 'RCUR';
    }

    $test_date_parse = strtotime($latest_submission_date);
    if (empty($test_date_parse)) {
      return "Bad date adjustment given!";
    }

    $notice_period = (int) CRM_Sepa_Logic_Settings::getSetting("batching.{$txgroup['type']}.notice", $txgroup['sdd_creditor_id']);
    $new_collection_date = date('YmdHis', strtotime("$latest_submission_date + $notice_period days"));
    CRM_Sepa_Logic_Batching::deferCollectionDate($new_collection_date, $txgroup['sdd_creditor_id']);
    $new_latest_submission_date = date('YmdHis', strtotime("$latest_submission_date"));

    $result = civicrm_api('SepaTransactionGroup', 'create', array(
      'version'                => 3,
      'id'                     => $txgroup_id,
      'collection_date'        => $new_collection_date,
      'latest_submission_date' => $new_latest_submission_date));
    if (!empty($result['is_error'])) {
      return $result['error_message'];
    }

    // reload the item
    try {
      $txgroup = \Civi\Api4\SepaTransactionGroup::get(TRUE)
        ->addWhere('id', '=', $txgroup_id)
        ->execute()
        ->single();
    }
    catch (CRM_Core_Exception $exception) {
      return $exception->getMessage();
    }
    return $txgroup;
  }


  /**
   * This method will delete a transaction group
   *
   * If required, it could also delete all
   *
   * @param txgroup_id                 the transaction group that should be deleted
   * @param delete_contributions_mode  select what to do with the associated mandates (OOFF) or contributions (RCUR):
   *                                      'no'   -  don't touch them
   *                                      'open' -  delete only open ones
   *                                      'all'  -  delete them all
   *
   * @return an array (contribution_id => error message) that have been deleted or a string with an error message.
   *            an error message of 'ok' means deletion succesfull
   */
  static function deleteGroup($txgroup_id, $delete_contributions_mode = 'no') {
    // load the group
    try {
      $txgroup = \Civi\Api4\SepaTransactionGroup::get(TRUE)
        ->addWhere('id', '=', $txgroup_id)
        ->execute()
        ->single();
    }
    catch (\CRM_Core_Exception $exception) {
      return "Transaction group [$txgroup_id] could not be loaded. Error was: " . $exception->getMessage();
    }

    // first, delete the contents of this group
    if ($delete_contributions_mode == 'no') {
      $contributions_deleted = array();
    } elseif ($delete_contributions_mode == 'open') {
      $status_id_pending = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
      $contributions_deleted = self::_deleteGroupContents($txgroup_id, $txgroup['type'], "civicrm_contribution.contribution_status_id = $status_id_pending");
    } elseif ($delete_contributions_mode == 'all') {
      $contributions_deleted = self::_deleteGroupContents($txgroup_id, $txgroup['type'], "TRUE");
    } else {
      return "Undefined deleteGroup mode '$delete_contributions_mode'. Ignored.";
    }

    // now: detach all the associated contribtuions
    $detach_contributions = "DELETE FROM civicrm_sdd_contribution_txgroup WHERE txgroup_id = $txgroup_id";
    CRM_Core_DAO::executeQuery($detach_contributions);

    // then delete the group itself
    $result = civicrm_api('SepaTransactionGroup', 'delete', array('id' => $txgroup_id, 'version' => 3));
    if (empty($result['is_error'])) {
      return $contributions_deleted;
    } else {
      return "Transaction group [$txgroup_id] could not be deleted. Error was: ".$result['error_message'];
    }
  }


  /**
   * Helper method to delete a txgroup along with all the contents that match the selector
   */
  static function _deleteGroupContents($txgroup_id, $type, $selector) {
    $txgroup_id = (int) $txgroup_id;
    $deleted_contributions = array();

    if ($type=='OOFF') {
      // delete the mandates first
      $query = "
      SELECT
        civicrm_contribution.id  AS contribution_id,
        civicrm_sdd_mandate.id   AS mandate_id
      FROM
        civicrm_sdd_contribution_txgroup
      LEFT JOIN
        civicrm_contribution ON civicrm_contribution.id = civicrm_sdd_contribution_txgroup.contribution_id
      LEFT JOIN
        civicrm_sdd_mandate ON civicrm_sdd_mandate.entity_id = civicrm_contribution.id AND civicrm_sdd_mandate.entity_table = 'civicrm_contribution' AND civicrm_sdd_mandate.type = 'OOFF'
      WHERE
        civicrm_sdd_contribution_txgroup.txgroup_id = $txgroup_id
      AND
        $selector;";
      $results = CRM_Core_DAO::executeQuery($query);
      while ($results->fetch()) {
        $contribution_id = (int) $results->contribution_id;
        // delete the mandate
        if (empty($results->mandate_id)) {
          $deleted_contributions[$contribution_id] = "No mandate found!";
        } else {
          $delete = civicrm_api('SepaMandate', 'delete', array('id' => $results->mandate_id, 'version' => 3));
          if (!empty($delete['is_error'])) {
            $deleted_contributions[$contribution_id] = $delete['error_message'];
          }
        }
      }
    }

    // now delete all the contributions
    $query = "
    SELECT
      civicrm_contribution.id  AS contribution_id
    FROM
      civicrm_sdd_contribution_txgroup
    LEFT JOIN
      civicrm_contribution ON civicrm_contribution.id = civicrm_sdd_contribution_txgroup.contribution_id
    WHERE
      civicrm_sdd_contribution_txgroup.txgroup_id = $txgroup_id
    AND
      $selector;";
    $results = CRM_Core_DAO::executeQuery($query);
    while ($results->fetch()) {
      if (isset($deleted_contributions[$results->contribution_id])) {
        // there has already been an error -> skip
        continue;
      } else {
        // remove contribution from mandate.first_contribution_id
        $contribution_id = (int) $results->contribution_id;
        CRM_Core_DAO::executeQuery("UPDATE civicrm_sdd_mandate SET first_contribution_id = NULL WHERE first_contribution_id = $contribution_id;");

        // delete the contribution
        $delete = civicrm_api('Contribution', 'delete', array('id' => $contribution_id, 'version' => 3));
        if (empty($delete['is_error'])) {
          $deleted_contributions[$contribution_id] = "ok";
        } else {
          $deleted_contributions[$contribution_id] = $delete['error_message'];
        }
      }
    }
    return $deleted_contributions;
  }

  /**
   * Get the custom transaction message for the given group.
   *
   * @param int|string $groupId
   *
   * @return string|null
   */
  public static function getCustomGroupTransactionMessage($groupId) {
    return self::getNoteWithSubject($groupId, 'transaction_message');
  }

  /**
   * Set the custom transaction message for the given group.
   *
   * @param int|string $groupId
   * @param string $note
   */
  public static function setCustomGroupTransactionMessage($groupId, $note) {
    self::setNoteWithSubject($groupId, 'transaction_message', $note);
  }

  /**
   * Get the transaction note for the given group.
   *
   * @param int|string $groupId
   *
   * @return string|null
   */
  public static function getNote($groupId) {
    return self::getNoteWithSubject($groupId, 'transaction_note');
  }

  /**
   * Set the transaction note for the given group.
   *
   * @param int|string $groupId
   * @param string $note
   */
  public static function setNote($groupId, $note) {
    self::setNoteWithSubject($groupId, 'transaction_note', $note);
  }

  /**
   * Get the CiviCRM note with the given subject for the given group.
   *
   * @param int|string $groupId
   * @param string $subject
   *
   * @return string|null
   */
  private static function getNoteWithSubject($groupId, $subject) {
    // NOTE: We cannot use the API here as it seems to only allow a fixed set of values for entity_table.

    $queryResult = CRM_Core_DAO::executeQuery(
      "SELECT
        note
      FROM
        civicrm_note
      WHERE
        entity_table = 'civicrm_sdd_txgroup'
        AND entity_id = %1
        AND `subject` = %2",
      [
        1 => [(int)$groupId, 'Integer'],
        2 => [$subject, 'String'],
      ]
    );

    if ($queryResult->fetch()) {
      return $queryResult->note;
    }
    else {
      return null;
    }
  }

  /**
   * Set a note with the given subject for the given group.
   *
   * @param int|string $groupId
   * @param string $subject
   * @param string $note
   */
  private static function setNoteWithSubject($groupId, $subject, $note) {
    // NOTE: We cannot use the API here as it seems to only allow a fixed set of values for entity_table.

    CRM_Core_DAO::executeQuery(
      "DELETE FROM
        civicrm_note
      WHERE
        entity_table = 'civicrm_sdd_txgroup'
        AND entity_id = %1
        AND `subject` = %2",
      [
        1 => [(int)$groupId, 'Integer'],
        2 => [$subject, 'String'],
      ]
    );
    // TODO: Is simply deleting any existing notes a good practice or should we check if it exists and then update?

    CRM_Core_DAO::executeQuery(
      "INSERT INTO
        civicrm_note (entity_table, entity_id, `subject`, note)
      VALUES
        ('civicrm_sdd_txgroup', %1, %2, %3)",
      [
        1 => [(int)$groupId, 'Integer'],
        2 => [$subject, 'String'],
        3 => [$note, 'String'],
      ]
    );
  }
}
