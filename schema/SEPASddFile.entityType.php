<?php
use CRM_Sepa_ExtensionUtil as E;

return [
  'name' => 'SepaSddFile',
  'table' => 'civicrm_sdd_file',
  'class' => 'CRM_Sepa_DAO_SEPASddFile',
  'getInfo' => fn() => [
    'title' => E::ts('SEPASdd File'),
    'title_plural' => E::ts('SEPASdd Files'),
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
    'UI_filename' => [
      'fields' => [
        'filename' => TRUE,
      ],
      'unique' => TRUE,
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
      'description' => E::ts('End-to-end reference for this sdd file.'),
    ],
    'filename' => [
      'title' => E::ts('Filename'),
      'sql_type' => 'char(64)',
      'input_type' => 'Text',
      'description' => E::ts('Name of the generated file'),
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
    'created_id' => [
      'title' => E::ts('Created ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to Contact ID of creator'),
      'entity_reference' => [
        'entity' => 'Contact',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
    'status_id' => [
      'title' => E::ts('Status ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('fk to Batch Status options in civicrm_option_values'),
    ],
    'comments' => [
      'title' => E::ts('Comments'),
      'sql_type' => 'text',
      'input_type' => 'TextArea',
      'description' => E::ts('Comments about processing of this file'),
    ],
    'tag' => [
      'title' => E::ts('Tag'),
      'sql_type' => 'varchar(64)',
      'input_type' => 'Text',
      'description' => E::ts('Tag used to group multiple creditors in this XML file.'),
    ],
  ],
];
