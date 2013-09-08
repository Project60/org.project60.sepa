{foreach from=$files item=file}
<h3 title="id {$file.id}">{$file.reference} {$file.status_id}</h3>
{assign var='key' value="api.SepaTransactionGroup.getdetail"}
{assign var='groups' value=$file.$key.values}
{foreach from=$groups item=group}
  {*crmAPI var='result' entity='SepaTransactionGroup' action='getdetail' sequential=1 id=$group.id*}
<div class="status_{$result.status_id}">
<h4 title="id {$group.id}">{$group.reference}</h4>
<ul>
<li>Type: {$group.type}</li>
<li>Creation: {$group.created_date}</li>
<li>Collection: {$group.collection_date}</li>
<li>file: {$group.file_id}</li>
<li>Transactions: {$group.nb_contrib}</li>
<li>Total: {$group.total} &euro;</li>
<li><a href="#" class="button">Close & Generate next batch</a></li>
</ul>
  <table>
  {foreach from=$result.values item=tx}
{assign var="reference" value=$tx.reference}
{assign var="contact_id" value=$tx.contact_id}
{assign var="contribution_id" value=$tx.contribution_id}
    <tr><td><a href="{crmURL p='civicrm/contact/view/contribution' q="reset=1&id=$contribution_id&cid=$contact_id&action=view&context=contribution&selectedChild=contribute"}">{$tx.contribution_id}</a></td>
<td><a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=$contact_id"}">{$tx.contact_id}</a></td><td><a href="{crmURL p='civicrm/sepa/pdf' q="ref=$reference"}">{$tx.reference}</a></td><td>{$tx.total_amount}</td><td>{$tx.validation_date}</td></tr>
  {/foreach} 
  </table>
</li>
{/foreach}
</div>
{/foreach}


