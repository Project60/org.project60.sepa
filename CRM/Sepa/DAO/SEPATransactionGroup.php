<?php

/**
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 *
 * Generated from org.project60.sepa/xml/schema/CRM/Sepa/TransactionGroup.xml
 * DO NOT EDIT.  Generated by CRM_Core_CodeGen
 * (GenCodeChecksum:43505c2b275afb7b54192132dcb4de64)
 */
use CRM_Sepa_ExtensionUtil as E;

/**
 * Database access object for the SEPATransactionGroup entity.
 */
class CRM_Sepa_DAO_SEPATransactionGroup extends CRM_Core_DAO {
  const EXT = E::LONG_NAME;
  const TABLE_ADDED = '';

  /**
   * Static instance to hold the table name.
   *
   * @var string
   */
  public static $_tableName = 'civicrm_sdd_txgroup';

  /**
   * Should CiviCRM log any modifications to this table in the civicrm_log table.
   *
   * @var bool
   */
  public static $_log = TRUE;

  /**
   * ID
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $id;

  /**
   * End-to-end reference for this tx group.
   *
   * @var string|null
   *   (SQL type: varchar(64))
   *   Note that values will be retrieved from the database as a string.
   */
  public $reference;

  /**
   * FRST, RCUR or OOFF
   *
   * @var string|null
   *   (SQL type: char(4))
   *   Note that values will be retrieved from the database as a string.
   */
  public $type;

  /**
   * Target collection date
   *
   * @var string|null
   *   (SQL type: datetime)
   *   Note that values will be retrieved from the database as a string.
   */
  public $collection_date;

  /**
   * Financial type of contained contributions if CiviSEPA is generating groups
   * matching financial types.
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $financial_type_id;

  /**
   * Latest submission date
   *
   * @var string|null
   *   (SQL type: datetime)
   *   Note that values will be retrieved from the database as a string.
   */
  public $latest_submission_date;

  /**
   * When was this item created
   *
   * @var string|null
   *   (SQL type: datetime)
   *   Note that values will be retrieved from the database as a string.
   */
  public $created_date;

  /**
   * fk sepa group Status options in civicrm_option_values
   *
   * @var int|string
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $status_id;

  /**
   * fk to SDD Creditor Id
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $sdd_creditor_id;

  /**
   * fk to SDD File Id
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $sdd_file_id;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->__table = 'civicrm_sdd_txgroup';
    parent::__construct();
  }

  /**
   * Returns localized title of this entity.
   *
   * @param bool $plural
   *   Whether to return the plural version of the title.
   */
  public static function getEntityTitle($plural = FALSE) {
    return $plural ? E::ts('SEPATransaction Groups') : E::ts('SEPATransaction Group');
  }

