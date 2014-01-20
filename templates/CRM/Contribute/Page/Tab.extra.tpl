{* only show this for the list view: *}
{if $summary}
<a id="sepa_payment_extra_button" class="button" href="{crmURL p="civicrm/sepa/cmandate" q="cid=$contactId"}">
	<span>
		<div class="icon add-icon"></div>
		{ts}Record SEPA Contribution{/ts}
	</span>
</a>

<script type="text/javascript">
cj(".action-link").prepend(cj("#sepa_payment_extra_button"));
</script>
{/if}
