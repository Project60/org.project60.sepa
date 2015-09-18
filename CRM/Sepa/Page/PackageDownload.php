<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2015 Scardinius                     |
| Author: Scardinius                                     |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/


require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_PackageDownload extends CRM_Core_Page {

    function run() {

        $pid = (int)CRM_Utils_Request::retrieve('pid', 'Positive', $this);

        if ($pid > 0) {
            $apiChainRow = 'api.SepaMandateFileRow.get';
            $apiChainCreditor = 'api.SepaCreditor.get';
            $params = array(
                'id' => $pid,
                'sequential' => 1,
                $apiChainRow => array(
                    'mandate_file_id' => '$value.id'
                ),
                $apiChainCreditor => array(
                    'id' => '$value.creditor_id'
                ),
            );
            $result = civicrm_api3('SepaMandateFile', 'get', $params);
            if ($result['count'] == 1 && $result['values'][0][$apiChainRow]['count'] > 0) {
                $sepa_file_format_id = $result['values'][0][$apiChainCreditor]['values'][0]['sepa_file_format_id'];
                $fileFormat = CRM_Core_OptionGroup::getValue('sepa_file_format', $sepa_file_format_id, 'value', 'Integer', 'name');
                $fileFormat = CRM_Sepa_Logic_Format::sanitizeFileFormat($fileFormat);
                CRM_Sepa_Logic_Format::loadFormatClass($fileFormat);
                $classFormat = "CRM_Sepa_Logic_Format_".$fileFormat;
                $format = new $classFormat();

                header('Content-Type: text/xml; charset=utf-8');
                echo $format->getMandatePackage($result);
                CRM_Utils_System::civiExit();
            } else {
                CRM_Core_Error::fatal("Error occured! Corrupted data.");
                return;
            }
        } else {
            CRM_Core_Error::fatal("Error occured! Missing parameter");
            return;
        }
        parent::run();
    }

}
