<?php

return array (
  0 => 
  array (
    'name' => 'CRM_Sepa_Direct_Debit',
    'entity' => 'PaymentProcessorType',
    'params' => 
    array (
      'version' => 3,
      'description' => 'SEPA Direct Debit (org.project60.sepa)',
    'name' => 'sepa_dd',
    'title' => 'SEPA Direct Debit',
    "is_active"=> 1,
    "user_name_label"=>"SEPA Creditor identifier",
    "class_name"=>"Payment_SEPA_DD",
    "url_site_default"=>"",
    "url_recur_default"=>"",
    "url_site_test_default"=>"",
    "url_recur_test_default"=>"",
    "billing_mode"=>"1", //form, seems to be the easiest
    "is_recur"=>"1",
    "payment_type"=>"9000" // SDD
    ),
  ),
);
