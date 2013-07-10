<?php

require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_SepaFile extends CRM_Core_Page {
  function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(ts('Generate XML File'));

    $id = (int)CRM_Utils_Request::retrieve('id', 'Positive', $this);
    if ($id>0) {
      //fetch the file, then the group
      $file = new CRM_Sepa_BAO_SEPASddFile();
      echo $file->generateXML($id);
      die ("generate xml file");
    } else {

      CRM_Core_Error::fatal("missing parameter. you need id");
      return;
    }

    parent::run();
  }
}
