<?php
/*
+--------------------------------------------------------------------+
| CiviCRM version 4.3                                                |
+--------------------------------------------------------------------+
| Copyright CiviCRM LLC (c) 2004-2013                                |
+--------------------------------------------------------------------+
| This file is a part of CiviCRM.                                    |
|                                                                    |
| CiviCRM is free software; you can copy, modify, and distribute it  |
| under the terms of the GNU Affero General Public License           |
| Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
|                                                                    |
| CiviCRM is distributed in the hope that it will be useful, but     |
| WITHOUT ANY WARRANTY; without even the implied warranty of         |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
| See the GNU Affero General Public License for more details.        |
|                                                                    |
| You should have received a copy of the GNU Affero General Public   |
| License and the CiviCRM Licensing Exception along                  |
| with this program; if not, contact CiviCRM LLC                     |
| at info[AT]civicrm[DOT]org. If you have questions about the        |
| GNU Affero General Public License or the licensing of CiviCRM,     |
| see the CiviCRM license FAQ at http://civicrm.org/licensing        |
+--------------------------------------------------------------------+
*/
/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */
require_once 'CRM/Core/DAO.php';
require_once 'CRM/Utils/Type.php';
require_once 'CRM/Core/DAO/Country.php';
class CRM_Sepa_DAO_SEPACreditor extends CRM_Core_DAO
{
  /**
   * static instance to hold the table name
   *
   * @var string
   * @static
   */
  static $_tableName = 'civicrm_sdd_creditor';
  /**
   * static instance to hold the field values
   *
   * @var array
   * @static
   */
  static $_fields = null;
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
   * FK to Contact ID that owns that account
   *
   * @var int unsigned
   */
  public $creditor_id;
  /**
   * Provided by the bank. ISO country code+check digit+ZZZ+country specific identifier
   *
   * @var string
   */
  public $identifier;
  /**
   * by default creditor_id.display_name snapshot at creation
   *
   * @var string
   */
  public $name;
  /**
   * by default creditor_id.address (billing) at creation
   *
   * @var string
   */
  public $address;
  /**
   * Which Country does this address belong to.
   *
   * @var int unsigned
   */
  public $country_id;
  /**
   * Iban of the creditor
   *
   * @var string
   */
  public $iban;
  /**
   * BIC of the creditor
   *
   * @var string
   */
  public $bic;
  /**
   * prefix for mandate identifiers
   *
   * @var string
   */
  public $mandate_prefix;
  /**
   * Payment processor link (to be deprecated)
   *
   * @var int unsigned
   */
  public $payment_processor_id;
  /**
   * Default value
   *
   * @var string
   */
  public $category;
  /**
   * Place this creditor's transaction groups in an XML file tagged with this value.
   *
   * @var string
   */
  public $tag;
  /**
   * class constructor
   *
   * @access public
   * @return civicrm_sdd_creditor
   */
  function __construct()
  {
    $this->__table = 'civicrm_sdd_creditor';
    parent::__construct();
  }
  /**
   * return foreign links
   *
   * @access public
   * @return array
   */
  function links()
  {
    if (!(self::$_links)) {
      self::$_links = array(
        'creditor_id' => 'civicrm_contact:id',
        'country_id' => 'civicrm_country:id',
        'payment_processor_id' => 'civicrm_payment_processor:id',
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
        'id' => array(
          'name' => 'id',
          'type' => CRM_Utils_Type::T_INT,
          'required' => true,
        ) ,
        'creditor_id' => array(
          'name' => 'creditor_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => ts('Creditor Contact ID') ,
          'FKClassName' => 'CRM_Contact_DAO_Contact',
        ) ,
        'identifier' => array(
          'name' => 'identifier',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => ts('SEPA Creditor identifier') ,
          'maxlength' => 35,
          'size' => CRM_Utils_Type::BIG,
        ) ,
        'name' => array(
          'name' => 'name',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => ts('name of the creditor') ,
          'maxlength' => 255,
          'size' => CRM_Utils_Type::HUGE,
        ) ,
        'address' => array(
          'name' => 'address',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => ts('Address of the creditor') ,
          'maxlength' => 255,
          'size' => CRM_Utils_Type::HUGE,
        ) ,
        'country_id' => array(
          'name' => 'country_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => ts('Country') ,
          'FKClassName' => 'CRM_Core_DAO_Country',
        ) ,
        'iban' => array(
          'name' => 'iban',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => ts('Iban') ,
          'required' => false,
          'maxlength' => 42,
          'size' => CRM_Utils_Type::BIG,
        ) ,
        'bic' => array(
          'name' => 'bic',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => ts('Bic') ,
          'maxlength' => 11,
          'size' => CRM_Utils_Type::TWELVE,
        ) ,
        'mandate_prefix' => array(
          'name' => 'mandate_prefix',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => ts('Mandate numering prefix') ,
          'maxlength' => 4,
          'size' => CRM_Utils_Type::FOUR,
        ) ,
        'payment_processor_id' => array(
          'name' => 'payment_processor_id',
          'type' => CRM_Utils_Type::T_INT,
          'FKClassName' => 'CRM_Financial_DAO_PaymentProcessor',
        ) ,
        'category' => array(
          'name' => 'category',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => ts('Category purpose of the collection') ,
          'maxlength' => 4,
          'size' => CRM_Utils_Type::FOUR,
        ) ,
        'tag' => array(
          'name' => 'tag',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => ts('Tag') ,
          'maxlength' => 64,
          'size' => CRM_Utils_Type::BIG,
        ) ,
      );
    }
    return self::$_fields;
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
            self::$_import['sdd_creditor'] = & $fields[$name];
          } else {
            self::$_import[$name] = & $fields[$name];
          }
        }
      }
      self::$_import = array_merge(self::$_import, CRM_Core_DAO_Country::import(true));
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
            self::$_export['sdd_creditor'] = & $fields[$name];
          } else {
            self::$_export[$name] = & $fields[$name];
          }
        }
      }
      self::$_export = array_merge(self::$_export, CRM_Core_DAO_Country::export(true));
    }
    return self::$_export;
  }
}
