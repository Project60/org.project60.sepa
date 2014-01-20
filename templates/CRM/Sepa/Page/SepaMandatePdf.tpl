{if isset($recur)}
<h3>{$contact.display_name} for {$recur.amount} {$recur.currency}/{$recur.frequency_unit}</h3>
{else}
<h3>{$contact.display_name} for {$contribution.total_amount} {$contribution.currency}</h3>
{/if}
{include file="Sepa/Contribute/Page/ContributionRecur.tpl"}
<h3>Pdf content</h3>
{$html}
