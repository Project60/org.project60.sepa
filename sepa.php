<?php
require_once 'sepa.civix.php';
require_once 'hooks.php';

function sepa_pageRun_contribute( &$page ) {
/*
  $recur = $page->getTemplate()->get_template_vars("contribution_recur_id");
  CRM_Core_Region::instance('page-body')->add(array(
    'markup' => "Should we mention special steps to update/alter the contribution, eg if part of a batch already"
  ));
*/
}

function sepa_civicrm_pageRun( &$page ) {
  if (get_class($page) == "CRM_Contribute_Page_Tab") {
    if ($page->getTemplate()->get_template_vars('contribution_recur_id')) {
      // This is an installment of a recurring contribution.
      return sepa_pageRun_contribute( $page );
    }
    else {
      // This is a one-off contribution => try to show mandate data.
      if (!CRM_Sepa_Logic_Base::isSDD(array('payment_instrument_id' => $page->getTemplate()->get_template_vars('payment_instrument_id'))))
        return;

      $mandate = civicrm_api3('SepaMandate', 'getsingle', array('entity_table'=>'civicrm_contribution', 'entity_id'=>$page->getTemplate()->get_template_vars('id')));
      $mandate['is_enabled'] = CRM_Sepa_BAO_SEPAMandate::is_active($mandate['status']);
      $page->assign('sepa', $mandate);

      CRM_Core_Region::instance('page-body')->add(array(
        'template' => 'Sepa/Contribute/Form/ContributionView.tpl'
      ));
      CRM_Core_Region::instance('page-body')->add(array(
        'callback' => function(&$spec, &$html) {
          /*
           * Find the last 'crm-submit-buttons' section in the generated HTML,
           * and move the SDD mandate section before it.
           *
           * This is rather hacky -- but we don't really have any better anchor to work with...
           *
           * (Ideally, the original template should provide a crmRegion for the main content,
           * so we could just append the mandate stuff there without hacking HTML output.)
           */
          $html = preg_replace('%(.*)(\<[^>]*crm-submit-buttons.*)(\<!-- Mandate --\>.*\<!-- /Mandate --\>)%s', '$1$3$2', $html);
        }
      ));
    }
  }
  elseif ( get_class($page) == "CRM_Contribute_Page_ContributionRecur") {
    $recur = $page->getTemplate()->get_template_vars("recur");

    $pp = civicrm_api('PaymentProcessor', 'getsingle', 
      array('version' => 3, 'sequential' => 1, 'id' => $recur["payment_processor_id"]));
    if ("Payment_SEPA_DD" !=  $pp["class_name"])
      return;

    $mandate = civicrm_api("SepaMandate","getsingle",array("version"=>3, "entity_table"=>"civicrm_contribution_recur", "entity_id"=>$recur["id"]));
    if (!array_key_exists("id",$mandate)) {
        CRM_Core_Error::fatal(ts("Can't find the sepa mandate"));
    }
    $mandate['is_enabled'] = CRM_Sepa_BAO_SEPAMandate::is_active($mandate['status']);
    $page->assign("sepa",$mandate);
    CRM_Core_Region::instance('page-body')->add(array(
      'template' => 'Sepa/Contribute/Page/ContributionRecur.tpl'
    ));
  }
}

function _sepa_buildForm_Contribution_Main ($formName, &$form ){
  $pp= civicrm_api("PaymentProcessor","getsingle"
    ,array("version"=>3,"id"=>$form->_values["payment_processor"]));
  if("Payment_SEPA_DD" != $pp["class_name"])
    return;
  //workaround the notice message, as ContributionBase assumes these fields exist in the confirm step
  foreach (array("account_holder","bank_identification_number","bank_name","bank_account_number") as $field){
    $form->addElement("hidden",$field);
  }
$js= <<<'EOD'
cj(function($) {
 $('#bank_iban,#bank_bic').keyup(function() {
   this.value = this.value.toUpperCase();
 });
});
EOD;
  CRM_Core_Region::instance('page-header')->add(array('script' => $js));
}

