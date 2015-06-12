<?php

$optionGroupID = civicrm_api3('OptionGroup', 'getvalue', array('name' => 'payment_instrument', 'return' => 'id'));

return array (
  array (
    'name' => 'SEPA Payment Instrument FRST',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'params' => array (
      'version' => 3,
      'option_group_id' => $optionGroupID,
      'name' => 'FRST',
      'label' => 'SEPA DD First Transaction',
      'is_default' => 0,
      'is_reserved' => 1,
    )
  ),
  array (
    'name' => 'SEPA Payment Instrument RCUR',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'params' => array (
      'version' => 3,
      'option_group_id' => $optionGroupID,
      'name' => 'RCUR',
      'label' => 'SEPA DD Recurring Transaction',
      'is_default' => 0,
      'is_reserved' => 1,
    )
  ),
  array (
    'name' => 'SEPA Payment Instrument OOFF',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'params' => array (
      'version' => 3,
      'option_group_id' => $optionGroupID,
      'name' => 'OOFF',
      'label' => 'SEPA DD One-off Transaction',
      'is_default' => 0,
      'is_reserved' => 1,
    )
  ),
);
