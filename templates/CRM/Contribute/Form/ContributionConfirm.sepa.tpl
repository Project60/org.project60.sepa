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

{* calculate a more user friendly display of the interval *}
{if $is_recur}
  {if $frequency_unit eq 'month'}
    {if $frequency_interval eq 1}
      {capture assign=frequency_words}{ts}monthly{/ts}{/capture}
    {elseif $frequency_interval eq 3}
      {capture assign=frequency_words}{ts}quarterly{/ts}{/capture}
    {elseif $frequency_interval eq 6}
      {capture assign=frequency_words}{ts}semi-annually{/ts}{/capture}
    {elseif $frequency_interval eq 12}
      {capture assign=frequency_words}{ts}annually{/ts}{/capture}
    {else}
      {capture assign=frequency_words}{ts 1=$frequency_interval}every %1 months{/ts}{/capture}
    {/if}
  {elseif $frequency_unit eq 'year'}
    {if $frequency_interval eq 1}
      {capture assign=frequency_words}{ts}annually{/ts}{/capture}
    {else}
      {capture assign=frequency_words}{ts 1=$frequency_interval}every %1 years{/ts}{/capture}
    {/if}
  {else}
    {capture assign=frequency_words}{ts}on an irregular basis{/ts}{/capture}
  {/if}
{/if}

<div id="sepa-new-amount-display" class="display-block">
  <p id="sepa-confirm-text-amount">{ts}Total Amount{/ts}: <strong>{$amount|crmMoney:$currencyID}</strong></p>
  {if $is_recur}
  <p id="sepa-confirm-text-recur"><strong>{ts 1=$frequency_words}I want to contribute this amount %1.{/ts}</strong></p>
  {/if}

  <p id="sepa-confirm-text-account">{ts}This payment will be debited from the following account:{/ts}</p>
  <table class="sepa-confirm-text-account-details display" id="sepa-confirm-text-account-details">
    <tr><td>{ts}IBAN{/ts}</td> <td>{$bank_iban}</td> </tr>
    <tr><td>{ts}BIC{/ts}</td>  <td>{$bank_bic}</td>  </tr>
  </table>
</div>

<script type="text/javascript">
cj("div.amount_display-group > div.display-block").replaceWith(cj("#sepa-new-amount-display"));
</script>

