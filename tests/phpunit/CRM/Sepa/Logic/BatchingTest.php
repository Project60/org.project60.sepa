<?php

require_once 'CiviTest/CiviUnitTestCase.php';

/**
 * Only testing the error handling for over-long IDs for now.
 */
class CRM_Sepa_Logic_BatchingTest extends CiviUnitTestCase {
  /* PHP-IBAN only loads the registry when sourcing the file,
   * which is only allowed to happen once...
   * So make sure the registry is kept intact between tests. */
  protected $backupGlobalsBlacklist = array('_iban_registry');

  /* Hack to allow calling $this->getConnection() from static contexts. */
  private static $_anyInstance = null;

  /**
   * Purge any possible remnants of previously installed SEPA extension.
   *
   * The generic cleanup invoked by parent::setUpBeforeClass() only truncates the tables --
   * so we need to do some extra cleanup to make sure there is really nothing left.
   *
   * Note: we do not attempt to clean out entries in core tables,
   * as we rely on parent::setUpBeforeClass() to do this.
   * (Except for civicrm_extension, which is excluded from the automatic truncate...)
   *
   * This function uses PDO rather than CRM_Core_DAO::executeQuery(),
   * so it can be called from the constructor, before the CiviCRM bootstrap is complete.
   */
  static function _purgeExtension() {
    $utils = new Utils($GLOBALS['mysql_host'], $GLOBALS['mysql_port'], $GLOBALS['mysql_user'], $GLOBALS['mysql_pass']);
    $pdo = $utils->pdo;
    $dbName = parent::getDBName();
    $queries = array(
      "USE {$dbName}",
      "SET FOREIGN_KEY_CHECKS = 0",
      "DROP TABLE IF EXISTS `civicrm_sdd_contribution_txgroup`, `civicrm_sdd_txgroup`, `civicrm_sdd_file`, `civicrm_sdd_mandate`, `civicrm_sdd_creditor`",
      "DELETE FROM `civicrm_extension` WHERE `full_name` = 'org.project60.sepa'",
      "SET FOREIGN_KEY_CHECKS = 1",
    );
    foreach ($queries as $query) {
      $pdo->query($query);
      if ((int)$pdo->errorCode()) {
        die('Purging possible remnants of previously installed extension failed: ' . print_r($pdo->errorInfo(), true)); /* Can't throw an exception at this point, as that somehow totally unhinges the testing framework, instead of erroring out cleanly... */
      }
    }
  }

  function __construct($name = NULL, array $data = array(), $dataName = '') {
    /* We need to do this *before* the parent constructor,
     * because lingering remnants might interfere with civix attempting to auto-install the extension... */
    self::_purgeExtension();

    parent::__construct($name, $data, $dataName);

    self::$_anyInstance = $this;
  }

  static function setUpBeforeClass() {
    parent::setUpBeforeClass();

    /* The CiviUnitTestCase::getConnection() method is tremendously funny,
     * in that the first time it is called (typically during the first invocation of setUp()),
     * it initialises the DB again (which already has been done by, and indeed is the job of, setUpBeforeClass()) --
     * destroying any extra initialisations we have done in our setUpBeforeClass()!
     *
     * To deal with the implications of this questionable humour,
     * we need to make sure the first call to getConnection() method is triggered early
     * (even though we do not really need it otherwise),
     * so we can do our actual setup safely after that.
     *
     * To enable this gross hack, we need another gross hack (self::$_anyInstance),
     * so we can call the dynamic getConnection() method from the static setUpBeforeClass()...
     *
     * Note that in certain error situations,
     * civix/PHPUnit somehow causes setUpBeforeClass() to be invoked
     * before an instance has been constructed.
     * As it appears to happen only under fatal error conditions,
     * we can safely skip the hack entirely in these cases. */
    if (self::$_anyInstance) {
      self::$_anyInstance->getConnection();
    }

    self::_purgeExtension();
    civicrm_api3('Extension', 'install', array('keys' => 'org.project60.sepa'));
  }

  function setUp() {
    parent::setUp();

    /* Clean out all tables we might have populated in earlier tests within this class. */
    {
      /* Various tables, most of which are created implicitly. */
      $this->quickCleanUpFinancialEntities();

      /* Tables we create more or less explicitly. Some are already cleaned by quickCleanUpFinancialEntities(), though. */
      $this->quickCleanup(array(
        /* From PP setup. */
        #'civicrm_payment_processor',
        'civicrm_sdd_creditor',
        /* From Contact setup. */
        'civicrm_contact',
        'civicrm_email',
        /* From Contribution setup. */
        #'civicrm_contribution',
        #'civicrm_contribution_recur',
        'civicrm_sdd_mandate',
        /* From batching / file generation. */
        'civicrm_sdd_txgroup',
        'civicrm_sdd_contribution_txgroup',
        'civicrm_sdd_file',
      ));
    }
  }

