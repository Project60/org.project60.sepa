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

{* add new mandate button *}
<div>
  <a id="sepa_payment_extra_button" class="button" href="{crmURL p="civicrm/sepa/cmandate" q="cid=$contact_id"}"><span><div class="icon add-icon ui-icon-circle-plus"></div>{ts domain="org.project60.sepa"}Add new SEPA Mandate{/ts}</span></a>
  <br/>
  <br/>
</div>

{if $rcurs}
<h3>{ts domain="org.project60.sepa"}Recurring SEPA Mandates{/ts}</h3>
<table>
  <thead>
    <tr class="columnheader">
      <td>{ts domain="org.project60.sepa"}Start Date{/ts}</td>
      <td>{ts domain="org.project60.sepa"}Status{/ts}</td>
      <td>{ts domain="org.project60.sepa"}Type{/ts}</td>
      <td>{ts domain="org.project60.sepa"}Reference{/ts}</td>
      <td>{ts domain="org.project60.sepa"}Installs{/ts}</td>
      <td>{ts domain="org.project60.sepa"}Annual Amount{/ts}</td>
      <td>{ts domain="org.project60.sepa"}Last Collection{/ts}</td>
      <td>{ts domain="org.project60.sepa"}Next Collection{/ts}</td>
      <td>{ts domain="org.project60.sepa"}End Date{/ts}</td>
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
      <td>{$rcur.amount|crmMoney}<br/>{$rcur.frequency}</td>
      <td>{$rcur.total_amount|crmMoney}</td>
      <td>
        {$rcur.last_collection_date|crmDate:$date_format}
        {if $rcur.last_collection_issue}<div class="icon red-icon ui-icon-alert" title="{$rcur.last_cancel_reason}"/>{/if}
      <td>{$rcur.next_collection_date|crmDate:$date_format}</td>
      <td>{$rcur.end_date|crmDate:$date_format}</td>
      <td>
        <span>
          <a href="{$rcur.view_link}" class="action-item crm-hover-button crm-popup" title="{ts domain="org.project60.sepa"}View Mandate{/ts}">{ts domain="org.project60.sepa"}View{/ts}</a>
          {if $rcur.edit_link}
            <a href="{$rcur.edit_link}" class="action-item crm-hover-button crm-popup" title="{ts domain="org.project60.sepa"}Edit Mandate{/ts}">{ts domain="org.project60.sepa"}Edit{/ts}</a>
          {/if}
        </span>
      </td>
    </tr>
    {/foreach}
  </tbody>
</table>
{else}
<div id="help">
{ts domain="org.project60.sepa"}This contact has no recorded recurring mandates.{/ts}
</div>
{/if}

{if $ooffs}
<h3>{ts domain="org.project60.sepa"}One-Off SEPA Mandates{/ts}</h3>
<table>
  <thead>
    <tr class="columnheader">
      <td>{ts domain="org.project60.sepa"}Collection Date{/ts}</td>
      <td>{ts domain="org.project60.sepa"}Status{/ts}</td>
      <td>{ts domain="org.project60.sepa"}Type{/ts}</td>
      <td>{ts domain="org.project60.sepa"}Reference{/ts}</td>
      <td>{ts domain="org.project60.sepa"}Amount{/ts}</td>
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
      <td>{$ooff.total_amount|crmMoney}</td>
      <td>
        <span>
          <a href="{$ooff.view_link}" class="action-item crm-hover-button crm-popup" title="{ts domain="org.project60.sepa"}View Mandate{/ts}">{ts domain="org.project60.sepa"}View{/ts}</a>
          {if $ooff.edit_link}
            <a href="{$ooff.edit_link}" class="action-item crm-hover-button crm-popup" title="{ts domain="org.project60.sepa"}Edit Mandate{/ts}">{ts domain="org.project60.sepa"}Edit{/ts}</a>
          {/if}
        </span>
      </td>
    </tr>
    {/foreach}
  </tbody>
</table>
{else}
<div id="help">
{ts domain="org.project60.sepa"}This contact has no recorded one-off mandates.{/ts}
</div>
{/if}
