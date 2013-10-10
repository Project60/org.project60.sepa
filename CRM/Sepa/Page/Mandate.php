<?php

require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_Mandate extends CRM_Core_Page {
  function run() {

    $r = civicrm_api ("ContributionRecur","getfull", array("version"=>3));
    $this->assign('contributions', $r);

    parent::run();
  }
}
