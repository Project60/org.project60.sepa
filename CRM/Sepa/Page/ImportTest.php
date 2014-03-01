<?php
require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_ImportTest extends CRM_Core_Page {
  function run() {
    $result = civicrm_api3('SepaImport', 'create', array(
      'contact_id' => '71942',
      #'reference' => '',
      'iban' => 'DE83510900000002359405',
      #'bic' => '',
      'status' => 'FRST',
      'create_date' => '2014-03-03',
      #'date' => '',
      #'validation_date' => '',
      'start_date' => '2005-01-01',
      #'end_date' => '',
      #'installments' => '',
      'frequency_unit' => 'month',
      'frequency_interval' => '1',
      #'cycle_day' => '',
      'amount' => '19',
      #'amount_level' => '',
      'payment_processor_id' => '7',
      'financial_type_id' => '1',
      #'is_email_receipt' => '',
      #'is_test' => '',
      #'source' => '',
      #'contribution_page_id' => '',
      #'campaign_id' => '',
      #'honor_contact_id' => '',
      #'honor_type_id' => '',
      #'address_id' => '',
    ));
    die('<pre>'.print_r($result, true));
  }
}