function sepa_civicrm_buildForm ( $formName, &$form ){
  $tag = str_replace('_', '', $formName);
  if (stream_resolve_include_path('CRM/Sepa/Hooks/'.$tag.'.php')) {
    $className = 'CRM_Sepa_Hooks_' . $tag;
    if (class_exists($className)) {
      if (method_exists($className, 'buildForm')) {
        CRM_Sepa_Logic_Base::debug(ts('Calling SEPA Hook '), $className . '::buildForm', 'alert');
        $className::buildForm($form);
      }
    }
  }

  if ("CRM_Admin_Form_PaymentProcessor" == $formName) {
    $pp=civicrm_api("PaymentProcessorType","getsingle",array("id"=>$form->_ppType, "version"=>3));
    if("Payment_SEPA_DD" != $pp["class_name"])
      return;
    $form->add('text', 'creditor_name', ts('Organisation Name'));
    $form->addRule("creditor_name", ts('%1 is a required field.', array(1 => ts('Organisation Name'))), 'required');

    $form->add('textarea', 'creditor_address', ts('Address'), array('cols' => '60', 'rows' => '3'));
    $form->add('checkbox', 'mandate_active', ts('Activate new mandates directly when submitted'));
    $form->add( 'text', 'creditor_prefix',  ts('Mandate Prefix'), array('size' => 10, 'maxlength' => 35));
    $form->add( 'text', 'creditor_contact_id',  ts('Contact ID'));
    $form->add( 'text', 'creditor_bic',  ts('BIC'),"size=11 maxlength=11");
    $form->addElement( 'text', 'creditor_iban',  ts('IBAN'),array("size"=>34,"maxlength"=>34));
    $form->addRule("creditor_contact_id", ts('%1 must be a number', array(1 => ts('Contact ID'))),'numeric');
    $form->add( 'hidden', 'creditor_id');
    $form->addRule("creditor_prefix", ts('%1 is a required field.', array(1 => ts('Mandate Prefix'))), 'required');

    $fileFormatOptions = array();
    $fileFormats = CRM_Core_PseudoConstant::get('CRM_Sepa_DAO_SEPACreditor', 'sepa_file_format_id', array('localize' => TRUE));
    foreach ($fileFormats as $key => $var) {
      $fileFormatOptions[$key] = $form->createElement('radio', NULL,
        ts('SEPA File Format'), $var, $key,
        array('id' => "civicrm_sepa_file_format_{$var}_{$key}")
      );
    }
    $form->addGroup($fileFormatOptions, 'sepa_file_format_id', ts('SEPA File Format'));

    $form->add('text', 'extra_advance_days', ts('Extra advance days'), null, true);
    $form->addRule('extra_advance_days', ts('%1 must be a whole number.', array(1 => ts('Extra advance days'))), 'integer');

    $form->add('text', 'maximum_advance_days', ts('Maximum advance days', null, true));
    $form->addRule('maximum_advance_days', ts('%1 must be a whole positive number.', array(1 => ts('Maximum advance days'))), 'positiveInteger');

    $form->add('checkbox', 'use_cor1', ts('Use COR1 for domestic payments'));

    $form->addRadio(
      'group_batching_mode',
      'Group batching mode',
      array(
        'NONE' => 'No batching (each group in separate file)',
        'TYPE' => 'Batch by sequence type',
        'COR' => 'Batch by type and instrument (CORE/COR1)',
        'ALL' => 'All groups in one file',
      )
    );

    $form->addRadio(
      'month_wrap_policy',
      'Month Wrap Policy',
      array(
        'PRE' => 'Move date before end of month',
        'POST' => 'Wrap to 1st of next month',
        'NONE' => 'No explicit handling',
      )
    );

    $form->add('text', 'remittance_info', ts('Remittance Information text'));

    // get the creditor info as well
    $ppid=$form->getVar("_id");
    if (isset($ppid)) {
      $cred = civicrm_api3("SepaCreditor","get",array("sequential"=>1,"payment_processor_id"=>$ppid));
    }
    if (isset($ppid) && $cred['count']) {
      $cred = $cred["values"][0];
      $form->setDefaults(array(
        "creditor_id"=>$cred["id"],
        "creditor_name"=>$cred["name"],
        "creditor_contact_id"=>$cred["creditor_id"],
        "creditor_address"=>$cred["address"],
        "mandate_active"=>$cred["mandate_active"],
        "creditor_prefix"=>$cred["mandate_prefix"],
        "creditor_iban"=>$cred["iban"],
        'creditor_bic' => CRM_Utils_Array::value('bic', $cred),
        "sepa_file_format_id"=>$cred["sepa_file_format_id"],
        'extra_advance_days' => $cred['extra_advance_days'],
        'maximum_advance_days' => $cred['maximum_advance_days'],
        'use_cor1' => $cred['use_cor1'],
        'group_batching_mode' => $cred['group_batching_mode'],
        'month_wrap_policy' => $cred['month_wrap_policy'],
        'remittance_info' => $cred['remittance_info'],
      ));
    } else {
      $session = CRM_Core_Session::singleton();
      $form->setDefaults(array(
        'creditor_prefix' => 'SEPA',
        'creditor_contact_id' => $session->get('userID'),
        'sepa_file_format_id' => CRM_Core_OptionGroup::getDefaultValue('sepa_file_format'),
        'extra_advance_days' => 1,
        'maximum_advance_days' => 14,
        'use_cor1' => false,
        'group_batching_mode' => 'COR',
        'month_wrap_policy' => 'PRE',
      ));
    }
    CRM_Core_Region::instance('page-body')->add(array(
      'template' => 'Sepa/Admin/Form/PaymentProcessor.tpl'
    ));
  }

  if ("CRM_Contribute_Form_Contribution_Confirm" == $formName && array_key_exists("bank_iban",$form->_params) ) {
    require_once("packages/php-iban-1.4.0/php-iban.php");
    $form->assign("iban",iban_to_human_format($form->_params["bank_iban"]));
    $form->assign("bic",$form->_params["bank_bic"]);
    CRM_Core_Region::instance('contribution-confirm-billing-block')->add(array(
      'template' => 'Sepa/Contribute/Form/Contribution/Confirm.tpl'));
  };
  if ("CRM_Contribute_Form_Contribution_Main" == $formName) { 
    _sepa_buildForm_Contribution_Main ($formName, $form );
    return;
  }

  if ("CRM_Contribute_Form_Contribution_ThankYou" == $formName && array_key_exists("bank_iban",$form->_params)) {
    $form->assign("iban",$form->_params["bank_iban"]);
    $form->assign("bic",$form->_params["bank_bic"]);
    CRM_Core_Region::instance('contribution-thankyou-billing-block')->add(array(
      'template' => 'Sepa/Contribute/Form/Contribution/ThankYou.tpl'));
  }

  if ("CRM_Contribute_Form_Contribution" == $formName) { /* Backoffice Contribution add/edit form */
    if (!empty($form->_mode)) { /* Submitting new PP-based contribution => need to adjust payment fields if SDD. */
      $formType = $form->get_template_vars('formType');
      if (empty($formType)) {
        /* Main Contribution form. */
        $paymentProcessorId = CRM_Utils_Array::value('payment_processor_id', $form->_submitValues);
        if (!$paymentProcessorId) {
          /* No PP choice submitted yet => need to find the one pre-selected when form was first loaded. */
          list($paymentProcessorId) = array_keys($form->_processors); /* The option lists always put the default first. */
        }
        $className = civicrm_api3('PaymentProcessor', 'getvalue', array('id' => $paymentProcessorId, 'return' => 'class_name'));
        if ($className == 'Payment_SEPA_DD') {
          /* The currently selected PP is an SDD one => swap out the Credit Card stuff. */

          /* When processing the submitted form, we need to swap out the fields in the main form object. */
          if ($form->_flagSubmitted) {
            /* Get rid of CreditCard fields.
             * (Many of them are mandatory, and thus really in the way when processing the submitted form.) */
            foreach ($form->_paymentFields as $fieldName => $field) {
              $form->removeElement($fieldName);
              if ($field['htmlType'] == 'chainSelect') {
                $form->addChainSelect($fieldName); /* Work around the "chainSelect" code not coping with a missing 'target' field... */
              }
            }

            /* Fields we actually want processed and passed to the PP. */
            $form->addElement('checkbox', 'sepa_active');
            $form->addElement('text', 'bank_bic');
            $form->addElement('text', 'bank_iban');
          }

          /* When building the form for actual (re)display -- i.e. on first load or after validation failure -- we need to swap out the Credit Card pane. */
          { /* There doesn't seem to be an obvious conditional for checking whether the form will be (re)displayed -- so just do this unconditionally. */
            $allPanes = $form->get_template_vars('allPanes');
            $sepaPane = array(ts('SEPA Mandate') => array(
              'url' => CRM_Utils_System::url('civicrm/contact/view/contribution', "snippet=4&formType=SDD&mode={$form->_mode}"),
              'open' => true,
              'id' => 'SDD',
            ));

            /* Like array_splice(), but preserves the key(s) of the replacement array.
             * Another entry in the category "useful functions PHP could provide, but doesn't"... */
            $array_splice_assoc = function(&$input, $offset, $length = 0, $replacement = array()) {
              $tail = array_splice($input, $offset);
              $extracted = array_splice($tail, 0, $length);
              $input += $replacement + $tail;
              return $extracted;
            };

            $array_splice_assoc($allPanes, array_search(ts('Credit Card Information'), array_keys($allPanes)), 1, $sepaPane);
            $form->assign('allPanes', $allPanes);

            $form->assign('processorSupportsFutureStartDate', true); /* Tell the template to display the "Start Date" field. */
          }
        } /* Selected PP is SDD */

        /* Switch between CreditCard and SDD panes dynamically when changing PP selection. */
        $js = <<<'EOD'
cj('select#payment_processor_id').change( function() {
  paymentProcessorId = cj(this).val();
  CRM.api('PaymentProcessor', 'getvalue', {'q': 'civicrm/ajax/rest', 'id': paymentProcessorId, 'return': 'class_name'}, {success: function(data) {
    isSDD = (data.result == 'Payment_SEPA_DD');

    SDDBlock = cj('.crm-SDD-accordion')[0];
    CreditCardBlock = cj('.crm-CreditCard-accordion')[0];

    if (!isSDD && SDDBlock) {
      oldBlock = SDDBlock;
      newType = 'CreditCard';
      newName = ts('Credit Card Information');
    } else if (isSDD && CreditCardBlock) {
      oldBlock = CreditCardBlock;
      newType = 'SDD';
      newName = ts('SEPA Mandate');
    } else {
      return;
    }

    oldBlock.outerHTML = '\n\
      <div class="crm-accordion-wrapper crm-ajax-accordion crm-' + newType + '-accordion ">\n\
        <div class="crm-accordion-header" id="' + newType + '">\n\
          ' + newName + '\n\
        </div>\n\
        <div class="crm-accordion-body">\n\
          <div class="' + newType + '"></div>\n\
        </div>\n\
      </div>\n\
    ';
    loadPanes(newType);
  }});
});
EOD;
        CRM_Core_Region::instance('page-header')->add(array('jquery' => $js));
      } elseif($formType == 'SDD') {
        /* SDD subform invoked from main form (in place of Credit Card pane) => build Mandate fields. */
        $form->add('checkbox', 'sepa_active', ts('Active mandate'))->setValue(1);
        $form->addElement('text', 'bank_bic', ts('BIC'), array('size' => 11, 'maxlength' => 11));
        $form->addElement('text', 'bank_iban', ts('IBAN'), array('size' => 34, 'maxlength' => 34));

        /* Tell the template to load the $formType (i.e. 'SDD') subform from templates/CRM/Contribute/Form/AdditionalInfo/SDD.tpl
         * instead of displaying the main Contribution form. */
        $form->assign('showAdditionalInfo', 1);

        /* The SDD pane template needs to include the CreditCard template for the recur handling JS;
         * but we don't want the actual Credit Card billing block => disable it. */
        CRM_Core_Region::instance('billing-block')->update('default', array('disabled' => true));
      }
    } else { /* Not a new PP contribution. (I.e. editing existing PP contribution; or adding/editing non-PP contribution.) */
      if (isset($form->_values)) { // Deal with weird recursive partial invocation...
        if (!array_key_exists("contribution_recur_id",$form->_values)) {
          // This is a one-off contribution => insert mandate block.

          if (!CRM_Sepa_Logic_Base::isSDD(array('payment_instrument_id' => $form->_values['payment_instrument_id'])))
            return;

          $mandate = civicrm_api3('SepaMandate', 'getsingle', array('entity_table' => 'civicrm_contribution', 'entity_id' => $form->_id));
          $form->assign($mandate);

          $form->add( 'checkbox', 'sepa_active',  ts('Active mandate'))->setValue(CRM_Sepa_BAO_SEPAMandate::is_active($mandate['status']));
          $form->add( 'text', 'bank_bic',  ts('BIC'),"size=11 maxlength=11")->setValue(CRM_Utils_Array::value('bic', $mandate));
          $form->addElement( 'text', 'bank_iban',  ts('IBAN'),array("size"=>34,"maxlength"=>34))->setValue($mandate["iban"]);

          CRM_Core_Region::instance('page-body')->add(array(
            'template' => 'CRM/Sepa/Form/SepaMandate.tpl'
          ));
        } else {
          // This is an installment of a recurring contribution.
          if (false) { //TODO remove definitely if we don't do anything with it
            //should we be able to set the mandate info from the contribution?
            $id=$form->_values['contribution_recur_id'];
            $mandate = civicrm_api("SepaMandate","getsingle",array("version"=>3, "entity_table"=>"civicrm_contribution_recur", "entity_id"=>$id));
            if (!array_key_exists("id",$mandate))
              return;
            //TODO, add in the form? link to something else?
          }
        }
      }
    } /* Not a new PP Contribution. */
  } /* Backoffice Contribution add/edit form */

  if ("CRM_Contribute_Form_UpdateSubscription" == $formName && $form->_paymentProcessor["class_name"] == "Payment_SEPA_DD") {
    $id= $form->getVar( '_crid' );
    $mandate = civicrm_api("SepaMandate","getsingle",array("version"=>3, "entity_table"=>"civicrm_contribution_recur", "entity_id"=>$id));
    if (!array_key_exists("id",$mandate))
      return;
    if (!$form->getVar("_subscriptionDetails")->installments) {
      $form->getElement('installments')->setValue(0);//by default, sepa is without end date
    }
    $form->getElement('is_notify')->setValue(0); // the notification isn't clear, disable it
    $form->assign($mandate);
    //TODO, add in the form, as a region?
    $form->add( 'checkbox', 'sepa_active',  ts('Active mandate'))->setValue(CRM_Sepa_BAO_SEPAMandate::is_active($mandate['status']));
    $form->add( 'text', 'bank_bic',  ts('BIC'),"size=11 maxlength=11")->setValue(CRM_Utils_Array::value('bic', $mandate));
    $form->addElement( 'text', 'bank_iban',  ts('IBAN'),array("size"=>34,"maxlength"=>34))->setValue($mandate["iban"]);
    CRM_Core_Region::instance('page-body')->add(array(
      'template' => 'CRM/Sepa/Form/SepaMandate.tpl'
     ));
  }
  
}




