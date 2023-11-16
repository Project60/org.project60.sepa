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

namespace Civi\Sepa\DataProcessor\Join;

use Civi\DataProcessor\DataFlow\AbstractDataFlow;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinInterface;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\SimpleJoin;
use Civi\DataProcessor\DataFlow\SqlDataFlow;
use Civi\DataProcessor\DataFlow\SqlTableDataFlow;
use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\ProcessorType\AbstractProcessorType;
use Civi\DataProcessor\Source\SourceInterface;
use Civi\DataProcessor\Utils\Sql;
use CRM_Core_Exception;
use CRM_Core_Form;
use CRM_Dataprocessor_Utils_DataSourceFields;
use CRM_Sepa_ExtensionUtil as E;
use Exception;

abstract class AbstractMandateJoin extends SimpleJoin {

  /**
   * Returns the entity table.
   *
   * @return string
   */
  abstract protected function getEntityTable(): string;

  /**
   * @var AbstractProcessorType
   */
  protected $dataProcessor;

  /**
   * @param AbstractProcessorType $dataProcessor
   * @return \Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinInterface
   * @throws \Exception
   */
  public function setDataProcessor(AbstractProcessorType $dataProcessor): JoinInterface {
    parent::setDataProcessor($dataProcessor);
    $this->dataProcessor = $dataProcessor;
    return $this;
  }


  /**
   * Returns true when this join has additional configuration
   *
   * @return bool
   */
  public function hasConfiguration(): bool {
    return true;
  }

  /**
   * When this join has additional configuration you can add
   * the fields on the form with this function.
   *
   * @param \CRM_Core_Form $form
   * @param SourceInterface $joinFromSource
   * @param SourceInterface[] $joinableToSources
   * @param array $joinConfiguration
   *   The current join configuration
   */
  public function buildConfigurationForm(CRM_Core_Form $form, SourceInterface $joinFromSource, $joinableToSources, $joinConfiguration=array()) {
    $leftFieldCallback = null;
    $lookForRightSddEntityIdField = true;
    if ($joinFromSource->getDataFlow() instanceof SqlTableDataFlow && $joinFromSource->getDataFlow()->getTable() == 'civicrm_sdd_mandate') {
      $leftFieldCallback = [$this, 'filterEntityIdField'];
      $lookForRightSddEntityIdField = false;
    }
    $leftFields = [];
    try {
      $leftFields = CRM_Dataprocessor_Utils_DataSourceFields::getAvailableFieldsInDataSource($joinFromSource, '', '', $leftFieldCallback);
    }
    catch (Exception $e) {
    }

    try {
      $form->add('select', 'left_field', ts('Select field'), $leftFields, TRUE, [
        'style' => 'min-width:250px',
        'class' => 'crm-select2 huge',
        'placeholder' => E::ts('- select -'),
      ]);
    }
    catch (CRM_Core_Exception $e) {
    }

    $rightFields = array();
    foreach($joinableToSources as $joinToSource) {
      try {
        if ($lookForRightSddEntityIdField && $joinToSource->getDataFlow() instanceof SqlTableDataFlow && $joinToSource->getDataFlow()
            ->getTable() == 'civicrm_sdd_mandate') {
          $rightFields = array_merge($rightFields, CRM_Dataprocessor_Utils_DataSourceFields::getAvailableFieldsInDataSource($joinToSource, $joinToSource->getSourceTitle() . ' :: ', $joinToSource->getSourceName() . '::', [
            $this,
            'filterEntityIdField',
          ]));
        }
        elseif (!$lookForRightSddEntityIdField) {
          $rightFields = array_merge($rightFields, CRM_Dataprocessor_Utils_DataSourceFields::getAvailableFieldsInDataSource($joinToSource, $joinToSource->getSourceTitle() . ' :: ', $joinToSource->getSourceName() . '::'));
        }
      }
      catch (Exception $e) {
      }
    }

    try {
      $form->add('select', 'right_field', ts('Select field'), $rightFields, TRUE, [
        'style' => 'min-width:250px',
        'class' => 'crm-select2 huge',
        'placeholder' => E::ts('- select -'),
      ]);
    }
    catch (CRM_Core_Exception $e) {
    }

    try {
      $form->add('select', 'mandate_join_type', ts('Type'), [
        'INNER' => E::ts('Required'),
        'LEFT' => E::ts('Not required'),
      ], TRUE, [
        'style' => 'min-width:250px',
        'class' => 'crm-select2 huge',
        'placeholder' => E::ts('- select -'),
      ]);
    }
    catch (CRM_Core_Exception $e) {
    }

    $defaults = array();
    if (isset($joinConfiguration['left_field'])) {
      $defaults['left_field'] = $joinConfiguration['left_field'];
    }
    if (isset($joinConfiguration['right_prefix'])) {
      $defaults['right_field'] = $joinConfiguration['right_prefix']."::".$joinConfiguration['right_field'];
    }
    if (!isset($joinConfiguration['mandate_join_type'])) {
      $joinConfiguration['mandate_join_type'] = 'LEFT';
    }
    $defaults['mandate_join_type'] = $joinConfiguration['mandate_join_type'];
    $form->setDefaults($defaults);
  }