  /**
   * Returns foreign keys and entity references.
   *
   * @return array
   *   [CRM_Core_Reference_Interface]
   */
  public static function getReferenceColumns() {
    if (!isset(Civi::$statics[__CLASS__]['links'])) {
      Civi::$statics[__CLASS__]['links'] = static::createReferenceColumns(__CLASS__);
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'sdd_creditor_id', 'civicrm_sdd_creditor', 'id');
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'sdd_file_id', 'civicrm_sdd_file', 'id');
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'links_callback', Civi::$statics[__CLASS__]['links']);
    }
    return Civi::$statics[__CLASS__]['links'];
  }

  /**
   * Returns all the column names of this table
   *
   * @return array
   */
  public static function &fields() {
    if (!isset(Civi::$statics[__CLASS__]['fields'])) {
      Civi::$statics[__CLASS__]['fields'] = [
        'id' => [
          'name' => 'id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('ID'),
          'description' => E::ts('ID'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => TRUE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_sdd_txgroup.id',
          'export' => TRUE,
          'table_name' => 'civicrm_sdd_txgroup',
          'entity' => 'SEPATransactionGroup',
          'bao' => 'CRM_Sepa_DAO_SEPATransactionGroup',
          'localizable' => 0,
          'readonly' => TRUE,
          'add' => NULL,
        ],
        'reference' => [
          'name' => 'reference',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('Reference'),
          'description' => E::ts('End-to-end reference for this tx group.'),
          'maxlength' => 64,
          'size' => CRM_Utils_Type::BIG,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_sdd_txgroup.reference',
          'table_name' => 'civicrm_sdd_txgroup',
          'entity' => 'SEPATransactionGroup',
          'bao' => 'CRM_Sepa_DAO_SEPATransactionGroup',
          'localizable' => 0,
          'add' => NULL,
        ],
        'type' => [
          'name' => 'type',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('Type'),
          'description' => E::ts('FRST, RCUR or OOFF'),
          'maxlength' => 4,
          'size' => CRM_Utils_Type::FOUR,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_sdd_txgroup.type',
          'table_name' => 'civicrm_sdd_txgroup',
          'entity' => 'SEPATransactionGroup',
          'bao' => 'CRM_Sepa_DAO_SEPATransactionGroup',
          'localizable' => 0,
          'add' => NULL,
        ],
        'collection_date' => [
          'name' => 'collection_date',
          'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
          'title' => E::ts('Collection Date'),
          'description' => E::ts('Target collection date'),
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_sdd_txgroup.collection_date',
          'table_name' => 'civicrm_sdd_txgroup',
          'entity' => 'SEPATransactionGroup',
          'bao' => 'CRM_Sepa_DAO_SEPATransactionGroup',
          'localizable' => 0,
          'add' => NULL,
        ],
        'financial_type_id' => [
          'name' => 'financial_type_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Financial Type ID'),
          'description' => E::ts('Financial type of contained contributions if CiviSEPA is generating groups matching financial types.'),
          'required' => FALSE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_sdd_txgroup.financial_type_id',
          'table_name' => 'civicrm_sdd_txgroup',
          'entity' => 'SEPATransactionGroup',
          'bao' => 'CRM_Sepa_DAO_SEPATransactionGroup',
          'localizable' => 0,
          'add' => NULL,
        ],
        'latest_submission_date' => [
          'name' => 'latest_submission_date',
          'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
          'title' => E::ts('Latest Submission Date'),
          'description' => E::ts('Latest submission date'),
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_sdd_txgroup.latest_submission_date',
          'table_name' => 'civicrm_sdd_txgroup',
          'entity' => 'SEPATransactionGroup',
          'bao' => 'CRM_Sepa_DAO_SEPATransactionGroup',
          'localizable' => 0,
          'add' => NULL,
        ],
        'created_date' => [
          'name' => 'created_date',
          'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
          'title' => E::ts('Created Date'),
          'description' => E::ts('When was this item created'),
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_sdd_txgroup.created_date',
          'table_name' => 'civicrm_sdd_txgroup',
          'entity' => 'SEPATransactionGroup',
          'bao' => 'CRM_Sepa_DAO_SEPATransactionGroup',
          'localizable' => 0,
          'add' => NULL,
        ],
        'status_id' => [
          'name' => 'status_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Status ID'),
          'description' => E::ts('fk sepa group Status options in civicrm_option_values'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_sdd_txgroup.status_id',
          'table_name' => 'civicrm_sdd_txgroup',
          'entity' => 'SEPATransactionGroup',
          'bao' => 'CRM_Sepa_DAO_SEPATransactionGroup',
          'localizable' => 0,
          'add' => NULL,
        ],
        'sdd_creditor_id' => [
          'name' => 'sdd_creditor_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Sdd Creditor ID'),
          'description' => E::ts('fk to SDD Creditor Id'),
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_sdd_txgroup.sdd_creditor_id',
          'table_name' => 'civicrm_sdd_txgroup',
          'entity' => 'SEPATransactionGroup',
          'bao' => 'CRM_Sepa_DAO_SEPATransactionGroup',
          'localizable' => 0,
          'FKClassName' => 'CRM_Sepa_DAO_SEPACreditor',
          'add' => NULL,
        ],
        'sdd_file_id' => [
          'name' => 'sdd_file_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Sdd File ID'),
          'description' => E::ts('fk to SDD File Id'),
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_sdd_txgroup.sdd_file_id',
          'table_name' => 'civicrm_sdd_txgroup',
          'entity' => 'SEPATransactionGroup',
          'bao' => 'CRM_Sepa_DAO_SEPATransactionGroup',
          'localizable' => 0,
          'FKClassName' => 'CRM_Sepa_DAO_SEPASddFile',
          'add' => NULL,
        ],
      ];
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'fields_callback', Civi::$statics[__CLASS__]['fields']);
    }
    return Civi::$statics[__CLASS__]['fields'];
  }

  /**
   * Return a mapping from field-name to the corresponding key (as used in fields()).
   *
   * @return array
   *   Array(string $name => string $uniqueName).
   */
  public static function &fieldKeys() {
    if (!isset(Civi::$statics[__CLASS__]['fieldKeys'])) {
      Civi::$statics[__CLASS__]['fieldKeys'] = array_flip(CRM_Utils_Array::collect('name', self::fields()));
    }
    return Civi::$statics[__CLASS__]['fieldKeys'];
  }

  /**
   * Returns the names of this table
   *
   * @return string
   */
  public static function getTableName() {
    return self::$_tableName;
  }

  /**
   * Returns if this table needs to be logged
   *
   * @return bool
   */
  public function getLog() {
    return self::$_log;
  }

  /**
   * Returns the list of fields that can be imported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &import($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getImports(__CLASS__, 'sdd_txgroup', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of fields that can be exported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &export($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getExports(__CLASS__, 'sdd_txgroup', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of indices
   *
   * @param bool $localize
   *
   * @return array
   */
  public static function indices($localize = TRUE) {
    $indices = [
      'UI_reference' => [
        'name' => 'UI_reference',
        'field' => [
          0 => 'reference',
        ],
        'localizable' => FALSE,
        'unique' => TRUE,
        'sig' => 'civicrm_sdd_txgroup::1::reference',
      ],
      'creditor_id' => [
        'name' => 'creditor_id',
        'field' => [
          0 => 'sdd_creditor_id',
        ],
        'localizable' => FALSE,
        'sig' => 'civicrm_sdd_txgroup::0::sdd_creditor_id',
      ],
      'file_id' => [
        'name' => 'file_id',
        'field' => [
          0 => 'sdd_file_id',
        ],
        'localizable' => FALSE,
        'sig' => 'civicrm_sdd_txgroup::0::sdd_file_id',
      ],
    ];
    return ($localize && !empty($indices)) ? CRM_Core_DAO_AllCoreTables::multilingualize(__CLASS__, $indices) : $indices;
  }

}
