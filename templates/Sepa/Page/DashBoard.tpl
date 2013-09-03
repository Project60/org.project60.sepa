{foreach from=$files item=file}
<h1>{$file.reference}</h1>
{assign var='key' value="api.SepaTransactionGroup.get"}
{assign var='groups' value=$file.$key.values}
<hr>
{foreach from=$groups item=group}
  {crmAPI var='result' entity='SepaTransactionGroup' action='getdetail' sequential=1 id=$group.id}
<li><h3>{$group.latest_submission_date}:{$group.reference} <i>{$result.count} transactions for {$result.total_amount}€</i></h3>
  <table>
  {foreach from=$result.values item=tx}
{assign var="reference" value=$tx.reference}
    <tr><td><a href="{crmURL p='civicrm/sepa/pdf' q="ref=$reference"}">{$tx.reference}</a></td><td>{$tx.total_amount}</td><td>{$tx.validation_date}</td></tr>
  {/foreach} 
  </table>
</li>
{/foreach}
{/foreach}


