{*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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
+-------------------------------------------------------*}

{* check for the org.project60.bic extension *}
{crmAPI var='bic_extension_check' entity='Bic' action='findbyiban' q='civicrm/ajax/rest' bic='TEST'}
{capture assign=bic_extension_installed}{if $bic_extension_check.is_error eq 0}1{/if}{/capture}

<!-- this field is hidden by default, so people wouldn't worry about it. Feel free to show via a customisation extension -->
<div id="sdd-cycle-day-section" class="crm-section {$form.cycle_day.name}-section" style="display: none;">
	<div class="label">{$form.cycle_day.label}</div>
	<div class="content">{$form.cycle_day.html}</div>
	<div class="clear"></div>
</div>

<!-- this field is hidden by default, so people wouldn't worry about it. Feel free to show via a customisation extension -->
<div id="sdd-start-date-section" class="crm-section {$form.start_date.name}-section" style="display: none;">
	<div class="label">{$form.start_date.label}</div>
	<div class="content">{include file="CRM/common/jcalendar.tpl" elementName=start_date}</div>
	<div class="clear"></div>
</div>

<!-- JS Magic -->
<script type="text/javascript">
// translated captions
var earliest_ooff_date = new Date(parseInt({$earliest_ooff_date[0]}, 10), parseInt({$earliest_ooff_date[1]}, 10) - 1, parseInt({$earliest_ooff_date[2]}, 10));
var earliest_rcur_date = new Date(parseInt({$earliest_rcur_date[0]}, 10), parseInt({$earliest_rcur_date[1]}, 10) - 1, parseInt({$earliest_rcur_date[2]}, 10));
var currently_set_date = new Date("{$form.start_date.value}");

{literal}

// change elements according to recur status
function _sdd_update_elements() {
	var is_recur = cj("#is_recur").prop('checked');

	// the cycle day field is hidden by default, so people wouldn't have to
	// care about it:
	// cj("#cycle_day").parent().parent().attr('hidden', !is_recur);
	var start_date_display = cj('.start_date-section .content input:nth-child(2)');
	if (is_recur) {
		cj(start_date_display).datepicker("option", "minDate", earliest_rcur_date);
		if (currently_set_date > earliest_rcur_date) {
			cj(start_date_display).datepicker("setDate", currently_set_date);
		} else {
			cj(start_date_display).datepicker("setDate", earliest_rcur_date);
		}
	} else {
		cj(start_date_display).datepicker("option", "minDate", earliest_ooff_date);
		if (currently_set_date > earliest_ooff_date) {
			cj(start_date_display).datepicker("setDate", currently_set_date);
		} else {
			cj(start_date_display).datepicker("setDate", earliest_ooff_date);
		}
	}
}

cj(function() {
	// add event handler for IBAN entered
	cj("#bank_account_number").change(sepa_process_iban);
});

function sepa_process_iban() {
	var reSpaceAndMinus = new RegExp('[\\s-]', 'g');
	var sanitized_iban = cj("#bank_account_number").val();
	sanitized_iban = sanitized_iban.replace(reSpaceAndMinus, "");
	sanitized_iban = sanitized_iban.toUpperCase();
	cj("#bank_account_number").val(sanitized_iban);
	{/literal}{if $bic_extension_installed}
	sepa_lookup_bic();
	{/if}{literal}
}

</script>
{/literal}

{if $bic_extension_installed}
<script type="text/javascript">
var busy_icon_url = "{$config->resourceBase}i/loading.gif";
var sepa_hide_bic_enabled = parseInt("{$sepa_hide_bic}");
var sepa_lookup_bic_error_message = "{ts}Bank unknown, please enter BIC.{/ts}";
{literal}

cj(function() {
	cj("#bank_account_number").parent().append('&nbsp;<img id="bic_busy" height="12" src="' + busy_icon_url + '"/>');
	cj("#bic_busy").hide();
	// call it once
	sepa_lookup_bic();
});

function sepa_clear_bank() {
  cj("#bank_name").val('');
  cj("#bic_busy").hide();
}

function sepa_show_bic(show_bic, message) {
	if (sepa_hide_bic_enabled) {
		if (show_bic) {
			cj("#bank_identification_number").parent().parent().show();
			cj("#bank_identification_number").parent().find("span.sepa-warning").remove();
			if (message.length) {
				cj("#bank_identification_number").parent().append("<span class='sepa-warning'>&nbsp;&nbsp;" + message + "</span>");
			}
		} else {
			// hide only if no error label attached:
			if (!cj("#bank_identification_number").parent().find("span.crm-error").length) {
				cj("#bank_identification_number").parent().parent().hide();
			}
		}
	}
}

function sepa_lookup_bic() {
	var iban_partial = cj("#bank_account_number").val();
	if (iban_partial.length == 0) return;
	if (sepa_hide_bic_enabled) {
		// if it's hidden, we should clear it at this point
		cj("#bank_identification_number").attr('value', '');
	}
	cj("#bic_busy").show();
  CRM.api('Bic', 'findbyiban', {'q': 'civicrm/ajax/rest', 'iban': iban_partial},
    {success: function(data) {
    	if ('bic' in data) {
        // use the following to urldecode the link url
        cj("#bank_identification_number").attr('value', data['bic']);
        cj("#bank_name").val(data['title']);
        cj("#bic_busy").hide();
        sepa_show_bic(false, "");
      } else {
      	sepa_clear_bank();
        //sepa_show_bic(true, sepa_lookup_bic_error_message);
        sepa_show_bic(true, "");
        cj("#bank_identification_number").attr('value', '');
      }
    }, error: function(result, settings) {
			// we suppress the message box here
			// and log the error via console
      cj("#bic_busy").hide();
			if (result.is_error) {
				console.log(result.error_message);
				sepa_clear_bank();
				sepa_show_bic(true, result.error_message);
			}
			return false;
		}});
}

// initially hide the bic (if hiding enabled)
cj(function(){
	sepa_show_bic(false, "");	
});
{/literal}
</script>
{/if}
