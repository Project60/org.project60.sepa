<?php

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
