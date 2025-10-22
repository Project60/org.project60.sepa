<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2018-2020                                |
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

  // caches
  protected static $contribution_id_to_pi   = [];
  protected static $sdd_creditors           = NULL;
  protected static $sdd_payment_instruments = NULL;

  /**
   * This class uses heavy caching, so this method allows
   *  you to clear the cache after changing anything about a creditor
   */
  public static function clearCaches() {
    self::$sdd_creditors = NULL;
    self::$sdd_payment_instruments = null;
  }

  /**
   * get the SDD payment instruments, indexed by name
   *
   * @return array
   *   map name => payment instrument id
   */
  public static function getSddPaymentInstruments() {
    $result = [];
    $all_pis = self::getAllSddPaymentInstruments();
    foreach ($all_pis as $pi_id => $pi_data) {
      $result[$pi_data['name']] = $pi_data['value'];
    }
    return $result;
  }

  /**
   * the payment instrument ID for a given SDD type (RCUR,OOFF,FRST)
   *
   * @param string $sdd_name
   *   PI name
   *
   * @return integer
   *   PI ID
   */
  public static function getSddPaymentInstrumentID($sdd_name) {
    $all_pis = self::getSddPaymentInstruments();
    return $all_pis[$sdd_name] ?? NULL;
  }

  /**
   * Determine the allowed SDD payment instruments for this contribution
   *
   * @param integer $contribution_id
   *   ID of the contribution
   *
   * @return array|null
   *   if it's a SEPA contribution, returns list of allowed payment instruments, otherwise null
   */
  public static function getSDDPaymentInstrumentsForContribution($contribution_id) {
    $mandate_id = CRM_Sepa_BAO_SEPAMandate::getContributionMandateID($contribution_id);
    if (!$mandate_id) {
      return null;
    } else {
      // get the creditor ID
      $mandate = \Civi\Api4\SepaMandate::get(TRUE)
        ->addSelect('creditor_id', 'type', 'status')
        ->addWhere('id', '=', $mandate_id)
        ->execute()
        ->single();
      return self::getPaymentInstrumentsForCreditor($mandate['creditor_id'], $mandate['type']);
    }
  }

  /**
   * look up the SDD payment instrument for the given contribution
   *
   * @return string OOFF, FRST, RCUR or NULL (if not SDD)
   *
   * @deprecated With the introduction of #572 this doesn't work reliably any more.
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
   *   This works for contribution and contribution_recur entities
   *
   * It simply checks, whether the contribution uses a SEPA payment instrument
   *
   * @param array $contribution
   *   attributes of the contribution
   *
   * @return true if the contribution is a SEPA contribution
   *
   * @deprecated With the introduction of #572 this doesn't work reliably any more.
   *  Use getContributionMandateID or getRecurringContributionMandateID instead
   *
   * @see https://github.com/Project60/org.project60.sepa/issues/572
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
   * Restrict payment instruments in some UI forms
   */
  public static function restrictPaymentInstrumentsInForm($formName, $form) {
    if ($formName == 'CRM_Contribute_Form_Contribution') {
      $mandate = null;
      if ($form->_id) {
        // this is an edit, so see if this is a CiviSEPA contribution
        $mandate = CRM_Sepa_BAO_SEPAMandate::getMandateFor($form->_id);
      }

      if ($mandate) {
        // this is a CiviSEPA contribution, leave only the allowed PIs
        $allowed_pis = self::getPaymentInstrumentsForCreditor($mandate['creditor_id'], $mandate['type']);

        CRM_Core_Resources::singleton()->addVars('sdd', array('pis_keep'   => array_keys($allowed_pis)));
        CRM_Core_Resources::singleton()->addVars('sdd', array('pis_remove' => null));

      }
      else {
        // this is a regular or new contribution: remove all SDD-only PIs
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
    if (self::$sdd_creditors === NULL) {
      self::$sdd_creditors = [];
      $creditors = civicrm_api3('SepaCreditor', 'get', [
        'option.limit' => 0,
      ]);
      foreach ($creditors['values'] as $creditor) {
        self::$sdd_creditors[$creditor['id']] = $creditor;
      }
    }
    return self::$sdd_creditors;
  }

  /**
   * Get a list of all payment instruments used by CiviSEPA
   *
   * @return array
   *   id => [name, label, id]
   */
  public static function getAllSddPaymentInstruments()
  {
    if (self::$sdd_payment_instruments === NULL) {
      self::$sdd_payment_instruments = [];

      // first, collect all payment instruments in use
      $creditors = self::getAllSddCreditors();
      $pi_ids = [];

      // collect OOFF payment instruments
      foreach ($creditors as $creditor) {
        $creditor_id = (int) $creditor['id'];
        $creditor_pi_ooff = $creditor['pi_ooff'] ?? '';
        foreach (explode(',', $creditor_pi_ooff) as $pi_value) {
          $pi_id = (int) $pi_value;
          if ($pi_id) {
            $pi_ids[] = $pi_id;
          }
        }
      }

      // collect RCUR payment instruments
      foreach ($creditors as $creditor) {
        $creditor_pi_rcur = $creditor['pi_rcur'] ?? '';
        foreach (explode(',', $creditor_pi_rcur) as $pi_value) {
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
      if (!empty($pi_ids)) {
        $instruments = civicrm_api3('OptionValue', 'get', [
          'option_group_id' => 'payment_instrument',
          'value'           => ['IN' => $pi_ids],
          'is_active'       => 1,
          'return'          => 'value,name,label',
        ]);
        foreach ($instruments['values'] as $instrument) {
          $payment_instrument_id = $instrument['value'];
          $instrument['id'] = $payment_instrument_id;
          self::$sdd_payment_instruments[$payment_instrument_id] = $instrument;
        }
      }
    }
    return self::$sdd_payment_instruments;
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
    $creditor = $creditors[$creditor_id] ?? NULL;
    if (!$creditor) {
      return []; // creditor not found
    }

    // now extract the IDs as defined by the creditor
    $payment_instrument_ids = [];
    if (isset($creditor['pi_ooff']) && $type == 'OOFF') {
      $payment_instrument_ids = explode(',', $creditor['pi_ooff']);

    } elseif (isset($creditor['pi_rcur']) && ($type == 'FRST' || $type == 'RCUR')) {
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
      if (!in_array($type, ['OOFF', 'FRST', 'RCUR'])) {
        Civi::log()->warning("Invalid type '{$type}' passed to CRM_Sepa_Logic_PaymentInstruments::getPaymentInstrumentsForCreditor()");
      }
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

  /**
   * Determine the correct payment instrument the next installment
   *  for the given creditor/recurring contribution ID
   *
   * @param integer $creditor_id
   *   creditor ID
   * @param integer $recurring_contribution_pi
   *   recurring contribution's payment instrument
   * @param boolean $is_first
   *   is the next installment the first contribution?
   */
  public static function getInstallmentPaymentInstrument($creditor_id, $recurring_contribution_pi, $is_first)
  {
    if (!$is_first) {
      // in the RCUR case, this is simple: it's the same as the recurring contribution
      return $recurring_contribution_pi;
    }

    // OK: we're looking for the matching FRST PI for a given RCUR PI (from the recurring contribution)
    // get the creditor
    static $cache = [];
    if (isset($cache[$creditor_id][$recurring_contribution_pi])) {
      return $cache[$creditor_id][$recurring_contribution_pi];
    }

    $creditors = self::getAllSddCreditors();
    $creditor = $creditors[$creditor_id] ?? NULL;
    if (!$creditor) {
      $cache[$creditor_id][$recurring_contribution_pi] = $recurring_contribution_pi;
      return $recurring_contribution_pi; // creditor not found
    }

    // we found our creditor
    if (isset($creditor['pi_rcur'])) {
      foreach (explode(',', $creditor['pi_rcur']) as $pi_spec) {
        if (strstr($pi_spec, '-')) {
          // this is a frst-rcur combo
          $frst_rcur = explode('-', $pi_spec, 2);
          if ($frst_rcur[1] == $recurring_contribution_pi) {
            $cache[$creditor_id][$recurring_contribution_pi] = $frst_rcur[0];
            return $frst_rcur[0];
          }
        } else {
          // if this matches an individual PI, we're also happy
          if ($pi_spec == $recurring_contribution_pi) {
            $cache[$creditor_id][$recurring_contribution_pi] = $recurring_contribution_pi;
            return $recurring_contribution_pi;
          }
        }
      }
    }

    // fallback (happens e.g. if creditor settings have changed, or recurring contribution has been manipulated)
    $cache[$creditor_id][$recurring_contribution_pi] = $recurring_contribution_pi;
    return $recurring_contribution_pi;
  }

  /**
   * Get the default payment instruments for SEPA creditors
   *
   * @return array
   *   'ooff_sepa_default' -> payment instrument list
   *   'rcur_sepa_default' -> payment instrument list
   */
  public static function getDefaultSEPAPaymentInstruments() {
    $instruments = self::getClassicSepaPaymentInstruments();
    return [
      'ooff_sepa_default' => ["{$instruments['OOFF']}"],
      'rcur_sepa_default' => ["{$instruments['FRST']}-{$instruments['RCUR']}"],
    ];
  }


  /**
   * Return a list of FRST-RCUR payment instrument tuples for the given creditor
   *
   * @param integer $creditor_id
   *    the creditor used
   *
   * @return array
   *    [FRST-PI-id => RCUR-PI-id]
   */
  public static function getFrst2RcurMapping($creditor_id) {
    static $creditor2frst_rcur_map = [];
    if (!isset($creditor2frst_rcur_map[$creditor_id])) {
      $creditor2frst_rcur_map[$creditor_id] = [];

      // get the creditor data
      $creditors = self::getAllSddCreditors();
      $creditor = $creditors[$creditor_id] ?? NULL;
      if ($creditor) {
        foreach (explode(',', $creditor['pi_rcur']) as $pi_spec) {
          if (strstr($pi_spec, '-')) {
            // this is a frst-rcur combo. record it!
            $frst_rcur = explode('-', $pi_spec, 2);
            $creditor2frst_rcur_map[$creditor_id][(int) $frst_rcur[0]] = (int) $frst_rcur[1];
          }
        }
      }
    }

    return $creditor2frst_rcur_map[$creditor_id];
  }

  /**
   * Will get the list of the three classic SEPA payment instruments
   *   OOFF, FRST, RCUR
   *
   * @return array
   *   [name => id]
   *
   * @throws Exception
   *   if not all of these payment instruments could be identified
   */
  public static function getClassicSepaPaymentInstruments() {
    static $classic_payment_instrument_ids = NULL;
    if ($classic_payment_instrument_ids === null) {
      $classic_payment_instrument_ids = [];
      $classic_payment_instruments = civicrm_api3('OptionValue', 'get', [
        'option_group_id' => 'payment_instrument',
        'name'            => ['IN' => ['OOFF', 'FRST', 'RCUR']],
        'return'          => 'name,value']);
      foreach ($classic_payment_instruments['values'] as $classic_payment_instrument) {
        $classic_payment_instrument_ids[$classic_payment_instrument['name']] = $classic_payment_instrument['value'];
      }
    }

    if (count($classic_payment_instrument_ids) <> 3) {
      throw new Exception("Missing classic SEPA payment instruments ('OOFF', 'FRST', 'RCUR')");
    } else {
      return $classic_payment_instrument_ids;
    }
  }
}
