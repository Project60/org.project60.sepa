<?php
/**
 * Copyright (C) 2023  Jaap Jansma (jaap.jansma@civicoop.org)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Civi\Sepa\DataProcessor\Source;

use Civi\DataProcessor\DataSpecification\DataSpecification;
use Civi\DataProcessor\Source\AbstractCivicrmEntitySource;

class SepaTransactionGroup extends AbstractCivicrmEntitySource {

  /**
   * Returns the entity name
   *
   * @return String
   */
  protected function getEntity(): string {
    return 'SepaTransactionGroup';
  }

  /**
   * Returns the table name of this entity
   *
   * @return String
   */
  protected function getTable(): string {
    return 'civicrm_sdd_txgroup';
  }

  /**
   * Load the fields from this entity.
   *
   * @param DataSpecification $dataSpecification
   * @param array $fieldsToSkip
   *
   * @throws \Civi\DataProcessor\DataSpecification\FieldExistsException
   */
  protected function loadFields(DataSpecification $dataSpecification, $fieldsToSkip = []) {
    parent::loadFields($dataSpecification, $fieldsToSkip);
    $dataSpecification->getFieldSpecificationByName('type')->options = [
      'FRST' => 'FRST',
      'RCUR' => 'RCUR',
      'OOFF' => 'OOFF',
    ];
  }
}
