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

{assign var='mandate_id' value=$sepa.id}

{if not $deleted_mandate}

<h3>{if $contribution.cycle_day}{ts}SEPA Recurring Mandate{/ts}{else}{ts}SEPA Single Payment Mandate{/ts}{/if} [{$sepa.id}]</h3>
<div class="crm-container">
    <div class="crm-block crm-content-block crm-sdd-mandate">
        <table class="crm-info-panel">
            <tr><td class="label">{ts}Status{/ts}</td><td><b>
            	{if $sepa.status eq 'INIT'}
            		{ts}Not activated{/ts}
            	{elseif $sepa.status eq 'FRST' or $sepa.status eq 'OOFF'}
            		{ts}Ready{/ts}
            	{elseif $sepa.status eq 'RCUR' or $sepa.status eq 'SENT'}
            		{ts}In Use{/ts}
            	{elseif $sepa.status eq 'COMPLETE'}
            		{ts}Completed{/ts}
            	{elseif $sepa.status eq 'INVALID'}
            		{ts}Error{/ts}
            	{elseif $sepa.status eq 'ONHOLD'}
            		{ts}Suspended{/ts}
            	{/if}
            </b></td></tr>
            <tr><td class="label">{ts}Contact{/ts}</td><td><a href="{$contact1.link}"><div class="icon crm-icon {$contact1.contact_type}-icon"></div>{$contact1.display_name}</a></td></tr>
            <tr><td class="label">{ts}Reference{/ts}</td><td>{$sepa.reference}</td></tr>
            <tr><td class="label">{ts}IBAN{/ts}</td><td>{$sepa.iban}</td></tr>
            <tr><td class="label">{ts}BIC{/ts}</td><td>{$sepa.bic}</td></tr>
            <tr><td class="label">{ts}Creditor{/ts}</td><td>{$sepa.creditor_name} [{$sepa.creditor_id}]</td></tr>
            <tr><td class="label">{ts}Source{/ts}</td><td>{$sepa.source}</td></tr>
            <tr><td class="label">{ts}Status{/ts}</td><td>{$sepa.status}</td></tr>
            <tr><td class="label">{ts}Creation date{/ts}</td><td>{$sepa.creation_date}</td></tr>
            <tr><td class="label">{ts}Signature date{/ts}</td><td>{$sepa.date}</td></tr>
            <tr><td class="label">{ts}Validation date{/ts}</td><td>{$sepa.validation_date}</td></tr>
        </table>
    </div>
</div>
<h3>{ts}Payment Details{/ts}: <a href="{$contribution.link}">{if $contribution.cycle_day}{ts}Recurring Contribution{/ts}{else}{ts}Contribution{/ts}{/if} [{$contribution.id}]</a></h3>
<div class="crm-container">
    <div class="crm-block crm-content-block crm-sdd-mandate">
        <table class="crm-info-panel">
            <tr><td class="label">{ts}Contact{/ts}</td><td><a href="{$contact2.link}"><div class="icon crm-icon {$contact2.contact_type}-icon"></div>{$contact2.display_name}</a></td></tr>
            <tr><td class="label">{ts}Financial Type{/ts}</td><td>{$contribution.financial_type}</td></tr>
            <tr><td class="label">{ts}Campaign{/ts}</td><td>{$contribution.campaign}</td></tr>
            <tr><td class="label">{ts}Amount{/ts}</td><td>{$contribution.amount|crmMoney:$contribution.currency}</td></tr>
            {if $contribution.cycle_day}
            	{* this is a recurring contribution *}
	            <tr><td class="label">{ts}Create Date{/ts}</td><td>{$contribution.create_date}</td></tr>
	            <tr><td class="label">{ts}Last Modified{/ts}</td><td>{$contribution.modified_date}</td></tr>
	            <tr><td class="label">{ts}Frequency{/ts}</td><td>{$contribution.cycle}</td></tr>
	            <tr><td class="label">{ts}Collection Day{/ts}</td><td>{$contribution.cycle_day}</td></tr>
                <tr><td class="label">{ts}Start Date{/ts}</td><td>{$contribution.start_date}</td></tr>
                <tr><td class="label">{ts}End Date{/ts}</td><td>{$contribution.end_date}</td></tr>
           	{else}
            	{* this is a simple contribution *}
                <tr><td class="label">{ts}Date{/ts}</td><td>{$contribution.receive_date}</td></tr>
	       	{/if}

            {* add note field *}
            {crmAPI var='result' entity='Note' action='get' q='civicrm/ajax/rest' subject='cancel_reason' entity_id=$contribution.id entity_table='civicrm_contribution_recur'}
            {foreach from=$result.values item=Note}
            <tr><td class="label">{ts}Cancel Reason{/ts}</td><td>{$Note.note}</td></tr>
            {/foreach}
        </table>
    </div>