  function tearDown() {
    parent::tearDown();

    /* Try to avoid littering the disk. */
    civicrm_api3('File', 'get', array('api.File.delete' => array()));
  }


  /**
   * Set up a SEPA Payment Processor.
   *
   * This creates a PP record along with an associated Creditor record,
   * just like creating a new SEPA PP through the UI.
   *
   * The $params are passed to both the PP and Creditor create calls.
   * Any values present replace the default values used by this function.
   *
   * Note: some of the default values are crucial for correct operation --
   * i.e. certain $params values could break this function.
   * Use with caution.
   *
   * (This is just a testsuite, not application code --
   * so prioritising simplicity over robustness here...)
   *
   * @param array $params Optional parameters passed to PP and Creditor create API calls
   * @return array(integer,integer) Payment Processor ID, Creditor ID
   */
  function _createPP(array $params = array()) {
    $result = civicrm_api3('PaymentProcessorType', 'get', array(
      'name' => 'sepa_dd',
      'sequential' => 1,
      'api.PaymentProcessor.create' => array_merge(array(
        'domain_id' => 1,
        'name' => 'SEPA test',
        'is_active' => 1,
        'is_default' => 0,
        'is_test' => 0,
        'user_name' => 'TEST_CREDITOR_ID',
      ), $params),
      'api.SepaCreditor.create' => array_merge(array(
        'payment_processor_id' => '$value.api.PaymentProcessor.create.id',
        #'creditor_id' /* Do we ever use this? I don't think so... (Too lazy to create extra contact for that.) */
        'identifier' => '$value.api.PaymentProcessor.create.values.0.user_name',
        'name' => 'Test Organisation',
        'address' => "Teststr. 1\n12345 Teststadt",
        'iban' => 'DE06495352657836424132',
        'remmitance_info' => 'thanks', /* Not exactly necessary -- but most real-world installs will have it set... */
        #'tag' /* Usually empty in real-world installs. */
        'mandate_active' => 1, /* This probably should actually be the global default; but it isn't so far... */
        'sepa_file_format_id' => CRM_Core_OptionGroup::getValue('sepa_file_format', 'pain.008.003.02', 'name'), /* That's what all existing real-world users have, even though it's not the default... */
        'use_cor1' => 1, /* Most (all?) users will want this. */
      ), $params),
      /* Test processor. We don't really use it -- but try to emulate creation through UI, so tests are as close to real-world setup as possible... */
      'api.PaymentProcessor.create.2' => array_merge(array(
        'domain_id' => '$value.api.PaymentProcessor.create.values.0.domain_id',
        'name' => '$value.api.PaymentProcessor.create.values.0.name',
        'is_active' => '$value.api.PaymentProcessor.create.values.0.is_active',
        'is_default' => '$value.api.PaymentProcessor.create.values.0.is_default',
        'is_test' => 1,
        'user_name' => '$value.api.PaymentProcessor.create.values.0.user_name',
      ), $params),
    ));

    return array(
      $result['values'][0]['api.PaymentProcessor.create']['id'],
      $result['values'][0]['api.SepaCreditor.create']['id'],
    );
  }

  /**
   * Create a SEPA Contribution.
   *
   * This creates a Contribution record along with an associated Mandate record,
   * just like creating a new SEPA Contribution through the UI.
   *
   * Note: This function is implemented using api.SepaImport.create,
   * which means it currently can only create recurring contributions!
   * (Though it will only create the first installment.)
   *
   * This function provides defaults for all the mandatory parameters.
   * Most of them are quite arbitrary though,
   * and tests generally should override them with explicit values,
   * unless the values are irrelevant for the particular test.
   *
   * @param array $params Optional parameters passed to SepaImport.create call
   */
  function _createContribution(array $params = array()) {
    civicrm_api3('SepaImport', 'create', array_merge(array(
      'contact_id' => '1',
      'iban' => 'DE89 3704 0044 0532 0130 00',
      'status' => 'FRST',
      'create_date' => '1970-01-01', /* Shouldn't matter in most cases, as long as it's <= start_date. */
      'start_date' => '2015-05-05',
      'frequency_unit' => 'month',
      'frequency_interval' => '1',
      'amount' => '23',
      'payment_processor_id' => '1', /* Usually tests will set up exactly one PP for SEPA. */
      'financial_type_id' => '1', /* Shouldn't matter for most tests. */
      'sequential' => 1,
    ), $params));

    CRM_Core_DAO::$_dbColumnValueCache = null; /* Get rid of stale values, causing mysterious failures later on. */
  }


