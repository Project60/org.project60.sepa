<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2019 SYSTOPIA                            |
| Author: B. Endres (endres -at- systopia.de)            |
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

namespace Civi\Sepa\ActionProvider\Action;

use Civi\ActionProvider\Action\AbstractAction;
use \Civi\ActionProvider\Parameter\ParameterBagInterface;
use \Civi\ActionProvider\Parameter\Specification;
use \Civi\ActionProvider\Parameter\SpecificationBag;

use Civi\FormProcessor\API\Exception;
use Civi\Sepa\DataProcessor\Source\SepaMandate;
use CRM_Sepa_ExtensionUtil as E;

class TerminateMandate extends AbstractAction {

  /**
   * Returns the specification of the configuration options for the actual action.
   *
   * @return SpecificationBag specs
   */
  public function getConfigurationSpecification() {
    return new SpecificationBag([
      // required fields
      new Specification('config_cancel_reason', 'String', E::ts('Cancel Reason (if no parameter)'), TRUE),
    ]);
  }

  /**
   * Returns the specification of the parameters of the actual action.
   *
   * @return SpecificationBag specs
   */
  public function getParameterSpecification() {
    return new SpecificationBag([
        // required fields
        new Specification('reference', 'String', E::ts('Mandate Reference'), TRUE),
        new Specification('cancel_reason', 'String', E::ts('Cancel Reason')),
    ]);
  }

  /**
   * Returns the specification of the output parameters of this action.
   *
   * This function could be overridden by child classes.
   *
   * @return SpecificationBag specs
   */
  public function getOutputSpecification() {
    return new SpecificationBag([
      new Specification('mandate_id', 'Integer', E::ts('Mandate ID')),
    ]);
  }

  /**
   * Run the action
   *
   * @param ParameterBagInterface $parameters
   *   The parameters to this action.
   * @param ParameterBagInterface $output
   *   The parameters this action can send back
   * @return void
   * @throws \Exception
   */
  protected function doAction(ParameterBagInterface $parameters, ParameterBagInterface $output): void {
    $mandateReference = $parameters->getParameter('reference');
    if ($mandateReference) {
      // find mandate ID with reference
      try {
        $mandateId = \Civi\Api4\SepaMandate::get(TRUE)
          ->addSelect('id')
          ->addWhere('reference', '=', $mandateReference)
          ->execute()
          ->single()['id'];
        if ($mandateId) {
          $output->setParameter('mandate_id', $mandateId);
          // terminate mandate with cancel reason from parameter if provided else condiguration cancel reason
          $cancelReason = $parameters->getParameter('cancel_reason');
          if (!$cancelReason) {
            $cancelReason = $this->configuration->getParameter('config_cancel_reason');
          }
          $terminateDate = new \DateTime();
          \CRM_Sepa_BAO_SEPAMandate::terminateMandate((int) $mandateId, $terminateDate->format("YmdHis"), $cancelReason);
          // the BAO function terminateMandate does everything aport from set the status to COMPLETE for a RCUR mandate
          $update = "UPDATE civicrm_sdd_mandate SET status = %1 WHERE id = %2 AND type = %3";
          $updateParams = [
            1 => ["COMPLETE", "String"],
            2 => [(int) $mandateId, "Integer"],
            3 => ["RCUR", "String"],
          ];
        }
        \CRM_Core_DAO::executeQuery($update, $updateParams);
      }
      catch (\CiviCRM_API3_Exception $ex) {
      }
    }
  }
}
