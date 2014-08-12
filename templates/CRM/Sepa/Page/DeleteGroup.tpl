{capture assign=type_label}
{if $txgroup.type eq "OOFF"}
{ts}one-off direct debit payment{/ts}
{elseif $txgroup.type eq "RCUR"}
{ts}recurring direct debit payment{/ts}
{else}
{$txgroup.type}
{/if}
{/capture}

{capture assign=entity_label}
{if $txgroup.type eq "OOFF"}
{ts}mandates{/ts}
{elseif $txgroup.type eq "RCUR"}
{ts}contributions{/ts}
{else}
things
{/if}
{/capture}

{* General statement describing the group *}
<h3>{ts 1=$txgroup.reference}Deleting SEPA transaction group '%1'{/ts}</h3>
<form>
<p>
	{ts 1=$type_label 2=$txgroup.status_label}This %1 transaction group is currently in status '<b>%2</b>'.{/ts}
	{if $txgroup.status_name neq 'Open'}
		{if $txgroup.status_name eq 'Closed'}
			{ts}This means, that is has already been submitted to the bank.{/ts}
			{ts}Deleting the group will erase any record of this transaction.{/ts}
		{elseif $txgroup.status_name eq 'Received'}
			{ts}This means, that the payments should have already been processed.{/ts}
			{ts}Deleting the group will erase any record of this transaction.{/ts}
		{else}
			<span class="status message">{ts}<b>This is an illegal state!{/ts}</b></span>
		{/if}
		<p class="status message"><span class="icon red-icon alert-icon"></span>{ts}Only proceed if you know what you're doing.{/ts}</p>
	{/if}
</p>

{* General statement describing the contents of the group *}
<p>
	{ts 1=$stats.total 2=$entity_label}The group has a total of %1 associated %2.{/ts}
	{if $stats.total eq $stats.open}
		{ts}None of them have been processed yet, so they could be deleted.{/ts}
	{elseif $stats.total eq $stats.busy}
		{ts}All of them have already been transmitted to the bank.{/ts}
	{elseif $stats.total eq $stats.other}
		{ts}All of them have already been fully processed.{/ts}
	{else}
		{ts 1=$stats.open 2=$stats.busy 3=$stats.other 4=$entity_label}%1 of them have not been processed yet, and could be deleted. However, there are %2 %4 that have already been sent to the bank for collection, and another %3 that have been fully processed.{/ts}
	{/if}
	{ts 1=$entity_label}What do you want to do with the associated %1?{/ts}
</p>

{* Options on how to proceed with the contents (mandates/contributions) *}
<p>
	<input id="delete_contents_NO" name="delete_contents" value="no" checked="checked" class="form-radio" type="radio">
	<label for="delete_contents_NO">{ts 1=$entity_label}don't delete any %1{/ts}</label>
	</input>
	<input id="delete_contents_OPEN" name="delete_contents" value="open" class="form-radio" type="radio">
	<label for="delete_contents_OPEN">{ts 1=$entity_label}delete open %1{/ts}</label>
	</input>
	<input id="delete_contents_ALL" name="delete_contents" value="all" class="form-radio" type="radio">
	<label for="delete_contents_ALL">{ts 1=$entity_label}delete all %1{/ts}</label>
	</input>

</p>


{* These warnings relate to the option selected above *}
<p id="warning_RCUR-Open_all" hidden="1" class="status message" name="warnings">
	<span class="icon red-icon alert-icon"></span>
	{ts}You should {/ts}
</p>
<p id="warning_RCUR-Open_no" hidden="1" class="status message" name="warnings">
	{ts}You should consider deleting the open contributions {/ts}
</p>



{* finally the submit or leave choice buttons *}
<p>
	{ts}Are you sure this is what you want to do?{/ts}
</p>
<div class="crm-submit-buttons">
	<input id="ok_button" class="button button_close" type="button" value="{ts}Yes{/ts}" />
	<input id="cancel_button" class="button button_close" type="button" value="{ts}No{/ts}" />
</div> 
</form>




{* ...and some JS to make it interactive *}
<script type="text/javascript">
var group_type = "{$txgroup.type}";
var group_status = "{$txgroup.status_name}";

// register event handlers
cj("[name='delete_contents']").change(showWarning);
cj("#delete_contents_NO").attr("checked", "checked");
cj("#ok_button").click(submitForm);
cj("#cancel_button").click(leaveForm);


{literal}
function showWarning(event) {
	// first hide all warnings
	cj("[name='warnings']").hide();

	// then show the right one
	var warning_selector = "#warning_" + group_type + "-" + group_status + "_" + event.currentTarget.value;
	cj(warning_selector).show();
}

function submitForm() {
	console.log("TODO");
}

function leaveForm() {
	console.log("TODO");
}
{/literal}
</script>