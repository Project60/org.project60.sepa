<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2018 SYSTOPIA                            |
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

use CRM_Sepa_ExtensionUtil as E;
use Civi\Api4\SepaMandate;
use Civi\Api4\Contribution;

class CRM_Sepa_Page_MandateTab extends CRM_Core_Page {

  /**
   * load mandate information
   */
  public function run() {
    CRM_Utils_System::setTitle(E::ts('SEPA Mandates'));
    $contactId = CRM_Utils_Request::retrieve('cid', 'Integer');
    $this->assign('date_format', '%Y-%m-%d');
    $this->assign('contact_id', $contactId);
    $this->assign(
      'financialacls',
      \CRM_Extension_System::singleton()
        ->getManager()
        ->getStatus('financialacls') === \CRM_Extension_Manager::STATUS_INSTALLED
    );
    $this->assign(
      'permissions',
      [
        'create' => CRM_Core_Permission::check('create sepa mandates'),
        'view' => CRM_Core_Permission::check('view sepa mandates'),
        'edit' => CRM_Core_Permission::check('edit sepa mandates'),
        'delete' => CRM_Core_Permission::check('delete sepa mandates'),
      ]
    );

    // Retrieve OOFF mandates.
    $ooffList = [];
    $ooffMandates = SepaMandate::get(TRUE)
      ->addSelect(
        'id',
        'contribution.id',
        'contribution.receive_date',
        'status',
        'reference',
        'contribution.financial_type_id:name',
        'campaign.title',
        'contribution.total_amount',
        'contribution.currency',
        'contribution.cancel_reason'
      )
      ->addJoin(
        'Contribution AS contribution',
        'INNER',
        ['entity_table', '=', '"civicrm_contribution"'],
        ['entity_id', '=', 'contribution.id']
      )
      ->addJoin(
        'Campaign AS campaign',
        'LEFT',
        ['campaign.id', '=', 'contribution.campaign_id']
      )
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('type', '=', 'OOFF')
      ->execute();
    foreach ($ooffMandates as $ooffMandate) {
      $ooffList[] = [
        'receive_date' => $ooffMandate['contribution.receive_date'],
        'status_raw' => $ooffMandate['status'],
        'status' => CRM_Sepa_Logic_Status::translateMandateStatus($ooffMandate['status'], TRUE),
        'reference' => $ooffMandate['reference'],
        'financial_type' => $ooffMandate['contribution.financial_type_id:name'],
        'campaign' => $ooffMandate['campaign.title'],
        'total_amount' => $ooffMandate['contribution.total_amount'],
        'currency' => $ooffMandate['contribution.currency'],
        'cancel_reason' => $ooffMandate['contribution.cancel_reason'],
        'class' => 'OOFF' === $ooffMandate['status'] ? 'sepa-active' : 'sepa-inactive',
        'view_link' => CRM_Utils_System::url(
          'civicrm/contact/view/contribution',
          "reset=1&id={$ooffMandate['contribution.id']}&cid={$contactId}&action=view&context=contribution"
        ),
        'edit_link' => CRM_Utils_System::url(
          'civicrm/sepa/xmandate',
          "mid={$ooffMandate['id']}"
        ),
      ];
    }
    $this->assign('ooffs', $ooffList);


    // Retrieve RCUR mandates.
    $rcurList = [];
    $rcurMandates = SepaMandate::get(TRUE)
      ->addSelect(
        'id',
        'contribution_recur.id',
        'contribution_recur.start_date',
        'contribution_recur.end_date',
        'contribution_recur.next_sched_contribution_date',
        'last_contribution.cancel_reason',
        'status',
        'reference',
        'GROUP_FIRST(cancel_reason.note) AS cancel_reason',
        'contribution_recur.financial_type_id:name',
        'campaign.title',
        'contribution_recur.frequency_interval',
        'contribution_recur.frequency_unit',
        'contribution_recur.cycle_day',
        'contribution_recur.currency',
        'contribution_recur.amount'
      )
      ->addJoin(
        'ContributionRecur AS contribution_recur',
        'INNER',
        ['entity_id', '=', 'contribution_recur.id'],
        ['entity_table', '=', '"civicrm_contribution_recur"']
      )
      ->addJoin(
        'Note AS cancel_reason',
        'LEFT',
        ['cancel_reason.entity_id', '=', 'contribution_recur.id'],
        ['cancel_reason.entity_table', '=', '"civicrm_contribution_recur"'],
        ['cancel_reason.subject', '=', '"cancel_reason"']
      )
      ->addJoin(
        'Campaign AS campaign',
        'LEFT',
        ['campaign.id', '=', 'contribution_recur.campaign_id']
      )
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('type', '=', 'RCUR')
      ->addWhere('entity_table', '=', 'civicrm_contribution_recur')
      ->addOrderBy('contribution_recur.start_date', 'DESC')
      ->addOrderBy('id', 'DESC')
      ->addGroupBy('id')
      ->execute();

    foreach ($rcurMandates as $rcurMandate) {
      $lastInstallment = Contribution::get()
        ->addSelect('receive_date', 'cancel_reason')
        ->addWhere('contribution_recur_id', '=', $rcurMandate['contribution_recur.id'])
        ->addWhere('contribution_status_id:name', '!=', 'Pending')
        ->addOrderBy('receive_date', 'DESC')
        ->setLimit(1)
        ->execute()
        ->first();
      $rcurRow = [
        'mandate_id' => $rcurMandate['id'],
        'start_date' => $rcurMandate['contribution_recur.start_date'],
        'cycle_day' => $rcurMandate['contribution_recur.cycle_day'],
        'status_raw' => $rcurMandate['status'],
        'reference' => $rcurMandate['reference'],
        'financial_type' => $rcurMandate['contribution_recur.financial_type_id:name'],
        'campaign' => $rcurMandate['campaign.title'],
        'status' => CRM_Sepa_Logic_Status::translateMandateStatus($rcurMandate['status'], TRUE),
        'frequency' => CRM_Utils_SepaOptionGroupTools::getFrequencyText(
          $rcurMandate['contribution_recur.frequency_interval'],
          $rcurMandate['contribution_recur.frequency_unit'],
          TRUE
        ),
        'next_collection_date' => $rcurMandate['contribution_recur.next_sched_contribution_date'],
        'last_collection_date' => $lastInstallment['receive_date'] ?? NULL,
        'cancel_reason' => $rcur_mandates['cancel_reason'],
        'last_cancel_reason' => $lastInstallment['cancel_reason'] ?? NULL,
        'end_date' => $rcurMandate['contribution_recur.end_date'],
        'currency' => $rcurMandate['contribution_recur.currency'],
        'amount' => $rcurMandate['contribution_recur.amount'],
        'class' => in_array($rcurMandate['status'], ['FRST', 'RCUR'])
          ? 'sepa-active'
          : 'sepa-inactive',
      ];

      // Calculate annual amount.
      if ('year' === $rcurMandate['contribution_recur.frequency_unit']) {
        $rcurRow['total_amount'] =
          $rcurMandate['contribution_recur.amount'] / $rcurMandate['contribution_recur.frequency_interval'];
      } elseif ('month' === $rcurMandate['contribution_recur.frequency_unit']) {
        $rcurRow['total_amount'] =
          $rcurMandate['contribution_recur.amount'] * 12.0 / $rcurMandate['contribution_recur.frequency_interval'];
      }

      // Add links.
      $rcurRow['view_link'] = CRM_Utils_System::url(
        'civicrm/contact/view/contributionrecur',
        "reset=1&id={$rcurMandate['contribution_recur.id']}&cid={$contactId}&context=contribution");
      if (CRM_Core_Permission::check('edit sepa mandates')) {
        $rcurRow['edit_link'] = CRM_Utils_System::url('civicrm/sepa/xmandate', "mid={$rcurMandate['id']}");
      }

      $rcurList[$rcurMandate['id']] = $rcurRow;
    }

    // Add cancellation info.
    // TODO: Transform into APIv4 query.
    //       This currently generates a string of "0" (for pending/in progress/completed contributions) and "1" for
    //       other status and counts trailing "1"s, passing the number of failed last installments to the template.
    //       As this does not disclose contribution information that has not yet been fetched via the API, no additional
    //       ACL bypassing is being done here.
    if (!empty($rcurList)) {
      $mandate_id_list = implode(',', array_keys($rcurList));
      $fail_sequence = "
        SELECT
         civicrm_sdd_mandate.id AS mandate_id,
         GROUP_CONCAT(
          IF(civicrm_contribution.contribution_status_id IN (1,2,5), '0', '1')
          SEPARATOR '')        AS fail_sequence
        FROM civicrm_sdd_mandate
        LEFT JOIN civicrm_contribution_recur ON civicrm_contribution_recur.id = civicrm_sdd_mandate.entity_id
        LEFT JOIN civicrm_contribution       ON civicrm_contribution.contribution_recur_id = civicrm_contribution_recur.id
        WHERE civicrm_sdd_mandate.id IN ({$mandate_id_list})
          AND civicrm_sdd_mandate.type = 'RCUR'
          AND civicrm_sdd_mandate.entity_table = 'civicrm_contribution_recur'
          AND civicrm_contribution.id IS NOT NULL
        GROUP BY civicrm_sdd_mandate.id
        ORDER BY civicrm_contribution.receive_date;";

      CRM_Core_DAO::disableFullGroupByMode();
      $fail_query = CRM_Core_DAO::executeQuery($fail_sequence);
      CRM_Core_DAO::reenableFullGroupByMode();

      while ($fail_query->fetch()) {
        if (preg_match("#(?<last_fails>1+)$#", $fail_query->fail_sequence, $match)) {
          $last_sequence = $match['last_fails'];
          $rcurList[$fail_query->mandate_id]['fail_sequence'] = strlen($last_sequence);
        }
      }
    }

    $this->assign('rcurs', $rcurList);

    parent::run();
  }

  /**
   * get the number of mandates
   * for the given contact
   */
  public static function getMandateCount($contact_id) {
    return CRM_Core_DAO::singleValueQuery("
        SELECT COUNT(id) FROM civicrm_sdd_mandate
        WHERE contact_id = %1
          AND status IN ('FRST', 'RCUR', 'OOFF', 'INIT');",
            array( 1 => array($contact_id, 'Integer')));
  }

}
