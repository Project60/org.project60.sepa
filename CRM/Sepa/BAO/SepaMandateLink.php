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

declare(strict_types = 1);

class CRM_Sepa_BAO_SepaMandateLink extends CRM_Sepa_DAO_SepaMandateLink {


  public static string $LINK_CLASS_REPLACES    = 'REPLACES';
  public static string $LINK_CLASS_MEMBERSHIP  = 'MEMBERSHIP';

  /**
   * Create a new mandate link
   *
   * @param int $old_mandate_id        ID of the SepaMandate being replaced
   * @param int $new_mandate_id        ID of the SepaMandate replacing the old one
   * @param string $replacement_date   timestamp of the replacement, default is: now
   *
   * @throws Exception if mandatory fields aren't set
   */
  public static function addReplaceMandateLink(
    int $old_mandate_id,
    int $new_mandate_id,
    string $replacement_date = 'now'
  ): self {
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
   * @param string|null $end_date           end date of the link, default is NONE
   *
   * @throws Exception if mandatory fields aren't set
   */
  public static function createMandateLink(
    int $mandate_id,
    int $entity_id,
    string $entity_table,
    string $class,
    bool $is_active = TRUE,
    string $start_date = 'now',
    ?string $end_date = NULL
  ): self {
    $params = [
      'mandate_id'   => $mandate_id,
      'entity_id'    => $entity_id,
      'entity_table' => $entity_table,
      'class'        => $class,
      '$is_active'   => $is_active ? 1 : 0,
    ];

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
   * @param int|null $mandate_id            the mandate to link
   * @param string|list<string>|null $class        link class, max 16 characters
   * @param int|null $entity_id             ID of the linked entity
   * @param string|null $entity_table          table name of the linked entity
   * @param string $date               what timestamp does the "active" refer to? Default is: now
   *
   * @todo: add limit
   *
   * @return list<array<string, mixed>> list of link data
   *
   * @throws Exception if mandate_id is invalid
   */
  public static function getActiveLinks(
    ?int $mandate_id = NULL,
    string|array|null $class = NULL,
    ?int $entity_id = NULL,
    ?string $entity_table = NULL,
    string $date = 'now'
  ): array {
    // build where clause
    $WHERE_CLAUSES = [];

    // process date
    $now = date('YmdHis', strtotime($date));
    $WHERE_CLAUSES[] = 'is_active >= 1';
    $WHERE_CLAUSES[] = "start_date IS NULL OR start_date <= '{$now}'";
    $WHERE_CLAUSES[] = "end_date   IS NULL OR end_date   >  '{$now}'";

    // process mandate_id
    if (!empty($mandate_id)) {
      $WHERE_CLAUSES[] = "mandate_id = {$mandate_id}";
    }

    // process entity restrictions
    if (!empty($entity_id) && NULL !== $entity_table && preg_match('#^[a-z_]+$#', $entity_table)) {
      $entity_id = (int) $entity_id;
      $WHERE_CLAUSES[] = "entity_id = {$entity_id}";
      $WHERE_CLAUSES[] = "entity_table = '{$entity_table}'";
    }

    // process class restrictions
    if (NULL !== $class) {
      // make sure they are upper case
      $classes = [];
      if (is_string($class)) {
        $candidates = explode(',', $class);
      }
      else {
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
    /** @var \CRM_Core_DAO $query */
    $query = CRM_Core_DAO::executeQuery($query_sql);
    $results = [];
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
   * @throws Exception if mandatory fields aren't set
   */
  public static function endMandateLink(int $link_id, string $date = 'now'): void {
    if ($link_id) {
      $link = new CRM_Sepa_BAO_SepaMandateLink();
      $link->id = $link_id;
      $link->is_active = FALSE;
      $link->end_date = date('YmdHis', strtotime($date));
      $link->save();
    }
  }

  /**
   * Create/edit a SepaMandateLink entry
   *
   * @param array<string, mixed> $params
   *
   * @access public
   * @static
   * @throws Exception if mandatory parameters not set
   */
  public static function add(array &$params): self {
    // class should always be upper case
    if (!empty($params['class'])) {
      $params['class'] = strtoupper($params['class']);
    }

    $hook = empty($params['id']) ? 'create' : 'edit';
    if ($hook == 'create') {
      // check mandatory fields
      if (empty($params['mandate_id'])) {
        throw new Exception('Field mandate_id is mandatory.');
      }
      if (empty($params['entity_id'])) {
        throw new Exception('Field entity_id is mandatory.');
      }
      if (empty($params['entity_table'])) {
        throw new Exception('Field entity_table is mandatory.');
      }
      if (empty($params['class'])) {
        throw new Exception('Field class is mandatory.');
      }

      // set create date
      $params['creation_date'] = date('YmdHis');
    }

    CRM_Utils_Hook::pre($hook, 'SepaMandateLink', $params['id'] ?? NULL, $params);

    $dao = new CRM_Sepa_BAO_SepaMandateLink();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'SepaMandateLink', (int) $dao->id, $dao);
    /** @var \CRM_Sepa_BAO_SepaMandateLink $dao */
    return $dao;
  }

}
