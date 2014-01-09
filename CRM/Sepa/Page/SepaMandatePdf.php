<?php

require_once 'CRM/Core/Page.php';
require_once 'api/class.api.php';

class CRM_Sepa_Page_SepaMandatePdf extends CRM_Core_Page {

  /* get the template message, if not exist, creates it */
  function getMessage ($name,$group="msg_tpl_workflow_contribution",$file = null) {
    $tpl= civicrm_api('OptionValue', 'getsingle', array('version' => 3,'option_group_name' => $group,'name'=>$name));
    if (array_key_exists("is_error",$tpl)) {
      $grp= civicrm_api('OptionGroup', 'getsingle', array('version' => 3,'name'=>$group));
      $tpl= civicrm_api('OptionValue', 'create', array('version' => 3,'option_group_id' => $grp["id"],'name'=>$name,"label"=>$name));
    }

    $msg =  civicrm_api('MessageTemplate','getSingle',array("version"=>3,"workflow_id"=>$tpl["id"]));
    if (array_key_exists("is_error",$msg)) {
      $msg =  civicrm_api('MessageTemplate','create',array("version"=>3,"workflow_id"=>$tpl["id"],
            "msg_title"=>$name,
            "msg_subject"=>$name,
            "is_reserved"=>0,
            "msg_html"=> file_get_contents(__DIR__ . "/../../../msg_template/$name.html"),
            "msg_text"=>"N/A"
            ));
    };
    return $msg;
  
  }



  function generateHTML($mandate) {
    if (is_array($mandate)) {
       $mandate= json_decode(json_encode($mandate), FALSE);
    }
    $this->mandate = $mandate;
    if (!isset($this->api))
      $this->api = new civicrm_api3();
    $api = $this->api;

    switch ($mandate->entity_table) {
      case "civicrm_contribution_recur":
        $api->ContributionRecur->getsingle(array("id"=>$mandate->entity_id));
        $recur=$api->result;
        $api->Contact->getsingle(array("id"=>$recur->contact_id));
        $this->contact=$api->result;
        break;
      case "civicrm_contribution":
        $api->Contribution->getsingle(array("id"=>$mandate->entity_id));
        $contribution=$api->result;
        $api->Contact->getsingle(array("id"=>$contribution->contact_id));
        $this->contact=$api->result;
        break;
      default:
        return CRM_Core_Error::fatal("We don't know how to handle mandates for ".$mandate->entity_table);
    }

    $msg = $this->getMessage("sepa_mandate_pdf");
    CRM_Utils_System::setTitle($msg["msg_title"]  ." ". $mandate->reference);
    $this->assign("contact",(array) $this->contact);
    $this->assign("contactId",$this->contact->contact_id);
    $this->assign("sepa",(array) $mandate);
    if (isset($recur))
      $this->assign("recur",(array) $recur);
    if (isset($contribution))
      $this->assign("contribution",(array) $contribution);

    $api->SepaCreditor->getsingle(array('id' => $mandate->creditor_id, 'api.PaymentProcessor.getsingle' => array('id' => '$value.payment_processor_id')));
    $pp=$api->result->{'api.PaymentProcessor.getsingle'};
    $this->assign("creditor",$pp->user_name);

    $this->html = $this->getTemplate()->fetch("string:".$msg["msg_html"]);
  }

  function generatePDF($send =false) {
    require_once 'CRM/Utils/PDF/Utils.php';
    $fileName = $this->mandate->reference.".pdf";
    if ($send) {
      $config = CRM_Core_Config::singleton();
      $pdfFullFilename = $config->templateCompileDir . CRM_Utils_File::makeFileName($fileName);
      file_put_contents($pdfFullFilename, CRM_Utils_PDF_Utils::html2pdf( $this->html,$fileName, true, null ));
      list($domainEmailName,$domainEmailAddress) = CRM_Core_BAO_Domain::getNameAndEmail();
      $params              = array();
      $params['groupName'] = 'SEPA Email Sender';
      $params['from']      = '"' . $domainEmailName . '" <' . $domainEmailAddress . '>';
      $params['toEmail'] = $this->contact->email;
      $params['toName']  = $params['toEmail'];

      if (empty ($params['toEmail'])){
        CRM_Core_Session::setStatus(ts("Error sending $fileName: Contact doesn't have an email."));
        return false;
      }
      $params['subject'] = "SEPA " . $fileName;
      if (!CRM_Utils_Array::value('attachments', $instanceInfo)) {
        $instanceInfo['attachments'] = array();
      }
      $params['attachments'][] = array(
          'fullPath' => $pdfFullFilename,
          'mime_type' => 'application/pdf',
          'cleanName' => $fileName,
          );
      ;
      $mail = $this->getMessage("sepa_mandate");
      $params['text'] = "this is the mandate, please return signed";
      $params['html'] = $this->getTemplate()->fetch("string:".$mail["msg_html"]);
      CRM_Utils_Mail::send($params);
//      CRM_Core_Session::setStatus(ts("Mail sent"));
    }  else {
      CRM_Utils_PDF_Utils::html2pdf( $this->html, $fileName, false, null );
    } 
  }

  function run() {
    $this->api = new civicrm_api3();
    $api = $this->api;
    $id = (int)CRM_Utils_Request::retrieve('id', 'Positive', $this);
    $reference = CRM_Utils_Request::retrieve('ref', 'String', $this);
    //TODO: once debugged, force POST, not GET
    $action = CRM_Utils_Request::retrieve('pdfaction', 'String', $this);
    if ($id>0) {
      $api->SepaMandate->get($id);
    } elseif ($reference){ 
      $api->SepaMandate->get(array("reference"=>$reference));
    } else {
      CRM_Core_Error::fatal("missing parameter. you need id or ref of the mandate");
      return;
    }
    if ($api->is_error()) {
      CRM_Core_Error::fatal($api->errorMsg());
      return;
    }
    $this->generateHTML ($api->values[0]);
    if (!$action) {
      $this->assign("html",$this->html);
      parent::run();
      return;
    }
    if ($action != "email") {
      $this->generatePDF (false);
      CRM_Utils_System::civiExit();
    } 
    $this->generatePDF (true);
  }

}
