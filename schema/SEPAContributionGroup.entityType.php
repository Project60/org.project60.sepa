<?php
use CRM_Sepa_ExtensionUtil as E;

return [
  'name' => 'SepaContributionGroup',
  'table' => 'civicrm_sdd_contribution_txgroup',
  'class' => 'CRM_Sepa_DAO_SEPAContributionGroup',
  'getInfo' => fn() => [
    'title' => E::ts('SEPAContribution Group'),
    'title_plural' => E::ts('SEPAContribution Groups'),
    'description' => E::ts('Link Contributions to TX Group'),
  ],
  'getIndices' => fn() => [
    'contriblookup' => [
      'fields' => [
        'contribution_id' => TRUE,
      ],
    ],
    'txglookup' => [
      'fields' => [
        'txgroup_id' => TRUE,
      ],
    ],
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'description' => E::ts('primary key'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'contribution_id' => [
      'title' => E::ts('Contribution ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to Contribution ID'),
      'entity_reference' => [
        'entity' => 'Contribution',
        'key' => 'id',
      ],
    ],
    'txgroup_id' => [
      'title' => E::ts('Txgroup ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to civicrm_sdd_txgroup'),
      'entity_reference' => [
        'entity' => 'SepaTransactionGroup',
        'key' => 'id',
      ],
    ],
  ],
];
