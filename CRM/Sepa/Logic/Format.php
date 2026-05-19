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

use CRM_Sepa_ExtensionUtil as E;

class CRM_Sepa_Logic_Format {

  /**
   * @var array Settings per format */
  public static array $settings = [];

  protected string $fileFormatName;

  /**
   * get the file format for the given creditor ID
   */
  public static function getFormatForCreditor(int $creditor_id): static {
    try {
      /** @var array{sepa_file_format_id: int|numeric-string} $creditor */
      $creditor = civicrm_api3('SepaCreditor', 'getsingle', [
        'id'     => $creditor_id,
        'return' => 'sepa_file_format_id',
      ]);
    }
    catch (Exception $e) {
      throw new RuntimeException(E::ts('Creditor [%1] is missing!', [1 => $creditor_id]), $e->getCode(), $e);
    }

    try {
      /** @var array{id: int|numeric-string, value: string, name: string, label: string} $file_format */
      $file_format = civicrm_api3('OptionValue', 'getsingle', [
        'option_group_id' => 'sepa_file_format',
        'value' => $creditor['sepa_file_format_id'],
      ]);
    }
    catch (Exception $e) {
      throw new RuntimeException(
        E::ts('File format [%1] is missing!', [1 => $creditor['sepa_file_format_id']]),
        $e->getCode(),
        $e
      );
    }

    return self::loadFormatClass($file_format['name']);
  }

  /**
   * Load class based on format name.
   *
   * @throws Exception
   */
  public static function loadFormatClass(string $fileFormatName): static {
    $s              = DIRECTORY_SEPARATOR;
    $fileFormatName = self::sanitizeFileFormat($fileFormatName);

    $file = stream_resolve_include_path("templates{$s}Sepa{$s}Formats{$s}{$fileFormatName}{$s}Format.php");
    if ($file && file_exists($file)) {
      require_once $file;
    }
    else {
      throw new Exception("Class format file '{$file}' not found.");
    }

    /** @var class-string<\CRM_Sepa_Logic_Format> $format_class */
    $format_class = 'CRM_Sepa_Logic_Format_' . $fileFormatName;
    return new $format_class($fileFormatName);
  }

  /**
   * Lets the format add extra information to each individual
   *  transaction (contribution + extra data)
   *
   * @param array<string, mixed> $txn
   */
  public function extendTransaction(array &$txn, int $creditor_id) {
    // nothing to do here, but overwritten by some formats
  }

  /**
   * Constructor
   */
  protected function __construct(string $fileFormatName) {
    $this->fileFormatName = $fileFormatName;
  }

  /**
   * get file format string
   */
  public function getFileFormatName(): string {
    return $this->fileFormatName;
  }

  /**
   * get the header TPL file
   *
   * @return string
   */
  public function getHeaderTpl() {
    return "Sepa/Formats/{$this->fileFormatName}/transaction-header.tpl";
  }

  /**
   * get the header TPL file
   *
   * @return string
   */
  public function getDetailsTpl() {
    return "Sepa/Formats/{$this->fileFormatName}/transaction-details.tpl";
  }

  /**
   * get the header TPL file
   *
   * @return string
   */
  public function getFooterTpl() {
    return "Sepa/Formats/{$this->fileFormatName}/transaction-footer.tpl";
  }

  /**
   * Sanitize file format name which is used in directory name.
   */
  public static function sanitizeFileFormat(string $fileFormat): string {
    return preg_replace(['/[^a-zA-Z0-9]+/'], '_', $fileFormat) ?? '';
  }

  /**
   * Improve content of file.
   *
   * @return string
   */
  public function improveContent(string $content) {
    return $content;
  }

  /**
   * Apply string encoding
   *
   * @return string
   */
  public function characterEncode(string $content) {
    return $content;
  }

  /**
   * Get the propsed file reference. The final
   * reference might have an '--1' like extension
   * if there is conflicts.
   *
   * @param array{reference: string} $txgroup
   *
   * @return string
   */
  public function getFileReference(array $txgroup) {
    return $this->getDDFilePrefix() . $txgroup['reference'];
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
   * @return string
   */
  public function getFilename(string $file_reference) {
    return $file_reference . '.xml';
  }

  /**
   * gives the option of setting extra variables to the template
   *
   * @return void
   */
  public function assignExtraVariables(\CRM_Core_Smarty $template) {
    $template->assign('fileFormat', $this->fileFormatName);
    // nothing to do here
  }

}
