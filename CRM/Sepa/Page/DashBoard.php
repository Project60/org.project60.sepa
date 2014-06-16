<?php

require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_DashBoard extends CRM_Core_Page {

  function run() {
    CRM_Core_Resources::singleton()
    ->addScriptFile('civicrm', 'packages/backbone/underscore.js', 110, 'html-header', FALSE);

    $r = civicrm_api("SepaTransactionGroup","getdetail",array("version"=>3,"sequential"=>1,
    'options' => array(
      'sort' => 'created_date DESC',
      'limit' => 1,
      ),
    ));

    $groups = array();
    foreach ($r['values'] as $group) {
      $files = CRM_Core_BAO_File::getEntityFile('civicrm_sdd_file', $group['file_id']);
      if (!empty($files)) {
        list($file) = array_slice($files, 0, 1);
        $group['file_href'] = $file['href'];
      }

      $group['status_label'] = CRM_Core_OptionGroup::getLabel('contribution_status', $group['status_id']);
      $group['status'] = CRM_Core_OptionGroup::getValue('contribution_status', $group['status_id'], 'value', 'String', 'name');

      $groups = array_replace_recursive($groups, array($group['sdd_creditor_id'] => array())); /* Create any missing array levels, to avoid PHP notice. */
      $groups[$group['sdd_creditor_id']][] = $group;
    }
    $this->assign("groups",$groups);

    parent::run();
  }

  function getTemplateFileName() {
    return "CRM/Sepa/Page/DashBoard.tpl";
}
}
