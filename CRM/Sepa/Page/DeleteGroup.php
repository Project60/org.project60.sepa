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
 * Close a sepa group
 *
 * @package CiviCRM_SEPA
 *
 */

require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_DeleteGroup extends CRM_Core_Page {

  function run() {
    CRM_Utils_System::setTitle(ts('Delete SEPA Group', array('domain' => 'org.project60.sepa')));
    if (empty($_REQUEST['group_id'])) {
    	$this->assign('status', 'error');

    } else {
        $group_id = (int) $_REQUEST['group_id'];
        $this->assign('txgid', $group_id);
        $txgroup = civicrm_api('SepaTransactionGroup', 'getsingle', array('id'=>$group_id, 'version'=>3));
        if (empty($txgroup['is_error'])) {
	        $txgroup['status_label'] = CRM_Core_OptionGroup::optionLabel('batch_status', $txgroup['status_id']);
	        $txgroup['status_name'] = CRM_Core_OptionGroup::getValue('batch_status', $txgroup['status_id'], 'value', 'String', 'name');
	        $this->assign('txgroup', $txgroup);        	
        } else {
        	$_REQUEST['confirmed'] = 'error'; // skip the parts below
        }

        if (empty($_REQUEST['confirmed'])) {
        	// gather information to display
    	    $PENDING = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name');
    	    $INPROGRESS = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'In Progress', 'name');

        	$stats = array('busy' => 0, 'open' => 0, 'other' => 0, 'total' => 0);
        	$status2contributions = $this->contributionStats($group_id);
        	foreach ($status2contributions as $contribution_status_id => $contributions) {
        		foreach ($contributions as $contribution_id) {
        			$stats['total'] += 1;
        			if ($contribution_status_id==$PENDING) {
        				$stats['open'] += 1;
        			} elseif ($contribution_status_id==$INPROGRESS) {
        				$stats['busy'] += 1;
        			} else {
        				$stats['other'] += 1;
        			}
        		}
        	}
        	$this->assign('stats', $stats);
	    	$this->assign('status', 'unconfirmed');
	        $this->assign('submit_url', CRM_Utils_System::url('civicrm/sepa/deletegroup'));

        } elseif ($_REQUEST['confirmed']=='yes') {
        	// delete the group
        	$this->assign('status', 'done');
			$delete_contributions_mode = $_REQUEST['delete_contents'];
			$deleted_ok = array();
			$deleted_error = array();
        	$result = CRM_Sepa_BAO_SEPATransactionGroup::deleteGroup($group_id, $delete_contributions_mode);
        	if (is_string($result)) {
        		// a very basic error happened
        		$this->assign('error', $result);
        	} else {
        		// do some stats on the result
				$deleted_total = count($result);
	        	foreach ($result as $contribution_id => $message) {
	        		if ($message=='ok') {
	        			array_push($deleted_ok, $contribution_id);
	        		} else {
	        			array_push($deleted_error, $contribution_id);
	        		}
	        	}	        	
				$this->assign('deleted_result', $result);
				$this->assign('deleted_ok', $deleted_ok);
				$this->assign('deleted_error', $deleted_error);
        	}

        } elseif ($_REQUEST['confirmed']=='error') {
	        $this->assign('status', 'error');

    	} else {
    		CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/sepa'));
        }
    }

    parent::run();
  }

  /**
   * gather some statistics about the contributions linked to this txgroup
   * 
   * @return array(contribution_status_id->array(contribution_ids))
   */
  function contributionStats($group_id) {
  	$stats = array();
  	$sql = "
  	SELECT
  		civicrm_contribution.id 						AS contribution_id,
  		civicrm_contribution.contribution_status_id 	AS status_id
  	FROM 		civicrm_sdd_contribution_txgroup
  	LEFT JOIN 	civicrm_contribution ON civicrm_sdd_contribution_txgroup.contribution_id = civicrm_contribution.id
  	WHERE
  		civicrm_sdd_contribution_txgroup.txgroup_id = $group_id;
  	";
  	$contribution_info = CRM_Core_DAO::executeQuery($sql);
  	while ($contribution_info->fetch()) {
  		if (!isset($stats[$contribution_info->status_id])) {
  			$stats[$contribution_info->status_id] = array();
  		}
  		array_push($stats[$contribution_info->status_id], $contribution_info->contribution_id);
  	}
  	return $stats;
  }
}