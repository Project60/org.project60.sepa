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
    $this->assign("groups",$r["values"]);
    parent::run();
  }

  function getTemplateFileName() {
    return "CRM/Sepa/Page/DashBoard.tpl";
}
}
