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
 * Report on OOFF SEPA mandates
 */
class CRM_Sepa_Form_Report_SepaMandateRCUR extends CRM_Sepa_Form_Report_SepaMandateGeneric {

  /**
   * internal function to init the configuration array (_columns)
   */
  protected function _initColumns() {
    parent::_initColumns();

    // remove filter for type (always OOFF)
    unset($this->_columns['civicrm_sdd_mandate']['fields']['mandate_type']);
    unset($this->_columns['civicrm_sdd_mandate']['filters']['mandate_type']);

    // remove contribution status ID (will be added as separate column)
    unset($this->_columns['civicrm_sdd_mandate']['fields']['status_id']);
    unset($this->_columns['civicrm_sdd_mandate']['filters']['status_id']);

    // remove contribution amount (will be added as separate column)
    unset($this->_columns['civicrm_sdd_mandate']['fields']['amount']);
    unset($this->_columns['civicrm_sdd_mandate']['filters']['amount']);


    $this->_columns['civicrm_contribution_recur'] = array(
      'dao' => 'CRM_Contribute_BAO_ContributionRecur',
      'fields' => array(
        'financial_type_id' => array(
          'title' => ts('Financial Type'),
          'default' => TRUE,
        ),
        'campaign_id' => array(
          'title' => ts('Campaign'),
        ),
        'contribution_status_id' => array(
          'title' => ts('Status'),
        ),
        // 'cancel_reason' => array(
        //   'title' => ts('Cancel Reason'),
        // ),
        'currency' => array(
          'required' => TRUE,
          'no_display' => TRUE,
        ),
        'trxn_id' => NULL,
        'installment_amount' => array(
          'dbAlias' => 'amount',
          'title' => ts('Installment Amount'),
        ),
      ),
      'filters' => array(
        'financial_type_id' => array(
          'title' => ts('Financial Type'),
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => CRM_Contribute_PseudoConstant::financialType(),
          'type' => CRM_Utils_Type::T_INT,
        ),
        'campaign_id' => array(
          'title' => ts('Campaign'),
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => CRM_Campaign_BAO_Campaign::getCampaigns(),
          'type' => CRM_Utils_Type::T_INT,
        ),
        'contribution_status_id' => array(
          'title' => ts('Contribution Status'),
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => CRM_Contribute_PseudoConstant::contributionStatus(),
          'type' => CRM_Utils_Type::T_INT,
        ),
        // 'cancel_reason' => array(
        //   'name' => 'cancel_reason',
        //   'type' => CRM_Utils_Type::T_STRING,
        //   'operatorType' => CRM_Report_Form::OP_STRING,
        //   'title' => ts('Cancel Reason'),
        // ),
        'installment_amount' => array(
          'dbAlias' => 'amount',
          'title' => ts('Installment Amount'),
          'type'  => CRM_Utils_Type::T_FLOAT,
          'operatorType' => CRM_Report_Form::OP_FLOAT,
        ),
      ),
      'order_bys' => array(
        'financial_type_id' => array('title' => ts('Financial Type')),
        'contribution_status_id' => array('title' => ts('Contribution Status')),
      ),
      'grouping' => 'rcontri-fields',
    );
  
    // add aggregated contribution fields
    $this->_columns['civicrm_contribution'] = array(
      'dao' => 'CRM_Contribute_DAO_Contribution',
      'fields' => array(
        'total_amount_collected' => array(
          'title' => ts('Total Amount Collected'),
        ),
        'total_count_collected' => array(
          'title' => ts('Collected Contribution'),
        ),
        'total_count_failed' => array(
          'title' => ts('Failed/Cancelled Contributions'),
        ),
      ),
      'filters' => array(
        'total_amount_collected' => array(
          'dbAlias' => 'total_amount_collected',
          'title'   => ts('Total Amount Collected'),
          'type'    => CRM_Utils_Type::T_FLOAT,
          'operatorType' => CRM_Report_Form::OP_FLOAT,
        ),
        'total_count_collected' => array(
          'dbAlias' => 'total_count_collected',
          'title'   => ts('Collected Contribution'),
          'type'    => CRM_Utils_Type::T_INT,
          'operatorType' => CRM_Report_Form::OP_INT,
        ),
        'total_count_failed' => array(
          'dbAlias' => 'total_count_failed',
          'title'   => ts('Failed/Cancelled Contribution'),
          'type'    => CRM_Utils_Type::T_INT,
          'operatorType' => CRM_Report_Form::OP_INT,
        ),
      ),
    );
  }

