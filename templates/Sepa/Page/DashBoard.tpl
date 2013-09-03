{foreach from=$files item=file}
<h2>{$file.reference}</h2>
{assign var='key' value="api.SepaTransactionGroup.get"}
{assign var='groups' value=$file.$key.values}
<hr>
{foreach from=$groups item=group}
<li>{$group.latest_submission_date}{$group.reference}</li>
{/foreach}
{/foreach}


