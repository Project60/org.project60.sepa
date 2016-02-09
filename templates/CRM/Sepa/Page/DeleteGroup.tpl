{capture assign=type_label}
{if $txgroup.type eq "OOFF"}
{ts domain="org.project60.sepa"}one-off direct debit payment{/ts}
{elseif $txgroup.type eq "RCUR" or $txgroup.type eq "FRST"}
{ts domain="org.project60.sepa"}recurring direct debit payment{/ts}
{else}
{$txgroup.type}
{/if}
{/capture}

{capture assign=entity_label}
{if $txgroup.type eq "OOFF"}
{ts domain="org.project60.sepa"}mandates{/ts}
{elseif $txgroup.type eq "RCUR" or $txgroup.type eq "FRST"}
{ts domain="org.project60.sepa"}contributions{/ts}
{else}
things
{/if}
{/capture}

{* General statement describing the group *}
<h3>{ts 1=$txgroup.reference domain="org.project60.sepa"}Deleting SEPA transaction group '%1'{/ts}</h3>


{if $status eq 'unconfirmed'}
<form id="delete_group_form" action="{$submit_url}" method="POST">

<input type="hidden" name="group_id" value="{$txgroup.id}">
<input type="hidden" name="confirmed" value="no">

<p>
	{ts 1=$type_label 2=$txgroup.status_label domain="org.project60.sepa"}This %1 transaction group is currently in status '<b>%2</b>'.{/ts}
	{if $txgroup.status_name neq 'Open'}
		{if $txgroup.status_name eq 'Closed'}
			{ts domain="org.project60.sepa"}This means, that is has already been submitted to the bank.{/ts}
			{ts domain="org.project60.sepa"}Deleting the group will erase any record of this transaction.{/ts}
		{elseif $txgroup.status_name eq 'Received'}
			{ts domain="org.project60.sepa"}This means, that the payments should have already been processed.{/ts}
			{ts domain="org.project60.sepa"}Deleting the group will erase any record of this transaction.{/ts}
		{else}
			<span class="status message"><b>{ts domain="org.project60.sepa"}This is an illegal state!{/ts}</b></span>
		{/if}
		<p class="status message"><span class="icon red-icon alert-icon"></span>{ts domain="org.project60.sepa"}Only proceed if you know what you're doing.{/ts}</p>
	{/if}
</p>

{* General statement describing the contents of the group *}
<p>
	{ts 1=$stats.total 2=$entity_label domain="org.project60.sepa"}The group has a total of %1 associated %2.{/ts}
	{if $stats.total eq $stats.open}
		{ts domain="org.project60.sepa"}None of them have been processed yet, so they could be deleted.{/ts}
	{elseif $stats.total eq $stats.busy}
		{ts domain="org.project60.sepa"}All of them have already been transmitted to the bank.{/ts}
	{elseif $stats.total eq $stats.other}
		{ts domain="org.project60.sepa"}All of them have already been fully processed.{/ts}
	{else}
		{ts 1=$stats.open 2=$stats.busy 3=$stats.other 4=$entity_label domain="org.project60.sepa"}%1 of them have not been processed yet, and could be deleted. However, there are %2 %4 that have already been sent to the bank for collection, and another %3 that have been fully processed.{/ts}
	{/if}
	{ts 1=$entity_label domain="org.project60.sepa"}What do you want to do with the associated %1?{/ts}
</p>

{* Options on how to proceed with the contents (mandates/contributions) *}
<p>
	<input id="delete_contents_NO" name="delete_contents" value="no" class="form-radio" type="radio">
	<label for="delete_contents_NO">{ts 1=$entity_label domain="org.project60.sepa"}don't delete any %1{/ts}</label>
	</input>
	<input id="delete_contents_ALL" name="delete_contents" value="all" class="form-radio" type="radio">
	<label for="delete_contents_ALL">{ts 1=$entity_label domain="org.project60.sepa"}delete all %1{/ts}</label>
	</input>
	<input id="delete_contents_OPEN" name="delete_contents" value="open" class="form-radio" type="radio">
	<label for="delete_contents_OPEN">{ts 1=$entity_label domain="org.project60.sepa"}delete pending %1{/ts}</label>
	</input>
