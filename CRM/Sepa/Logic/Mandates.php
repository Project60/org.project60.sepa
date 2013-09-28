<?php

class CRM_Sepa_Logic_Mandates extends CRM_Sepa_Logic_Base {

  /**
   * Fix the initial contribution if it exists. 
   * 
   * Assuming that we will have a case later where we import/create mandates and this function is 
   * more comples ... for now, we'll assume it's DD-created
   * 
   * @param type $objectId
   * @param type $objectRef
   */
  public static function hook_post_sepamandate_create($objectId, $objectRef) {
    // TODO: move this to CRM_Utils_SepaCustomisationHooks?
  }

  public static function hook_post_contributionrecur_create($objectId, $objectRef) {
    // TODO: move this whole thing to CRM_Utils_SepaCustomisationHooks::mend_rcontrib? When is it called anyways?
    if (array_key_exists("sepa_context", $GLOBALS) && $GLOBALS["sepa_context"]["payment_instrument_id"]) {
      $objectRef->payment_instrument_id = $GLOBALS["sepa_context"]["payment_instrument_id"];
      CRM_Utils_SepaCustomisationHooks::mend_rcontrib($objectId, $objectRef);
      $objectRef->save();
    }
  }


  /**
   * Fix the recurring contribution created by the PP
   * 
   * If the mandate is created by the PP, it has a recurring contrib, when the status changes, the recurring contrib has the appropriate status too
   */
  public static function fix_recurring_contribution($api_mandate) {
    // TODO: merge with CRM_Utils_SepaCustomisationHooks::mend_rcontrib?
    $bao = new CRM_Sepa_BAO_SEPAMandate();
    $bao->get($api_mandate["id"]);
    if ($bao->entity_table == "civicrm_contribution_recur") {
      $rcid=$bao->entity_id;
      $rc = new CRM_Contribute_BAO_ContributionRecur();
      $rc->get('id', $bao->entity_id);

      // set start date if not set
      if (strlen($rc->start_date)>0) {
        $rc->start_date = date('Y-m', strtotime("now"))."-".$rc->cycle_day;
        $rc->start_date = date("Ymd",strtotime($rc->start_date));             // copied that from X+, must be sth about the format...
      }

      // adjust start date, if in the past or less than five days in the future
      if (strtotime($rc->start_date) < strtotime("now + 5 days")) {
        $rc->start_date = date("Ymd", strtotime('+1 month', strtotime($rc->start_date)));
      }

      $rc->create_date = date("YmdHis",strtotime($rc->create_date));
      $rc->modified_date = date("YmdHis",strtotime("now"));

      if (!$bao->is_enabled && $api_mandate["is_enabled"]) {
        $rc->contribution_status_id=1; //TODO match the status to the mandate is_enabled
        // figure out whether there is a contribution for this mandate
        $contrib = $bao->findContribution();
        $contrib->receive_date = $rc->start_date;
        $contrib->save();
      } elseif (!$bao->is_enabled && !$api_mandate["is_enabled"]) {
        // TODO should we disable the next contribution or only the recurring contrib? 
        $rc->contribution_status_id=3; //TODO match the status to the mandate is_enabled
      }
      $rc->save();
    }
  }

  /**
   * Fix the initial contribution created by the PP
   * 
   * If the mandate is created by a PP, there is also an initial contribution (possibly
   * underneath a recurring contrib) if the mandate is ready to generate transactions. 
   * In such case, its status is set to pending. This contribution also needs to
   * be registered as the first contribution in the mandate (FRST for now, OOFF later).
   * 
   * Note: PP settings will need to include a 'make active immediately' flag (or 
   * set initial status to <dropdown>). It's part of the "creditor" info that needs to be exposed 
   * "custom fields" of the PP
   * 
   * @param CRM_Sepa_BAO_SEPAMandate $bao
   */
  public static function fix_initial_contribution(CRM_Sepa_DAO_SEPAMandate $dao) {
    $bao = new CRM_Sepa_BAO_SEPAMandate();
    $bao->get($dao->id);
    // figure out whether there is a contribution for this mandate
    $contrib = $bao->findContribution();
    // if we find a contribution, mark it as first for this mandate
    if ($contrib !== null) {
      // check recurring and set receive_date correctly wrt. cycle_day
      if ($contrib->contribution_recur_id) {
        $rc = new CRM_Contribute_BAO_ContributionRecur();
        $rc->get('id', $contrib->contribution_recur_id);
        if ($rc->cycle_day) {
          $rday = date('d', strtotime($contrib->receive_date));
          if ($rday <= $rc->cycle_day) {
            $contrib->receive_date = date('Ym', strtotime($contrib->receive_date)) . sprintf("%02d", $rc->cycle_day);
          } else {
            $ryr = date('Y', strtotime($contrib->receive_date));
            $rmo = date('m', strtotime($contrib->receive_date));
            if ($rmo == 12) {
              $rmo = 1;
              $ryr++;
            }
            $contrib->receive_date = sprintf("%04d%02d%02d", $ryr, $rmo, $rc->cycle_day);
          }
          self::debug('Pushed out first contribution collection day from ' . $rday . ' to ' . $rc->cycle_day);
          $contrib->save();
        } else {
        }
        $rc->receive_date = sprintf("%04d%02d%02d", $ryr, $rmo, $rc->cycle_day);
      }

      self::debug('Found first contribution ' . $contrib->id);
      $dao->first_contribution_id = $contrib->id;
      $dao->save();
    }
  }

  //hook which batches the contribution when it is created (using the hook magic function)
  // @pdelbar: this creates more problems than it solves, as the mandate isn't validated yet
  public static function disabled_hook_post_contribution_create($objectId, $objectRef) {
//    self::post_contribution_modify($objectId, $objectRef);
  }

  public static function hook_post_contribution_edit($objectId, $objectRef) {
//    self::post_contribution_modify($objectId, $objectRef);
  }

  /**
   * This hook picks up the context info set by the Contribution form hook and sets
   * the payment instrument correctly, thus identifying the actual creditor (by
   * means of the sdd_creditor.payment_instrument_id value).
   * 
   * If you feel this is shitty coding, you're probably right -- read the Datamodel.md
   * file for more info.
   * 
   * @param type $op
   * @param type $objectName
   * @param type $id
   * @param type $params
   */
  /*
    public static function hook_pre_contribution_create($op, $objectName, $id, &$params) {
    if (array_key_exists("sepa_context", $GLOBALS) && $GLOBALS["sepa_context"]["payment_instrument_id"]) {
    $params["payment_instrument_id"] = $GLOBALS["sepa_context"]["payment_instrument_id"];
    CRM_Core_Session::setStatus('Picking up context-defined payment instrument ' . $GLOBALS["sepa_context"]["payment_instrument_id"], '', 'info');
    }
    }
   */

  public static function hook_post_contribution_create($objectId, $objectRef) {
    // check whether this is a SDD contribution. This could be done using a financial_type_id created specially 
    // for that purpose, or by examining the contrib->payment_instrument->pptype
    if (array_key_exists("sepa_context", $GLOBALS) && $GLOBALS["sepa_context"]["payment_instrument_id"]) {
      $objectRef->payment_instrument_id = $GLOBALS["sepa_context"]["payment_instrument_id"];
      $objectRef->save();
      //CRM_Core_Session::setStatus('Picking up context-defined payment instrument ' . $GLOBALS["sepa_context"]["payment_instrument_id"], '', 'info');
    }
  }

}

