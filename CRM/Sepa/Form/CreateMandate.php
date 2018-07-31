<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 SYSTOPIA                       |
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
use CRM_Sepa_ExtensionUtil as E;

/**
 * SEPA Create Mandate form
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Sepa_Form_CreateMandate extends CRM_Core_Form {

  public function buildQuickForm() {

    // add creditor field
    $creditors = $this->getCreditors();
    $this->assign('sdd_creditors', json_encode($creditors));
    $this->add(
      'select',
      'creditor_id',
      E::ts('Creditor'),
      $this->getCreditorList($creditors),
      TRUE
    );

    // add contact field

    // add financial type

    // add campaign

    // add mandate reference

    // add source field

    // add bank account field

    // add iban field

    // add bic field

    // add type field
    $this->add(
        'select',
        'type',
        E::ts('Mandate Type'),
        array('OOFF' => E::ts("One-Off Collection (OOFF)"),
              'RCUR' => E::ts("Recurring Collection (RCUR)")),
        TRUE
    );

    // add amount field

    // add currency field

    // add 'replaces' fields

    // add OOFF fields
    // add collection date

    // add RCUR fields
    // add start date
    // add collection day
    // add interval
    // add end_date
    // show next collection




    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Create'),
        'isDefault' => TRUE,
      ),
    ));

    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();


//    CRM_Core_Session::setStatus(E::ts('You picked color "%1"', array(
//      1 => $options[$values['favorite_color']],
//    )));
    parent::postProcess();
  }



  // ############################# HELPER FUNCTIONS #############################

  /**
   * Get a list of all creditors, extended by
   *  - the list of cycle_days
   *  - a 'default' flag
   *  - the currency
   */
  protected function getCreditors() {
    $creditors = civicrm_api3('SepaCreditor', 'get', array());

    $default_creditor_id = CRM_Sepa_Logic_Settings::defaultCreditor();
    foreach ($creditors as &$creditor) {
      
    }
  }
}
