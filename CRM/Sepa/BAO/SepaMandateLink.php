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


  public static $LINK_CLASS_REPLACE     = 'REPLACE';
  public static $LINK_CLASS_MEMBERSHIP  = 'MEMBERSHIP';

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
       '$entity_id'   => $entity_id,
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
   * Get all mandate links with the given parameters
   *
   * @param int $mandate_id            the mandate to link
   * @param int $entity_id             the ID of the entity to link to
   * @param string $entity_table       table name of the entity to link to
   * @param string|array $class        link class, max 16 characters
   * @param bool $is_active            is the link active? default is YES
   * @param string $start_date         start date of the link, default NOW
   * @param string $end_date           end date of the link, default is NONE
   *
   * @return array of CRM_Sepa_BAO_SepaMandateLinks
   */
  public static function getMandateLinks($mandate_id, $class = NULL, $entity_id = NULL, $entity_table = NULL, $is_active = TRUE, $start_date = NULL, $end_date = NULL) {
    // TODO
  }


  /**
   * @param array  $params (reference ) an assoc array of name/value pairs
   *
   * @return object CRM_Sepa_BAO_SepaMandateLink object on success, null otherwise
   * @access public
   * @static
   * @throws Exception if mandatory parameters not set
   */
  static function add(&$params) {
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

    CRM_Utils_Hook::pre($hook, 'SepaMandateLink', CRM_Utils_Array::value('id', $params), $params);

    $dao = new CRM_Sepa_BAO_SepaMandateLink();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'SepaMandateLink', $dao->id, $dao);
    return $dao;
  }
}
