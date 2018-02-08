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

class CRM_Sepa_Logic_Format_bphpl extends CRM_Sepa_Logic_Format {

  public static $settings = array(
    'nip' => '7251872505',
    'zleceniodawca_nazwa' => 'Instytut Spraw Obywatelskich',
    'zleceniodawca_adres1' => 'Pomorska 40',
    'zleceniodawca_adres2' => '91-408 Łódź',
  );

  public function getDDFilePrefix() {
    return 'BPH-';
  }

  public function getFilename($variable_string) {
    return $variable_string.'.pld';
  }
}
