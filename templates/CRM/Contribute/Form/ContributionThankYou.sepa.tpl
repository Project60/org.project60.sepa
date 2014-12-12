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


<!-- a new, nicer payment info -->
<div id="sepa-thank-amount-display" class="display-block">
  <p id="sepa-confirm-text-amount">{ts}Total Amount{/ts}: <strong>{$amount|crmMoney:$currencyID}</strong></p>
  <p id="sepa-confirm-text-date">{ts}Date{/ts}: <strong>{$receive_date|crmDate}</strong></p>
  <p id="sepa-confirm-text-reference">{ts}Payment Reference{/ts}: <strong>{$trxn_id}</strong></p>
  {if $is_recur}
  <p id="sepa-confirm-text-recur"><strong>{ts 1=$frequency_words}The amount will be debited %1.{/ts}</strong></p>
  {/if}
</div>



<fieldset class="label-left crm-sepa">
<div class="header-dark">{ts}Direct Debit Payment{/ts}</div>

<div class="crm-section sepa-section no-label">
  <div class="display-block">
    {ts}The following will be debited from your account.{/ts}
    {ts}The collection date is subject to bank working days.{/ts}
  </div>

  <table class="sepa-confirm-text-account-details display" id="sepa-confirm-text-account-details">
    <tr id="sepa-thankyou-amount">
      <td>{ts}Amount{/ts}</td>
      <td class="content">{$amount|crmMoney:$currencyID}</td>
    </tr>
    <tr id="sepa-thankyou-reference">
      <td>{ts}Mandate Reference{/ts}</td>
      <td class="content">{$mandate_reference}</td>
    </tr>
    <tr id="sepa-thankyou-creditor">
      <td>{ts}Creditor ID{/ts}</td>
      <td class="content">{$creditor_id}</td>
    </tr>
    <tr id="sepa-thankyou-iban">
      <td>{ts}IBAN{/ts}</td>
      <td class="content">{$bank_iban}</td>
    </tr>
    <tr id="sepa-thankyou-bic">
      <td>{ts}BIC{/ts}</td>
      <td class="content">{$bank_bic}</td>
    </tr>
    {if $is_recur}
      <tr id="sepa-thankyou-collectionday">
        <td>{ts}Collection Day{/ts}</td>
        <td class="content">{$collection_day}.</td>
      </tr>
      <tr id="sepa-thankyou-frequency">
        <td>{ts}Collection Frequency{/ts}</td>
        <td class="content">{$frequency_words}</td>
      </tr>
      <tr id="sepa-thankyou-date">
        <td>{ts}First Collection Date{/ts}</td>
        <td class="content">{$collection_date|crmDate}</td>
      </tr>
    {else}
      <tr id="sepa-thankyou-date">
        <td>{ts}Earliest Collection Date{/ts}</td>
        <td class="content">{$collection_date|crmDate}</td>
      </tr>
    {/if}
  </table>
</div>
</fieldset>


<script type="text/javascript">
// hide credit card info
cj('.credit_card-group').html("");

// modify amount display group
cj(".amount_display-group > .display-block").replaceWith(cj("#sepa-thank-amount-display"));

</script>
