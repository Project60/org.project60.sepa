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
 * File for the CiviCRM sepa_sdd_file business logic
 *
 * @package CiviCRM_SEPA
 *
 */


/**
 * Class contains functions for Sepa mandates
 */
class CRM_Sepa_BAO_SEPASddFile extends CRM_Sepa_DAO_SEPASddFile {


  /**
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return object       CRM_Core_BAO_SEPASddFile object on success, null otherwise
   * @access public
   * @static
   */
  static function add(&$params) {
    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'SepaSddFile', CRM_Utils_Array::value('id', $params), $params);

    $dao = new CRM_Sepa_DAO_SEPASddFile();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'SepaSddFile', $dao->id, $dao);
    return $dao;
  }

  function generatexml($id) {
    $xml = "";
    $template = CRM_Core_Smarty::singleton();
    $this->get((int)$id);
    $template->assign("file", $this->toArray());
    $txgroup = new CRM_Sepa_BAO_SEPATransactionGroup();
    $txgroup->sdd_file_id=$this->id;
    $txgroup->find();
    $total =0;
    $nbtransactions =0;
    $fileFormats = array();
    while ($txgroup->fetch()) {
      $xml .= $txgroup->generateXML();
      $total += $txgroup->total;
      $nbtransactions += $txgroup->nbtransactions;
      $fileFormats[] = $txgroup->fileFormat;
    }
    if (count(array_unique($fileFormats)) > 1) {
      throw new Exception('Creditors with mismatching File Formats cannot be mixed in same File');
    }
    $template->assign("file",$this->toArray());
    $template->assign("total", number_format($total, 2, '.', '')); // SEPA-432: two-digit decimals
    $template->assign("nbtransactions",$nbtransactions);
    $head = $template->fetch('CRM/Sepa/xml/file_header.tpl');
    $footer = $template->fetch('CRM/Sepa/xml/file_footer.tpl');
    return $head.$xml.$footer;
  }
}

