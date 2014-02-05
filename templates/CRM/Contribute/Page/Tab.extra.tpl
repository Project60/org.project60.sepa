{* only show this for the list view: *}
{if $summary}
{* add extra payment button *}
<a id="sepa_payment_extra_button" class="button" href="{crmURL p="civicrm/sepa/cmandate" q="cid=$contactId"}">
	<span>
		<div class="icon add-icon"></div>
		{ts}Record SEPA Contribution{/ts}
	</span>
</a>
<script type="text/javascript">
cj(".action-link").prepend(cj("#sepa_payment_extra_button"));
</script>


{* modify the options for SEPA payments *}
<script type="text/javascript">
{foreach from=$recurRows item=recur_data key=recur_id}
	{crmAPI var='mandate' entity='SepaMandate' action='getsingle' q='civicrm/ajax/rest' entity_id=$recur_id entity_table='civicrm_contribution_recur'}
	{if $mandate.id}
		{* this is a SEPA rcontribution *}
		// remove the cancel option
		var disable_action = cj("#row_{$recur_id}").find("a.disable-action");
		disable_action.hide();
		// modify the edit option
		{assign var='mandate_id' value=$mandate.id}
		var edit_action = disable_action.prev();
		edit_action.attr('href', '{crmURL p="civicrm/sepa/xmandate" q="mid=$mandate_id"}'.replace('&amp;', '&'));
		edit_action.html("{ts}edit mandate{/ts}");
		edit_action.attr('title', "{ts}edit sepa mandate{/ts}");
	{/if}
{/foreach}
</script>

{/if} {* is summary *}
