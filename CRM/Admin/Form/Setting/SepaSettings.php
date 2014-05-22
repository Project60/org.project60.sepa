<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 SYSTOPIA                       |
| Author: N. Bochan (bochan -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

require_once 'CRM/Admin/Form/Setting.php';
require_once 'CRM/Core/BAO/CustomField.php';
 
class CRM_Admin_Form_Setting_SepaSettings extends CRM_Admin_Form_Setting
{

    public function buildQuickForm( ) {
        CRM_Utils_System::setTitle(ts('Sepa Direct Debit - Settings'));
 
 		$config = CRM_Core_Config::singleton();	
 		print_r($config->sepasettings_ooff_horizon_days);
 		$ret = CRM_Core_BAO_Setting::getItem('sepasettings', "sepasettings_ooff_horizon_days");
 		print_r($ret);

        $customFields = CRM_Core_BAO_CustomField::getFields();
        $cf = array();
        foreach ($customFields as $k => $v) {
            $cf[$k] = $v['label'];
        }

 		    // OOFF
        $this->addElement('text',
		                        'sepasettings_ooff_horizon_days',
		                        ts('OOFF horizon days')
		                        );
        $this->addElement('text',
                          'sepasettings_ooff_notice_days',
                          ts('OOFF notice days')
                          );
     		// RCUR
     		$this->addElement('text',
                          'sepasettings_rcur_horizon_days',
                          ts('RCUR horizon days')
                          );
 
        $this->addElement('text',
                          'sepasettings_rcur_notice_days',
                          ts('RCUR notice days')
                          );

        // FRST
 		    $this->addElement('text',
                          'sepasettings_frst_horizon_days',
                          ts('FRST horizon days')
                          );
 
        $this->addElement('text',
                          'sepasettings_frst_notice_days',
                          ts('FRST notice days')
                          );

        // System
        $this->addElement('text',
                          'sepasettings_update_lock_timeout',
                          ts('Update lock timeout')
                          );

        // Rules
        $this->addRule('sepasettings_ooff_horizon_days', 
                       ts('Please enter the horizon as number of days (integers only).'),
                      'positiveInteger');

        $this->addRule('sepasettings_ooff_notice_days', 
                       ts('Please enter the notice days as number of days (integers only).'),
                      'positiveInteger');

        $this->addRule('sepasettings_rcur_horizon_days', 
                       ts('Please enter the horizon as number of days (integers only).'),
                      'positiveInteger');

        $this->addRule('sepasettings_rcur_notice_days', 
                       ts('Please enter the notice days as number of days (integers only).'),
                      'positiveInteger');

        $this->addRule('sepasettings_frst_horizon_days', 
                       ts('Please enter the horizon as number of days (integers only).'),
                      'positiveInteger');

        $this->addRule('sepasettings_frst_notice_days', 
                       ts('Please enter the notice days as number of days (integers only).'),
                      'positiveInteger');

        $this->addRule('sepasettings_update_lock_timeout', 
                       ts('Please enter the lock timeout as number of days (integers only).'),
                      'positiveInteger');

        parent::buildQuickForm();
    }
}