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

/**
 * CAUTION: NOT Generated from xml/schema/CRM/Sepa/SepaMandateLink.xml
 * This is handcrafted - and yes, that should be changed.
 */
require_once 'CRM/Core/DAO.php';
require_once 'CRM/Utils/Type.php';

class CRM_Sepa_DAO_SepaMandateLink extends CRM_Core_DAO
{
  /**
   * static instance to hold the table name
   *
   * @var string
   * @static
   */
  static $_tableName = 'civicrm_sdd_entity_mandate';
  /**
   * static instance to hold the field values
   *
   * @var array
   * @static
   */
  static $_fields = null;
  /**
   * static instance to hold the keys used in $_fields for each field.
   *
   * @var array
   * @static
   */
  static $_fieldKeys = null;
  /**
   * static instance to hold the FK relationships
   *
   * @var string
   * @static
   */
  static $_links = null;
  /**
   * static instance to hold the values that can
   * be imported
   *
   * @var array
   * @static
   */
  static $_import = null;
  /**
   * static instance to hold the values that can
   * be exported
   *
   * @var array
   * @static
   */
  static $_export = null;
  /**
   * static value to see if we should log any modifications to
   * this table in the civicrm_log table
   *
   * @var boolean
   * @static
   */
  static $_log = true;
  /**
   * ID
   *
   * @var int unsigned
   */
  public $id;

  /**
   * mandate id
   *
   * @var int unsigned
   */
  public $mandate_id;

  /**
   * linked entity id
   *
   * @var int unsigned
   */
  public $entity_id;

  /**
   * linked entity table
   *
   * @var string
   */
  public $entity_table;

  /**
   * link class
   *
   * @var string
   */
  public $class;

  /**
   * is this link activ?
   *
   * @var int unsigned
   */
  public $is_active;

  /**
   * creation date
   * by default now()
   *
   * @var datetime
   */
  public $creation_date;

  /**
   * link start date (optional)
   *
   * @var datetime
   */
  public $start_date;

  /**
   * link end date (optional)
   *
   * @var datetime
   */
  public $end_date;


  function __construct()
  {
    $this->__table = 'civicrm_sdd_mandate';
    parent::__construct();
  }
  /**
   * return foreign keys and entity references
   *
   * @static
   * @access public
   * @return array of CRM_Core_EntityReference
   */
  static function getReferenceColumns()
  {
    if (!self::$_links) {
      self::$_links = array(
          new CRM_Core_EntityReference(self::getTableName() , 'mandate_id', 'civicrm_sdd_mandate', 'id') ,
          new CRM_Core_EntityReference(self::getTableName() , 'entity_id', NULL, 'id', 'entity_table') ,
      );
    }
    return self::$_links;
  }

