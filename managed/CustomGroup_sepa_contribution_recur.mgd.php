<?php
use CRM_Sepa_ExtensionUtil as E;

return [
  [
    'name' => 'CustomGroup_sepa_contribution_recur',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'sepa_contribution_recur',
        'table_name' => 'civicrm_value_sepa_contribution_recur',
        'title' => E::ts('CiviSEPA'),
        'extends' => 'ContributionRecur',
        'weight' => 4,
        'collapse_adv_display' => TRUE,
        'is_public' => FALSE,
        'is_reserved' => TRUE,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_sepa_contribution_recur_CustomField_is_on_hold',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'sepa_contribution_recur',
        'name' => 'is_on_hold',
        'label' => E::ts('Is On Hold'),
        'data_type' => 'Boolean',
        'html_type' => 'Toggle',
        'default_value' => '0',
        'is_searchable' => TRUE,
        'is_view' => TRUE,
        'text_length' => 255,
        'note_columns' => 60,
        'note_rows' => 4,
        'column_name' => 'is_on_hold',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];
