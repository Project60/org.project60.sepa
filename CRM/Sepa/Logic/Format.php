<?php

abstract class CRM_Sepa_Logic_Format {

  /** @var string Charset used in output files. */
  public static $out_charset = 'UTF-8';

  /** @var bool Determine whether format provides creating additional packages of mandates. */
  public static $create_package = false;

  /**
   * Load class based on format name.
   *
   * @param $fileFormat
   *
   * @throws Exception
   */
  public static function loadFormatClass($fileFormat) {
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
   * Sanitize file format name which is used in directory name.
   *
   * @param $fileFormat
   *
   * @return mixed
   */
  public static function sanitizeFileFormat($fileFormat) {
    $fileFormat = preg_replace(array('/[^a-zA-Z0-9]+/'), '_', $fileFormat);
    return $fileFormat;
  }


  /**
   * Method returns fileformat based on child class name.
   *
   * @param $class
   *
   * @return mixed
   */
  public static function getFileFormat($class) {
    return str_replace(__CLASS__.'_', '', $class);
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


  /**
   * Method returns content of file for package of mandates.
   *
   * @param $result
   *
   * @return string
   */
  public function getMandatePackage($result) {
    return "file content, change in child class";
  }

}
