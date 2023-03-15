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
use CRM_Core_DAO_AllCoreTables;
use ReflectionException;
use ReflectionMethod;

class SepaMandateLink extends AbstractCivicrmEntitySource {

  /**
   * @var array
   */
  protected $entityTables = [];

  /**
   * Returns the entity name
   *
   * @return String
   */
  protected function getEntity(): string {
    return 'SepaMandateLink';
  }

  /**
   * Returns the table name of this entity
   *
   * @return String
   */
  protected function getTable(): string {
    return 'civicrm_sdd_entity_mandate';
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
    $dataSpecification->getFieldSpecificationByName('entity_table')->options = $this->getEntityTables();
  }

  protected function getEntityTables(): array {
    if (!$this->entityTables) {
      $this->entityTables = array();
      $allTables = CRM_Core_DAO_AllCoreTables::getCoreTables();
      foreach($allTables as $entity_table => $daoClass) {
        try {
          $r = new ReflectionMethod($daoClass, 'getEntityTitle');
          if ($r->getDeclaringClass()->getName() == $daoClass) {
            $this->entityTables[$entity_table] = call_user_func([$daoClass,'getEntityTitle']);
          }
        } catch (ReflectionException $e) {
        }
        if (!isset($this->entityTables[$entity_table])) {
          $this->entityTables[$entity_table] = CRM_Core_DAO_AllCoreTables::getBriefName($daoClass);
        }
      }
      asort($this->entityTables);
    }
    return $this->entityTables;
  }
}
