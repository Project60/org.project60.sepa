<?php

class CRM_Sepa_Logic_Format_citibankpl extends CRM_Sepa_Logic_Format {

  public function getDDFilePrefix() {
    return 'CITIBANK-';
  }

  public function getFilename($variable_string) {
    return $variable_string.'.txt';
  }

}
