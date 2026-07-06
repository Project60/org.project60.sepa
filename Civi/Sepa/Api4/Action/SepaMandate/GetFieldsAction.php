<?php
/*
 * Copyright (C) 2026 SYSTOPIA GmbH
 *
 * This program is free software: you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types = 1);

namespace Civi\Sepa\Api4\Action\SepaMandate;

use Civi\Api4\Extension;
use Civi\Api4\Generic\DAOGetFieldsAction;
use Civi\Api4\Service\Spec\SpecFormatter;
use CRM_Sepa_ExtensionUtil as E;

/**
 * @phpstan-type outputFormatterT callable(string $value, array<mixed> $row, array<string, mixed> $field): void
 * @phpstan-type sqlRendererT callable(array<string, mixed> $field, \Civi\Api4\Query\Api4SelectQuery): string
 * @phpstan-type fieldT array<string, array<string, scalar>|scalar[]|scalar|null|list<outputFormatterT>|sqlRendererT>
 */
final class GetFieldsAction extends DAOGetFieldsAction {

  /**
   * @return array<fieldT>
   */
  protected function getRecords(): array {
    /** @var array<string, fieldT> $fields */
    $fields = array_column(parent::getRecords(), NULL, 'name');

    /** @see \CRM_Sepa_BAO_SEPAMandate::self_hook_civicrm_pre() */
    $fields['creditor_id']['required'] = FALSE;
    $fields['date']['required'] = FALSE;
    $fields['reference']['required'] = FALSE;

    if ('createFull' === $this->getAction()) {
      unset($fields['entity_id']);
      unset($fields['entity_table']);

      $fields['contact_id']['required'] = TRUE;
      $fields['type']['required'] = TRUE;
      $fields['iban']['required'] = TRUE;

      $fields['status']['required'] = FALSE;

      // Note: For RCUR mandate the entity is actually "ContributionRecur".
      // To load the options via SpecFormatter::getOptions() we have to use a
      // correct entity and  cannot use something like "Contribution[Recur]".
      $fields['payment_instrument_id'] = [
        'type' => 'Field',
        'entity' => 'Contribution',
        'required' => FALSE,
        'nullable' => FALSE,
        'name' => 'payment_instrument_id',
        'title' => E::ts('Payment Method ID'),
        'data_type' => 'Integer',
        'options' => TRUE,
        // Suffixes are not supported. (Would be set automatically if not given or empty.)
        'suffixes' => [''],
        'options_callback' => [SpecFormatter::class, 'getOptions'],
        'input_type' => 'Select',
      ];

      $fields['financial_type_id'] = [
        'type' => 'Field',
        'entity' => 'Contribution',
        'required' => TRUE,
        'nullable' => FALSE,
        'name' => 'financial_type_id',
        'title' => E::ts('Financial Type ID'),
        'data_type' => 'Integer',
        'fk_entity' => 'FinancialType',
        'fk_column' => 'id',
        'options' => TRUE,
        // Suffixes are not supported. (Would be set automatically if not given or empty.)
        'suffixes' => [''],
        'options_callback' => [SpecFormatter::class, 'getOptions'],
        'input_type' => 'Select',
      ];

      $fields['contribution_contact_id'] = [
        'type' => 'Field',
        'entity' => 'Contribution',
        'required' => FALSE,
        'nullable' => FALSE,
        'name' => 'contribution_contact_id',
        'title' => E::ts('Contact ID for contribution'),
        'description' => E::ts('Can be used if contribution contact is different from mandate contact.'),
        'data_type' => 'Integer',
        'fk_entity' => 'Contact',
        'fk_column' => 'id',
        'input_type' => 'EntityRef',
      ];

      $fields['currency'] = [
        'type' => 'Field',
        'entity' => 'Contribution',
        'required' => FALSE,
        'nullable' => FALSE,
        'name' => 'currency',
        'title' => E::ts('Currency'),
        'description' => E::ts('Creditor currency will be used if not given.'),
        'data_type' => 'String',
        'options' => TRUE,
        // Suffixes are not supported. (Would be set automatically if not given or empty.)
        'suffixes' => [''],
        'options_callback' => [SpecFormatter::class, 'getOptions'],
        'input_type' => 'Select',
      ];

      $fields['contribution_status_id'] = [
        'type' => 'Field',
        'entity' => 'Contribution',
        'required' => FALSE,
        'nullable' => FALSE,
        'name' => 'contribution_status_id',
        'title' => E::ts('Contribution Status ID'),
        'data_type' => 'Integer',
        'options' => TRUE,
        // Only suffix "name" is supported.
        'suffixes' => ['name'],
        'options_callback' => [SpecFormatter::class, 'getOptions'],
        'input_type' => 'Select',
      ];

      // Mapped to "total_amount" for OOFF mandates-
      $fields['amount'] = [
        'type' => 'Field',
        'entity' => 'Contribution',
        'required' => TRUE,
        'nullable' => FALSE,
        'name' => 'amount',
        'title' => E::ts('Amount'),
        'data_type' => 'Money',
        'input_type' => 'Text',
      ];

      $fields['receive_date'] = [
        'type' => 'Field',
        'entity' => 'Contribution',
        'required' => FALSE,
        'nullable' => TRUE,
        'name' => 'receive_date',
        'title' => E::ts('Collection date (only for OOFF)'),
        'data_type' => 'Timestamp',
        'input_type' => 'Date',
        'input_attrs' => [
          'time' => TRUE,
          'date' => TRUE,
          'start_date_years' => 20,
          'end_date_years' => 10,
        ],
      ];

      $fields['start_date'] = [
        'type' => 'Field',
        'entity' => 'ContributionRecur',
        'required' => FALSE,
        'nullable' => FALSE,
        'name' => 'start_date',
        'title' => E::ts('Start of collection (only for RCUR)'),
        'data_type' => 'Timestamp',
        'input_type' => 'Date',
        'input_attrs' => [
          'time' => TRUE,
          'date' => TRUE,
          'start_date_years' => 20,
          'end_date_years' => 10,
        ],
      ];

      $fields['end_date'] = [
        'type' => 'Field',
        'entity' => 'ContributionRecur',
        'required' => FALSE,
        'nullable' => TRUE,
        'name' => 'end_date',
        'title' => E::ts('End of collection (only for RCUR)'),
        'data_type' => 'Timestamp',
        'input_type' => 'Date',
        'input_attrs' => [
          'time' => FALSE,
          'date' => TRUE,
          'start_date_years' => 20,
          'end_date_years' => 10,
        ],
      ];

      $fields['frequency_interval'] = [
        'default_value' => 1,
        'type' => 'Field',
        'entity' => 'ContributionRecur',
        'required' => FALSE,
        'nullable' => FALSE,
        'name' => 'frequency_interval',
        'title' => E::ts('Collection interval (together with frequency_unit, only for RCUR)'),
        'data_type' => 'Integer',
        'input_type' => 'Text',
      ];

      $fields['frequency_unit'] = [
        'default_value' => 'month',
        'type' => 'Field',
        'entity' => 'ContributionRecur',
        'required' => FALSE,
        'nullable' => TRUE,
        'name' => 'frequency_unit',
        'title' => E::ts('Collection interval unit (together with frequency_interval, only for RCUR)'),
        'data_type' => 'String',
        'options' => TRUE,
        'suffixes' => [
          'name',
          'label',
          'description',
        ],
        'options_callback' => [SpecFormatter::class, 'getOptions'],
        'input_type' => 'Select',
      ];

      $fields['cycle_day'] = [
        'default_value' => 1,
        'type' => 'Field',
        'entity' => 'ContributionRecur',
        'required' => FALSE,
        'nullable' => FALSE,
        'name' => 'cycle_day',
        'title' => E::ts('Day of the month (for collection, only for RCUR)'),
        'data_type' => 'Integer',
        'input_type' => 'Text',
      ];

      if ($this->isExtensionInstalled('civi_campaign')) {
        $fields['campaign_id'] = [
          'type' => 'Field',
          'entity' => 'ContributionRecur',
          'required' => FALSE,
          'nullable' => TRUE,
          'name' => 'campaign_id',
          'title' => E::ts('Campaign ID'),
          'data_type' => 'Integer',
          'fk_entity' => 'Campaign',
          'fk_column' => 'id',
          'input_type' => 'EntityRef',
        ];
      }

      $fieldsToGet = $this->_itemsToGet('name');
      if (FALSE !== $this->loadOptions) {
        $this->loadFieldOptions(
          $fields,
          $fieldsToGet ?? [
            'payment_instrument_id',
            'financial_type_id',
            'currency',
            'contribution_status_id',
            'frequency_unit',
          ]
        );
      }
    }

    return $fields;
  }

  private function isExtensionInstalled(string $extensionKey): bool {
    return 1 === Extension::get(FALSE)
      ->addWhere('key', '=', $extensionKey)
      ->addWhere('status', '=', 'installed')
      ->selectRowCount()
      ->execute()
      ->countMatched();
  }

}
