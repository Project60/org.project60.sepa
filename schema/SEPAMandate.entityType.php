<?php
use CRM_Sepa_ExtensionUtil as E;

return [
  'name' => 'SepaMandate',
  'table' => 'civicrm_sdd_mandate',
  'class' => 'CRM_Sepa_DAO_SEPAMandate',
  'getInfo' => fn() => [
    'title' => E::ts('SEPAMandate'),
    'title_plural' => E::ts('SEPAMandates'),
    'description' => E::ts('FIXME'),
    'log' => TRUE,
  ],
  'getIndices' => fn() => [
    'reference' => [
      'fields' => [
        'reference' => TRUE,
      ],
      'add' => '4.3',
    ],
    'index_entity' => [
      'fields' => [
        'entity_table' => TRUE,
        'entity_id' => TRUE,
      ],
      'add' => '4.3',
    ],
    'iban' => [
      'fields' => [
        'iban' => TRUE,
      ],
      'add' => '4.3',
    ],
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('ID'),
      'add' => '4.3',
      'usage' => ['export'],
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'reference' => [
      'title' => E::ts('Reference'),
      'sql_type' => 'varchar(35)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('A unique mandate reference'),
      'add' => '4.3',
      'usage' => ['export'],
    ],
    'source' => [
      'title' => E::ts('Source'),
      'sql_type' => 'varchar(64)',
      'input_type' => 'Text',
      'description' => E::ts('Information about the source of registration of the mandate'),
      'add' => '4.3',
    ],
    'entity_table' => [
      'title' => E::ts('Entity Table'),
      'sql_type' => 'varchar(64)',
      'required' => TRUE,
      'input_type' => 'Select',
      'description' => E::ts('Physical tablename for the contract entity being joined, eg contributionRecur or Membership'),
      'add' => '3.2',
      'pseudoconstant' => [
        'callback' => 'CRM_Sepa_BAO_SEPAMandate::entityTableOptions',
      ],
    ],
    'entity_id' => [
      'title' => E::ts('Entity ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'required' => TRUE,
      'description' => E::ts('FK to contract entity table specified in entity_table column.'),
      'add' => '4.3',
      'entity_reference' => [
        'dynamic_entity' => 'entity_table',
        'key' => 'id',
      ],
    ],
    'date' => [
      'title' => E::ts('Mandate signature date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'required' => TRUE,
      'description' => E::ts('by default now()'),
      'add' => '4.3',
      'input_attrs' => [
        'format_type' => 'activityDateTime',
      ],
    ],
    'creditor_id' => [
      'title' => E::ts('Creditor ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to ssd_creditor'),
      'input_attrs' => [
        'label' => E::ts('Creator'),
      ],
      'entity_reference' => [
        'entity' => 'SepaCreditor',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
    'contact_id' => [
      'title' => E::ts('Contact ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to Contact ID of the debtor'),
      'input_attrs' => [
        'label' => E::ts('Contact'),
      ],
      'entity_reference' => [
        'entity' => 'Contact',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
    'account_holder' => [
      'title' => E::ts('Account Holder'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'description' => E::ts('Name of the account holder'),
      'add' => '4.3',
    ],
    'iban' => [
      'title' => E::ts('Iban'),
      'sql_type' => 'varchar(42)',
      'input_type' => 'Text',
      'description' => E::ts('Iban of the debtor'),
      'add' => '4.3',
    ],
    'bic' => [
      'title' => E::ts('Bic'),
      'sql_type' => 'varchar(11)',
      'input_type' => 'Text',
      'description' => E::ts('BIC of the debtor'),
      'add' => '4.3',
    ],
    'type' => [
      'title' => E::ts('Type'),
      'sql_type' => 'varchar(4)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('RCUR for recurrent (default), OOFF for one-shot'),
      'default' => 'RCUR',
    ],
    'status' => [
      'title' => E::ts('Status'),
      'sql_type' => 'varchar(8)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('Status of the mandate (INIT, OOFF, FRST, RCUR, SENT, INVALID, COMPLETE, ONHOLD)'),
      'add' => '4.3',
      'default' => 'INIT',
    ],
    'creation_date' => [
      'title' => E::ts('creation date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'add' => '4.3',
      'usage' => ['export'],
      'input_attrs' => [
        'label' => E::ts('Created Date'),
      ],
    ],
    'first_contribution_id' => [
      'title' => E::ts('First Contribution (to be deprecated)'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to civicrm_contribution'),
      'entity_reference' => [
        'entity' => 'Contribution',
        'key' => 'id',
      ],
    ],
    'validation_date' => [
      'title' => E::ts('validation date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'add' => '4.3',
    ],
  ],
];
