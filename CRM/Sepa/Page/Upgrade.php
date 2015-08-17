<?php
require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_Upgrade extends CRM_Core_Page {
  function run() {
    /* Run old custom upgrader just in case. */
    $messages = CRM_Sepa_Upgrade::run();

    /* Now invoke the new upgrader based on the official upgrade mechanism.
     *
     * This is a bit icky, as there seems to be no straightforward way
     * to get messages back from this mechanism.
     * We are using a global variable to get around this.
     */
    $GLOBALS['sepa_upgrade_messages'] = array();
    civicrm_api3('Extension', 'upgrade', array('key' => 'sfe.ssepa'));
    $messages = array_merge($messages, $GLOBALS['sepa_upgrade_messages']);
    unset($GLOBALS['sepa_upgrade_messages']);

    $this->assign('messages', $messages);
    parent::run();
  }
}
