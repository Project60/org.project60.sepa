<?php

declare(strict_types = 1);

/**
 * -------------------------------------------------------+
 * | Project 60 - SEPA direct debit                         |
 * | Copyright (C) 2016-2018                                |
 * | Author: @scardinius                                    |
 * +--------------------------------------------------------+
 * | This program is released as free software under the    |
 * | Affero GPL license. You can redistribute it and/or     |
 * | modify it under the terms of this license which you    |
 * | can read by viewing the included agpl.txt or online    |
 * | at www.gnu.org/licenses/agpl.html. Removal of this     |
 * | copyright header is strictly prohibited without        |
 * | written permission from the original author(s).        |
 * +--------------------------------------------------------
 */
class CRM_Sepa_Logic_Format_citibankpl extends CRM_Sepa_Logic_Format {

  /**
   * Apply string encoding
   *
   * @param string $content
   */
  public function characterEncode(string $content): string {
    /** @var string */
    return iconv('UTF-8', 'WINDOWS-1250', $content);
  }

  public function improveContent(string $content): string {
    /** @var string */
    return preg_replace('~(*BSR_ANYCRLF)\R~', "\r\n", $content);
  }

  public function getDDFilePrefix(): string {
    return 'CITIBANK-';
  }

  public function getFilename(string $variable_string): string {
    return $variable_string . '.txt';
  }

}
