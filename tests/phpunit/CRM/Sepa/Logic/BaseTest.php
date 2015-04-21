<?php

require_once 'CiviTest/CiviUnitTestCase.php';

/**
 * Presently, we only test adjustBankDays()...
 */
class CRM_Sepa_Logic_BaseTest extends CiviUnitTestCase {
  static function setUpBeforeClass() {
    parent::setUpBeforeClass();

    /* Need to initialize include path etc. explicitly, as we don't install the extension here, so the hooks don't get run... */
    require_once dirname(__FILE__) . '/../../../../../sepa.php';
    sepa_civicrm_config($_);
  }

  /**
   * @dataProvider adjustBankDays_provider
   *
   * @param array $params
   * @param string $expectedResult
   */
  function test_adjustBankDays($params, $expectedResult) {
    list($date_to_adjust, $days_delta) = $params;

    if ($expectedResult instanceof Exception) {
      $this->setExpectedException(get_class($expectedResult), $expectedResult->getMessage(), $expectedResult->getCode());
    }
    $actualResult = CRM_Sepa_Logic_Base::adjustBankDays($date_to_adjust, $days_delta);
    if (! $expectedResult instanceof Exception) {
      $this->assertSame($expectedResult, $actualResult);
    }
  }

  function adjustBankDays_provider() {
    return array(
      'one weekday' => array(array('2015-04-21', 1), '2015-04-22'),
      'two weekdays' => array(array('2015-04-21', 2), '2015-04-23'),
      'spanning weekend' => array(array('2015-04-23', 2), '2015-04-27'),
      'starting right before weekend' => array(array('2015-04-24', 2), '2015-04-28'),
      'starting during weekend' => array(array('2015-04-25', 2), '2015-04-29'),
      'spanning two weekends' => array(array('2015-04-16', 7), '2015-04-27'),
      'zero weekdays' => array(array('2015-04-21', 0), '2015-04-21'),
      'zero days on weekend' => array(array('2015-04-25', 0), '2015-04-27'),
      'wrapping over short month end' => array(array('2015-06-30', 2), '2015-07-02'),
      'wrapping over long month end' => array(array('2015-03-30', 2), '2015-04-01'),
      'two weekdays after february 28th' => array(array('2011-02-28', 2), '2011-03-02'),
      'two weekdays after february 28th in leap year' => array(array('2012-02-28', 2), '2012-03-01'),
      'wrapping over year end and spanning new year\'s day' => array(array('2014-12-30', 2), '2015-01-02'),
      'spanning labour day' => array(array('2014-04-29', 2), '2014-05-02'),
      'spanning christmas' => array(array('2013-12-23', 2), '2013-12-27'),
      'spanning easter 2015' => array(array('2015-04-02', 2), '2015-04-08'),
      'spanning easter 2030' => array(array('2030-04-18', 2), '2030-04-24'),
      'spanning labour day connected to weekend' => array(array('2015-04-29', 2), '2015-05-04'),
      'spanning labour day and then weekend' => array(array('2014-04-30', 2), '2014-05-05'),
      'starting on labour day and then spanning weekend' => array(array('2014-05-01', 2), '2014-05-06'),
      'spanning christmas overlapping with weekend' => array(array('2015-12-23', 2), '2015-12-28'),
      'datetime input' => array(array('2015-04-21 04:14:00', 1), '2015-04-22'),
      'input with timezone' => array(array('2015-04-21 04:14:00 CEST', 1), '2015-04-22'),
      'timezone causing day advance' => array(array('2015-04-21 23:00:00 -02:00', 1), '2015-04-22'),
      'timezone causing day delay' => array(array('2015-04-21 01:00:00 CEST', 1), '2015-04-22'),
      'invalid date' => array(array('invalid', 1), new CRM_Exception('Failed parsing date')),
      'invalid date format (no separators)' => array(array('20150421', 1), new CRM_Exception('Failed parsing date')),
      'empty date' => array(array('', 1), new CRM_Exception('Failed parsing date')),
    );
  }
}
