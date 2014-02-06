<?php
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
    while ($txgroup->fetch()) {
      $xml .= $txgroup->generateXML();
      $total += $txgroup->total;
      $nbtransactions += $txgroup->nbtransactions;
    }
    $template->assign("file",$this->toArray());
    $template->assign("total",$total );
    $template->assign("nbtransactions",$nbtransactions);
    $head = $template->fetch('CRM/Sepa/xml/file_header.tpl');
    $footer = $template->fetch('CRM/Sepa/xml/file_footer.tpl');
    return $head.$xml.$footer;
  }
}

