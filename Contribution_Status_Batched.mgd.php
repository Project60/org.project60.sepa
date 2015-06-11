<?php

$optionGroupID = civicrm_api3('OptionGroup', 'getvalue', array('name' => 'contribution_status', 'return' => 'id'));

return array (
  array (
    'name' => 'Batched Contribution Status',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'params' => array (
      'version' => 3,
      'option_group_id' => $optionGroupID,
      'name' => 'Batched',
      'label' => ts('Pending/Batched'),
      'is_default' => 0,
      'is_reserved' => 1,
    )
  ),
);