  /**
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $field
   *
   * @return bool
   */
  public function filterEntityIdField(FieldSpecification $field): bool {
    if ($field->getName() == 'entity_id') {
      return true;
    }
    return false;
  }

  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @param SourceInterface $joinFromSource
   * @return array
   */
  public function processConfiguration($submittedValues, SourceInterface $joinFromSource): array {
    $configuration = parent::processConfiguration($submittedValues, $joinFromSource);
    $configuration['mandate_join_type'] = $submittedValues['mandate_join_type'];
    return $configuration;
  }

  /**
   * @param array $configuration
   *
   * @return \Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinInterface
   */
  public function setConfiguration($configuration): JoinInterface {
    parent::setConfiguration($configuration);
    $this->setType($configuration['mandate_join_type']);
    return $this;
  }

  /**
   * Returns true when this join is compatible with this data flow
   *
   * @param \Civi\DataProcessor\DataFlow\AbstractDataFlow $dataFlow
   * @return bool
   */
  public function worksWithDataFlow(AbstractDataFlow $dataFlow): bool {
    if (!$dataFlow instanceof SqlDataFlow) {
      return false;
    }
    $this->initialize();
    if ($dataFlow->getTableAlias() == $this->left_table) {
      return true;
    }
    if ($dataFlow->getTableAlias() == $this->right_table) {
      return true;
    }
    return false;
  }

  /**
   * Returns the SQL join statement
   *
   * For example:
   *  INNER JOIN civicrm_contact source_3 ON source_3.id = source_2.contact_id
   * OR
   *  LEFT JOIN civicrm_contact source_3 ON source3.id = source_2.contact_id
   *
   * @param \Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription $sourceDataFlowDescription
   *   The source data flow description used to genereate the join stament.
   *
   * @return string
   */
  public function getJoinClause(DataFlowDescription $sourceDataFlowDescription): string {
    $this->initialize();
    $tablePart = '';
    $joinClause = '';
    $mandateTableAlias = "`$this->left_table`";
    if ($this->right_source->getDataFlow() instanceof SqlTableDataFlow && $this->right_source->getDataFlow()->getTable() == 'civicrm_sdd_mandate') {
      $mandateTableAlias = "`$this->right_table`";
    }
    if ($sourceDataFlowDescription->getJoinSpecification()) {
      $joinClauses = [];
      $leftColumnName = "`$this->left_table`.`$this->left_field`";
      if ($this->leftFieldSpec) {
        $leftColumnName = $this->leftFieldSpec->getSqlColumnName($this->left_table);
      }
      $rightColumnName = "`$this->right_table`.`$this->right_field`";
      if ($this->rightFieldSpec) {
        $rightColumnName = $this->rightFieldSpec->getSqlColumnName($this->right_table);
      }

      $joinClauses[] = "($leftColumnName = $rightColumnName AND $mandateTableAlias.`entity_table` = '" . $this->getEntityTable() . "')";
      $joinClause = "ON (" . implode(" OR ", $joinClauses) . ")";
    }
    if ($sourceDataFlowDescription->getDataFlow() instanceof SqlDataFlow) {
      $tablePart = $sourceDataFlowDescription->getDataFlow()->getTableStatement();
    }

    $dataFlow = $sourceDataFlowDescription->getDataFlow();
    if ($dataFlow  instanceof  SqlDataFlow) {
      $whereClauses = $dataFlow->getWhereClauses(TRUE, FALSE);
      foreach($whereClauses as $whereClause) {
        if ($whereClause && $whereClause->isJoinClause()) {
          $this->filterClauses[] = $whereClause;
          $dataFlow->removeWhereClause($whereClause);
        }
      }
    }
    $extraClause = Sql::generateConditionStatement($this->filterClauses);
    if (strlen($extraClause)) {
      $extraClause = " AND ".$extraClause;
    }

    return "$this->type JOIN $tablePart $joinClause $extraClause";
  }

}
