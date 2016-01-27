<?php

class CRM_Sepa_Logic_Format_citibankpl extends CRM_Sepa_Logic_Format {

  public static $out_charset = 'WINDOWS-1250';

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
