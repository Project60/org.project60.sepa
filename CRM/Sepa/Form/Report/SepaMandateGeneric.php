<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2016 SYSTOPIA                       |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

/**
 * Generic report on SEPA mandates
 */
class CRM_Sepa_Form_Report_SepaMandateGeneric extends CRM_Report_Form {

  protected $_customGroupExtends = NULL;//array('Contact');
  protected $_customGroupGroupBy = FALSE; 

  function __construct() {
    $this->_columns = array(
      'civicrm_sdd_mandate' => array(
        'dao' => 'CRM_Sepa_DAO_SEPAMandate',
        'fields' => array(
          'reference' => array(
            'title' => ts('Mandate Reference'),
            'default' => TRUE,
            'no_repeat' => TRUE,
          ),
          'id' => array(
            'title' => ts('Mandate ID'),
          ),
          'mandate_type' => array(
            'name'  => 'type',
            'title' => ts('Type')
          ),
          'status' => array(
            'title' => ts('Status')
          ),
          'iban' => array(
            'title' => ts('IBAN')
          ),
          'bic' => array(
            'title' => ts('BIC')
          ),
          'source' => array(
            'title' => ts('Source')
          ),
          'date' => array(
            'title' => ts('Signature Date'),
            'default' => TRUE,
          ),
          'creation_date' => array(
            'title' => ts('Creation Date'),
            'default' => TRUE,
          ),
          'validation_date' => array(
            'title' => ts('Validation Date'),
            'default' => TRUE,
          ),
        ),
        'filters' => array(
          'reference' => array(
            'name' => 'reference',
            'type' => CRM_Utils_Type::T_STRING,
            'operatorType' => CRM_Report_Form::OP_STRING,
            'title' => ts('Mandate Reference'),
          ),
          'mandate_type' => array(
            'name' => 'type',
            'title' => ts('Type'),
            'type' => CRM_Utils_Type::T_STRING,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'options' => array(
              ''         => ts('Any', array('domain' => 'org.project60.sepa')),
              'OOFF'     => ts('One-off', array('domain' => 'org.project60.sepa')),
              'RCUR'     => ts('Recurring', array('domain' => 'org.project60.sepa')),
            ),
          ),

          // TODO: use combined status
          // 'status' => array(
          //   'name' => 'status',
          //   'title' => ts('Status'),
          //   'type' => CRM_Utils_Type::T_STRING,
          //   'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          //   'options' => array(
          //     'INIT'     => ts('Initialised', array('domain' => 'org.project60.sepa')),
          //     'OOFF'     => ts('One-off ready', array('domain' => 'org.project60.sepa')),
          //     'SENT'     => ts('One-off sent', array('domain' => 'org.project60.sepa')),
          //     'FRST'     => ts('Recurring first', array('domain' => 'org.project60.sepa')),
          //     'RCUR'     => ts('Recurring followup', array('domain' => 'org.project60.sepa')),
          //     'INVALID'  => ts('Invalid', array('domain' => 'org.project60.sepa')),
          //     'COMPLETE' => ts('Complete', array('domain' => 'org.project60.sepa')),
          //     'ONHOLD'   => ts('On Hold', array('domain' => 'org.project60.sepa')),
          //     'PARTIAL'  => ts('Partial', array('domain' => 'org.project60.sepa')),
          //   ),
          // ),
          'iban' => array(
            'name' => 'iban',
            'type' => CRM_Utils_Type::T_STRING,
            'operatorType' => CRM_Report_Form::OP_STRING,
            'title' => ts('IBAN'),
          ),
          'bic' => array(
            'name' => 'bic',
            'type' => CRM_Utils_Type::T_STRING,
            'operatorType' => CRM_Report_Form::OP_STRING,
            'title' => ts('BIC'),
          ),
          'source' => array(
            'name' => 'source',
            'type' => CRM_Utils_Type::T_STRING,
            'operatorType' => CRM_Report_Form::OP_STRING,
            'title' => ts('Source'),
          ),
          'date' => array(
            'title' => ts('Signature Date'),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
          ),
          'creation_date' => array(
            'title' => ts('Creation Date'),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
          ),
          'validation_date' => array(
            'title' => ts('Validation Date'),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
          ),
        ),
        'grouping' => 'mandate-fields',
      ),


      'civicrm_contact' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array(
          'sort_name' => array(
            'title' => ts('Contact Name'),
            // 'required' => TRUE,
            'default' => TRUE,
          ),
          'id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
        ),
        'filters' => array(
          'sort_name' => array(
            'title' => ts('Contact Name'),
            'operator' => 'like',
          ),
          'id' => array(
            'title' => ts('Contact ID'),
            'type' => CRM_Utils_Type::T_INT,
          ),
        ),
        'grouping' => 'contact-fields',
      ),

    );

    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    parent::__construct();
  }

  function select() {
    $select = $this->_columnHeaders = array();

    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if (CRM_Utils_Array::value('required', $field) ||
            CRM_Utils_Array::value($fieldName, $this->_params['fields'])
          ) {
            if ($tableName == 'civicrm_address') {
              $this->_addressField = TRUE;
            }
            elseif ($tableName == 'civicrm_email') {
              $this->_emailField = TRUE;
            }
            $select[] = "{$field['dbAlias']} as {$tableName}_{$fieldName}";
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = $field['title'];
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);
          }
        }
      }
    }

    $this->_select = "SELECT " . implode(', ', $select) . " ";
  }

  function from() {
    $this->_from = NULL;
    $this->_from = "
         FROM  civicrm_sdd_mandate {$this->_aliases['civicrm_sdd_mandate']} {$this->_aclFrom}
               INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
                          ON {$this->_aliases['civicrm_contact']}.id =
                             {$this->_aliases['civicrm_sdd_mandate']}.contact_id";
  }

  function where() {
    $clauses = array();
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('filters', $table)) {
        foreach ($table['filters'] as $fieldName => $field) {
          $clause = NULL;
          if (CRM_Utils_Array::value('operatorType', $field) & CRM_Utils_Type::T_DATE) {
            $relative = CRM_Utils_Array::value("{$fieldName}_relative", $this->_params);
            $from     = CRM_Utils_Array::value("{$fieldName}_from", $this->_params);
            $to       = CRM_Utils_Array::value("{$fieldName}_to", $this->_params);

            $clause = $this->dateClause($field['name'], $relative, $from, $to, $field['type']);
          }
          else {
            $op = CRM_Utils_Array::value("{$fieldName}_op", $this->_params);
            if ($op) {
              $clause = $this->whereClause($field,
                $op,
                CRM_Utils_Array::value("{$fieldName}_value", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_min", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_max", $this->_params)
              );
            }
          }

          if (!empty($clause)) {
            $clauses[] = $clause;
          }
        }
      }
    }

    if (empty($clauses)) {
      $this->_where = "WHERE ( 1 ) ";
    }
    else {
      $this->_where = "WHERE " . implode(' AND ', $clauses);
    }

    if ($this->_aclWhere) {
      $this->_where .= " AND {$this->_aclWhere} ";
    }
  }

  // function groupBy() {
  //   $this->_groupBy = '';// " GROUP BY {$this->_aliases['civicrm_contact']}.id, {$this->_aliases['civicrm_membership']}.membership_type_id";
  // }

  // function orderBy() {
  //   $this->_orderBy = '';// " ORDER BY {$this->_aliases['civicrm_contact']}.sort_name, {$this->_aliases['civicrm_contact']}.id, {$this->_aliases['civicrm_membership']}.membership_type_id";
  // }

  function postProcess() {

    $this->beginPostProcess();

    // get the acl clauses built before we assemble the query
    $sql = $this->buildQuery(TRUE);

    $rows = array();
    $this->buildRows($sql, $rows);

    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

  function alterDisplay(&$rows) {
    // custom code to alter rows
    $entryFound = FALSE;
    $checkList = array();
    foreach ($rows as $rowNum => $row) {

      if (!empty($this->_noRepeats) && $this->_outputMode != 'csv') {
        // not repeat contact display names if it matches with the one
        // in previous row
        $repeatFound = FALSE;
        foreach ($row as $colName => $colVal) {
          if (CRM_Utils_Array::value($colName, $checkList) &&
            is_array($checkList[$colName]) &&
            in_array($colVal, $checkList[$colName])
          ) {
            $rows[$rowNum][$colName] = "";
            $repeatFound = TRUE;
          }
          if (in_array($colName, $this->_noRepeats)) {
            $checkList[$colName][] = $colVal;
          }
        }
      }

      if (array_key_exists('civicrm_address_state_province_id', $row)) {
        if ($value = $row['civicrm_address_state_province_id']) {
          $rows[$rowNum]['civicrm_address_state_province_id'] = CRM_Core_PseudoConstant::stateProvince($value, FALSE);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_address_country_id', $row)) {
        if ($value = $row['civicrm_address_country_id']) {
          $rows[$rowNum]['civicrm_address_country_id'] = CRM_Core_PseudoConstant::country($value, FALSE);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_contact_sort_name', $row) &&
        $rows[$rowNum]['civicrm_contact_sort_name'] &&
        array_key_exists('civicrm_contact_id', $row)
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view",
          'reset=1&cid=' . $row['civicrm_contact_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contact_sort_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_sort_name_hover'] = ts("View Contact Summary for this Contact.");
        $entryFound = TRUE;
      }

      if (!$entryFound) {
        break;
      }
    }
  }
}
