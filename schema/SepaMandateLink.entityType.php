<?php
use CRM_Sepa_ExtensionUtil as E;

return [
  'name' => 'SepaMandateLink',
  'table' => 'civicrm_sdd_entity_mandate',
  'class' => 'CRM_Sepa_DAO_SepaMandateLink',
  'getInfo' => fn() => [
    'title' => E::ts('Sepa Mandate Link'),
    'title_plural' => E::ts('Sepa Mandate Links'),
    'log' => TRUE,
  ],
  'getIndices' => fn() => [
    'link' => [
      'fields' => [
        'entity_table' => TRUE,
        'entity_id' => TRUE,
      ],
    ],
    'class' => [
      'fields' => [
        'class' => TRUE,
      ],
    ],
    'is_active' => [
      'fields' => [
        'is_active' => TRUE,
      ],
    ],
    'start_date' => [
      'fields' => [
        'start_date' => TRUE,
      ],
    ],
    'end_date' => [
      'fields' => [
        'end_date' => TRUE,
      ],
    ],
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique SepaMandateLink ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'mandate_id' => [
      'title' => E::ts('SepaMandate ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'required' => TRUE,
      'description' => E::ts('FK to SepaMandate'),
      'entity_reference' => [
        'entity' => 'SepaMandate',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'entity_table' => [
      'title' => E::ts('Entity Table'),
      'sql_type' => 'varchar(64)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('Physical table name for entity being linked, eg civicrm_membership'),
    ],
    'entity_id' => [
      'title' => E::ts('Entity ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'required' => TRUE,
      'description' => E::ts('FK to entity table specified in entity_table column'),
      'entity_reference' => [
        'dynamic_entity' => 'entity_table',
        'key' => 'id',
      ],
    ],
    'class' => [
      'title' => E::ts('Link Class'),
      'sql_type' => 'varchar(100)',
      'input_type' => 'Text',
      'description' => E::ts('Link class, freely defined by client'),
    ],
    'is_active' => [
      'title' => E::ts('Is Active?'),
      'sql_type' => 'boolean',
      'input_type' => 'CheckBox',
      'required' => TRUE,
      'description' => E::ts('Is this link still active?'),
      'default' => TRUE,
    ],
    'creation_date' => [
      'title' => E::ts('Creation Date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'required' => TRUE,
      'description' => E::ts('Link creation date (default now())'),
      'input_attrs' => [
        'format_type' => 'activityDateTime',
      ],
    ],
    'start_date' => [
      'title' => E::ts('Start Date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'description' => E::ts('Start date of the link (optional)'),
      'input_attrs' => [
        'format_type' => 'activityDateTime',
      ],
    ],
    'end_date' => [
      'title' => E::ts('End Date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'description' => E::ts('End date of the link (optional)'),
      'input_attrs' => [
        'format_type' => 'activityDateTime',
      ],
    ],
  ],
];
