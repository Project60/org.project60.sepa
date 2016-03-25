<?php

abstract class CRM_Sepa_Logic_Format {

  /** @var string Charset used in output files. */
  public static $out_charset = 'UTF-8';


  /** @var array Settings per format */
  public static $settings = array();


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
        require_once $file;
      } else {
        throw new Exception(ts('File with class format does not exist.', array('domain' => 'org.project60.sepa')));
      }
    } else {
      throw new Exception(ts('Directory for file format does not exist.', array('domain' => 'org.project60.sepa')));
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
   * Improve content of file.
   *
   * @param string $content
   *
   * @return mixed
   */
  public function improveContent($content) {
    return $content;
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
}