  /**
   * returns all the column names of this table
   *
   * @access public
   * @return array
   */
  static function &fields()
  {
    if (!(self::$_fields)) {
      self::$_fields = array(
          'id' => [
              'name' => 'id',
              'type' => CRM_Utils_Type::T_INT,
              'description' => 'Unique SepaMandateLink ID',
              'required' => TRUE,
              'table_name' => 'civicrm_sdd_entity_mandate',
              'entity' => 'SepaMandateLink',
              'bao' => 'CRM_Sepa_DAO_SepaMandateLink',
              'localizable' => 0,
          ],
          'mandate_id' => [
              'name' => 'mandate_id',
              'type' => CRM_Utils_Type::T_INT,
              'title' => ts('SepaMandate ID'),
              'description' => 'FK to SepaMandate ID',
              'table_name' => 'civicrm_sdd_entity_mandate',
              'entity' => 'SepaMandateLink',
              'bao' => 'CRM_Sepa_DAO_SepaMandateLink',
              'localizable' => 0,
          ],
          'entity_table' => [
              'name' => 'entity_table',
              'type' => CRM_Utils_Type::T_STRING,
              'title' => ts('Entity Table'),
              'description' => 'Physical table name for entity being linked, eg civicrm_membership',
              'maxlength' => 64,
              'size' => CRM_Utils_Type::BIG,
              'table_name' => 'civicrm_sdd_entity_mandate',
              'entity' => 'SepaMandateLink',
              'bao' => 'CRM_Sepa_DAO_SepaMandateLink',
              'localizable' => 0,
          ],
          'entity_id' => [
              'name' => 'entity_id',
              'type' => CRM_Utils_Type::T_INT,
              'title' => ts('Entity ID'),
              'description' => 'FK to entity table specified in entity_table column',
              'required' => TRUE,
              'table_name' => 'civicrm_sdd_entity_mandate',
              'entity' => 'SepaMandateLink',
              'bao' => 'CRM_Sepa_DAO_SepaMandateLink',
              'localizable' => 0,
          ],
          'class' => [
              'name' => 'class',
              'type' => CRM_Utils_Type::T_STRING,
              'title' => ts('Entity Table'),
              'description' => 'Link class, freely defined by client',
              'maxlength' => 16,
              'size' => CRM_Utils_Type::TWELVE,
              'table_name' => 'civicrm_sdd_entity_mandate',
              'entity' => 'SepaMandateLink',
              'bao' => 'CRM_Sepa_DAO_SepaMandateLink',
              'localizable' => 0,
          ],
          'is_active' => [
              'name' => 'is_active',
              'type' => CRM_Utils_Type::T_BOOLEAN,
              'description' => 'Is this link still active?',
              'table_name' => 'civicrm_sdd_entity_mandate',
              'entity' => 'SepaMandateLink',
              'bao' => 'CRM_Sepa_DAO_SepaMandateLink',
              'localizable' => 0,
          ],
          'creation_date' => [
              'name' => 'creation_date',
              'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
              'title' => ts('Creation Date'),
              'description' => 'Link creation date',
              'table_name' => 'civicrm_sdd_entity_mandate',
              'entity' => 'SepaMandateLink',
              'bao' => 'CRM_Sepa_DAO_SepaMandateLink',
              'localizable' => 0,
          ],
          'start_date' => [
              'name' => 'start_date',
              'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
              'title' => ts('Start Date'),
              'description' => 'Start date of the link (optional)',
              'table_name' => 'civicrm_sdd_entity_mandate',
              'entity' => 'SepaMandateLink',
              'bao' => 'CRM_Sepa_DAO_SepaMandateLink',
              'localizable' => 0,
          ],
          'end_date' => [
              'name' => 'end_date',
              'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
              'title' => ts('End Date'),
              'description' => 'End date of the link (optional)',
              'table_name' => 'civicrm_sdd_entity_mandate',
              'entity' => 'SepaMandateLink',
              'bao' => 'CRM_Sepa_DAO_SepaMandateLink',
              'localizable' => 0,
          ],
      );
    }
    return self::$_fields;
  }


  /**
   * Returns an array containing, for each field, the arary key used for that
   * field in self::$_fields.
   *
   * @access public
   * @return array
   */
  static function &fieldKeys()
  {
    if (!(self::$_fieldKeys)) {
      self::$_fieldKeys = array(
          'id' => 'id',
          'mandate_id' => 'mandate_id',
          'entity_table' => 'entity_table',
          'entity_id' => 'entity_id',
          'class' => 'class',
          'is_active' => 'is_active',
          'creation_date' => 'creation_date',
          'start_date' => 'start_date',
          'end_date' => 'end_date'
      );
    }
    return self::$_fieldKeys;
  }
  /**
   * returns the names of this table
   *
   * @access public
   * @static
   * @return string
   */
  static function getTableName()
  {
    return self::$_tableName;
  }
  /**
   * returns if this table needs to be logged
   *
   * @access public
   * @return boolean
   */
  function getLog()
  {
    return self::$_log;
  }
  /**
   * returns the list of fields that can be imported
   *
   * @access public
   * return array
   * @static
   */
  static function &import($prefix = false)
  {
    if (!(self::$_import)) {
      self::$_import = array();
      $fields = self::fields();
      foreach($fields as $name => $field) {
        if (CRM_Utils_Array::value('import', $field)) {
          if ($prefix) {
            self::$_import['sdd_entity_mandate'] = & $fields[$name];
          } else {
            self::$_import[$name] = & $fields[$name];
          }
        }
      }
    }
    return self::$_import;
  }
  /**
   * returns the list of fields that can be exported
   *
   * @access public
   * return array
   * @static
   */
  static function &export($prefix = false)
  {
    if (!(self::$_export)) {
      self::$_export = array();
      $fields = self::fields();
      foreach($fields as $name => $field) {
        if (CRM_Utils_Array::value('export', $field)) {
          if ($prefix) {
            self::$_export['sdd_entity_mandate'] = & $fields[$name];
          } else {
            self::$_export[$name] = & $fields[$name];
          }
        }
      }
    }
    return self::$_export;
  }
}
