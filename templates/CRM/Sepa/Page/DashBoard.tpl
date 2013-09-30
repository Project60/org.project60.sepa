<table>
<tr>
<th>Reference</th>
<th>type</th>
<th>created</th>
<th>collection</th>
<th>file</th>
<th>transactions</th>
<th>total</th>
<th></th>
</tr>
{foreach from=$groups item=group}
  {*crmAPI var='result' entity='SepaTransactionGroup' action='getdetail' sequential=1 id=$group.id*}
<tr class="status_{$result.status_id}">
<td title="id {$group.id}">{$group.reference}</td>
<td>{$group.type}</td>
<td>{$group.created_date}</td>
<td>{$group.collection_date}</td>
<td class="file_{$group.file_id}">{$group.file}</td>
<td>{$group.nb_contrib}</td>
<td>{$group.total} &euro;</td>
<td><a href="#" class="button">Close & Generate next batch</a></td>
</tr>
{*  <table>
  {foreach from=$result.values item=tx}
{assign var="reference" value=$tx.reference}
{assign var="contact_id" value=$tx.contact_id}
{assign var="contribution_id" value=$tx.contribution_id}
    <tr><td><a href="{crmURL p='civicrm/contact/view/contribution' q="reset=1&id=$contribution_id&cid=$contact_id&action=view&context=contribution&selectedChild=contribute"}">{$tx.contribution_id}</a></td>
<td><a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=$contact_id"}">{$tx.contact_id}</a></td><td><a href="{crmURL p='civicrm/sepa/pdf' q="ref=$reference"}">{$tx.reference}</a></td><td>{$tx.total_amount}</td><td>{$tx.vatddation_date}</td></tr>
  {/foreach} 
  </table>
*}
{/foreach}
