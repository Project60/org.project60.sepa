<?php

$optionGroupID = civicrm_api3('OptionGroup', 'getvalue', array('name' => 'msg_tpl_workflow_contribution', 'return' => 'id'));

return array (
  array (
    'name' => 'Mandate Template (PDF variant)',
    'entity' => 'OptionValue',
    'cleanup' => 'never', /* 'Unused' doesn't currently work with `msg_template` users. */
    'params' => array (
      'version' => 3,
      'option_group_id' => $optionGroupID,
      'name' => 'sepa_mandate_pdf',
      'label' => 'PDF Mandate',
      'value' => 1, /* For consistency with previous installation method. (This is not actually used for the "Workflow" Option Value entries; and many existing Workflows have this set to '1', or other arbitrary, possibly overlapping values as well. */
      'is_default' => 0,
      'is_reserved' => 1,
    )
  ),
  array (
    'name' => 'Mandate Template (HTML variant)',
    'entity' => 'OptionValue',
    'cleanup' => 'never', /* 'Unused' doesn't currently work with `msg_template` users. */
    'params' => array (
      'version' => 3,
      'option_group_id' => $optionGroupID,
      'name' => 'sepa_mandate',
      'label' => 'Mail Sepa Mandate',
      'value' => 1, /* For consistency with previous installation method. (This is not actually used for the "Workflow" Option Value entries; and many existing Workflows have this set to '1', or other arbitrary, possibly overlapping values as well. */
      'is_default' => 0,
      'is_reserved' => 1,
    )
  ),
);
