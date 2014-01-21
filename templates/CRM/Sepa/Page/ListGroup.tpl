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
			<td align="right"><a href="{$contribution.contribution_link}">{$contribution.contribution_amount_str}</a></td>
			<td>&nbsp;</td>
			<td><a href="{$contribution.contact_link}"><div class="icon crm-icon {$contribution.contact_type}-icon"></div>{$contribution.contact_display_name}</a></td>
			<td><a href="{$contribution.contribution_link}">{$contribution.financial_type}</a></td>
			<td><a href="{$contribution.contribution_link}">{$contribution.campaign}</a></td>
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
