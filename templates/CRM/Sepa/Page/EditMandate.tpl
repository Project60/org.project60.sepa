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
            <tr><td class="label">{ts}Amount{/ts}</td><td>{$contribution.amount}</td></tr>
            {if $contribution.cycle_day}
            	{* this is a recurring contribution *}
	            <tr><td class="label">{ts}Create Date{/ts}</td><td>{$contribution.create_date}</td></tr>
	            <tr><td class="label">{ts}Last Modified{/ts}</td><td>{$contribution.modified_date}</td></tr>
	            <tr><td class="label">{ts}Frequency{/ts}</td><td>{$contribution.cycle}</td></tr>
	            <tr><td class="label">{ts}Collection Day{/ts}</td><td>{$contribution.cycle_day}.</td></tr>
	            <tr><td class="label">{ts}End Date{/ts}</td><td>{$contribution.end_date}</td></tr>
           	{else}
            	{* this is a simple contribution *}
                <tr><td class="label">{ts}Date{/ts}</td><td>{$contribution.receive_date}</td></tr>
	       	{/if}
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

        	{if $sepa.status eq 'OOFF' or $sepa.status eq 'FRST' or $sepa.status eq 'RCUR' or $sepa.status eq 'INIT'}<tr>
            	<td class="label" style="vertical-align: middle;"><a class="button" onclick="mandate_action_cancel();">{ts}Cancel{/ts}</td>
            	<td>{ts}Cancel this mandate immediately for the following reason:{/ts}&nbsp;<input type="text" name="cancel_reason" size="32" /></td>
            </tr>{/if}

            {if $contribution.cycle_day}{if $sepa.status eq 'FRST' or $sepa.status eq 'RCUR' or $sepa.status eq 'INIT'}<tr>
            	<td class="label" style="vertical-align: middle;"><a class="button" onclick="mandate_action_end();">{ts}Set End Date{/ts}</td>
            	<td>
                    {ts}Terminate this mandate:{/ts}&nbsp;<input type="text" name="end_date" size="12" value="{$contribution.default_end_date}" />
                    <br/>
                    {ts}Terminate for the following reason:{/ts}&nbsp;
                    <input type="text" name="end_reason" size="32" />
                </td>
            </tr>{/if}{/if}

            {if $contribution.cycle_day}{if $sepa.status eq 'FRST' or $sepa.status eq 'RCUR' or $sepa.status eq 'INIT'}<tr>
                <td class="label" style="vertical-align: middle;"><a class="button" onclick="mandate_action_replace();">{ts}Replace{/ts}</td>
                <td>
                    {ts}Replace the mandate beginning:{/ts}&nbsp;<input type="text" name="replace_date" size="12" value="{$contribution.default_end_date}" />
                    <br/>
                    {ts}Replace for the following reason:{/ts}&nbsp;
                    <input type="text" name="replace_reason" size="32" />
                </td>
            </tr>{/if}{/if}

            <tr>
            	<td class="label" style="vertical-align: middle;"><a href="{crmURL p="civicrm/sepa/cmandate" q="clone=$mandate_id"}" class="button">{ts}Clone{/ts}</td>
            	<td>{ts}Create a new mandate similar to this.{/ts}</td>
            </tr>
        </table>
	</div>
</form>

<script type="text/javascript">
cancel_reason_message = "{ts}You need to specify a cancel reason!{/ts}";
end_date_message = "{ts}You need to specify a date!{/ts}";
replace_url = "{crmURL p="civicrm/sepa/cmandate" q="replace=$mandate_id"}";

{literal}
function mandate_action_delete() {
	cj("#mandate_action_value").val('delete');
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

</script>
{/literal}


{else}
<p>{ts}Mandate {$deleted_mandate} succesfully deleted.{/ts}
{/if}