function sepa_civicrm_postProcess( $formName, &$form ) {
  $tag = str_replace('_', '', $formName);
  if (stream_resolve_include_path('CRM/Sepa/Hooks/'.$tag.'.php')) {
    $className = 'CRM_Sepa_Hooks_' . $tag;
    if (class_exists($className)) {
      if (method_exists($className, 'postProcess')) {
        CRM_Sepa_Logic_Base::debug(ts('Calling SEPA Hook '), $className . '::postProcess', 'alert');
        $className::postProcess($form);
      }
    }
  }
 
  if ("CRM_Admin_Form_PaymentProcessor" == $formName) {
    $ppType = civicrm_api3('PaymentProcessorType', 'getsingle', array('id' => $form->_ppType));
    if ($ppType["class_name"]!="Payment_SEPA_DD") return;
    $paymentProcessor = civicrm_api3('PaymentProcessor', 'getsingle', array('name' => $form->_submitValues['name'], 'is_test' => 0));
    $creditor = array ("version"=>3,"payment_processor_id"=>$paymentProcessor['id']);
    foreach (array("user_name"=>"identifier","creditor_name"=>"name","creditor_id"=>"id","creditor_address"=>"address","creditor_prefix"=>"mandate_prefix","creditor_contact_id"=>"creditor_id","creditor_iban"=>"iban","creditor_bic"=>"bic","sepa_file_format_id"=>"sepa_file_format_id") as $field => $api) {
      $creditor[$api] = $form->_submitValues[$field];
    }
    $creditor['mandate_active'] = isset($form->_submitValues['mandate_active']);

    $creditor['extra_advance_days'] = $form->_submitValues['extra_advance_days'];
    $creditor['maximum_advance_days'] = $form->_submitValues['maximum_advance_days'];
    $creditor['use_cor1'] = isset($form->_submitValues['use_cor1']);
    $creditor['group_batching_mode'] = $form->_submitValues['group_batching_mode'];
    $creditor['month_wrap_policy'] = $form->_submitValues['month_wrap_policy'];
    $creditor['remittance_info'] = !empty($form->_submitValues['remittance_info']) ? $form->_submitValues['remittance_info'] : false; /* Using FALSE to work around bug, where attempting to store an empty string stores the string(!) value 'null' instead... */

    if (!$creditor["id"]) {
      unset($creditor["id"]);
    } 
    $r= civicrm_api("SepaCreditor","create",$creditor);
    if ($r["is_error"]) {
      CRM_Core_Session::setStatus($r["error_message"], ts("SEPA Creditor"), "error");
    } else {
     CRM_Core_Session::setStatus("created new creditor ".$r["id"], ts("SEPA Creditor"), "info");
    }
//CRM_Admin_Form_PaymentProcessor
  }
  if ("CRM_Contribute_Form_UpdateSubscription" == $formName && $form->_paymentProcessor["class_name"] == "Payment_SEPA_DD" /* SEPA recurring record. */
      || "CRM_Contribute_Form_Contribution" == $formName && !isset($form->_values['contribution_recur_id']) && CRM_Sepa_Logic_Base::isSDD(array('payment_instrument_id' => $form->_values['payment_instrument_id'])) /* SEPA OOFF contribution record. */
  ) {
    /* Update mandate data. */
    $fieldMapping = array ("bank_iban"=>"iban",'bank_bic'=>"bic");
    $newMandate = array();
    if ("CRM_Contribute_Form_UpdateSubscription" == $formName) {
      // Updating recur record of a recurring contribution.
      $id= $form->getVar( '_crid' );
      $mandate = civicrm_api("SepaMandate","getsingle",array("version"=>3, "entity_table"=>"civicrm_contribution_recur","entity_id"=>$id));
      if (!array_key_exists("id",$mandate))
        return;
    } else {
      // Updating one-off contribution.
      $mandate = civicrm_api3('SepaMandate', 'getsingle', array('entity_table' => 'civicrm_contribution', 'entity_id' => $form->_id));
    }
    foreach ($fieldMapping as $field => $api) {
      $newMandate[$api] = $form->_submitValues[$field];
    }

    $oldActive = CRM_Sepa_BAO_SEPAMandate::is_active($mandate['status']);
    $newActive = isset($form->_submitValues['sepa_active']) && $form->_submitValues['sepa_active'];
    if ($newActive != $oldActive) {
      if ($oldActive) {
        /*
         * Deactivating previously active mandate.
         *
         * If this is a recurring mandate that has already been used, we put it 'ONHOLD',
         * to distinguish it from mandates that have never been used.
         *
         * One-off mandates always go back to 'INIT',
         * as manual deactivation only makes sense here if they haven't been used yet.
         */
        $newMandate['status'] = ($mandate['status'] == 'RCUR') ? 'ONHOLD' : 'INIT';
      } else {
        /* Activating previously inactive mandate. */
        if ($mandate['type'] == 'RCUR') {
          $newMandate['status'] = ($mandate['status'] == 'ONHOLD') ? 'RCUR' : 'FRST';
        } else {
          $newMandate['status'] = 'OOFF';
        }
      }
    }

    $newMandate["id"]=$mandate["id"];
    $newMandate['creditor_id'] = $mandate['creditor_id'];
    //not strictly needed, uncomment if proven handy in the underlying api/bao
    //$newMandate["entity_id"]=$mandate["entity_id"];
    //$newMandate["entity_table"]=$mandate["entity_table"];
    $newMandate["version"] = 3;
    $mandate = civicrm_api("SepaMandate","create",$newMandate);
    if ($mandate["is_error"]) {
      CRM_Core_Error::fatal($mandate["error_message"]);
    }
  }
  
}

