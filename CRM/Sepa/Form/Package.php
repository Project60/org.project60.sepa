<?php
/**
 * Created by PhpStorm.
 * User: tomasz
 * Date: 25.08.15
 * Time: 15:53
 */

class CRM_Sepa_Form_Package extends CRM_Core_Form {

    /** @var int How many mandates are not packaged */
    public $countNotPackaged = 0;

    /** @var array List of creditors (creditor_id => name) */
    public $creditors = array();

    /** @var null Default creditor set up in configuration */
    public $defaultCreditor = null;

    /** @var array Sepa file format according to creditor (creditor_id => sepa_file_format_id) */
    public $sepaFileFormats = array();

    public function preProcess() {
        $this->countNotPackaged = CRM_Sepa_BAO_SEPAMandate::countNotPackaged();

        $this->defaultCreditor = CRM_Sepa_Logic_Settings::getSetting('batching_default_creditor');

        $result = civicrm_api3('SepaCreditor', 'get');
        if (array_key_exists('values', $result) && count($result['values'] > 0)) {
            foreach ($result['values'] as $item) {
                $this->creditors[$item['id']] = $item['name'];
                $this->sepaFileFormats[$item['id']] = $item['sepa_file_format_id'];
            }
        }

        $this->assign('countNotPackaged', $this->countNotPackaged);
        $this->assign('processState', 'pre');
        parent::preProcess();
    }


    public function setDefaultValues() {
        $defaults = array();
        $defaults['creditor_id'] = $this->defaultCreditor;
        return $defaults;
    }



    public function buildQuickForm() {
        $this->add('select', 'creditor_id', 'Creditor', array(null => '- select creditor -') + $this->creditors, true);
        $this->add('checkbox', 'confirm', ts('Confirm'), null, true);
        $buttons = array();
        if ($this->countNotPackaged > 0) {
            $buttons[] = array(
                'type' => 'submit',
                'name' => ts('Create'),
                'isDefault' => true,
            );
        }
        $buttons[] = array(
            'type' => 'cancel',
            'name' => ts('Cancel'),
        );
        $this->addButtons($buttons);
    }


    public function postProcess() {
        $values = $this->exportValues();
        $creditor_id = $values['creditor_id'];
        $contact_id = (int)$_SESSION['CiviCRM']['userID'];
        $ids = CRM_Sepa_BAO_SEPAMandate::getNotPackaged();

        $fileFormat = CRM_Core_OptionGroup::getValue('sepa_file_format', $this->sepaFileFormats[$creditor_id], 'value', 'Integer', 'name');
        $fileFormat = CRM_Sepa_Logic_Format::sanitizeFileFormat($fileFormat);
        CRM_Sepa_Logic_Format::loadFormatClass($fileFormat);
        $classFormat = "CRM_Sepa_Logic_Format_".$fileFormat;
        $pf = new $classFormat();
        $filename = $pf->getNewPackageFilename();

        $params_row = array();
        foreach ($ids as $id) {
            $params_row[] = array(
                'mandate_file_id' => '$value.id',
                'mandate_id' => $id,
            );
        }
        $params = array(
            'creditor_id' => $creditor_id,
            'contact_id' => $contact_id,
            'filename' => $filename,
            'create_date' => date('Y-m-d H:i:s'),
            'api.SepaMandateFileRow.create' => $params_row,
        );
        $result = civicrm_api3('SepaMandateFile', 'create', $params);

        $this->assign('result', $result);
        $this->assign('filename', $filename);
        $this->assign('filelink', CRM_Utils_System::url('civicrm/sepa/dpackage', "pid=".$result['id']));
        $this->assign('processState', 'post');
    }
}
