<?php

abstract class CRM_Sepa_Logic_Format {

  public function getDDFilePrefix() {
    return 'SDDXML-';
  }

  public function getFilename($variable_string) {
    return $variable_string.'.xml';
  }

}
