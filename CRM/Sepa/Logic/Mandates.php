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

  /**
   * Trick : keep the created membership id in GLOBALS to the postProcess can pick it up
   * 
   * @param type $objectId
   * @param type $objectRef
   */
  public static function hook_post_membership_create($objectId, $objectRef) {
    $GLOBALS["sepa_context"]["membership_id"] = $objectId;
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
      if (!isset($rc->start_date)) {
        $rc->start_date = date('Ymd');
      } else {
        $rc->start_date = date("Ymd",strtotime($rc->start_date));             // copied that from X+, must be sth about the format...
      }

      $rc->create_date = date("YmdHis",strtotime($rc->create_date));
      $rc->modified_date = date("YmdHis",strtotime("now"));

      if (!CRM_Sepa_BAO_SEPAMandate::is_active($bao->status) && CRM_Sepa_BAO_SEPAMandate::is_active($api_mandate["status"])) {
        $rc->contribution_status_id=1; //TODO match the status to the mandate is_active
        // figure out whether there is a contribution for this mandate
        $contrib = $bao->findContribution();
        $contrib->receive_date = $rc->start_date;
        $contrib->save();
      } elseif (!CRM_Sepa_BAO_SEPAMandate::is_active($bao->status) && !CRM_Sepa_BAO_SEPAMandate::is_active($api_mandate["status"])) {
        // TODO should we disable the next contribution or only the recurring contrib? 
        $rc->contribution_status_id=3; //TODO match the status to the mandate is_active
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
    public static function hook_pre_contribution_create($id, &$params) {
      if (array_key_exists("sepa_context", $GLOBALS) && $GLOBALS["sepa_context"]["payment_instrument_id"]) {
        $params["payment_instrument_id"] = $GLOBALS["sepa_context"]["payment_instrument_id"];
//        CRM_Core_Session::setStatus('Picking up context-defined payment instrument ' . $GLOBALS["sepa_context"]["payment_instrument_id"], '', 'info');
      }
    }

  public static function hook_post_contribution_create($objectId, $objectRef) {
    // check whether this is a SDD contribution. This could be done using a financial_type_id created specially 
    // for that purpose, or by examining the contrib->payment_instrument->pptype
    if (array_key_exists("sepa_context", $GLOBALS) && $GLOBALS["sepa_context"]["payment_instrument_id"]) {
      $objectRef->payment_instrument_id = $GLOBALS["sepa_context"]["payment_instrument_id"];
      $objectRef->contribution_status_id = CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name'); // For some reason (hopefully not SEPA-related), this is pre-set to 'pending' for recurring contributions, but to 'completed' for one-off contributions... We always want 'pending' for DD -- so set it explicitly.
      $objectRef->save();
      //CRM_Core_Session::setStatus('Picking up context-defined payment instrument ' . $GLOBALS["sepa_context"]["payment_instrument_id"], '', 'info');

      /*
       * Set `sequence_number` default value.
       *
       * Using API here, as the BAO (which is passed in the hook) doesn't seem to handle custom fields.
       *
       * The 'get' -> 'create' dance is necessary to prevent overwriting the status with the default 'Completed' value...
       */
      $sequenceNumberField = CRM_Sepa_Logic_Base::getSequenceNumberField();
      civicrm_api3('Contribution', 'getsingle', array(
        'id' => $objectId,
        'return' => 'contribution_status_id',
        'api.Contribution.create' => array(
          $sequenceNumberField => 1,
          'contribution_status_id' => '$value.contribution_status_id',
        ),
      ));
    }

    // If this is a one-off payment, doDirectPayment() has already been invoked before creating the contribution.
    // However, we can only create the mandate once the contribution record is in place, i.e. now.
    if (isset($GLOBALS['sepa_context']['mandateParams'])) {
      $mandateParams = $GLOBALS['sepa_context']['mandateParams'];
      $mandateParams["entity_id"] = $objectId;
      self::createMandate($mandateParams);
    }
  }

  /**
   * Create a SEPA mandate for a new contribution
   */
  public static function createMandate($params) {
    $apiParams = array('version' => 3, 'sequential' => 1);
    $apiParams = array_merge($apiParams, $params);

    $r = civicrm_api ("SepaMandate","create", $apiParams);
    if ($r["is_error"]) {
      CRM_Core_Error::fatal( 'Mandate creation failed : ' . $r["error_message"]);
    }

    if (!isset($params['status']) || $params['status'] == 'INIT') {
      $page = new CRM_Sepa_Page_SepaMandatePdf();
      $page->generateHTML($r["values"][0]);
      $page->generatePDF (true);
    }
  }

  
  public static function handleMembershipSepaPayment($membership_id,$params) {
    self::debug('Creating mandate for membership ' . $membership_id);
    //echo '<pre>';print_r($params);echo '</pre>';
    $mparams = array();
    $membership = civicrm_api3('Membership','getsingle',array('id'=>$membership_id));
    $membershipType = civicrm_api3('MembershipType','getsingle',array('id'=>$membership['membership_type_id']));
    $start = isset($params['contract_start_date']) ? trim($params['contract_start_date']) : date("Y-m-d");
    
    // create the contract object that goes with this membership
    switch ($params['contract_frequency']) {

      case '0' :
        // one-off mandate
        $mparams['type'] = 'OOFF';
        $mparams['status'] = $params['is_enabled'] ? 'OOFF' : 'INIT';
        $ref = 'MEMBER-SDD-'.$mparams['status'].'-'.$membership_id;
        $txref = $ref.'-'.md5(date('YmdHis'));
        $contractType = 'civicrm_contribution';
        $rcontrib = null;
        break;
      
      default :
        // recurring mandate
        $mparams['type'] = 'RCUR';
        $mparams['status'] = $params['is_enabled'] ? 'FRST' : 'INIT';
        $ref = 'MEMBER-SDD-'.$mparams['status'].'-'.$membership_id;
        $txref = $ref.'-'.md5(date('YmdHis'));
        $contractType = 'civicrm_contribution_recur';
        // create recurring contribution
        $freq = isset($_REQUEST['freq']) ? trim($_REQUEST['freq']) : '';
        $start = isset($params['contract_start_date']) ? trim($params['contract_start_date']) : date("Y-m-d");
        $pi_rcur = CRM_Core_OptionGroup::getValue('payment_instrument', 'RCUR', 'name', 'String', 'value');
        $rparams = array(
          'contact_id' => $membership['contact_id'],
          'frequency_interval' => '1',
          'frequency_unit' => 'month',
          'amount' => $params['contract_amount'],
          'contribution_status_id' => 1,
          'start_date' => $start,
          'currency' => $params['contract_currency'],
          'payment_instrument_id' => $pi_rcur,
          'trxn_id' => $ref,
        );
//        print_r($rparams);
        $rcontrib = civicrm_api3('contribution_recur', 'create', $rparams);
        if($rcontrib['is_error']) {
          echo '<br/>Error creating recurring contribution :';
          echo '<pre>';print_r($rcontrib);echo '</pre>';
          return;
        }
        $rcontrib = $rcontrib['values'][ $rcontrib['id'] ];
        echo '<br/>Succesfully created recurring contribution #', $rcontrib['id'];
        $upparams = array(
            'id' => $membership_id,
            'contribution_recur_id' => $rcontrib['id'],
        );
        civicrm_api3('Membership','create',$upparams);

        break;
    }

    // create simple contribution
    $pi = CRM_Core_OptionGroup::getValue('payment_instrument', $mparams['status'], 'name', 'String', 'value');
    $cparams = array(
      'contact_id' => $membership['contact_id'],
      'receive_date' => $start,
      'total_amount' => $params['contract_amount'],
      'currency' => $params['contract_currency'],
      'financial_type_id' => $membershipType['financial_type_id'],
      'trxn_id' => $txref,
      'invoice_id' => $txref,
      'source' => $membership['source'],
      'contribution_status_id' => 2,  // moet nog geparametriseerd worden
      'payment_instrument_id' => $pi,
    );
    if ($rcontrib) $cparams['contribution_recur_id'] = $rcontrib['id'];

    $contrib = civicrm_api3('contribution', 'create', $cparams);
    if($contrib['is_error']) {
      echo '<br/>Error creating contribution :';
      echo '<pre>';print_r($contrib);echo '</pre>';
      return;
    }
    $contrib = $contrib['values'][ $contrib['id'] ];
    echo '<br/>Successfully created contribution #', $contrib['id'];
    
    // link membership and contribution
    $r = civicrm_api3('membership_payment', 'create', array('membership_id' => $membership_id, 'contribution_id' => $contrib['id']));

    // create the mandate
    $moreparams = array(
      "source" => $membership['source'],
      "date" => $start,
      "creditor_id" => "1",       // make variable as well
      "contact_id" => $membership['contact_id'],
      "iban" => $params['iban'],
      "bic" => $params['bic'],
      "creation_date" => date("Y-m-d H:i:s"),
      'entity_table' => $contractType,
      'entity_id' => $rcontrib ? $rcontrib['id'] : $contrib['id'],
    );
    $mparams = array_merge($mparams,$moreparams);
    if ($params['reference']) $mparams['reference'] = $params['reference'];

    print_r($mparams);
    $m = civicrm_api3( 'SepaMandate','create', $mparams);
    if($m['is_error']) {
      echo '<br/>Error creating mandate : ';
      echo '<pre>';print_r($m);echo '</pre>';
      return;
    }
    $m= $m['values'][ $m['id'] ];
    echo '<br/>Successfully created mandate #', $m['id'];

//    die();
  }

}

