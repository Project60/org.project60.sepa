<?php
use CRM_Sepa_ExtensionUtil as E;

return [
  [
    'name' => 'CustomGroup_civi_sepa_contribution',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'civi_sepa_contribution',
        'title' => E::ts('civi_sepa_contribution'),
        'extends' => 'Contribution',
        'weight' => 3,
        'collapse_adv_display' => TRUE,
        'created_date' => '2026-07-02 15:45:37',
        'is_public' => FALSE,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_civi_sepa_contribution_CustomField_is_on_hold',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'civi_sepa_contribution',
        'name' => 'is_on_hold',
        'label' => E::ts('is_on_hold'),
        'data_type' => 'Boolean',
        'html_type' => 'Toggle',
        'default_value' => '0',
        'is_searchable' => TRUE,
        'text_length' => 255,
        'note_columns' => 60,
        'note_rows' => 4,
        'column_name' => 'is_on_hold_45',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];
