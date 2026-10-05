{*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2018 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+-------------------------------------------------------*}

{crmScope extensionKey='org.project60.sepa'}

{literal}
<style>
  tr.sepa-inactive td {
    color: #cacaca;
  }
</style>
{/literal}

{* add new mandate button *}
{if $permissions.create}
  <div>
    <a id="sepa_payment_extra_button" class="button crm-popup" href="{crmURL p="civicrm/sepa/createmandate" q="action=update&cid=$contact_id"}"><span><div class="icon add-icon ui-icon-circle-plus"></div>{ts}Add new SEPA Mandate{/ts}</span></a>
    <br/>
    <br/>
  </div>
{/if}

{if $rcurs}
<h3>{ts}Recurring SEPA Mandates{/ts}</h3>
<table>
  <thead>
    <tr class="columnheader">
      <td>{ts}Start Date{/ts}</td>
      <td>{ts}Status{/ts}</td>
      <td>{ts}Type{/ts}</td>
      <td>{ts}Reference{/ts}</td>
      <td>{ts}Installs{/ts}</td>
      <td>{ts}Annual Amount{/ts}</td>
      <td>{ts}Last Collection{/ts}</td>
      <td>{ts}Next Collection{/ts}</td>
      <td>{ts}End Date{/ts}</td>
      <td></td>
    </tr>
  </thead>

  <tbody>
    {foreach from=$rcurs item=rcur}
    <tr class="bmfsa-record {$rcur.class} {cycle values="odd-row,even-row"}"">
      <td>{$rcur.start_date|crmDate:$date_format}</td>
      <td><span title="{if $rcur.cancel_reason}{$rcur.cancel_reason}{else}{$rcur.status_raw}{/if}">{$rcur.status}</span></td>
      <td>{$rcur.financial_type}{if $rcur.campaign}<br/>({$rcur.campaign}){/if}</td>
      <td>{$rcur.reference}</td>
      <td>{$rcur.amount|crmMoney:$rcur.currency}<br/>{$rcur.frequency}</td>
      <td>{$rcur.total_amount|crmMoney:$rcur.currency}</td>
      <td>
        {$rcur.last_collection_date|crmDate:$date_format}
        {* Show as many warnings as last installments have failed. *}
        {if $rcur.fail_sequence}
          {for $i=1 to $rcur.fail_sequence}
            <div class="icon red-icon ui-icon-alert" title="{$rcur.last_cancel_reason}"/>
          {/for}
        {/if}
      <td>{$rcur.next_collection_date|crmDate:$date_format}</td>
      <td>{$rcur.end_date|crmDate:$date_format}</td>
      <td>
        <span>
          {if $permissions.view}
            <a href="{$rcur.view_link}" class="action-item crm-hover-button crm-popup" title="{ts escape='htmlattribute'}View Mandate{/ts}">{ts}View{/ts}</a>
          {/if}
          {if $permissions.edit && $rcur.edit_link}
            <a href="{$rcur.edit_link}" class="action-item crm-hover-button crm-popup" title="{ts escape='htmlattribute'}Edit Mandate{/ts}">{ts}Edit{/ts}</a>
          {/if}
        </span>
      </td>
    </tr>
    {/foreach}
  </tbody>
</table>
{else}
<div id="help">
{ts}This contact has no recorded recurring mandates.{/ts}
{if $financialacls}
 {ts}Note that only mandates associated with contributions of authorized financial types are being displayed.{/ts}
{/if}
</div>
{/if}

{if $ooffs}
<h3>{ts}One-Off SEPA Mandates{/ts}</h3>
<table>
  <thead>
    <tr class="columnheader">
      <td>{ts}Collection Date{/ts}</td>
      <td>{ts}Status{/ts}</td>
      <td>{ts}Type{/ts}</td>
      <td>{ts}Reference{/ts}</td>
      <td>{ts}Amount{/ts}</td>
      <td></td>
    </tr>
  </thead>

  <tbody>
    {foreach from=$ooffs item=ooff}
    <tr class="bmfsa-record {$ooff.class} {cycle values="odd-row,even-row"}"">
      <td>{$ooff.receive_date|crmDate:$date_format}</td>
      <td><span title="{if $ooff.cancel_reason}{$ooff.cancel_reason}{else}{$ooff.status_raw}{/if}">{$ooff.status}<span></td>
      <td>{$ooff.financial_type}{if $ooff.campaign}<br/>({$ooff.campaign}){/if}</td>
      <td>{$ooff.reference}</td>
      <td>{$ooff.total_amount|crmMoney:$ooff.currency}</td>
      <td>
        <span>
          {if $permissions.view}
            <a href="{$ooff.view_link}" class="action-item crm-hover-button crm-popup" title="{ts escape='htmlattribute'}View Mandate{/ts}">{ts}View{/ts}</a>
          {/if}
          {if $permissions.edit && $ooff.edit_link}
            <a href="{$ooff.edit_link}" class="action-item crm-hover-button crm-popup" title="{ts escape='htmlattribute'}Edit Mandate{/ts}">{ts}Edit{/ts}</a>
          {/if}
        </span>
      </td>
    </tr>
    {/foreach}
  </tbody>
</table>
{else}
<div id="help">
{ts}This contact has no recorded one-off mandates.{/ts}
{if $financialacls}
 {ts}Note that only mandates associated with contributions of authorized financial types are being displayed.{/ts}
{/if}
</div>
{/if}


<script type="application/javascript">
  {literal}
  // trigger reload of tab
  cj(document).ready(function() {
      cj(document).on('crmPopupClose', function(event) {
          if(cj(event.target).attr('href').includes('civicrm/sepa/createmandate') || cj(event.target).attr('href').includes('civicrm/sepa/xmandate')) {
              cj("#sepa_payment_extra_button").closest("div.crm-ajax-container").crmSnippet('refresh');
          }
      });
  });
  {/literal}
</script>

{/crmScope}
