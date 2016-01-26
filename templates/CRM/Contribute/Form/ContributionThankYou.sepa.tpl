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

<!-- a new, nicer payment info -->
<div id="sepa-thank-amount-display" class="display-block">
  <p id="sepa-confirm-text-amount">{ts}Total Amount{/ts}: <strong>{$amount|crmMoney:$currencyID}</strong></p>
  <p id="sepa-confirm-text-date">{ts}Date{/ts}: <strong>{$receive_date|crmDate}</strong></p>
  <p id="sepa-confirm-text-reference">{ts}Payment Reference{/ts}: <strong>{$trxn_id}</strong></p>
  {if $is_recur}
  <p id="sepa-confirm-text-recur"><strong>{ts 1=$cycle}The amount will be debited %1.{/ts}</strong></p>
  {/if}
</div>


{if $bank_account_number} {* only for SEPA PPs *}
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
      <td class="content">{$bank_account_number}</td>
    </tr>
    <tr id="sepa-thankyou-bic">
      <td>{ts}BIC{/ts}</td>
      <td class="content">{$bank_identification_number}</td>
    </tr>
    {if $is_recur}
      <tr id="sepa-thankyou-collectionday">
        <td>{ts}Collection Day{/ts}</td>
        <td class="content">{$cycle_day}</td>
      </tr>
      <tr id="sepa-thankyou-frequency">
        <td>{ts}Collection Frequency{/ts}</td>
        <td class="content">{$cycle}</td>
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
{/if}

<script type="text/javascript">
// hide credit card info
{if $bank_account_number} {* only for SEPA PPs *}
cj('.credit_card-group').html("");
{/if}

// modify amount display group
cj(".amount_display-group > .display-block").replaceWith(cj("#sepa-thank-amount-display"));

// remove "print" button - this doesn't work here
cj("#printer-friendly").hide();

</script>
