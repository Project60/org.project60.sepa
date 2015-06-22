<?php
require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_Upgrade extends CRM_Core_Page {
  function run() {
    $messages = CRM_Sepa_Upgrade::run();
    $this->assign('messages', $messages);
    parent::run();
  }
}
