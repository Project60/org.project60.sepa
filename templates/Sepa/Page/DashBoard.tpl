{foreach from=$files item=file}
<h1>{$file.reference}</h1>
{assign var='key' value="api.SepaTransactionGroup.get"}
{assign var='groups' value=$file.$key.values}
{foreach from=$groups item=group}
  {crmAPI var='result' entity='SepaTransactionGroup' action='getdetail' sequential=1 id=$group.id}
<div class="status_{$result.status_id}">
<h3>{$group.reference}</h3>
<ul>
<li>Type: {$group.type}</li>
<li>Creation: {$group.created_date}</li>
<li>Collection: {$group.collection_date}</li>
<li>Transactions: {$result.count}</li>
<li>Total: {$result.total_amount} &euro;</li>
<li><a href="#" class="button">Close & Generate next batch</a></li>
</ul>
  <table>
  {foreach from=$result.values item=tx}
{assign var="reference" value=$tx.reference}
    <tr><td><a href="{crmURL p='civicrm/sepa/pdf' q="ref=$reference"}">{$tx.reference}</a></td><td>{$tx.total_amount}</td><td>{$tx.validation_date}</td></tr>
  {/foreach} 
  </table>
</li>
{/foreach}
</div>
{/foreach}


