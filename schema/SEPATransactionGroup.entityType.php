<?php
use CRM_Sepa_ExtensionUtil as E;

return [
  'name' => 'SepaTransactionGroup',
  'table' => 'civicrm_sdd_txgroup',
  'class' => 'CRM_Sepa_DAO_SEPATransactionGroup',
  'getInfo' => fn() => [
    'title' => E::ts('SEPATransaction Group'),
    'title_plural' => E::ts('SEPATransaction Groups'),
    'description' => E::ts('FIXME'),
    'log' => TRUE,
  ],
  'getIndices' => fn() => [
    'UI_reference' => [
      'fields' => [
        'reference' => TRUE,
      ],
      'unique' => TRUE,
    ],
    'creditor_id' => [
      'fields' => [
        'sdd_creditor_id' => TRUE,
      ],
      'add' => '4.3',
    ],
    'file_id' => [
      'fields' => [
        'sdd_file_id' => TRUE,
      ],
    ],
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('ID'),
      'usage' => ['export'],
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'reference' => [
      'title' => E::ts('Reference'),
      'sql_type' => 'varchar(64)',
      'input_type' => 'Text',
      'description' => E::ts('End-to-end reference for this tx group.'),
    ],
    'type' => [
      'title' => E::ts('Type'),
      'sql_type' => 'char(4)',
      'input_type' => 'Text',
      'description' => E::ts('FRST, RCUR or OOFF'),
    ],
    'collection_date' => [
      'title' => E::ts('Collection Date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'description' => E::ts('Target collection date'),
    ],
    'financial_type_id' => [
      'title' => E::ts('Financial Type ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'description' => E::ts('Financial type of contained contributions if CiviSEPA is generating groups matching financial types.'),
    ],
    'latest_submission_date' => [
      'title' => E::ts('Latest Submission Date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'description' => E::ts('Latest submission date'),
    ],
    'created_date' => [
      'title' => E::ts('Created Date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'description' => E::ts('When was this item created'),
    ],
    'status_id' => [
      'title' => E::ts('Status ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('fk sepa group Status options in civicrm_option_values'),
    ],
    'sdd_creditor_id' => [
      'title' => E::ts('Sdd Creditor ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('fk to SDD Creditor Id'),
      'entity_reference' => [
        'entity' => 'SepaCreditor',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
    'sdd_file_id' => [
      'title' => E::ts('Sdd File ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('fk to SDD File Id'),
      'entity_reference' => [
        'entity' => 'SepaSddFile',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
  ],
];