</div>

<h2>{ts}Options{/ts}</h2>
<form id="sepa_action_form" action="{crmURL p="civicrm/sepa/xmandate" q="mid=$mandate_id"}" method="post">
	<input type="hidden" name="action" id="mandate_action_value" value=""/>
	<div class="crm-container">
        <table class="crm-info-panel">
        	{if $sepa.status eq 'INIT'}<tr>
            	<td class="label" style="vertical-align: middle;"><a class="button" onclick="mandate_action_activate();">{ts}Activate{/ts}</td>
            	<td>{ts}Activate the mandate when the written permission was received.{/ts}</td>
            </tr>{/if}

        	{if $can_delete}{if $sepa.status eq 'OOFF' or $sepa.status eq 'FRST'}<tr>
            	<td class="label"><a class="button" onclick="mandate_action_delete();">{ts}Delete{/ts}</td>
            	<td>{ts}Completely delete this mandate along with the contribution. This is only possible because it has not yet been submitted to the bank.{/ts}</td>
            </tr>{/if}{/if}

        	{if $sepa.status eq 'FRST' or $sepa.status eq 'RCUR' or $sepa.status eq 'INIT' or $sepa.status eq 'OOFF'}<tr>
            	<td class="label" style="vertical-align: middle;"><a class="button" onclick="mandate_action_cancel();">{ts}Cancel{/ts}</td>
            	<td>{ts}Cancel this mandate immediately for the following reason:{/ts}&nbsp;<input type="text" name="cancel_reason" size="32" /></td>
            </tr>{/if}

            {if $contribution.cycle_day}{if $sepa.status eq 'FRST' or $sepa.status eq 'RCUR' or $sepa.status eq 'INIT'}<tr>
            	<td class="label" style="vertical-align: middle;"><a class="button" onclick="mandate_action_end();">{ts}Set End Date{/ts}</td>
            	<td>
                    {ts}Terminate this mandate:{/ts}&nbsp;<input type="text" name="end_date" id="end_date" size="12" value="{$contribution.default_end_date}" />
                    <br/>
                    {ts}Terminate for the following reason:{/ts}&nbsp;
                    <input type="text" name="end_reason" size="32" />
                </td>
            </tr>{/if}{/if}

            {if $contribution.cycle_day}{if $sepa.status eq 'FRST' or $sepa.status eq 'RCUR' or $sepa.status eq 'INIT'}<tr>
                <td class="label" style="vertical-align: middle;"><a class="button" onclick="mandate_action_replace();">{ts}Replace{/ts}</td>
                <td>
                    {ts}Replace the mandate beginning:{/ts}&nbsp;<input type="text" name="replace_date" id="replace_date" size="12" value="{$contribution.default_end_date}" />
                    <br/>
                    {ts}Replace for the following reason:{/ts}&nbsp;
                    <input type="text" name="replace_reason" size="32" />
                </td>
            </tr>{/if}{/if}

            {if $can_modify}{if $contribution.cycle_day}{if $sepa.status eq 'FRST' or $sepa.status eq 'RCUR' or $sepa.status eq 'INIT'}<tr>
                <td class="label" style="vertical-align: middle;"><a class="button" onclick="mandate_action_adjust_amount();">{ts}Adjust Amount{/ts}</td>
                <td>
                    {ts}Change amount to:{/ts}&nbsp;<input type="text" name="adjust_amount" id="adjust_amount" size="12" value="{$contribution.amount}" />&nbsp;{$contribution.currency}
                </td>
            </tr>{/if}{/if}{/if}

            <tr>
            	<td class="label" style="vertical-align: middle;"><a href="{crmURL p="civicrm/sepa/cmandate" q="clone=$mandate_id"}" class="button">{ts}Clone{/ts}</td>
            	<td>{ts}Create a new mandate similar to this.{/ts}</td>
            </tr>

            <tr>
                <td id='mandate_pdf_action' class="label" style="vertical-align: middle;"><a class="button" onclick="mandate_action_create_pdf();">{ts}PDF Prenotification{/ts}</td>
                <td>
                    {ts}Will generate a Prenotification PDF with this mandate's data.{/ts}                    
                    <br/>
                    {if !empty($sepa_templates)}
                    {ts}Select the template to be used:{/ts}<a id='template_help' onclick='CRM.help("{ts}Template{/ts}", {literal}{"id":"id-template-help","file":"CRM\/Sepa\/Page\/EditMandate"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a>
                    &nbsp;
                    <select id="sepa_tpl_select" style="">
                        {foreach from=$sepa_templates item=item}
                        <option value="{$item[0]}">{$item[1]}</option>
                        {/foreach}
                    </select>
                    {else}
                    <strong>{ts}No suitable templates found! Reinstall the SEPA extensions and check your message templates.{/ts}</strong>
                    {/if}
                </td>
            </tr>
        </table>
	</div>
