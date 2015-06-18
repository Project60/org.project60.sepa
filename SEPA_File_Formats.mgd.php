<?php

/* Pre-allocate Option Group placeholder.
 *
 * This seems to be the only way to handle Option Groups + Option Values as "managed" entities,
 * as we need to know the Group ID before we can create the params for the Value entries.
 *
 * Note that the Group record created here is just a placeholder --
 * it will be replaced with the proper one when the actual managed entities are auto-created.
 *
 * If the Group already exists while this file is invoked
 * (which happend when the extension is re-enabled),
 * we just look up the ID of the existing Group here. */
$existingGroup = civicrm_api3('OptionGroup', 'get', array('name' => 'sepa_file_format'));
if ($existingGroup['count']) {
  $optionGroupID = $existingGroup['id'];
} else {
  $optionGroupID = civicrm_api3('OptionGroup', 'create', array('name' => '*** Placeholder for sepa_file_format ***', 'format.only_id' => 1));
}

return array (
  array (
    'name' => 'SEPA File Formats',
    'entity' => 'OptionGroup',
    'cleanup' => 'never',
    'params' => array (
      'version' => 3,
      'id' => $optionGroupID,
      'name' => 'sepa_file_format',
      'title' => ts('SEPA XML File Format Variants'),
      'is_reserved' => 1,
      'is_active' => 1,
      'is_locked' => 1, /* Don't allow meddling with the values through the UI. */
    )
  ),
  array (
    'name' => 'SEPA File Format pain.008.001.02',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'params' => array (
      'version' => 3,
      'option_group_id' => $optionGroupID,
      'name' => 'pain.008.001.02',
      'label' => ts('pain.008.001.02 (ISO 20022/official SEPA guidelines)'),
      'is_default' => 1,
      'is_reserved' => 1,
    )
  ),
  array(
    'name' => 'SEPA File Format pain.008.003.02',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'params' => array (
      'version' => 3,
      'option_group_id' => $optionGroupID,
      'name' => 'pain.008.003.02',
      'label' => ts('pain.008.003.02 (German Sonderwurst)'),
      'is_reserved' => 1,
    )
  ),
);
