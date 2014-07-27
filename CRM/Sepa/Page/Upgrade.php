<?php
require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_Upgrade extends CRM_Core_Page {
  function run() {
    $result = civicrm_api3('PaymentProcessorType', 'get', array(
      'name' => 'sepa_dd',
      'payment_type' => array('!=' => 1),
      'api.PaymentProcessorType.create' => array(
        /* 'id' inherited. */
        'payment_type' => 1,
      ),
      'api.PaymentProcessor.get' => array(
        /* 'payment_processor_type_id' inherited */
        'api.PaymentProcessor.create' => array(
          /* 'id' inherited. */
          'payment_processor_type_id' => '$value.payment_processor_type_id',
          'payment_type' => 1,
        ),
      ),
    ));

    if ($result['count']) {
      echo "<pre>Upgraded: \n" . print_r($result, true) . "</pre>";
    } else {
      echo "Nothing to upgrade.";
    }
  }
}