</form>

<script type="text/javascript">
cancel_reason_message = "{ts}You need to specify a cancel reason!{/ts}";
end_date_message = "{ts}You need to specify a date!{/ts}";
replace_url = "{crmURL p="civicrm/sepa/cmandate" q="replace=$mandate_id"}";

{literal}
function mandate_action_create_pdf() {
  var selected_template = cj("#sepa_tpl_select").val();
  window.open({/literal}'{crmURL p='civicrm/sepa/pdf' h=0 q="reset=1&pdfaction=print&id=$mandate_id"}'{literal} + '&tpl=' + selected_template, '_blank');
}

function mandate_action_delete() {
	cj("#mandate_action_value").val('delete');
	cj("#sepa_action_form").submit();
}

function mandate_action_adjust_amount() {
    cj("#mandate_action_value").val('adjustamount');
    cj("#sepa_action_form").submit();
}

function mandate_action_cancel() {
	cj("#mandate_action_value").val('cancel');
	if (cj("[name='cancel_reason']").val()) {
		cj("#sepa_action_form").submit();
	} else {
		alert(cancel_reason_message);
	}
}

function mandate_action_end() {
	cj("#mandate_action_value").val('end');
    if (!cj("[name='end_reason']").val()) {
        alert(cancel_reason_message);
        return;
    }
    if (!cj("[name='end_date']").val()) {
        alert(end_date_message);
        return;
    }
	cj("#sepa_action_form").submit();
}

function mandate_action_replace() {
    cj("#mandate_action_value").val('replace');
    if (!cj("[name='replace_reason']").val()) {
        alert(cancel_reason_message);
        return;
    }
    if (!cj("[name='replace_date']").val()) {
        alert(end_date_message);
        return;
    }
    replace_url = cj("<div/>").html(replace_url).text();
    replace_url += '&replace_date=' + encodeURIComponent(cj("[name='replace_date']").val());
    replace_url += '&replace_reason=' + encodeURIComponent(cj("[name='replace_reason']").val());
    location.href = replace_url;
}

// Date picker
var dateOptions = {
    dateFormat: 'yy-mm-dd',
    changeMonth: true,
    changeYear: true,
    minDate: 'now',
    yearRange: '0:+50'
  };
cj('#replace_date').addClass('dateplugin');
cj('#replace_date').datepicker(dateOptions);
cj('#end_date').addClass('dateplugin');
cj('#end_date').datepicker(dateOptions);
</script>
{/literal}


{else}
<p>{ts}Mandate {$deleted_mandate} succesfully deleted.{/ts}
{/if}