  /**
   * Override select clauses for some fields
   */
  function _getSelectClause($fieldName, $field, $tableName) {
    // add amount from either OOFF or RCUR
    if ($fieldName == 'total_amount_collected') {
      $this->_columnHeaders['total_amount_collected']['title'] = $field['title'];
      $this->_columnHeaders['total_amount_collected']['type']  = CRM_Utils_Array::value('type', $field);
      return "SUM(IF({$this->_aliases['civicrm_contribution']}.contribution_status_id IN (1),{$this->_aliases['civicrm_contribution']}.total_amount, 0)) AS total_amount_collected";
    }

    if ($fieldName == 'total_count_collected') {
      $this->_columnHeaders['total_count_collected']['title'] = $field['title'];
      $this->_columnHeaders['total_count_collected']['type']  = CRM_Utils_Array::value('type', $field);
      return "SUM(IF({$this->_aliases['civicrm_contribution']}.contribution_status_id IN (1),1, 0)) AS total_count_collected";
    }

    if ($fieldName == 'total_count_failed') {
      $this->_columnHeaders['total_count_failed']['title'] = $field['title'];
      $this->_columnHeaders['total_count_failed']['type']  = CRM_Utils_Array::value('type', $field);
      return "SUM(IF({$this->_aliases['civicrm_contribution']}.contribution_status_id IN (3,4),1, 0)) AS total_count_failed";
    }

    return parent::_getSelectClause($fieldName, $field, $tableName);
  }

  /**
   * override FROM clause
   */
  function from() {
    $this->_from = NULL;
    $this->_from = "
         FROM  civicrm_sdd_mandate {$this->_aliases['civicrm_sdd_mandate']} {$this->_aclFrom}
               INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
                          ON {$this->_aliases['civicrm_contact']}.id =
                             {$this->_aliases['civicrm_sdd_mandate']}.contact_id
               LEFT JOIN civicrm_contribution_recur {$this->_aliases['civicrm_contribution_recur']}
                          ON 'civicrm_contribution_recur' = {$this->_aliases['civicrm_sdd_mandate']}.entity_table
                          AND {$this->_aliases['civicrm_contribution_recur']}.id = {$this->_aliases['civicrm_sdd_mandate']}.entity_id
               LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
                          ON {$this->_aliases['civicrm_contribution_recur']}.id = {$this->_aliases['civicrm_contribution']}.contribution_recur_id
         ";
  }

  /**
   * Group by mandate, so the installments get accumulated
   */
  public function groupBy() {
    $this->_groupBy = " GROUP BY {$this->_aliases['civicrm_sdd_mandate']}.id";
  }


