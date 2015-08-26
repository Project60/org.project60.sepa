<?php

class CRM_Sepa_Logic_Format_citibankpl extends CRM_Sepa_Logic_Format {

  /** It's possible to increase or decrease current number for packages of mandates file. */
  const PACKAGE_NUMBER_MODIFIER = 0;

  /** Client shortcut given by creditor */
  const CLIENT_SHORTCUT = 'CLNT';

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

      $test = ''; //todo usunąć po zakończeniu testów

      $template = CRM_Core_Smarty::singleton();
      $fileFormat = $this->getFileFormat(__CLASS__);

      $filename = $result['values'][0]['filename'];
      $createTimestamp = strtotime($result['values'][0]['create_date']);
      $createDate = date("Ymd", $createTimestamp);
      $createTime = date("H.i.s", $createTimestamp);
      preg_match("/^([0-9]+).*/", $filename, $matches);
      $fileNumber = $matches[1];

      $mandate_ids = array();
      foreach ($result['values'][0]['api.SepaMandateFileRow.get']['values'] as $item) {
        $mandate_ids[$item['mandate_id']] = $item['mandate_id'];
      }
      //$test .= print_r($mandate_ids, true);

      $mandateCounts = count($mandate_ids);

      $apiChainContact = 'api.Contact.get';
      $params = array(
          'sequential' => 1,
          'id' => array('IN' => $mandate_ids),
          $apiChainContact => array(
              'id' => '$value.contact_id',
          ),
      );

      $detailsRow = array();
      $result_mandates = civicrm_api3('SepaMandate', 'get', $params);
      //$test .= print_r($result_mandates, true);
      if (array_key_exists('values', $result_mandates) && count($result_mandates['values'] > 0)) {
          foreach ($result_mandates['values'] as $id => $item) {
              $detailsRow[] = array(
                  'reference' => $item['reference'],
                  'display_name' => $item[$apiChainContact]['values'][0]['display_name'],
                  'address' => $item[$apiChainContact]['values'][0]['street_address'], // todo poprawić, bo jest niepełny!
                  'bank_name' => $item['bic'], // todo poprawić!?
                  'account' => $item['bic'].'-'.$item['iban'], // todo numer rachunku w standardzie NRB poprzedzony numerem banku i myślnikiem;
              );
          }
      }
      $template->assign('filename', $filename);
      $template->assign('createDate', $createDate);
      $template->assign('createTime', $createTime);
      $template->assign('fileNumber', $fileNumber);
      $template->assign('client', self::CLIENT_SHORTCUT);
      $template->assign('details', $detailsRow);
      $template->assign('mandateCounts', $mandateCounts);

      $header = $template->fetch('../formats/'.$fileFormat.'/mandate-header.tpl');
      $details = $template->fetch('../formats/'.$fileFormat.'/mandate-details.tpl');
      $footer = $template->fetch('../formats/'.$fileFormat.'/mandate-footer.tpl');

      return $test.$header.$details.$footer;

  }

}
