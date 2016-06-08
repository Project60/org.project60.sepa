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
class CRM_Sepa_Form_Report_SepaMandateOOFF extends CRM_Sepa_Form_Report_SepaMandateGeneric {

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

    $this->_columns['civicrm_contribution'] = array(
      'dao' => 'CRM_Contribute_DAO_Contribution',
      'fields' => array(
        'contribution_id' => array(
          'name' => 'id',
          'no_display' => TRUE,
          'required' => TRUE,
        ),
        'list_contri_id' => array(
          'name' => 'id',
          'title' => ts('Contribution ID', array('domain' => 'org.project60.sepa')),
        ),
        'financial_type_id' => array(
          'title' => ts('Financial Type', array('domain' => 'org.project60.sepa')),
          'default' => TRUE,
        ),
        'campaign_id' => array(
          'title' => ts('Campaign', array('domain' => 'org.project60.sepa')),
        ),
        'contribution_status_id' => array(
          'title' => ts('Contribution Status', array('domain' => 'org.project60.sepa')),
        ),
        'cancel_reason' => array(
          'title' => ts('Cancel Reason', array('domain' => 'org.project60.sepa')),
        ),
        'contribution_page_id' => array(
          'title' => ts('Contribution Page', array('domain' => 'org.project60.sepa')),
        ),
        'source' => array(
          'title' => ts('Source', array('domain' => 'org.project60.sepa')),
        ),
        'currency' => array(
          'title' => ts('Currency', array('domain' => 'org.project60.sepa')),
          'required' => TRUE,
          'no_display' => TRUE,
        ),
        'trxn_id' => NULL,
        'receive_date' => array(
          'title'   => ts('Contribution Collection Date', array('domain' => 'org.project60.sepa')),
          'default' => TRUE
          ),
        'receipt_date' => NULL,
        'total_amount' => array(
          'title' => ts('Amount', array('domain' => 'org.project60.sepa')),
          'type'    => CRM_Utils_Type::T_FLOAT,
        ),
        'fee_amount' => array(
          'title' => ts('Fee Amount', array('domain' => 'org.project60.sepa')),
          'type'    => CRM_Utils_Type::T_FLOAT,
        ),
        'net_amount' => array(
          'title' => ts('Net Amount', array('domain' => 'org.project60.sepa')),
          'type'    => CRM_Utils_Type::T_FLOAT,
        ),
      ),
      'filters' => array(
        'receive_date' => array(
          'title' => ts('Contribution Collection Date', array('domain' => 'org.project60.sepa')),
          'operatorType' => CRM_Report_Form::OP_DATE
        ),
        'financial_type_id' => array(
          'title' => ts('Financial Type', array('domain' => 'org.project60.sepa')),
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => CRM_Contribute_PseudoConstant::financialType(),
          'type' => CRM_Utils_Type::T_INT,
        ),
        'campaign_id' => array(
          'title' => ts('Campaign', array('domain' => 'org.project60.sepa')),
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => CRM_Campaign_BAO_Campaign::getCampaigns(),
          'type' => CRM_Utils_Type::T_INT,
        ),
        'contribution_page_id' => array(
          'title' => ts('Contribution Page', array('domain' => 'org.project60.sepa')),
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => CRM_Contribute_PseudoConstant::contributionPage(),
          'type' => CRM_Utils_Type::T_INT,
        ),
        'contribution_status_id' => array(
          'title' => ts('Contribution Status', array('domain' => 'org.project60.sepa')),
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => CRM_Contribute_PseudoConstant::contributionStatus(),
          'type' => CRM_Utils_Type::T_INT,
        ),
        'cancel_reason' => array(
          'name' => 'cancel_reason',
          'type' => CRM_Utils_Type::T_STRING,
          'operatorType' => CRM_Report_Form::OP_STRING,
          'title' => ts('Cancel Reason'),
        ),
        'total_amount' => array('title' => ts('Contribution Amount'), array('domain' => 'org.project60.sepa')),
      ),
      'order_bys' => array(
        'financial_type_id' => array('title' => ts('Financial Type', array('domain' => 'org.project60.sepa'))),
        'contribution_status_id' => array('title' => ts('Contribution Status', array('domain' => 'org.project60.sepa'))),
        'receive_date' => array('title' => ts('Receive Date', array('domain' => 'org.project60.sepa'))),
      ),
      'grouping' => 'contri-fields',
    );
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
               LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
                          ON 'civicrm_contribution' = {$this->_aliases['civicrm_sdd_mandate']}.entity_table
                          AND {$this->_aliases['civicrm_contribution']}.id = {$this->_aliases['civicrm_sdd_mandate']}.entity_id
         ";
  }


  /**
   * internal function to generate where clauses
   */
  protected function _extendWhereClause(&$clauses) {
    $clauses[] = "( {$this->_aliases['civicrm_sdd_mandate']}.type = 'OOFF' )";
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
      if ($value = CRM_Utils_Array::value('civicrm_contribution_financial_type_id', $row)) {
        $rows[$rowNum]['civicrm_contribution_financial_type_id'] = $contributionTypes[$value];
      }
      if ($value = CRM_Utils_Array::value('civicrm_contribution_contribution_status_id', $row)) {
        $rows[$rowNum]['civicrm_contribution_contribution_status_id'] = $contributionStatus[$value];
      }
      if ($value = CRM_Utils_Array::value('civicrm_contribution_contribution_page_id', $row)) {
        $rows[$rowNum]['civicrm_contribution_contribution_page_id'] = $contributionPages[$value];
      }

      // alter amount
      if (array_key_exists('civicrm_contribution_total_amount', $row)) {
        $rows[$rowNum]['civicrm_contribution_total_amount'] = CRM_Utils_Money::format($row['civicrm_contribution_total_amount'], $row['civicrm_contribution_currency']);
      }
      if (array_key_exists('civicrm_contribution_net_amount', $row)) {
        $rows[$rowNum]['civicrm_contribution_net_amount'] = CRM_Utils_Money::format($row['civicrm_contribution_net_amount'], $row['civicrm_contribution_currency']);
      }
      if (array_key_exists('civicrm_contribution_fee_amount', $row)) {
        $rows[$rowNum]['civicrm_contribution_fee_amount'] = CRM_Utils_Money::format($row['civicrm_contribution_fee_amount'], $row['civicrm_contribution_currency']);
      }

      // convert campaign_id to campaign title
      if (array_key_exists('civicrm_contribution_campaign_id', $row)) {
        if ($value = $row['civicrm_contribution_campaign_id']) {
          $rows[$rowNum]['civicrm_contribution_campaign_id'] = CRM_Utils_Array::value($value, $campaigns, '');
        }
      }
    }
  }
}
