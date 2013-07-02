<?php

class CRM_Sepa_Logic_Mandates extends CRM_Sepa_Logic_Base {

  /**
   * Handle creation of mandate references. 
   * TODO: make this modifiable using a hook
   * 
   * @param type $ref
   * @param type $type
   * @return type
   */
  public static function createMandateReference(&$ref = null, $type = "R") {
    $r = "WMFR-" . date("Y");
    if ($ref) {
      $r .="-" . $ref["entity_id"];
    } else {
      $r .= "-RAND" . sprintf("%08d", rand(0, 999999));
    }
    return $r;
  }

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
    CRM_Sepa_Logic_Mandates::fix_initial_contribution($objectRef);
    CRM_Sepa_Logic_Batching::batch_initial_contribution($objectId, $objectRef);
  }

  public static function hook_post_contributionrecur_create($objectId, $objectRef) {
    // todo: check if sepa
    $objectRef->cycle_day = 18;
    $objectRef->save();
    CRM_Core_Session::setStatus('Set recurring contribution cycle date to ' . $objectRef->cycle_day );
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
   * set initial status to <dropdown>).
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
      CRM_Core_Session::setStatus('Found first contribution ' . $contrib->id);
      $dao->first_contribution_id = $contrib->id;
      $dao->save();
    }
  }

  //hook which batches the contribution when it is created (using the hook magic function)
  public static function disabled_hook_post_contribution_create($objectId, $objectRef) {
    self::post_contribution_modify($objectId, $objectRef);
  }

  public static function hook_post_contribution_edit($objectId, $objectRef) {
    self::post_contribution_modify($objectId, $objectRef);
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

