<?php

require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_CloseGroup extends CRM_Core_Page {

  function run() {
    if (isset($_REQUEST['group_id'])) {
      // CLOSE THE GROUP:
      $group_id = (int) $_REQUEST['group_id'];
      $result = civicrm_api('SepaAlternativeBatching', 'close', array('version'=>3, 'txgroup_id'=>$group_id));
      if ($result['is_error']) {
        CRM_Core_Session::setStatus("Cannot close group #$group_id.<br/>Error was: ".$result['error_message'], ts('Error'), 'error');
      } else {
        $group = civicrm_api('SepaTransactionGroup', 'getsingle', array('version'=>3, 'id'=>$group_id));
        if (isset($group['is_error']) && $group['is_error']) {
          CRM_Core_Session::setStatus("Cannot load group #$group_id.<br/>Error was: ".$group['error_message'], ts('Error'), 'error');
        } else {
          // everything is fine -> load the file 
          $this->assign('txgroup', $group);
          $file_id = $group['sdd_file_id'];
          $file = civicrm_api('SepaSddFile', 'getsingle', array('version'=>3, 'id'=>$file_id));
          if (isset($file['is_error']) && $file['is_error']) {
            CRM_Core_Session::setStatus("Cannot load file #".$file_id.".<br/>Error was: ".$file['error_message'], ts('Error'), 'error');
          } else {
            $this->assign('file_link', CRM_Utils_System::url('civicrm/sepa/xml', "id=$file_id"));
            $this->assign('file_name', $file['filename']);
          }
        }
      }
    }

    parent::run();
  }

}