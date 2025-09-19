{*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 SYSTOPIA                       |
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

<div class="crm-actions-ribbon">
  <ul id="actions">
  {if $status eq 'closed'}
    <li>
      <a title="{ts escape='htmlattribute' domain="org.project60.sepa"}show active groups{/ts}" class="search button" href="{$show_open_url}">
        <span>
          <div class="icon inform-icon"></div>
          {ts domain="org.project60.sepa"}show active groups{/ts}
        </span>
      </a>
    </li>
  {else}
    <li>
      <a title="{ts escape='htmlattribute' domain="org.project60.sepa"}show closed groups{/ts}" class="search button" href="{$show_closed_url}">
        <span>
          <div class="icon inform-icon"></div>
          {ts domain="org.project60.sepa"}show closed groups{/ts}
        </span>
      </a>
    <li>
    {if $can_batch}
    <li>
      <a title="{ts escape='htmlattribute' domain="org.project60.sepa"}update one-off{/ts}" class="refresh button" href="{$batch_ooff}">
        <span>
          <div class="icon refresh-icon ui-icon-refresh"></div>
          {ts domain="org.project60.sepa"}update one-off{/ts}
        </span>
      </a>
    </li>
    <li>
      <a title="{ts escape='htmlattribute' domain="org.project60.sepa"}update recurring{/ts}" class="refresh button" href="{$batch_recur}">
        <span>
          <div class="icon refresh-icon ui-icon-refresh"></div>
          {ts domain="org.project60.sepa"}update recurring{/ts}
        </span>
      </a>
    </li>
      <li>
        <a title="{ts escape='htmlattribute' domain="org.project60.sepa"}retry collection{/ts}" class="refresh button" href="{$batch_retry}">
        <span>
          <div class="icon refresh-icon  ui-icon-circle-plus"></div>
          {ts domain="org.project60.sepa"}retry collection{/ts}
        </span>
        </a>
      </li>
    {/if}
  {/if}
  </ul>
  <div class="clear"></div>
</div>

{if $financialacls}
  <div class="help">
    {ts}Note that only groups with contributions of authorized financial types are being displayed.{/ts}
  </div>
{/if}

<table>
  <tr>
    <th>{ts domain="org.project60.sepa"}Group Name{/ts}</th>
    <th>{ts domain="org.project60.sepa"}Status{/ts}</th>
    <th>{ts domain="org.project60.sepa"}Type{/ts}</th>
    <th>{ts domain="org.project60.sepa"}Submission{/ts}</th>
    <th>{ts domain="org.project60.sepa"}Collection{/ts}</th>
    <th>{ts domain="org.project60.sepa"}Transactions{/ts}</th>
    <th>{ts domain="org.project60.sepa"}Total{/ts}</th>
    <th></th>
  </tr>
  {foreach from=$groups item=group}
  {assign var='file_id' value=$group.file_id}
  {assign var='group_id' value=$group.id}
  <tr bgcolor="#FF0000" class="status_{$group.status_id} submit_{$group.submit}" data-id="{$group.id}" data-type="{$group.type}">
    <td title="id {$group.id}" class="nb_contrib">
      {$group.reference}
      {if $group.transaction_message}<span class="crm-i fa-envelope-o" title="{ts escape='htmlattribute' domain="org.project60.sepa"}Custom Transaction Message:{/ts} {$group.transaction_message}"></span>{/if}
      {if $group.transaction_note}<span class="crm-i fa-sticky-note" title="{ts escape='htmlattribute' domain="org.project60.sepa"}Note:{/ts} {$group.transaction_note}"></span>{/if}
    </td>
    <td>
      {$group.status_label}
      <img id="busy_{$group_id}" height="16" src="{$config->resourceBase}i/loading.gif" style="float: right; padding: 0px 4px;" hidden="1" />
    </td>
    <td>{$group.type}</td>
  {if $status eq 'closed'}
    <td>{$group.file_created_date}</td>
  {else}
    <td>{$group.latest_submission_date}</td>
  {/if}
    <td>{$group.collection_date}</td>
    <td class="nb_contrib" title="list all the contributions">
      {$group.nb_contrib}
    </td>
    <td style="white-space:nowrap;">{$group.total|crmMoney:$group.currency}</td>
    <td>
      <a href="{crmURL p="civicrm/sepa/listgroup" q="group_id=$group_id"}" class="button button_view">{ts domain="org.project60.sepa"}Contributions{/ts}</a>
      {if $group.status == 'open'}
        {if $can_batch}
          {if $group.submit == 'missed'}
            <a href="{crmURL p="civicrm/sepa/closegroup" q="group_id=$group_id&status=missed"}" class="button button_close">
          {else}
            <a href="{crmURL p="civicrm/sepa/closegroup" q="group_id=$group_id"}" class="button button_close">
          {/if}
          {ts domain="org.project60.sepa"}Close and Submit{/ts}</a>
        {/if}
      {else}
        <a href="{crmURL p="civicrm/sepa/xml" q="id=$file_id"}" download="{$group.file}" class="button button_export">{ts domain="org.project60.sepa"}Download Again{/ts}</a>
        {if $closed_status_id eq $group.status_id}
          {if not $group.collection_date_in_future}
            <a href="{crmURL p="civicrm/sepa/mark_received" q="group_id=$group_id"}" class="button button_received">{ts domain="org.project60.sepa"}Mark Received{/ts}</a>
          {/if}
        {/if}
      {/if}
      {if $can_delete}
      <a href="{crmURL p="civicrm/sepa/deletegroup" q="group_id=$group_id"}" class="button button_view">{ts domain="org.project60.sepa"}Delete{/ts}</a>
      {/if}
    </td>
  </tr>
  {/foreach}
</table>

{* legend by @scardinius *}
<br/>
<table>
  <caption>{ts domain="org.project60.sepa"}Legend{/ts}</caption>
  <tr>
    <th>{ts domain="org.project60.sepa"}Status{/ts}</th>
    <th>{ts domain="org.project60.sepa"}Description{/ts}</th>
  </tr>
  <tr class="submit_missed">
    <td>{ts domain="org.project60.sepa"}Missed{/ts}</td>
    <td>{ts domain="org.project60.sepa"}Submission date has passed!{/ts}</td>
  </tr>
  <tr class="submit_urgently">
    <td>{ts domain="org.project60.sepa"}Urgent{/ts}</td>
    <td>{ts domain="org.project60.sepa"}Submission date is immanent, you have to close group and upload file to creditor today!{/ts}</td>
  </tr>
  <tr class="submit_soon">
    <td>{ts domain="org.project60.sepa"}Soon{/ts}</td>
    <td>{ts domain="org.project60.sepa"}Submission within 6 days or OOFF (submission not enforced){/ts}</td>
  </tr>
  <tr class="submit_later">
    <td>{ts domain="org.project60.sepa"}Upcoming{/ts}</td>
    <td>{ts domain="org.project60.sepa"}Submission date more than 6 days from now{/ts}</td>
  </tr>
  <tr class="submit_closed">
    <td>{ts domain="org.project60.sepa"}Closed{/ts}</td>
    <td>{ts domain="org.project60.sepa"}The group is closed and uploaded to creditor, submission date is in the past.{/ts}</td>
  </tr>
</table>

{literal}
<style>
  tr.submit_missed {background-color: #EE0000AA;}
  tr.submit_urgently {background-color: #AC6700AA;}
  tr.submit_soon {background-color: #0165FFAA;}
  tr.submit_later {background-color: #008300AA;}
  tr.submit_closed {background-color: #00830033;}
</style>
{/literal}

<script type="text/javascript">
let received_confirmation_message = `{ts domain="org.project60.sepa"}Do you really want to mark this groups as 'payment received'?{/ts}`;

{literal}
function mark_received(group_id) {
  if (confirm(received_confirmation_message)) {
    cj("#mark_received_" + group_id).hide();
    cj("#busy_" + group_id).show();
    CRM.api3('SepaAlternativeBatching', 'received', {'q': 'civicrm/ajax/rest', 'txgroup_id': group_id},
      {success: function(data) {
        // reload page
        location.reload();
      },
       error: function(data) {
        // show error message
        cj("#busy_" + group_id).hide();
        alert(data.error_message.error_message);
      }}
    );
  }
}
</script>
{/literal}