  /**
   * Test IDs generated in batchForSubmit().
   *
   * Specifically, these are the <MsgId> and the <PmtInfId>;
   * and also the XML file name, which is closely related to the <MsgId>.
   *
   * @dataProvider batchForSubmit_ids_provider
   *
   * @param array $creditorParams
   * @param mixed $expectedResult array(<PmtInfId>, <MsgId>, fileName); or exception object when expecting error.
   */
  function test_batchForSubmit_ids(array $creditorParams, $expectedResult) {
    list($paymentProcessorID, $creditorID) = $this->_createPP($creditorParams);
    $contactID = $this->individualCreate();
    $this->_createContribution(array('payment_processor_id' => $paymentProcessorID, 'contact_id' => $contactID, 'start_date' => '2015-05-05'));

    if ($expectedResult instanceof Exception) {
      $this->setExpectedException(get_class($expectedResult), $expectedResult->getMessage(), $expectedResult->getCode());
      CRM_Sepa_Logic_Batching::batchForSubmit('2015-05-05', $creditorID);
    } else {
      list($pmtInfId, $msgId, $fileName) = $expectedResult;

      CRM_Sepa_Logic_Batching::batchForSubmit('2015-05-05', $creditorID);
      $this->assertEquals(1, civicrm_api3('SepaSddFile', 'getcount'), "No SEPA XML file created:");
      $this->assertEquals($pmtInfId, civicrm_api3('SepaTransactionGroup', 'getvalue', array('return' => 'reference')), "Wrong <PmtInfId>:");
      $this->assertEquals($msgId, civicrm_api3('SepaSddFile', 'getvalue', array('return' => 'reference')), "Wrong <MsgId>:");
      $this->assertEquals($fileName, civicrm_api3('SepaSddFile', 'getvalue', array('return' => 'filename')), "Wrong file name:");
    }
  }

  function batchForSubmit_ids_provider() {
    return array(
      'batching mode NONE' => array(
        'params' => array('mandate_prefix' => 'TEST', 'group_batching_mode' => 'NONE'),
        'expectedResult' => array('G-TEST,1-COR1-F150507-1', 'F-TEST-150505COR1-F150507-1', 'SDDXML-TEST-20150505-COR1-FRST-20150507-1.xml'),
      ),
      'batching mode TYPE' => array(
        'params' => array('mandate_prefix' => 'TEST', 'group_batching_mode' => 'TYPE'),
        'expectedResult' => array('G-TEST,1-COR1-F150507-1', 'F-TEST-150505COR1-F-1', 'SDDXML-TEST-20150505-COR1-FRST-1.xml'),
      ),
      'batching mode COR' => array(
        'params' => array('mandate_prefix' => 'TEST', 'group_batching_mode' => 'COR'),
        'expectedResult' => array('G-TEST,1-COR1-F150507-1', 'F-TEST-150505COR1-1', 'SDDXML-TEST-20150505-COR1-1.xml'),
      ),
      'batching mode ALL' => array(
        'params' => array('mandate_prefix' => 'TEST', 'group_batching_mode' => 'ALL'),
        'expectedResult' => array('G-TEST,1-COR1-F150507-1', 'F-TEST-150505-1', 'SDDXML-TEST-20150505-1.xml'),
      ),

      'no overflow (batching mode NONE)' => array(
        'params' => array('mandate_prefix' => 'LONGPREFIX', 'group_batching_mode' => 'NONE'),
        'expectedResult' => array('G-LONGPREFIX,1-COR1-F150507-1', 'F-LONGPREFIX-150505COR1-F150507-1', 'SDDXML-LONGPREFIX-20150505-COR1-FRST-20150507-1.xml'),
      ),
      'no overflow (batching mode ALL)' => array(
        'params' => array('mandate_prefix' => 'MUCHLONGERPREFIX', 'group_batching_mode' => 'ALL'),
        'expectedResult' => array('G-MUCHLONGERPREFIX,1-COR1-F150507-1', 'F-MUCHLONGERPREFIX-150505-1', 'SDDXML-MUCHLONGERPREFIX-20150505-1.xml'),
      ),
      'overflow <MsgId>' => array(
        'params' => array('mandate_prefix' => 'TOOLONGPREFIX', 'group_batching_mode' => 'NONE'),
        'expectedResult' => new CRM_Exception('Can\'t create SEPA XML file: <MsgId>'),
      ),
      'overflow <PmtInfId>' => array(
        'params' => array('mandate_prefix' => 'WAAAYTOOLONGPREFIX', 'group_batching_mode' => 'ALL'),
        'expectedResult' => new CRM_Exception('Can\'t create SEPA XML file: <PmtInfId>'),
      ),
    );
  }
}
