<?php

class CRM_Sepa_Logic_Format_citibankpl extends CRM_Sepa_Logic_Format {

  /** It's possible to increase or decrease current number for packages of mandates file. */
  const PACKAGE_NUMBER_MODIFIER = 0;

  /** Client shortcut given by creditor */
  const CLIENT_SHORTCUT = 'CLNT';

  public function getDDFilePrefix() {
    return 'CITIBANK-';
  }

  public function getFilename($variable_string) {
    return $variable_string.'.txt';
  }

  public function getLastPackageNumber() {
    return parent::getLastPackageNumber() + self::PACKAGE_NUMBER_MODIFIER;
  }

  public function getNewPackageFilename() {
    return ($this->getLastPackageNumber()+1).self::CLIENT_SHORTCUT.'.txt';
  }

}
