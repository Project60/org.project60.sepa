<?php

require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_DashBoard extends CRM_Core_Page {

  function run() {
    $groups = array();

    /* Get all pending Contributions. */
    $pending = civicrm_api3('SepaContributionPending', 'get', array(
      'options' => array(
        'sort' => 'receive_date DESC',
      ),
      'return' => array('receive_date', 'payment_instrument_id', 'contribution_status_id', 'total_amount'),
      'mandate' => array(
        'return' => array('creditor_id'),
      ),
    ));

    /*
     * Construct pending pseudo-groups from the pending Contributions.
     * A group is created for each combination of Creditor, `receive_date`, and `type`.
     *
     * Also, totals the number of contributions and cummulated amount for each group
     * as contributions are added to it.
     */
    foreach ($pending['values'] as $contribution) {
      $creditor = $contribution['creditor_id'];
      $date = date('Y-m-d', strtotime($contribution['receive_date']));
      $instrument = $contribution['payment_instrument_id'];
      $type = CRM_Core_OptionGroup::getValue('payment_instrument', $instrument, 'value', 'String', 'name');

      /* Assume new group -- empty for now. */
      $group = array(
        'payment_instrument_id' => $instrument,
        'type' => $type,
        'collection_date' => $date,
        'status_id' => $contribution['contribution_status_id'], /* Should always be 'Pending'. */
        'nb_contrib' => 0,
        'total' => 0.0,
      );

      /* Create new group if missing, along with all not yet present hierarchy levels.
       * No-op if we already created this group in a previous iteration.
       * (Won't override previous totals.) */
      $groups = array_replace_recursive(array($creditor => array('Pending' => array($date.$type => $group))), $groups);

      /* Add this contribution to totals. */
      ++$groups[$creditor]['Pending'][$date.$type]['nb_contrib'];
      $groups[$creditor]['Pending'][$date.$type]['total'] += $contribution['total_amount'];
    } /* foreach ($pending) */

    /* Get batched groups, and merge their data with the pending groups. */
    $r = civicrm_api("SepaTransactionGroup","getdetail",array("version"=>3,"sequential"=>1,
    'options' => array(
      'sort' => 'created_date DESC',
      'limit' => 1,
      ),
    ));
    foreach ($r['values'] as $group) {
      $files = CRM_Core_BAO_File::getEntityFile('civicrm_sdd_file', $group['file_id']);
      if (!empty($files)) {
        list($file) = array_slice($files, 0, 1);
        $group['file_href'] = $file['href'];
      }

      $creditor = $group['sdd_creditor_id'];
      $groups = array_replace_recursive($groups, array($creditor => array('Batched' => array()))); /* Create any missing array levels, to avoid PHP notice. */
      $groups[$creditor]['Batched'][] = $group;
    }

    /* Fetch status labels and names for all groups (both pending and batched). */
    foreach ($groups as &$creditor) {
      foreach ($creditor as &$kind) {
        foreach ($kind as &$group) {
          $group['status_label'] = CRM_Core_OptionGroup::getLabel('contribution_status', $group['status_id']);
          $group['status'] = CRM_Core_OptionGroup::getValue('contribution_status', $group['status_id'], 'value', 'String', 'name');
        }
      }
    }

    $this->assign("groups",$groups);

    parent::run();
  }

  function getTemplateFileName() {
    return "CRM/Sepa/Page/DashBoard.tpl";
}
}
