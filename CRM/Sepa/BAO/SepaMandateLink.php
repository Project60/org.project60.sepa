<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2018 SYSTOPIA                            |
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

class CRM_Sepa_BAO_SepaMandateLink extends CRM_Sepa_DAO_SepaMandateLink {


  public static $LINK_CLASS_REPLACES    = 'REPLACES';
  public static $LINK_CLASS_MEMBERSHIP  = 'MEMBERSHIP';

  /**
   * Create a new mandate link
   *
   * @param int $old_mandate_id        ID of the SepaMandate being replaced
   * @param int $new_mandate_id        ID of the SepaMandate replacing the old one
   * @param string $replacement_date   timestamp of the replacement, default is: now
   *
   * @return object CRM_Sepa_BAO_SepaMandateLink resulting object
   * @throws Exception if mandatory fields aren't set
   */
  public static function addReplaceMandateLink($old_mandate_id, $new_mandate_id, $replacement_date = 'now') {
    return self::createMandateLink(
        $new_mandate_id,
        $old_mandate_id,
        'civicrm_sdd_mandate',
        self::$LINK_CLASS_REPLACES,
        TRUE,
        $replacement_date);
  }

  /**
   * Create a new mandate link
   *
   * @param int $mandate_id            the mandate to link
   * @param int $entity_id             the ID of the entity to link to
   * @param string $entity_table       table name of the entity to link to
   * @param string $class              link class, max 16 characters
   * @param bool $is_active            is the link active? default is YES
   * @param string $start_date         start date of the link, default NOW
   * @param string $end_date           end date of the link, default is NONE
   *
   * @return object CRM_Sepa_BAO_SepaMandateLink resulting object
   * @throws Exception if mandatory fields aren't set
   */
  public static function createMandateLink($mandate_id, $entity_id, $entity_table, $class, $is_active = TRUE, $start_date = 'now', $end_date = NULL) {
    $params = array(
       'mandate_id'   => $mandate_id,
       'entity_id'    => $entity_id,
       'entity_table' => $entity_table,
       'class'        => $class,
       '$is_active'   => $is_active ? 1 : 0,
    );

    // set dates
    if ($start_date) {
      $params['start_date'] = date('YmdHis', strtotime($start_date));
    }
    if ($end_date) {
      $params['end_date'] = date('YmdHis', strtotime($end_date));
    }

    return self::add($params);
  }

  /**
   * Get all active mandate links with the given parameters,
   *  i.e. link fulfills the following criteria
   *        is_active = 1
   *        start_date NULL or in the past
   *
   *
   * @param int $mandate_id            the mandate to link
   * @param string|array $class        link class, max 16 characters
   * @param int $entity_id             ID of the linked entity
   * @param int $entity_table          table name of the linked entity
   * @param string $date               what timestamp does the "active" refer to? Default is: now
   *
   * @todo: add limit
   *
   * @return array of link data
   * @throws Exception if mandate_id is invalid
   */
  public static function getActiveLinks($mandate_id = NULL, $class = NULL, $entity_id = NULL, $entity_table = NULL, $date = 'now') {
    // build where clause
    $WHERE_CLAUSES = array();

    // process date
    $now = date('YmdHis', strtotime($date));
    $WHERE_CLAUSES[] = "is_active >= 1";
    $WHERE_CLAUSES[] = "start_date IS NULL OR start_date <= '{$now}'";
    $WHERE_CLAUSES[] = "end_date   IS NULL OR end_date   >  '{$now}'";

    // process mandate_id
    if (!empty($mandate_id)) {
      $mandate_id = (int) $mandate_id;
      $WHERE_CLAUSES[] = "mandate_id = {$mandate_id}";
    }

    // process entity restrictions
    if (!empty($entity_id) && preg_match('#^[a-z_]+$#', $entity_table)) {
      $entity_id = (int) $entity_id;
      $WHERE_CLAUSES[] = "entity_id = {$entity_id}";
      $WHERE_CLAUSES[] = "entity_table = '{$entity_table}'";
    }

    // process class restrictions
    if ($class) {
      // make sure they are upper case
      $classes = array();
      if (is_string($class)) {
        $candidates = explode(',', $class);
      } elseif (is_array($class)) {
        $candidates = $class;
      }
      foreach ($candidates as $candidate) {
        $candidate = strtoupper(trim($candidate));
        if (preg_match('#^[A-Z]+$#', $candidate)) {
          $classes[] = $candidate;
        }
      }

      // build clause
      if (!empty($classes)) {
        $classes_string = '"' . implode('","', $classes) . '"';
        $WHERE_CLAUSES[] = "class IN ({$classes_string})";
      }
    }

    // build and run query
    $WHERE_CLAUSE = '(' . implode(') AND (', $WHERE_CLAUSES) . ')';
    $query_sql = "SELECT * FROM civicrm_sdd_entity_mandate WHERE {$WHERE_CLAUSE}";
    $query = CRM_Core_DAO::executeQuery($query_sql);
    $results = array();
    while ($query->fetch()) {
      $results[] = $query->toArray();
    }
    return $results;
  }

  /**
   * Create a new mandate link
   *
   * @param int $link_id               ID of the link
   * @param string $date               at what timestamp should the link be ended - default is "now"
   *
   * @return object CRM_Sepa_BAO_SepaMandateLink resulting object
   * @throws Exception if mandatory fields aren't set
   */
  public static function endMandateLink($link_id, $date = 'now') {
    $link_id = (int) $link_id;
    if ($link_id) {
      $link = new CRM_Sepa_BAO_SepaMandateLink();
      $link->id = $link_id;
      $link->is_active = 0;
      $link->end_date = date('YmdHis', strtotime($date));
      $link->save();
    }
  }

  /**
   * Create/edit a SepaMandateLink entry
   *
   * @param array  $params (reference ) an assoc array of name/value pairs
   * @return object CRM_Sepa_BAO_SepaMandateLink object on success, null otherwise
   * @access public
   * @static
   * @throws Exception if mandatory parameters not set
   */
  static function add(&$params) {
    // class should always be upper case
    if (!empty($params['class'])) {
      $params['class'] = strtoupper($params['class']);
    }

    //
    $hook = empty($params['id']) ? 'create' : 'edit';
    if ($hook == 'create') {
      // check mandatory fields
      if (empty($params['mandate_id'])) {
        throw new Exception("Field mandate_id is mandatory.");
      }
      if (empty($params['entity_id'])) {
        throw new Exception("Field entity_id is mandatory.");
      }
      if (empty($params['entity_table'])) {
        throw new Exception("Field entity_table is mandatory.");
      }
      if (empty($params['class'])) {
        throw new Exception("Field class is mandatory.");
      }

      // set create date
      $params['creation_date'] = date('YmdHis');
    }

    CRM_Utils_Hook::pre($hook, 'SepaMandateLink', $params['id'] ?? NULL, $params);

    $dao = new CRM_Sepa_BAO_SepaMandateLink();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'SepaMandateLink', $dao->id, $dao);
    return $dao;
  }
}