</p>


{* These warnings relate to the option selected above *}
<p id="warning_RCUR-Open_no" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}You should consider deleting the open contributions. If the recurring transaction group is deleted, these payments do not make sense any more. They will also be generated again, once you hit update.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these contributions will <i>not</i> affect the associated mandates.{/ts}
</p>
<p id="warning_RCUR-Open_all" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}You should be very careful when deleting processed contributions. It usually means, that related 'real world activites' (like money transfers) have already been initiated or even completed.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these contributions will <i>not</i> affect the associated mandates.{/ts}
</p>
<p id="warning_RCUR-Closed_no" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}A closed group has usually been sent to a bank to initiate the payment process, so you shouldn't want to delete this group anyway. However if you do, you should also consider deleting all associated contributions, since they will otherwise remain in the status 'in progress' and not be processed any more.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these contributions will <i>not</i> affect the associated mandates.{/ts}
</p>
<p id="warning_RCUR-Closed_open" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}A closed group has usually been sent to a bank to initiate the payment process, so you shouldn't want to delete this group anyway. However if you do, you should also consider deleting all associated contributions, since they will otherwise remain in the status 'in progress' and not be processed any more.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these contributions will <i>not</i> affect the associated mandates.{/ts}
</p>
<p id="warning_RCUR-Closed_all" hidden="1" class="status message" name="warnings">
	{ts domain="org.project60.sepa"}A closed group has usually been sent to a bank to initiate the payment process, so you shouldn't want to delete this group anyway. However if you do, deleting all associated contributions is a good choice, since they will otherwise remain in the status 'in progress' and not be processed any more.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these contributions will <i>not</i> affect the associated mandates.{/ts}
</p>
<p id="warning_RCUR-Received_no" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}A transaction group marked as received has usually been fully processed, i.e. the money actually arrived on your account. You shouldn't want to delete this group. However if you do, be sure to delete the associated contributions in case there was no money transferred in the first place.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these contributions will <i>not</i> affect the associated mandates.{/ts}
</p>
<p id="warning_RCUR-Received_open" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}A transaction group marked as received has usually been fully processed, i.e. the money actually arrived on your account. You shouldn't want to delete this group. However if you do, you should consider not deleting the associated contributions in case money was actually transferred.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these contributions will <i>not</i> affect the associated mandates.{/ts}
</p>
<p id="warning_RCUR-Received_all" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}A transaction group marked as received has usually been fully processed, i.e. the money actually arrived on your account. You shouldn't want to delete this group. However if you do, you should consider not deleting the associated contributions in case money was actually transferred.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these contributions will <i>not</i> affect the associated mandates.{/ts}
</p>


<p id="warning_FRST-Open_no" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}You should consider deleting the open contributions. If the recurring transaction group is deleted, these payments do not make sense any more. They will also be generated again, once you hit update.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these contributions will <i>not</i> affect the associated mandates.{/ts}
</p>
<p id="warning_FRST-Open_all" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}You should be very careful when deleting processed contributions. It usually means, that related 'real world activites' (like money transfers) have already been initiated or even completed.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these contributions will <i>not</i> affect the associated mandates.{/ts}
</p>
<p id="warning_FRST-Closed_no" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}A closed group has usually been sent to a bank to initiate the payment process, so you shouldn't want to delete this group anyway. However if you do, you should also consider deleting all associated contributions, since they will otherwise remain in the status 'in progress' and not be processed any more.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these contributions will <i>not</i> affect the associated mandates.{/ts}
</p>
<p id="warning_FRST-Closed_open" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}A closed group has usually been sent to a bank to initiate the payment process, so you shouldn't want to delete this group anyway. However if you do, you should also consider deleting all associated contributions, since they will otherwise remain in the status 'in progress' and not be processed any more.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these contributions will <i>not</i> affect the associated mandates.{/ts}
</p>
<p id="warning_FRST-Closed_all" hidden="1" class="status message" name="warnings">
	{ts domain="org.project60.sepa"}A closed group has usually been sent to a bank to initiate the payment process, so you shouldn't want to delete this group anyway. However if you do, deleting all associated contributions is a good choice, since they will otherwise remain in the status 'in progress' and not be processed any more.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these contributions will <i>not</i> affect the associated mandates.{/ts}
