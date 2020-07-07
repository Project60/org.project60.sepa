<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2018                                     |
| Author: B. Endres (endres@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

/**
 * takes care of the SDD payment instruments
 */
class CRM_Sepa_Logic_PaymentInstruments {

  protected static $sdd_pi_names            = array('FRST', 'RCUR', 'OOFF');

  // caches
  protected static $sdd_payment_instruments = NULL;
  protected static $contribution_id_to_pi   = array();

  /**
   * get the SDD payment instruments, indexed by name
   *
   * @todo adjust to https://github.com/Project60/org.project60.sepa/issues/572
   */
  public static function getSddPaymentInstruments() {
    if (self::$sdd_payment_instruments === NULL) {
      $instrument_query = civicrm_api3('OptionValue', 'get', array(
        'option_group_id' => 'payment_instrument',
        'name'            => array('IN' => array('FRST', 'RCUR', 'OOFF')),
        'return'          => 'value,name,label',
        'sequential'      => 0
      ));
      self::$sdd_payment_instruments = array();
      foreach ($instrument_query['values'] as $pi) {
        self::$sdd_payment_instruments[$pi['name']] = $pi;
      }
    }
    return self::$sdd_payment_instruments;
  }

  /**
   * the payment instrument ID for a given SDD type (RCUR,OOFF,FRST)
   *
   * @todo adjust to https://github.com/Project60/org.project60.sepa/issues/572
   */
  public static function getSddPaymentInstrumentID($sdd_name) {
    $payment_instruments = self::getSddPaymentInstruments();
    foreach ($payment_instruments as $payment_instrument) {
      if ($payment_instrument['name'] == $sdd_name) {
        return $payment_instrument['value'];
      }
    }
    return NULL;
  }

  /**
   * look up the SDD payment instrument for the given contribution
   *
   * @return OOFF, FRST, RCUR or NULL (if not SDD)
   *
   * @todo adjust to https://github.com/Project60/org.project60.sepa/issues/572
   */
  public static function getSDDPaymentInstrumentForContribution($contribution_id) {
    if (!array_key_exists($contribution_id, self::$contribution_id_to_pi)) {
      $contribution_pi = civicrm_api3('Contribution', 'getvalue', array(
        'return' => 'payment_instrument_id',
        'id'     => $contribution_id));

      $payment_instruments = self::getSddPaymentInstruments();
      foreach ($payment_instruments as $payment_instrument) {
        if ($payment_instrument['value'] == $contribution_pi) {
          // there's a match
          self::$contribution_id_to_pi[$contribution_id] = $payment_instrument['name'];
          return $payment_instrument['name'];
        }
      }

      // no match
      self::$contribution_id_to_pi[$contribution_id] = NULL;
      return NULL;
    } else {

      return self::$contribution_id_to_pi[$contribution_id];
    }
  }

  /**
   * Checks if a given contribution is a SEPA contribution.
   * This works for contribution and contribution_recur entities
   *
   * It simply checks, whether the contribution uses a SEPA payment instrument
   *
   * @param $contribution   an array with the attributes of the contribution
   *
   * @return true if the contribution is a SEPA contribution
   *
   * @todo adjust to https://github.com/Project60/org.project60.sepa/issues/572
   */
  public static function isSDD($contribution) {
    if (!empty($contribution['payment_instrument_id'])) {
      // if the payment_instrument is present -> great!
      $payment_instruments = self::getSddPaymentInstruments();
      foreach ($payment_instruments as $instrument) {
        if ($instrument['value'] == $contribution['payment_instrument_id']) {
          return TRUE;
        }
      }

      return FALSE;

    } elseif (!empty($contribution['id'])) {
      // if the ID is known, we can look it up
      $sdd_instrument = self::getSDDPaymentInstrumentForContribution($contribution['id']);
      return $sdd_instrument != NULL;
    }

    // we don't really know...
    return FALSE;
  }


  /**
   * restrict payment instruments in certain forms
   */
  public static function restrictPaymentInstrumentsInForm($formName, $form) {
    if ($formName == 'CRM_Contribute_Form_Contribution') {
      $payment_instruments = self::getSddPaymentInstruments();

      // is this a SEPA contribution?
      $my_sdd_pi = NULL;
      if ($form->_id) {
        // this is an edit
        $my_sdd_pi = self::getSDDPaymentInstrumentForContribution($form->_id);
      }

      if ($my_sdd_pi) {
        // this is a SEPA edit: remove all PIs except for ours
        $my_sdd_pi_ids[] = self::getSddPaymentInstrumentID($my_sdd_pi);
        CRM_Core_Resources::singleton()->addVars('sdd', array('pis_remove' => NULL));
        CRM_Core_Resources::singleton()->addVars('sdd', array('pis_keep'   => $my_sdd_pi_ids));

      } else {
        // this is a regular contributions: remove all SDD PIs
        $pi_ids[] = self::getSddPaymentInstrumentID('RCUR');
        $pi_ids[] = self::getSddPaymentInstrumentID('FRST');
        $pi_ids[] = self::getSddPaymentInstrumentID('OOFF');

        CRM_Core_Resources::singleton()->addVars('sdd', array('pis_keep'   => NULL));
        CRM_Core_Resources::singleton()->addVars('sdd', array('pis_remove' => $pi_ids));
      }

      // inject JS file
      CRM_Core_Resources::singleton()->addScriptFile('org.project60.sepa', 'js/form_adjustments/CRM/Contribute/Form/Contribution/manipulate_sdd_payment_instruments.js');
    }
  }

  /**
   * Get a list of all CiviSEPA creditors
   *
   * @return array
   *  [id => creditor data]
   */
  public static function getAllSddCreditors() {
    static $sdd_creditors = NULL;
    if ($sdd_creditors === NULL) {
      $sdd_creditors = [];
      $creditors = civicrm_api3('SepaCreditor', 'get', [
        'option.limit' => 0,
      ]);
      foreach ($creditors['values'] as $creditor) {
        $sdd_creditors[$creditor['id']] = $creditor;
      }
    }
    return $sdd_creditors;
  }

  /**
   * Get a list of all payment instruments used by CiviSEPA
   *
   * @return array
   *   id => [name, label, id]
   */
  public static function getAllSddPaymentInstruments()
  {
    static $sdd_payment_instruments = NULL;
    if ($sdd_payment_instruments === NULL) {
      $sdd_payment_instruments = [];

      // first, collect all payment instruments in use
      $creditors = self::getAllSddCreditors();
      $pi_ids = [];

      // collect OOFF payment instruments
      foreach ($creditors as $creditor) {
        $creditor_id = (int) $creditor['id'];
        foreach (explode(',', $creditor['pi_ooff']) as $pi_value) {
          $pi_id = (int) $pi_value;
          if ($pi_id) {
            $pi_ids[] = $pi_id;
          }
        }
      }

      // collect RCUR payment instruments
      foreach ($creditors as $creditor) {
        foreach (explode(',', $creditor['pi_rcur']) as $pi_value) {
          if (strstr($pi_value, '-')) {
            // this is a frst-rcur combo
            $frst_rcur = explode('-', $pi_value, 2);
            $pi_frst = (int) $frst_rcur[0];
            $pi_rcur = (int) $frst_rcur[1];
            if ($pi_frst) {
              $pi_ids[] = $pi_frst;
            }
            if ($pi_rcur) {
              $pi_ids[] = $pi_rcur;
            }

          } else {
            // this is a simple pi
            $pi_id = (int) $pi_value;
            if ($pi_id) {
              $pi_ids[] = $pi_id;
            }
          }
        }
      }

      // now load all of those payment instruments used by creditors
      $instruments = civicrm_api3('OptionValue', 'get', [
        'option_group_id' => 'payment_instrument',
        'value'           => ['IN' => $pi_ids],
        'is_active'       => 1,
        'return'          => 'value,name,label',
      ]);
      foreach ($instruments['values'] as $instrument) {
        $payment_instrument_id = $instrument['value'];
        $instrument['id'] = $payment_instrument_id;
        $sdd_payment_instruments[$payment_instrument_id] = $instrument;
      }
    }
    return $sdd_payment_instruments;
  }


  /**
   * Get the payment instruments used for the given creditor
   *
   * @param integer $creditor_id
   *    SddCreditor ID
   * @param string $type
   *    one of ['OOFF', 'FRST', 'RCUR']
   *
   * @return array
   *    list of payment instrument data
   */
  public static function getPaymentInstrumentsForCreditor($creditor_id, $type)
  {
    // get the creditor
    $creditors = self::getAllSddCreditors();
    $creditor = CRM_Utils_Array::value($creditor_id, $creditors);
    if (!$creditor) {
      return NULL; // creditor not found
    }

    // now extract the IDs as defined by the creditor
    $payment_instrument_ids = [];
    if ($type == 'OOFF') {
      $payment_instrument_ids = explode(',', $creditor['pi_ooff']);

    } elseif ($type == 'FRST' || $type == 'RCUR') {
      foreach (explode(',', $creditor['pi_rcur']) as $pi_spec) {
        if (strstr($pi_spec, '-')) {
          // this is a frst-rcur combo
          $frst_rcur = explode('-', $pi_spec, 2);
          if ($type == 'FRST') {
            $payment_instrument_ids[] = (int) $frst_rcur[0];
          } else {
            $payment_instrument_ids[] = (int) $frst_rcur[1];
          }
        } else {
          // this is one simple type
          $payment_instrument_ids[] = (int) $pi_spec;
        }
      }

    } else {
      Civi::log()->warning("Invalid type '{$type}' passed to CRM_Sepa_Logic_PaymentInstruments::getPaymentInstrumentsForCreditor()");
    }

    // strip duplicates
    $payment_instrument_ids = array_unique($payment_instrument_ids);

    // now return a subset of the payment instruments (there's probably some array_xxx magic for this...)
    $result_payment_instruments = [];
    foreach (self::getAllSddPaymentInstruments() as $existing_payment_instrument) {
      if (in_array($existing_payment_instrument['id'], $payment_instrument_ids)) {
        $result_payment_instruments[$existing_payment_instrument['id']] = $existing_payment_instrument;
      }
    }

    return $result_payment_instruments;
  }
}
