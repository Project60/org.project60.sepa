<h3>{ts}Contributions for transaction group{/ts} '{$reference}'</h3>
<table>
	<thead>
		<th>{ts}ID{/ts}</th>
		<th>{ts}Amount{/ts}</th>
		<th>&nbsp;</th>
		<th>{ts}Contact{/ts}</th>
		<th>{ts}Financial Type{/ts}</th>
		<th>{ts}Campaign{/ts}</th>
	</thead>
	<tbody>
		{foreach from=$contributions item=contribution}
		<tr>
			<td><a href="{$contribution.contribution_link}">[{$contribution.contribution_id}]</a></td>
			<td align="right"><a href="{$contribution.contribution_link}"><b>{$contribution.contribution_amount_str}</b></a></td>
			<td>&nbsp;</td>
			<td><a href="{$contribution.contact_link}"><div class="icon crm-icon {$contribution.contact_type}-icon"></div>{$contribution.contact_display_name}</a></td>
			<td>{$contribution.financial_type}</td>
			<td>{$contribution.campaign}</td>
		</tr>
		{/foreach}
	</tbody>
	<tfoot>
        <tr class="columnfooter">
            <td>{$total_count} {ts}Contributions{/ts}</td>
            <td align="right">{$total_amount_str}</td>
            <td/>
            <td>{$different_contacts} {ts}Contacts{/ts}</td>
            <td>{$different_types} {ts}Financial Types{/ts}</td>
            <td>{$different_campaigns} {ts}Campaigns{/ts}</td>
        </tr>
    </tfoot>
</table>

{if $txgroup.status_id neq 1}
{* only show button if group is closed *}
<a class="button" onClick="create_accounting_batch({$group_id});">{ts}Create Accounting Batch{/ts}</a>
{/if}

<script type="text/javascript">
var view_batch_url = '{crmURL p="civicrm/batchtransaction" q="&reset=1&bid=__BATCH_ID__"}';
{literal}
function create_accounting_batch(group_id) {
	CRM.api('SepaTransactionGroup', 'toaccgroup', {'q': 'civicrm/ajax/rest', 'txgroup_id': group_id},
	  {success: function(data) {
	  	document.location = cj(document.createElement('div')).html(view_batch_url.replace('__BATCH_ID__', data.id)).text();
	  }
	});
}
{/literal}
</script>
