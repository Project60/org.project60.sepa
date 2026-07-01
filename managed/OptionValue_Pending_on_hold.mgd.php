<?php
use CRM_Sepa_ExtensionUtil as E;

return [
  [
    'name' => 'OptionValue_Pending_on_hold',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'contribution_status',
        'label' => E::ts('Pending on hold'),
        'name' => 'Pending on hold',
        'is_reserved' => TRUE,
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionValue_Pending_on_hold_recur',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'contribution_recur_status',
        'label' => E::ts('Pending on hold'),
        'name' => 'Pending on hold',
        'is_reserved' => TRUE,
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
];