/**
 * Implementation of hook_civicrm_config
 */
function sepa_civicrm_config(&$config) {
/*
when civi 4.4, not sure how to make it compatible with both
CRM_Core_DAO_AllCoreTables::$daoToClass["SepaMandate"] = "CRM_Sepa_DAO_SEPAMandate";
CRM_Core_DAO_AllCoreTables::$daoToClass["SepaCreditor"] = "CRM_Sepa_DAO_SEPACreditor";
*/ 
  _sepa_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function sepa_civicrm_xmlMenu(&$files) {
  _sepa_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function sepa_civicrm_install() {
  $config = CRM_Core_Config::singleton();
  //create the tables
  $sql = file_get_contents(dirname( __FILE__ ) .'/sql/sepa.sql', true);
  CRM_Utils_File::sourceSQLFile($config->dsn, $sql, NULL, true);

  return _sepa_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function sepa_civicrm_uninstall() {
  //should we delete the tables?

  /* Delete "workflow" Option Value entries for the Mandate Templates, if the actual Templates are not populated.
   *
   * The Option Value entries are presently created on installation,
   * while the actual Templates (in `civicrm_msg_template`) are created lazily on first use.
   * Thus, we can have Option Value entries with no actual Template attached.
   *
   * Although we create the Option Values as "managed" entities,
   * we have to set the cleanup mode to 'never' and remove the unused Options manually,
   * as the automatic 'unused' cleanup mode doesn't currently recognise the `msg_template` references,
   * and thus would remove even the used ones. */
  foreach (array('sepa_mandate_pdf', 'sepa_mandate') as $templateName) {
    $result = civicrm_api3('OptionGroup', 'getsingle', array(
      'name' => 'msg_tpl_workflow_contribution',
      'api.OptionValue.getsingle' => array(
        'name' => $templateName,
      )
    ));
    $workflowID = $result['api.OptionValue.getsingle']['id'];

    if (!civicrm_api3('MessageTemplate', 'getcount', array('workflow_id' => $workflowID))) {
      civicrm_api3('OptionValue', 'delete', array('id' => $workflowID));

      /* Also need to remove the corresponding `civicrm_managed` entries manually.
       * (When the `cleanup` mode is 'never', the `managed` entries are not automatically removed either, even if the actual entities are gone.)
       *
       * Need to use DAO here, as there is no API (yet?) for `civicrm_managed`. */
      $managedDao = new CRM_Core_DAO_Managed();
      $managedDao->entity_type = 'OptionValue';
      $managedDao->entity_id = $workflowID;
      $managedDao->delete();
    }
  }

  return _sepa_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function sepa_civicrm_enable() {
  return _sepa_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function sepa_civicrm_disable() {
  return _sepa_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function sepa_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _sepa_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function sepa_civicrm_managed(&$entities) {
  return _sepa_civix_civicrm_managed($entities);
}

/* Support SEPA mandates in merge operations
 */
function sepa_civicrm_merge ( $type, &$data, $mainId = NULL, $otherId = NULL, $tables = NULL ) {
   switch ($type) {
    case 'relTables':
      // Offer user to merge SEPA Mandates
      $data['rel_table_sepamandate'] = array(
          'title'  => ts('SEPA Mandates'),
          'tables' => array('civicrm_sdd_mandate'),
          'url'    => CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid=$cid&selectedChild=contribute'),  // '$cid' will be automatically replaced
      );
    break;

    case 'cidRefs':
      // this is the only field that needs to be modified
        $data['civicrm_sdd_mandate'] = array('contact_id');
    break;
  }
}
