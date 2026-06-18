<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 SYSTOPIA                       |
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

declare(strict_types = 1);

use CRM_Sepa_ExtensionUtil as E;
use Civi\Api4\SepaTransactionGroup;
use Civi\Api4\Contribution;
use Civi\Api4\SepaMandate;

/**
 * back office sepa group content viewer
 *
 * @package CiviCRM_SEPA
 *
 */
class CRM_Sepa_Page_ListGroup extends CRM_Core_Page {

  public function run() {
    CRM_Utils_System::setTitle(E::ts('SEPA Group Contributions'));
    try {
      $groupId = CRM_Utils_Request::retrieve('group_id', 'Integer', NULL, TRUE);
      $txGroup = SepaTransactionGroup::get(TRUE)
        ->selectRowCount()
        ->addSelect(
          'id',
          'type',
          'reference',
          'status_id',
          'COUNT(DISTINCT contribution.id) AS total_count',
          'SUM(contribution.total_amount) AS total_amount',
          'COUNT(DISTINCT contribution.campaign_id) AS different_campaigns',
          'COUNT(DISTINCT contribution.financial_type_id) AS different_types',
          'COUNT(DISTINCT contribution.contact_id) AS different_contacts'
        )
        ->addJoin('Contribution AS contribution', 'INNER', 'SepaContributionGroup')
        ->addWhere('id', '=', $groupId)
        ->addGroupBy('id')
        ->execute()
        ->single();
    }
    catch (CRM_Core_Exception $exception) {
      CRM_Core_Error::statusBounce(
        E::ts(
          'Cannot read SEPA transaction group [%1]. Error was: %2',
          [
            1 => $groupId ?? NULL,
            2 => $exception->getMessage(),
          ]
        ),
        CRM_Utils_System::url('civicrm/sepa')
      );
    }

    $contributionApi = Contribution::get()
      ->selectRowCount()
      ->addSelect(
        'contact_id.display_name',
        'contact_id.contact_type',
        'contact_id.id',
        'id',
        'total_amount',
        'currency',
        'financial_type_id',
        'financial_type_id:label',
        'campaign_id.title',
        'contribution_status_id:label',
        'mandate.reference',
        'mandate.iban',
      )
      ->addJoin('SepaTransactionGroup AS sepa_transaction_group', 'INNER', 'SepaContributionGroup');
    if ($txGroup['type'] !== 'OOFF') {
      // This is a recurring a group.
      $contributionApi->addJoin('SepaMandate AS mandate', 'LEFT', NULL,
        ['mandate.entity_table', '=', 'civicrm_contribution_recur', FALSE],
        ['mandate.entity_id', '=', 'contribution_recur_id', TRUE]
      );
    }
    $result = $contributionApi
      ->addWhere('sepa_transaction_group.id', '=', $groupId)
      ->execute();
    $statusStats = [];
    $contributions = [];
    foreach ($result as $contribution) {
      if ($txGroup['type'] === 'OOFF') {
        // For one off's we have to fetch the mandate per contribution
        // There is an issue with Join between Contribution and Sepa Mandate
        // CiviCRM core adds a join on first_contribution_id as well.
        try {
          $mandate = SepaMandate::get(FALSE)
            ->addWhere('entity_table', '=', 'civicrm_contribution')
            ->addWhere('entity_id', '=', $contribution['id'])
            ->execute()
            ->single();
          $contribution['mandate.reference'] = $mandate['reference'];
          $contribution['mandate.iban'] = $mandate['iban'];
        }
        catch (\Exception $e) {
        }
      }
      $contributions[] = [
        'contact_display_name' => $contribution['contact_id.display_name'],
        'contact_type' => $contribution['contact_id.contact_type'],
        'contact_id' => $contribution['contact_id.id'],
        'contact_link' => CRM_Utils_System::url(
          'civicrm/contact/view',
          '&reset=1&cid=' . $contribution['contact_id.id']
        ),
        'contribution_link' => CRM_Utils_System::url(
          'civicrm/contact/view/contribution',
          '&reset=1&id=' . $contribution['id'] . '&cid=' . $contribution['contact_id.id'] . '&action=view'
        ),
        'contribution_id' => $contribution['id'],
        'contribution_status' => $contribution['contribution_status_id:label'],
        'contribution_amount' => $contribution['total_amount'],
        'contribution_amount_str' => CRM_Utils_Money::format(
          (float) $contribution['total_amount'],
          (string) $contribution['currency']
        ),
        'financial_type_id' => $contribution['financial_type_id'],
        'financial_type' => $contribution['financial_type_id:label'],
        'campaign' => $contribution['campaign_id.title'],
        'reference' => $contribution['mandate.reference'],
        'iban' => $contribution['mandate.iban'],
      ];
      $statusStats[$contribution['contribution_status_id:label']] =
        1 + ($statusStats[$contribution['contribution_status_id:label']] ?? 0);
    }

    $this->assign('txgroup', $txGroup);
    $this->assign('reference', $txGroup['reference']);
    $this->assign('group_id', $groupId);
    $this->assign('total_count', $txGroup['total_count']);
    $this->assign('total_amount', $txGroup['total_amount']);
    $this->assign('total_amount_str', CRM_Utils_Money::format(
      $txGroup['total_amount'],
      $result->first()['currency'] ?? NULL
    ));
    $this->assign('contributions', $contributions);
    $this->assign('different_campaigns', $txGroup['different_campaigns']);
    $this->assign('different_types', $txGroup['different_types']);
    $this->assign('different_contacts', $txGroup['different_contacts']);
    $this->assign('status_stats', $statusStats);

    parent::run();
  }

}