</p>
<p id="warning_FRST-Received_no" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}A transaction group marked as received has usually been fully processed, i.e. the money actually arrived on your account. You shouldn't want to delete this group. However if you do, be sure to delete the associated contributions in case there was no money transferred in the first place.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these contributions will <i>not</i> affect the associated mandates.{/ts}
</p>
<p id="warning_FRST-Received_open" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}A transaction group marked as received has usually been fully processed, i.e. the money actually arrived on your account. You shouldn't want to delete this group. However if you do, you should consider not deleting the associated contributions in case money was actually transferred.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these contributions will <i>not</i> affect the associated mandates.{/ts}
</p>
<p id="warning_FRST-Received_all" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}A transaction group marked as received has usually been fully processed, i.e. the money actually arrived on your account. You shouldn't want to delete this group. However if you do, you should consider not deleting the associated contributions in case money was actually transferred.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these contributions will <i>not</i> affect the associated mandates.{/ts}
</p>


<p id="warning_OOFF-Open_open" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}If you delete the mandates associated with this group, they cannot be collected any more. Be sure that you know what you're doing before proceeding.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these mandates will also delete the associated contributions.{/ts}
</p>
<p id="warning_OOFF-Open_all" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}If you delete the mandates associated with this group, they cannot be collected any more. Be sure that you know what you're doing before proceeding.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these mandates will also delete the associated contributions.{/ts}
</p>
<p id="warning_OOFF-Closed_no" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}A closed group has usually been sent to a bank to initiate the payment process, so you shouldn't want to delete this group anyway. However if you do, you should also consider deleting all associated mandates, since they will otherwise remain in the status 'in progress' and not be processed any more.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these mandates will also delete the associated contributions.{/ts}
</p>
<p id="warning_OOFF-Closed_open" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}A closed group has usually been sent to a bank to initiate the payment process, so you shouldn't want to delete this in the first place. However, if there's something wrong with it, be sure not to delete any mandates that you still want to collect or keep on record.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these mandates will also delete the associated contributions.{/ts}
</p>
<p id="warning_OOFF-Closed_all" hidden="1" class="status message" name="warnings">
	{ts domain="org.project60.sepa"}A closed group has usually been sent to a bank to initiate the payment process, so you shouldn't want to delete this in the first place. However, if there's something wrong with it, be sure not to delete any mandates that you still want to collect or keep on record.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these mandates will also delete the associated contributions.{/ts}
</p>
<p id="warning_OOFF-Received_no" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}A transaction group marked as received has usually been fully processed, i.e. the money actually arrived on your account. You shouldn't want to delete this group in the first place.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these mandates will also delete the associated contributions.{/ts}
</p>
<p id="warning_OOFF-Received_open" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}A transaction group marked as received has usually been fully processed, i.e. the money actually arrived on your account. You shouldn't want to delete this group in the first place. However, if there's something wrong with it, be sure not to delete any mandates that you still want to collect or keep a record of.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these mandates will also delete the associated contributions.{/ts}
</p>
<p id="warning_OOFF-Received_all" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts domain="org.project60.sepa"}A transaction group marked as received has usually been fully processed, i.e. the money actually arrived on your account. You shouldn't want to delete this group in the first place. However, if there's something wrong with it, be sure not to delete any mandates that you still want to collect or keep a record of.{/ts}
	<br/>{ts domain="org.project60.sepa"}Deleting these mandates will also delete the associated contributions.{/ts}
