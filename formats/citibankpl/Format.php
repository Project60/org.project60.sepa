<?php

class CRM_Sepa_Logic_Format_citibankpl extends CRM_Sepa_Logic_Format {

  public static $out_charset = 'WINDOWS-1250';

  /** @var string Only accepted (3) or active (5) mandates should be processed */
  public static $generatexml_sql_where = ' AND mandate.bank_status IN (3, 5)';

  public function improveContent($content) {
    return preg_replace('~(*BSR_ANYCRLF)\R~', "\r\n", $content);
  }

  public function getDDFilePrefix() {
    return 'CITIBANK-';
  }

  public function getFilename($variable_string) {
    return $variable_string.'.txt';
  }

}
