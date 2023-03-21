<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 TTTP                           |
| Author: X+                                             |
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
 * SEPA_DD prenotification generator
 *
 * @package CiviCRM_SEPA
 */

require_once 'CRM/Core/Page.php';
require_once 'api/class.api.php';

class CRM_Sepa_Page_SepaMandatePdf extends CRM_Core_Page {

  /**
   * Lookup and get a message template
   */
  function getMessage ($id) {
     $msg = civicrm_api('MessageTemplate','getSingle',array("version"=>3,"id"=>$id));
     if (array_key_exists("is_error",$msg)) {
       return CRM_Core_Error::fatal(sprintf(ts("The selected message template does not exist (%d)", array('domain' => 'org.project60.sepa')), $id));
     };

    return $msg;
  }

  function addContactTokens($contact_id, $variable_name) {
    if (empty($contact_id)) return;

    // use the API to load a extensive contact information bulk
    $contact = civicrm_api('Contact', 'getsingle', array('id'=>$contact_id, 'version'=>3));

    // ... add some missing fields
    $bao = new CRM_Contact_BAO_Contact();
    $bao->get('id', $contact_id);
    $contact['postal_greeting_display'] = $bao->postal_greeting_display;
    $contact['email_greeting_display']  = $bao->email_greeting_display;
    $contact['addressee_display']       = $bao->addressee_display;

    if (empty($contact['is_error'])) {
      $this->assign($variable_name, $contact);
    }
  }

  /**
   * generate the HTML text, and assign all the required variables (tokens)
   * this is a precondition for PDF generation as well as emails
   */
  function generateHTML($mandate, $template_id) {
    // init API wrapper object
    if (!isset($this->api)) $this->api = new civicrm_api3();
    $api = $this->api;


    // LOAD INFORMATION and set tokens (smarty variables)

    // fix mandate. @X+: Why?
    if (is_array($mandate)) {
      $mandate = json_decode(json_encode($mandate), FALSE);
    }
    $this->mandate = $mandate;
    $this->assign("sepa",    (array) $mandate);
    $this->assign("mandate", (array) $mandate);

    // load the associated contribution
    switch ($mandate->entity_table) {
      case "civicrm_contribution_recur":
        $api->ContributionRecur->getsingle(array("id"=>$mandate->entity_id));
        $recur = $api->result;
        $this->assign("recur", (array) $recur);
        $this->assign("contactId", $recur->contact_id);
        $this->addContactTokens($recur->contact_id, 'contact');

        // first_conttribution
        if (!empty($mandate->first_contribution_id)) {
          $api->Contribution->getsingle(array("id"=>$mandate->first_contribution_id));
          $this->assign("first_contribution", (array) $api->result);
        }

        // some more extra information:
        $recur_extra = array();
        $recur_extra['frequency_text'] = CRM_Utils_SepaOptionGroupTools::getFrequencyText($recur->frequency_interval, $recur->frequency_unit, false);
        $recur_extra['frequency_text_l10n'] = CRM_Utils_SepaOptionGroupTools::getFrequencyText($recur->frequency_interval, $recur->frequency_unit, true);
        $recur_extra['yearly_amount'] = $recur->amount;
        if ($recur->frequency_unit == 'month') {
          $recur_extra['yearly_amount'] = $recur_extra['yearly_amount'] * (12/$recur->frequency_interval);
        } elseif ($recur->frequency_unit == 'year') {
          $recur_extra['yearly_amount'] = $recur_extra['yearly_amount'] / $recur->frequency_interval;
        } else {
          // we don't support other units
          unset($recur_extra['yearly_amount']);
        }
        $this->assign("recur_extra", (array) $recur_extra);

        break;



      case "civicrm_contribution":
        $api->Contribution->getsingle(array("id"=>$mandate->entity_id));
        $contribution = $api->result;
        $this->assign("contribution", (array) $contribution);
        $this->assign("contactId", $contribution->contact_id);
        $this->addContactTokens($contribution->contact_id, 'contact');
        break;
      default:
        return CRM_Core_Error::fatal("We don't know how to handle mandates for ".$mandate->entity_table);
    }

    // add creditor information
    $api->SepaCreditor->getsingle(array('id' => $mandate->creditor_id));
    $creditor = $api->result;
    $this->assign("mandate_creditor", (array) $creditor);

    // add payment processor information
    if (!empty($recur->payment_processor_id))        $payment_processor_id = $recur->payment_processor_id;
    if (!empty($contribution->payment_processor_id)) $payment_processor_id = $contribution->payment_processor_id;
    if (!empty($payment_processor_id)) {
      $api->PaymentProcessor->getsingle(array('id' => $payment_processor_id));
      if (isset($api->$result)) {
        $this->assign("payment_processor", $api->$result);
        // LEGACY: set 'creditor' to the following (@X+: why?)
        $this->assign("creditor", $api->$result->user_name);
      }
    }

    // set some more basic information
    $this->addContactTokens($mandate->contact_id,                         'mandate_contact');
    $this->addContactTokens($creditor->creditor_id,                       'creditor_contact');
    $this->addContactTokens(CRM_Core_Session::singleton()->get('userID'), 'user');

    // Load the template and run SMARTY
    $msg = $this->getMessage($template_id);
    CRM_Utils_System::setTitle($msg["msg_title"]  ." ". $mandate->reference);
    $this->html = $this->getTemplate()->fetch("string:".$msg["msg_html"]);
  }



