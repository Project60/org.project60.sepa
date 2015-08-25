<?php
/**
 * Created by PhpStorm.
 * User: tomasz
 * Date: 25.08.15
 * Time: 15:53
 */

class CRM_Sepa_Form_Package extends CRM_Core_Form {

    public $countNotPackaged = 0;

    public function preProcess() {
        $this->countNotPackaged = CRM_Sepa_BAO_SEPAMandate::countNotPackaged();
        //$this->countNotPackaged = 0;
        $this->assign('countNotPackaged', $this->countNotPackaged);
        parent::preProcess();
    }


    public function setDefaultValues() {
        $defaults = array();
        return $defaults;
    }



    public function buildQuickForm() {
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

        $ids = CRM_Sepa_BAO_SEPAMandate::getNotPackaged();

    }
}