<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 TTTP                           |
| Author: X+                                             |
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
 * SEPA_DD XML file generator
 *
 * @package CiviCRM_SEPA
 */

require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_SepaFile extends CRM_Core_Page {
  function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(ts('Generate XML File', array('domain' => 'org.project60.sepa')));

    $id = (int)CRM_Utils_Request::retrieve('id', 'Positive', $this);
    if ($id>0) {
      //fetch the file, then the group
      $file = new CRM_Sepa_BAO_SEPASddFile();
      $xml = $file->generateXML($id);
      header('Content-Type: text/xml; charset=utf-8');
      //header('Content-Type: text/plain; charset=utf-8');
      echo $xml;
      CRM_Utils_System::civiExit();
    } else {

      CRM_Core_Error::fatal("missing parameter. you need id");
      return;
    }

    parent::run();
  }
}
