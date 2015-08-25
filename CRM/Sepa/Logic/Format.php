<?php

abstract class CRM_Sepa_Logic_Format {

  static function loadFormatClass($fileFormat) {
    $s = DIRECTORY_SEPARATOR;
    $directory = dirname(__FILE__)."{$s}..{$s}..{$s}..{$s}formats{$s}".$fileFormat;
    $file = $directory."{$s}Format.php";
    if (file_exists($directory)) {
      if (file_exists($file)) {
        require $file;
      } else {
        throw new Exception('File with class format does not exist.');
      }
    } else {
      throw new Exception('Directory for file format does not exist.');
    }
  }

  /**
   * Method returns prefix for transactional file.
   *
   * @return string
   */
  public function getDDFilePrefix() {
    return 'SDDXML-';
  }

  /**
   * Method returns filename with extension for transactional file.
   *
   * @param $variable_string
   *
   * @return string
   */
  public function getFilename($variable_string) {
    return $variable_string.'.xml';
  }

  /**
   * Method returns last number for package of mandates.
   *
   * @return int
   */
  public function getLastPackageNumber() {
    $packageFile = new CRM_Sepa_BAO_SEPAMandateFile();
    return $packageFile->count();
  }

  /**
   * Method returns new filename for package of mandates.
   *
   * @return string
   */
  public function getNewPackageFilename() {
    return ($this->getLastPackageNumber()+1).'PACKAGE.txt';
  }

}
