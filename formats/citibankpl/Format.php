<?php

class CRM_Sepa_Logic_Format_citibankpl extends CRM_Sepa_Logic_Format {

  /** It's possible to increase or decrease current number for packages of mandates file. */
  const PACKAGE_NUMBER_MODIFIER = 0;

  /** Client shortcut given by creditor */
  const CLIENT_SHORTCUT = 'CLNT';

  public static $out_charset = 'CP852';

  public static $create_package = true;

  public function getDDFilePrefix() {
    return 'CITIBANK-';
  }

  public function getFilename($variable_string) {
    return $variable_string.'.txt';
  }

  public function getLastPackageNumber() {
    return parent::getLastPackageNumber() + self::PACKAGE_NUMBER_MODIFIER;
  }

  public function getNewPackageFilename() {
    return ($this->getLastPackageNumber()+1).self::CLIENT_SHORTCUT.'.txt';
  }

  public function getMandatePackage($result) {

      $template = CRM_Core_Smarty::singleton();
      $fileFormat = $this->getFileFormat(__CLASS__);

      $filename = $result['values'][0]['filename'];
      $createTimestamp = strtotime($result['values'][0]['create_date']);
      $createDate = date("Ymd", $createTimestamp);
      $createTime = date("H.i.s", $createTimestamp);
      preg_match("/^([0-9]+).*/", $filename, $matches);
      $fileNumber = $matches[1];
      $creditor_identifier = $result['values'][0]['api.SepaCreditor.get']['values'][0]['identifier'];

      $mandate_ids = array();
      foreach ($result['values'][0]['api.SepaMandateFileRow.get']['values'] as $item) {
        $mandate_ids[$item['mandate_id']] = $item['mandate_id'];
      }

      $mandateCounts = count($mandate_ids);

      $apiChainContact = 'api.Contact.get';
      $params = array(
          'sequential' => 1,
          'id' => array('IN' => $mandate_ids),
          $apiChainContact => array(
              'id' => '$value.contact_id',
          ),
      );

      $bic_exists = false;
      $apiChainBic = 'api.Bic.findbyiban';
      $query = "SELECT count(id) AS test
              FROM civicrm_extension
              WHERE full_name = 'org.project60.bic' AND is_active = 1";
      $ext_test = CRM_Core_DAO::executeQuery($query);
      $ext_test->fetch();
      if ($ext_test->test == 1) {
          $bic_exists = true;
          $params[$apiChainBic] = array(
              'iban' => '$value.iban'
          );
      }

      $detailsRow = array();
      $result_mandates = civicrm_api3('SepaMandate', 'get', $params);
      if (array_key_exists('values', $result_mandates) && count($result_mandates['values']) > 0) {
          foreach ($result_mandates['values'] as $id => $item) {
              $bank_name = $item['bic'];
              if ($bic_exists && array_key_exists('title', $item[$apiChainBic]) && $item[$apiChainBic]['title'] != '') {
                  $bank_name = $item[$apiChainBic]['title'];
              }
              $detailsRow[] = array(
                  'reference' => $item['reference'],
                  'display_name' => $item[$apiChainContact]['values'][0]['display_name'],
                  'address' => $item[$apiChainContact]['values'][0]['street_address'].', '.$item[$apiChainContact]['values'][0]['postal_code'].' '.$item[$apiChainContact]['values'][0]['city'],
                  'bank_name' => $bank_name,
                  'account' => substr($item['iban'], 4, 8).'-'.substr($item['iban'], 2),
              );
          }
      }
      $template->assign('filename', $filename);
      $template->assign('createDate', $createDate);
      $template->assign('createTime', $createTime);
      $template->assign('fileNumber', $fileNumber);
      $template->assign('creditor_identifier', $creditor_identifier);
      $template->assign('details', $detailsRow);
      $template->assign('mandateCounts', $mandateCounts);

      $header = $template->fetch('../formats/'.$fileFormat.'/mandate-header.tpl');
      $details = $template->fetch('../formats/'.$fileFormat.'/mandate-details.tpl');
      $footer = $template->fetch('../formats/'.$fileFormat.'/mandate-footer.tpl');

      return iconv('UTF-8', self::$out_charset, $header.$details.$footer);

  }

}
