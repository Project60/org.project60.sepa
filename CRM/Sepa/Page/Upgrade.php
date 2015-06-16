<?php
require_once 'packages/array_column/array_column.php';

require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_Upgrade extends CRM_Core_Page {
  function run() {
    $messages = array();

    if (!CRM_Core_DAO::checkFieldExists('civicrm_sdd_txgroup', 'is_cor1')) {
      CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_sdd_txgroup` ADD `is_cor1` tinyint COMMENT 'Instrument for payments in this group will be COR1 (true/1) or CORE (false/0).' AFTER `reference`");
      $messages[] = 'Added `civicrm_sdd_txgroup`.`is_cor1`.';
    }

    if (!CRM_Core_DAO::checkFieldExists('civicrm_sdd_creditor', 'extra_advance_days')) {
      CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_sdd_creditor` ADD `extra_advance_days` int unsigned DEFAULT 1 COMMENT 'How many banking days (if any) to add on top of all minimum advance presentation deadlines defined in the SEPA rulebook.'");
      $messages[] = 'Added `civicrm_sdd_creditor`.`extra_advance_days`.';
    }
    if (!CRM_Core_DAO::checkFieldExists('civicrm_sdd_creditor', 'maximum_advance_days')) {
      CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_sdd_creditor` ADD `maximum_advance_days` tinyint DEFAULT 14 COMMENT 'When generating SEPA XML files, include payments up to this many calendar days from now. (14 is the minimum banks have to allow according to rulebook.)'");
      $messages[] = 'Added `civicrm_sdd_creditor`.`maximum_advance_days`.';
    /* Fix up comment typo we created in some versions. */
    } elseif (CRM_Core_DAO::singleValueQuery("SELECT COLUMN_COMMENT LIKE '%calender%' FROM INFORMATION_SCHEMA.COLUMNS WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'civicrm_sdd_creditor' AND `COLUMN_NAME` = 'maximum_advance_days'")) {
      CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_sdd_creditor` MODIFY `maximum_advance_days` tinyint DEFAULT 14 COMMENT 'When generating SEPA XML files, include payments up to this many calendar days from now. (14 is the minimum banks have to allow according to rulebook.)'");
      $messages[] = 'Fixed comment typo for `civicrm_sdd_creditor`.`maximum_advance_days`.';
    }
    if (!CRM_Core_DAO::checkFieldExists('civicrm_sdd_creditor', 'use_cor1')) {
      CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_sdd_creditor` ADD `use_cor1` tinyint DEFAULT 0 COMMENT 'Generate SEPA XML files using \"Local Instrument\" COR1 instead of CORE (along with the shorter minimum advance presentation deadlines) for domestic payments.'");
      $messages[] = 'Added `civicrm_sdd_creditor`.`use_cor1`.';
    }
    if (!CRM_Core_DAO::checkFieldExists('civicrm_sdd_creditor', 'group_batching_mode')) {
      CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_sdd_creditor` ADD `group_batching_mode` varchar(4) DEFAULT \"COR\" COMMENT 'How to batch TxGroups into files. NONE: every TxGroup in a separate file; TYPE: one file for each Sequence Type (FRST/RCUR/OOFF); COR: one file for all COR1 and one for all CORE; ALL: single file with all groups.'");
      $messages[] = 'Added `civicrm_sdd_creditor`.`group_batching_mode`.';
    }
    if (!CRM_Core_DAO::checkFieldExists('civicrm_sdd_creditor', 'month_wrap_policy')) {
      CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_sdd_creditor` ADD `month_wrap_policy` varchar(4) DEFAULT \"PRE\" COMMENT 'How to handle due dates of recurring payment installments (using \'month\' or \'year\' `frequency_unit`) that would wrap over into next month. PRE: move date before end of month; POST: wrap to 1st of next month; NONE: no explicit handling (February payments might wrap up to 3 days into March).'");
      $messages[] = 'Added `civicrm_sdd_creditor`.`month_wrap_policy`.';
    }

    if (!CRM_Core_DAO::checkFieldExists('civicrm_sdd_creditor', 'remittance_info')) {
      CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_sdd_creditor` ADD `remittance_info` varchar(140) NOT NULL  DEFAULT '' COMMENT 'String used as the <RmtInf> value for each collection in SEPA XML files.' AFTER `category`");
      $messages[] = 'Added `civicrm_sdd_creditor`.`remittance_info`.';
    }

    $instruments = implode(',', array_map(
      function ($type) { return CRM_Core_OptionGroup::getValue('payment_instrument', $type, 'name'); },
      array('FRST', 'RCUR', 'OOFF')
    ));
    if (CRM_Core_DAO::singleValueQuery("
      SELECT EXISTS (
        SELECT * FROM `civicrm_financial_trxn`
        WHERE `payment_instrument_id` IS NULL
          AND (
            SELECT `payment_instrument_id`
            FROM `civicrm_contribution`
            WHERE `id` = (
              SELECT `entity_id`
              FROM `civicrm_entity_financial_trxn`
              WHERE `entity_table` = 'civicrm_contribution' AND `financial_trxn_id` = `civicrm_financial_trxn`.`id`
            )
          ) IN($instruments)
      )
    ")) {
      $dao = CRM_Core_DAO::executeQuery("
        UPDATE `civicrm_financial_trxn`
        SET `payment_instrument_id` = (
          SELECT `payment_instrument_id`
          FROM `civicrm_contribution`
          WHERE `id` = (
            SELECT `entity_id`
            FROM `civicrm_entity_financial_trxn`
            WHERE `entity_table` = 'civicrm_contribution' AND `financial_trxn_id` = `civicrm_financial_trxn`.`id`
          )
        )
        WHERE `payment_instrument_id` IS NULL
          AND (
            SELECT `payment_instrument_id`
            FROM `civicrm_contribution`
            WHERE `id` = (
              SELECT `entity_id`
              FROM `civicrm_entity_financial_trxn`
              WHERE `entity_table` = 'civicrm_contribution' AND `financial_trxn_id` = `civicrm_financial_trxn`.`id`
            )
          ) IN($instruments)
      ");
      $rows = $dao->affectedRows();
      $messages[] = "Fixed $rows missing `payment_instrument_id` values in `civicrm_financial_trxn` records for SEPA Contributions.";
    }

    if (CRM_Core_DAO::singleValueQuery("SELECT CHARACTER_MAXIMUM_LENGTH != 35 FROM INFORMATION_SCHEMA.COLUMNS WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'civicrm_sdd_creditor' AND `COLUMN_NAME` = 'mandate_prefix'")) {
      CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_sdd_creditor` MODIFY `mandate_prefix` varchar(35) COMMENT 'Actually more a Creditor prefix -- it\'s used in various other references (<EndToEndId>, <PmtInfId>, and usually <MsgId>) as well.'");
      $messages[] = 'Updated field length (and comment) for `civicrm_sdd_creditor`.`mandate_prefix`.';
    }

    if (CRM_Core_DAO::singleValueQuery("SELECT `name` != 'SEPA Direct Debit payments' FROM `civicrm_extension` WHERE `full_name` = 'org.project60.sepa'")) {
      CRM_Core_DAO::executeQuery("UPDATE `civicrm_extension` SET `name` = 'SEPA Direct Debit payments', `label` = 'SEPA Direct Debit payments' WHERE `full_name` = 'org.project60.sepa'");
      $messages[] = 'Updated `name` and `label` in `civicrm_extension` to match newer info.xml.';
    }

    if (!civicrm_api3('OptionGroup', 'getvalue', array('name' => 'sepa_file_format', 'return' => 'is_locked'))) {
      civicrm_api3('OptionGroup', 'getsingle', array(
        'name' => 'sepa_file_format',
        'api.OptionGroup.setvalue' => array('id' => '$value.id', 'field' => 'is_locked', 'value' => 1),
      ));
      $messages[] = 'Set `is_locked` for "SEPA File Formats" Option Group.';
    }

    $optionGroups = array(
      'msg_tpl_workflow_contribution' => array('sepa_mandate_pdf', 'sepa_mandate'),
      'payment_instrument' => array('FRST', 'RCUR', 'OOFF'),
      'contribution_status' => array('Batched'),
    );
    foreach ($optionGroups as $groupName => $groupValues) {
      $optionGroup = civicrm_api3('OptionGroup', 'getsingle', array('name' => $groupName));
      foreach ($groupValues as $valueName) {
        $optionValue = civicrm_api3('OptionValue', 'getsingle', array('option_group_id' => $optionGroup['id'], 'name' => $valueName));

        if (civicrm_api3('OptionValue', 'getcount', array('option_group_id' => $optionGroup['id'], 'weight' => $optionValue['weight'])) > 1) { /* This Option Value has the same `weight` as some other in this group => need to fix. */
          $newWeight = CRM_Core_BAO_OptionValue::getDefaultWeight(array('option_group_id' => $optionGroup['id']));
          civicrm_api3('OptionValue', 'setvalue', array('id' => $optionValue['id'], 'field' => 'weight', 'value' => $newWeight));

          $messages[] = "Fixed `weight` for Option Value \"$valueName\" in Option Group \"$groupName\".";
        }
      }
    }

    /* Purge surplus SEPA File Format option values, and turn the correct ones into "managed" entities. */
    {
      $optionGroupID = civicrm_api3('OptionGroup', 'getvalue', array('name' => 'sepa_file_format', 'return' => 'id'));
      $optionValues = civicrm_api3('OptionValue', 'get', array('option_group_id' => $optionGroupID, 'options' => array('sort' => 'id', 'limit' => 0)));
      $options = $optionValues['values'];

      $correctEntries = array_unique(array_column($options, 'name', 'id'));
      $correctOptions = array_intersect_key($options, $correctEntries);
      $wrongOptions = array_diff_key($options, $correctEntries);

      if (!empty($wrongOptions)) {
        $wrongValues = array_column($wrongOptions, 'value');
        $wrongCreditors = civicrm_api3('SepaCreditor', 'get', array('sepa_file_format_id' => array('IN' => $wrongValues)));

        if ($wrongCreditors['count']) {
          $valueToName = array_column($options, 'name', 'value');
          $nameToCorrectValue = array_column($correctOptions, 'value', 'name');

          foreach ($wrongCreditors['values'] as $creditor) {
            civicrm_api3('SepaCreditor', 'setvalue', array(
              'id' => $creditor['id'],
              'field' => 'sepa_file_format_id',
              'value' => $nameToCorrectValue[$valueToName[$creditor['sepa_file_format_id']]]
            ));
          }
          $updateMessage = ", and updated {$wrongCreditors['count']} Creditor(s) accordingly";
        } else {
          $updateMessage = '';
        }

        foreach ($wrongOptions as $id => $_) {
          civicrm_api3('OptionValue', 'delete', array('id' => $id));
        }
        $messages[] = "Dropped " . count($wrongOptions) . " surplus \"SEPA File Format\" Option Values$updateMessage.";
      }

      /* Add "managed" entries. */
      foreach ($correctOptions as $option) {
        /* Have to use DAO here: there is no API for this; and creating new records with SQL is too fragile. */
        $dao = new CRM_Core_DAO_Managed();
        $dao->module = 'org.project60.sepa';
        $dao->name = "SEPA File Format {$option['name']}";
        if (!$dao->find()) {
          $dao->entity_type = 'OptionValue';
          $dao->entity_id = $option['id'];
          $dao->save();

          $messages[] = "Turned '{$option['name']}' \"SEPA File Format\" Option Value into a \"managed\" entity.";
        }
      }
    }

    {
      $result = civicrm_api3('OptionGroup', 'getsingle', array(
        'name' => 'contribution_status',
        'api.OptionValue.getsingle' => array(
          'name' => 'Batched',
        )
      ));
      $batchedOptionValue = $result['api.OptionValue.getsingle'];
      if (!$batchedOptionValue['is_reserved']) {
        civicrm_api3('OptionValue', 'setvalue', array('id' => $batchedOptionValue['id'], 'field' => 'is_reserved', 'value' => 1));
        $messages[] = "Marked 'Batched' Contribution Status as \"reserved\".";
      }

      /* Have to use DAO here: there is no API for this; and creating new records with SQL is too fragile. */
      $dao = new CRM_Core_DAO_Managed();
      $dao->module = 'org.project60.sepa';
      $dao->name = 'Batched Contribution Status';
      if (!$dao->find()) {
        $dao->entity_type = 'OptionValue';
        $dao->entity_id = $batchedOptionValue['id'];
        $dao->cleanup = 'unused';
        $dao->save();
        $messages[] = "Turned 'Batched' Contribution Status into a \"managed\" entity.";
      }
    }

    $instruments = array(
      'FRST' => 'SEPA Payment Instrument FRST',
      'RCUR' => 'SEPA Payment Instrument RCUR',
      'OOFF' => 'SEPA Payment Instrument OOFF',
    );
    foreach ($instruments as $instrumentName => $managedName) {
      $result = civicrm_api3('OptionGroup', 'getsingle', array(
        'name' => 'payment_instrument',
        'api.OptionValue.getsingle' => array(
          'name' => $instrumentName,
        )
      ));
      $optionValue = $result['api.OptionValue.getsingle'];
      if (!$optionValue['is_reserved']) {
        civicrm_api3('OptionValue', 'setvalue', array('id' => $optionValue['id'], 'field' => 'is_reserved', 'value' => 1));
        $messages[] = "Marked '$instrumentName' Payment Instrument as \"reserved\".";
      }

      /* Have to use DAO here: there is no API for this; and creating new records with SQL is too fragile. */
      $dao = new CRM_Core_DAO_Managed();
      $dao->module = 'org.project60.sepa';
      $dao->name = $managedName;
      if (!$dao->find()) {
        $dao->entity_type = 'OptionValue';
        $dao->entity_id = $optionValue['id'];
        $dao->cleanup = 'unused';
        $dao->save();
        $messages[] = "Turned '$instrumentName' Payment Instrument into a \"managed\" entity.";
      }
    }

    $templates = array(
      'sepa_mandate_pdf' => 'Mandate Template (PDF variant)',
      'sepa_mandate' => 'Mandate Template (HTML variant)',
    );
    foreach ($templates as $templateName => $managedName) {
      $result = civicrm_api3('OptionGroup', 'getsingle', array(
        'name' => 'msg_tpl_workflow_contribution',
        'api.OptionValue.getsingle' => array(
          'name' => $templateName,
        )
      ));
      $optionValue = $result['api.OptionValue.getsingle'];
      if (!$optionValue['is_reserved']) {
        civicrm_api3('OptionValue', 'setvalue', array('id' => $optionValue['id'], 'field' => 'is_reserved', 'value' => 1));
        $messages[] = "Marked '$templateName' Message Template as \"reserved\".";
      }

      /* Have to use DAO here: there is no API for this; and creating new records with SQL is too fragile. */
      $dao = new CRM_Core_DAO_Managed();
      $dao->module = 'org.project60.sepa';
      $dao->name = $managedName;
      if (!$dao->find()) {
        $dao->entity_type = 'OptionValue';
        $dao->entity_id = $optionValue['id'];
        $dao->cleanup = 'never';
        $dao->save();
        $messages[] = "Turned '$templateName' Message Template into a \"managed\" entity.";
      }
    }

    $group = civicrm_api3('CustomGroup', 'getsingle', array('name' => 'sdd_contribution'));
    if (!$group['collapse_adv_display']) {
      civicrm_api3('CustomGroup', 'setvalue', array('id' => $group['id'], 'field' => 'collapse_adv_display', 'value' => 1));
      $messages[] = "Set the `collapse_adv_display` flag for the SEPA \"Recurring Contribution\" Custom Group.";
    }
    if (!$group['is_reserved']) {
      civicrm_api3('CustomGroup', 'setvalue', array('id' => $group['id'], 'field' => 'is_reserved', 'value' => 1));
      $messages[] = "Marked the SEPA \"Recurring Contribution\" Custom Group as \"reserved\".";
    }

    $field = civicrm_api3('CustomField', 'getsingle', array('name' => 'sdd_contribution_sequence_number'));
    if (!$field['is_view']) {
      civicrm_api3('CustomField', 'setvalue', array('id' => $field['id'], 'field' => 'is_view', 'value' => 1));
      $messages[] = "Set the SEPA \"Sequence Number\" Custom Field to view-only.";
    }

    $this->assign('messages', $messages);
    parent::run();
  }
}