  /**
   * internal function to generate where clauses
   */
  protected function _getWhereClause($fieldName, $field) {
    if ($fieldName == 'total_amount_collected') {
      $op = CRM_Utils_Array::value("{$fieldName}_op", $this->_params);
      if ($op) {
        $clause = $this->whereClause($field,
          $op,
          CRM_Utils_Array::value("{$fieldName}_value", $this->_params),
          CRM_Utils_Array::value("{$fieldName}_min", $this->_params),
          CRM_Utils_Array::value("{$fieldName}_max", $this->_params)
        );
      }
      $subquery = "(SELECT SUM(total_amount) FROM civicrm_contribution WHERE contribution_recur_id={$this->_aliases['civicrm_contribution_recur']}.id AND contribution_status_id = 1)";
      $clause = preg_replace('/total_amount_collected/', $subquery, $clause);
      return $clause;
    }

    if ($fieldName == 'total_count_collected') {
      $op = CRM_Utils_Array::value("{$fieldName}_op", $this->_params);
      if ($op) {
        $clause = $this->whereClause($field,
          $op,
          CRM_Utils_Array::value("{$fieldName}_value", $this->_params),
          CRM_Utils_Array::value("{$fieldName}_min", $this->_params),
          CRM_Utils_Array::value("{$fieldName}_max", $this->_params)
        );
      }
      $subquery = "(SELECT COUNT(DISTINCT(id)) FROM civicrm_contribution WHERE contribution_recur_id={$this->_aliases['civicrm_contribution_recur']}.id AND contribution_status_id = 1)";
      $clause = preg_replace('/total_count_collected/', $subquery, $clause);
      return $clause;
    }

    if ($fieldName == 'total_count_failed') {
      $op = CRM_Utils_Array::value("{$fieldName}_op", $this->_params);
      if ($op) {
        $clause = $this->whereClause($field,
          $op,
          CRM_Utils_Array::value("{$fieldName}_value", $this->_params),
          CRM_Utils_Array::value("{$fieldName}_min", $this->_params),
          CRM_Utils_Array::value("{$fieldName}_max", $this->_params)
        );
      }
      $subquery = "(SELECT COUNT(DISTINCT(id)) FROM civicrm_contribution WHERE contribution_recur_id={$this->_aliases['civicrm_contribution_recur']}.id AND contribution_status_id IN (3,4))";
      $clause = preg_replace('/total_count_failed/', $subquery, $clause);
      return $clause;
    }

    // nothing special? process with parent implementation
    return parent::_extendWhereClause($fieldName, $field);
  }

  /**
   * internal function to generate where clauses
   */
  protected function _extendWhereClause(&$clauses) {
    $clauses[] = "( {$this->_aliases['civicrm_sdd_mandate']}.type = 'RCUR' )";
  }

  /**
   * Prep data for display
   */
  function alterDisplay(&$rows) {
    // first, let the generic code work through the data
    parent::alterDisplay($rows);

    // now, prep contribution specific data
    $contributionTypes = CRM_Contribute_PseudoConstant::financialType();
    $contributionStatus = CRM_Contribute_PseudoConstant::contributionStatus();
    $contributionPages = CRM_Contribute_PseudoConstant::contributionPage();
    $campaigns = CRM_Campaign_BAO_Campaign::getCampaigns();

    foreach ($rows as $rowNum => $row) {
      if ($value = CRM_Utils_Array::value('civicrm_contribution_recur_financial_type_id', $row)) {
        $rows[$rowNum]['civicrm_contribution_recur_financial_type_id'] = $contributionTypes[$value];
      }
      if ($value = CRM_Utils_Array::value('civicrm_contribution_recur_contribution_status_id', $row)) {
        $rows[$rowNum]['civicrm_contribution_recur_contribution_status_id'] = $contributionStatus[$value];
      }
      if ($value = CRM_Utils_Array::value('civicrm_contribution_recur_contribution_page_id', $row)) {
        $rows[$rowNum]['civicrm_contribution_recur_contribution_page_id'] = $contributionPages[$value];
      }

      // alter amount
      if (array_key_exists('civicrm_contribution_recur_installment_amount', $row)) {
        $rows[$rowNum]['civicrm_contribution_recur_installment_amount'] = CRM_Utils_Money::format($row['civicrm_contribution_recur_installment_amount'], $row['civicrm_contribution_recur_currency']);
      }
      if (array_key_exists('total_amount_collected', $row)) {
        $rows[$rowNum]['total_amount_collected'] = CRM_Utils_Money::format($row['total_amount_collected'], $row['civicrm_contribution_recur_currency']);
      }

      // convert campaign_id to campaign title
      if (array_key_exists('civicrm_contribution_recur_campaign_id', $row)) {
        if ($value = $row['civicrm_contribution_recur_campaign_id']) {
          $rows[$rowNum]['civicrm_contribution_recur_campaign_id'] = CRM_Utils_Array::value($value, $campaigns, '');
        }
      }
    }
  }
}
