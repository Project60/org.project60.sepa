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

<h3>{ts domain="org.project60.sepa"}Contributions for transaction group{/ts} '{$reference}'</h3>
<table>
	<thead>
		<th>{ts domain="org.project60.sepa"}ID{/ts}</th>
		<th>{ts domain="org.project60.sepa"}Amount{/ts}</th>
		<th>&nbsp;</th>
		<th>{ts domain="org.project60.sepa"}Contact{/ts}</th>
		<th>{ts domain="org.project60.sepa"}Financial Type{/ts}</th>
		<th>{ts domain="org.project60.sepa"}Campaign{/ts}</th>
	</thead>
	<tbody>
		{foreach from=$contributions item=contribution}
		<tr>
			<td><a href="{$contribution.contribution_link}">[{$contribution.contribution_id}]</a></td>
			<td style="text-align: right;"><a href="{$contribution.contribution_link}"><b>{$contribution.contribution_amount_str}</b></a></td>
			<td>&nbsp;</td>
			<td><a href="{$contribution.contact_link}"><div class="icon crm-icon {$contribution.contact_type}-icon"></div>{$contribution.contact_display_name}</a></td>
			<td>{$contribution.financial_type}</td>
			<td>{$contribution.campaign}</td>
		</tr>
		{/foreach}
	</tbody>
	<tfoot>
        <tr class="columnfooter">
            <td>{$total_count} {ts domain="org.project60.sepa"}Contributions{/ts}</td>
            <td align="right">{$total_amount_str}</td>
            <td/>
            <td>{$different_contacts} {ts domain="org.project60.sepa"}Contacts{/ts}</td>
            <td>{$different_types} {ts domain="org.project60.sepa"}Financial Types{/ts}</td>
            <td>{$different_campaigns} {ts domain="org.project60.sepa"}Campaigns{/ts}</td>
        </tr>
    </tfoot>
</table>

{if $txgroup.status_id neq 1}
{* only show button if group is closed *}
<a class="button" onClick="create_accounting_batch({$group_id});">{ts domain="org.project60.sepa"}Create Accounting Batch{/ts}</a>
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
