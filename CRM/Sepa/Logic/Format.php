<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2016-2018                                |
| Author: @scardinius                                    |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

abstract class CRM_Sepa_Logic_Format {

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
    $directory = dirname(__FILE__)."{$s}..{$s}..{$s}..{$s}templates{$s}Sepa{$s}Formats{$s}".$fileFormat;
    $file = $directory."{$s}Format.php";
    if (file_exists($directory)) {
      if (file_exists($file)) {
        require_once $file;
      } else {
        throw new Exception(ts('File with class format does not exist.'));
      }
    } else {
      throw new Exception(ts('Directory for file format does not exist.'));
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
   * Apply string encoding
   *
   * @param string $content
   *
   * @return mixed
   */
  public function characterEncode($content) {
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

  /**
   * gives the option of setting extra variables to the template
   */
  public function assignSettings($template) {
    // nothing to do here
  }
}
