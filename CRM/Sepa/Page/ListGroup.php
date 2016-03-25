<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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
 * back office sepa group content viewer
 *
 * @package CiviCRM_SEPA
 *
 */


require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_ListGroup extends CRM_Core_Page {

  function run() {
    CRM_Utils_System::setTitle(ts('SEPA Group Contributions', array('domain' => 'org.project60.sepa')));
    if (isset($_REQUEST['group_id'])) {
      // get some values
      $group_id = (int) $_REQUEST['group_id'];
      $financial_types = CRM_Contribute_PseudoConstant::financialType();

      // load the group
      $txgroup = civicrm_api('SepaTransactionGroup', 'getsingle', array('id'=>$group_id, 'version'=>3));
      if (isset($txgroup['is_error']) && $txgroup['is_error']) {
        CRM_Core_Session::setStatus(sprintf(ts("Cannot read SEPA transaction group [%s]. Error was: '%s'", array('domain' => 'org.project60.sepa')), $group_id, $txgroup['error_message']), ts("Error", array('domain' => 'org.project60.sepa')), "error");
      }

      // load the group's contributions
      $sql = "
      SELECT
        civicrm_sdd_txgroup.reference           AS reference,
        civicrm_contact.display_name            AS contact_display_name,
        civicrm_contact.contact_type            AS contact_contact_type,
        civicrm_contact.id                      AS contact_id,
        civicrm_contribution.id                 AS contribution_id,
        civicrm_contribution.total_amount       AS contribution_amount,
        civicrm_contribution.financial_type_id  AS contribution_financial_type_id,
        civicrm_contribution.currency           AS contribution_currency,
        civicrm_campaign.title                  AS contribution_campaign
      FROM   
        civicrm_sdd_txgroup
      LEFT JOIN 
        civicrm_sdd_contribution_txgroup   ON   civicrm_sdd_txgroup.id = civicrm_sdd_contribution_txgroup.txgroup_id
      LEFT JOIN 
        civicrm_contribution               ON   civicrm_contribution.id = civicrm_sdd_contribution_txgroup.contribution_id
      LEFT JOIN 
        civicrm_contact                    ON   civicrm_contact.id = civicrm_contribution.contact_id
      LEFT JOIN 
        civicrm_campaign                   ON   civicrm_campaign.id = civicrm_contribution.campaign_id
      WHERE       
        civicrm_sdd_txgroup.id = $group_id;";

      $total_amount = 0.0;
      $total_count = 0;
      $total_campaigns = array();
      $total_types = array();
      $total_contacts = array();
      $contact_base_link = CRM_Utils_System::url('civicrm/contact/view', '&reset=1&cid=');
      $contribution_base_link = CRM_Utils_System::url('civicrm/contact/view/contribution', '&reset=1&id=_cid_&cid=_id_&action=view');

      $currency = '';
      $contributions = array();
      $result = CRM_Core_DAO::executeQuery($sql);
      while ($result->fetch()) {
        $contributions[$total_count] = array(
          'contact_display_name'      => $result->contact_display_name,
          'contact_type'              => $result->contact_contact_type,
          'contact_id'                => $result->contact_id,
          'contact_link'              => $contact_base_link.$result->contact_id,
          'contribution_link'         => str_replace('_id_', $result->contact_id, str_replace('_cid_', $result->contribution_id, $contribution_base_link)),
          'contribution_id'           => $result->contribution_id,
          'contribution_amount'       => $result->contribution_amount,
          'contribution_amount_str'   => CRM_Utils_Money::format($result->contribution_amount, $result->contribution_currency),
          'financial_type'            => $financial_types[$result->contribution_financial_type_id],
          'campaign'                  => $result->contribution_campaign,
        );

        $total_count += 1;
        $total_amount += $result->contribution_amount;
        $total_types[$result->contribution_financial_type_id] = 1;
        $total_contacts[$result->contact_id] = 1;
        $total_campaigns[$result->contribution_campaign] = 1;
        $reference = $result->reference;
        $currency = $result->contribution_currency;
      }
    }

    $this->assign("txgroup", $txgroup);
    $this->assign("reference", $reference);
    $this->assign("group_id", $group_id);
    $this->assign("total_count", $total_count);
    $this->assign("total_amount", $total_amount);
    $this->assign("total_amount_str", CRM_Utils_Money::format($total_amount, $currency));
    $this->assign("contributions", $contributions);
    $this->assign("different_campaigns", count($total_campaigns));
    $this->assign("different_types", count($total_types));
    $this->assign("different_contacts", count($total_contacts));
    parent::run();
  }

}