</p>


{* finally the submit or leave choice buttons *}
<p>
	{ts domain="org.project60.sepa"}Are you sure this is what you want to do?{/ts}
</p>
<div class="crm-submit-buttons">
	<input id="ok_button" class="button button_close" type="button" value="{ts domain="org.project60.sepa"}Yes{/ts}" />
	<input id="cancel_button" class="button button_close" type="button" value="{ts domain="org.project60.sepa"}No{/ts}" />
</div> 
</form>





{* ...and some JS to make it interactive *}
<script type="text/javascript">
var group_type = "{$txgroup.type}";
var group_status = "{$txgroup.status_name}";

// register event handlers
cj("[name='delete_contents']").change(showWarning);
cj("#ok_button").click(submitForm);
cj("#cancel_button").click(leaveForm);
cj("#delete_contents_NO").attr("checked", "checked");
cj("#delete_contents_NO").trigger("change");


{literal}
function showWarning(event) {
	// first hide all warnings
	cj("[name='warnings']").hide();

	// then show the right one
	var warning_selector = "#warning_" + group_type + "-" + group_status + "_" + event.currentTarget.value;
	cj(warning_selector).show();
}

function submitForm(object) {
	cj("[name='confirmed']").val("yes");
	cj("#delete_group_form").submit();
}

function leaveForm(object) {
	cj("#delete_group_form").submit();
}
{/literal}

</script>






{elseif $status eq 'done'}
{if $error}
<p class="status message">
	{ts 1=$txgroup.reference domain="org.project60.sepa"}SEPA transaction group '%1' could not be deleted succesfully.{/ts}<br/>
	{ts domain="org.project60.sepa"}Error was:{/ts} {$error}
</p>
{elseif $deleted_error}
<p class="status message">
	{ts 1=$txgroup.reference domain="org.project60.sepa"}SEPA transaction group '%1' was deleted, but the following problems were encountered:{/ts}
	<table>
		<thead>
			<td>{ts domain="org.project60.sepa"}contribution ID{/ts}</td>
			<td>{ts domain="org.project60.sepa"}error message{/ts}</td>
		</thead>
	{foreach from=$deleted_error item=contribution_id}
		<tr>
			<td>{ts domain="org.project60.sepa"}Contribution{/ts} [{$contribution_id}]</td>
			<td>{$deleted_result[$contribution_id]}</td>
		</tr>
	{/foreach}
	</table>
	{ts 1=$deleted_ok|@count 2=$entity_label domain="org.project60.sepa"}%1 %2 have been deleted along with the group.{/ts}
</p>
{else}
<p>
	{ts 1=$txgroup.reference domain="org.project60.sepa"}SEPA transaction group '%1' has been succesfully deleted.{/ts}
	{ts 1=$deleted_ok|@count 2=$entity_label domain="org.project60.sepa"}%1 %2 have been deleted along with the group.{/ts}
</p>
{/if}
<div class="crm-submit-buttons">
	<a id="back_button" class="button button_close" href="{crmURL p='civicrm/sepa'}"><span><div class="icon back-icon"></div>{ts domain="org.project60.sepa"}Back{/ts}</span></a>
</div> 






{else}
<p class="status message">
	<span class="icon red-icon alert-icon"></span>
	{if not $smarty.request.group_id}
	{ts domain="org.project60.sepa"}No group_id given!{/ts}
	{else}
	{ts 1=$smarty.request.group_id domain="org.project60.sepa"}Transaction group [%1] couldn't be loaded.{/ts}
	{/if}
</p>
<div class="crm-submit-buttons">
	<a id="back_button" class="button button_close" href="{crmURL p='civicrm/sepa'}"><span><div class="icon back-icon"></div>{ts domain="org.project60.sepa"}Back{/ts}</span></a>
</div> 

{/if}