  function generatePDF($send = FALSE, $template_id) {
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
        CRM_Core_Session::setStatus(sprintf(ts("Error sending %s: Contact doesn't have an email.", array('domain' => 'org.project60.sepa')), $fileName));
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
      $mail = $this->getMessage($template_id);
      $params['text'] = "this is the mandate, please return signed";
      $params['html'] = $this->getTemplate()->fetch("string:".$mail["msg_html"]);
      CRM_Utils_Mail::send($params);
//      CRM_Core_Session::setStatus(ts("Mail sent", array('domain' => 'org.project60.sepa')));
    }  else {
      CRM_Utils_PDF_Utils::html2pdf( $this->html, $fileName, false, null );
    }
  }

  function run() {
    $this->api = new civicrm_api3();
    $api = $this->api;
    $id = (int)CRM_Utils_Request::retrieve('id', 'Positive', $this);
    $reference = CRM_Utils_Request::retrieve('ref', 'String', $this);
    $template = (int)CRM_Utils_Request::retrieve('tpl', 'Positive', $this, TRUE);
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
    $this->generateHTML ($api->values[0], $template);
    if (!$action) {
      $this->assign("html",$this->html);
      parent::run();
      return;
    }
    if ($action != "email") {
      $this->generatePDF (false, $template);
      CRM_Utils_System::civiExit();
    }
    $this->generatePDF (true, $template);
  }

  /**
   * Install SEPA's default message template, if not yet installed
   *
   * @author endres@systopia.de
   */
  static function installMessageTemplate() {
    $default_templates = array( "sepa_mandate"     => ts("SEPA default email template.", array('domain' => 'org.project60.sepa')),
                                "sepa_mandate_pdf" => ts("SEPA default PDF template.", array('domain' => 'org.project60.sepa')));

    foreach ($default_templates as $template_name => $template_title) {
      // find the template's entry in the option group
      $template_entry = civicrm_api('OptionValue', 'getsingle', array(
                                        'version'           => 3,
                                        'option_group_name' => 'msg_tpl_workflow_contribution',
                                        'name'              => $template_name));
      if (!empty($template_entry['is_error'])) {
        Civi::log()->debug("org.project60.sepa: OptionGroup 'msg_tpl_workflow_contribution' not properly populated. Reinstal extension. Error was: " . $template_entry['error_message']);
        continue;
      }

      // find the template itself
      $template = civicrm_api('MessageTemplate', 'get', array(
                                        'version'           => 3,
                                        'workflow_id'       => $template_entry['id']));

      if (!empty($template['is_error'])) {
        Civi::log()->debug("org.project60.sepa: Error while checking template '$template_name': ".$template['error_message']);
      } else if ($template['count'] > 1) {
        Civi::log()->debug("org.project60.sepa: There's multiple templates installed for '$template_name'.");
      } else if ($template['count'] == 1) {
        Civi::log()->debug("org.project60.sepa: Template '$template_name' seems to be correctly installed. Not updated.");
      } else {
        // template not yet installed, do it!
        $filepath = __DIR__ . "/../../../templates/Sepa/DefaultMessageTemplates/$template_name.html";
        if (!file_exists($filepath)) {
          Civi::log()->debug("org.project60.sepa: Couldn't find default template date at '$filepath'");
          continue;
        }
        $result =  civicrm_api('MessageTemplate', 'create', array(
              'version'     => 3,
              'workflow_id' => $template_entry['id'],
              'msg_title'   => $template_title,
              'msg_subject' => ts("SEPA Direct Debit Payment Information", array('domain' => 'org.project60.sepa')),
              'is_reserved' => 0,
              'msg_html'    => file_get_contents($filepath),
              'msg_text'    => 'N/A'
              ));

        if (!empty($result['is_error'])) {
          Civi::log()->debug("org.project60.sepa: There was an error trying to create template '$template_name': " . $result['error_message']);
        }
      }
    }
  }
}
