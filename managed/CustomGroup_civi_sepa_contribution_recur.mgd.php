<?php
use CRM_Sepa_ExtensionUtil as E;

return [
  [
    'name' => 'CustomGroup_civi_sepa_contribution_recur',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'civi_sepa_contribution_recur',
        'title' => E::ts('civi_sepa_contribution_recur'),
        'extends' => 'ContributionRecur',
        'weight' => 4,
        'collapse_adv_display' => TRUE,
        'created_date' => '2026-07-02 15:46:46',
        'is_public' => FALSE,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_civi_sepa_contribution_recur_CustomField_is_on_hold',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'civi_sepa_contribution_recur',
        'name' => 'is_on_hold',
        'label' => E::ts('is_on_hold'),
        'data_type' => 'Boolean',
        'html_type' => 'Toggle',
        'default_value' => '0',
        'is_searchable' => TRUE,
        'is_view' => TRUE,
        'text_length' => 255,
        'note_columns' => 60,
        'note_rows' => 4,
        'column_name' => 'is_on_hold_46',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];
