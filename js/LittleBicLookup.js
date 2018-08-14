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

let sdd_hide_bic_enabled   = false;
let sdd_lookup_bic_timerID = 0;
let sdd_lookup_bic_timeout = 400;
let sdd_last_lookup = '';


/**
 * Clear bank for lookup
 */
function sdd_clear_bank() {
  cj("#bank_name").html('');
  cj("#bic_busy").hide();
}

/**
 * BIC visibility
 */
function sdd_show_bic(show_bic, message) {
  if (sdd_hide_bic_enabled) {
    if (show_bic) {
      cj("#bic").parent().parent().show();
      cj("#bic").parent().find("span.sepa-warning").remove();
      if (message.length) {
        cj("#bic").parent().append("<span class='sepa-warning'>&nbsp;&nbsp;" + message + "</span>");
      }
    } else {
      // hide only if no error label attached:
      if (!cj("#bic").parent().find("span.crm-error").length) {
        cj("#bic").parent().parent().hide();
      }
    }
  }
}

function sdd_lookup_bic_trigger() {
  // clear any existing lookup timers
  if (sdd_lookup_bic_timerID) {
    clearTimeout(sdd_lookup_bic_timerID);
    sdd_lookup_bic_timerID = 0;
  }
  // set a new timeout
  sdd_lookup_bic_timerID = window.setTimeout(sdd_lookup_bic, sdd_lookup_bic_timeout);
}

/**
 * Resolve BIC
 */
function sdd_lookup_bic() {
  // first: clean up IBAN
  let reSpaceAndMinus = new RegExp('[\\s-]', 'g');
  let iban_partial = cj("#iban").val();
  iban_partial = iban_partial.replace(reSpaceAndMinus, "");
  iban_partial = iban_partial.toUpperCase();
  if (iban_partial == undefined || iban_partial.length == 0 || iban_partial == sdd_last_lookup) {
    // in these cases there's nothing to do
    return;
  }
  if (sdd_hide_bic_enabled) {
    // if it's hidden, we should clear it at this point
    cj("#bic").attr('value', '');
  }
  cj("#bic_busy").show();
  cj("div.payment_processor-section").trigger("sdd_biclookup", "started");
  sdd_last_lookup = iban_partial;
  CRM.api('Bic', 'findbyiban', {'iban': iban_partial},
    {success: function(data) {
      if ('bic' in data) {
        cj("#bic").attr('value', data['bic']);
        cj("#bank_name").html(data['title']);
        cj("#bic_busy").hide();
        cj("div.payment_processor-section").trigger("sdd_biclookup", "success");
        sdd_show_bic(false, "");
      } else {
        sdd_clear_bank();
        sdd_show_bic(true, "");
        cj("#bic").attr('value', '');
        cj("div.payment_processor-section").trigger("sdd_biclookup", "failed");
      }
    }, error: function(result, settings) {
      // we suppress the message box here
      // and log the error via console
      cj("#bic_busy").hide();
      cj("div.payment_processor-section").trigger("sdd_biclookup", "failed");
      if (result.is_error) {
        console.log(result.error_message);
        sdd_clear_bank();
        sdd_show_bic(true, result.error_message);
      }
      return false;
    }});
}

/**
 * bootstrap stuff
 */
cj(document).ready(function() {
  cj("#bic").parent().append('&nbsp;<img id="bic_busy" height="12" src="' + CRM.vars.p60sdd.busy_icon_url + '"/>&nbsp;<font color="gray"><span id="bank_name"></span></font>');
  cj("#iban").on("input click keydown blur", sdd_lookup_bic_trigger);
  cj("#bic_busy").hide();
  // call it once
  sdd_lookup_bic();
});
