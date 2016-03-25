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

<div class="crm-actions-ribbon">
  <ul id="actions">
    {if $status eq 'closed'}
    <li>
      <a title="{ts}show active groups{/ts}" class="search button" href="{$show_open_url}">
        <span>
          <div class="icon inform-icon"></div>
          {ts}show active groups{/ts}
        </span>
      </a>
    </li>
    {else}
    <li>
      <a title="{ts}show closed groups{/ts}" class="search button" href="{$show_closed_url}">
        <span>
          <div class="icon inform-icon"></div>
          {ts}show closed groups{/ts}
        </span>
      </a>
    <li>
    <li>
      <a title="{ts}update one-off{/ts}" class="refresh button" href="{$batch_ooff}">
        <span>
          <div class="icon refresh-icon ui-icon-refresh"></div>
          {ts}update one-off{/ts}
        </span>
      </a>
    </li>
    <li>
      <a title="{ts}update recurring{/ts}" class="refresh button" href="{$batch_recur}">
        <span>
          <div class="icon refresh-icon ui-icon-refresh"></div>
          {ts}update recurring{/ts}
        </span>
      </a>
    </li>
    {/if}
  </ul>
  <div class="clear"></div>
</div>

<table>
  <tr>
    <th>{ts}Group Name{/ts}</th>
    <th>{ts}Status{/ts}</th>
    <th>{ts}Type{/ts}</th>
    <th>{ts}Submission{/ts}</th>
    <th>{ts}Collection{/ts}</th>
    <th>{ts}Transactions{/ts}</th>
    <th>{ts}Total{/ts}</th>
    <th></th>
  </tr>
  {foreach from=$groups item=group}
  {assign var='file_id' value=$group.file_id}
  {assign var='group_id' value=$group.id}
  <tr bgcolor="#FF0000" class="status_{$group.status_id} submit_{$group.submit}" data-id="{$group.id}" data-type="{$group.type}">

    <td title="id {$group.id}" class="nb_contrib">{$group.reference}</td>
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
    <td class="nb_contrib" title="list all the contributions">{$group.nb_contrib}</td>
    <td style="white-space:nowrap;">{$group.total|crmMoney:$group.currency}</td>
    <td>
      <a href="{crmURL p="civicrm/sepa/listgroup" q="group_id=$group_id"}" class="button button_view">{ts}Contributions{/ts}</a>
      {if $group.status == 'open'}
        {if $group.submit == 'missed'}
        <a href="{crmURL p="civicrm/sepa/closegroup" q="group_id=$group_id&status=missed"}" class="button button_close">
        {else}
        <a href="{crmURL p="civicrm/sepa/closegroup" q="group_id=$group_id"}" class="button button_close">
        {/if}
        {ts}Close and Submit{/ts}</a>
      {else}
        <a href="{crmURL p="civicrm/sepa/xml" q="id=$file_id"}" download="{$group.filename}" class="button button_export">{ts}Download Again{/ts}</a>
        {if $closed_status_id eq $group.status_id}
          {if $group.collection_date|strtotime lt $smarty.now}
            <a id="mark_received_{$group_id}" onClick="mark_received({$group_id});" class="button button_export">{ts}Mark Received{/ts}</a>
          {/if}
        {/if}
      {/if}
      {if $can_delete eq yes}
      <a href="{crmURL p="civicrm/sepa/deletegroup" q="group_id=$group_id"}" class="button button_view">{ts}Delete{/ts}</a>
      {/if}
    </td>
  </tr>
  {/foreach}
</table>

<table>
  <caption>Legend</caption>
  <tr>
    <th>Status</th>
    <th>Description</th>
  </tr>
  <tr class="submit_missed">
    <td>Missed</td>
    <td>Submission date is older than now.</td>
  </tr>
  <tr class="submit_urgently">
    <td>Urgently</td>
    <td>Submission date is current, you have to close group and upload file to creditor today.</td>
  </tr>
  <tr class="submit_soon">
    <td>Soon</td>
    <td>Submission date during the nearest 6 days.</td>
  </tr>
  <tr class="submit_later">
    <td>Later</td>
    <td>Submission date is greater than 6 days.</td>
  </tr>
  <tr class="submit_closed">
    <td>Closed</td>
    <td>The group is closed and uploaded to creditor, submission date is in the past.</td>
  </tr>
</table>

{literal}
<style>
  tr.submit_missed {color: #EE0000;}
  tr.submit_urgently {color: #ac6700;}
  tr.submit_soon {color: #0165FF;}
  tr.submit_later {color: #008300;}
  tr.submit_closed {color: inherit;}
</style>
{/literal}

<script type="text/javascript">
var received_confirmation_message = "{ts}Do you really want to mark this groups as 'payment received'?{/ts}";

{literal}
function mark_received(group_id) {
  if (confirm(received_confirmation_message)) {
    cj("#mark_received_" + group_id).hide();
    cj("#busy_" + group_id).show();
    CRM.api('SepaAlternativeBatching', 'received', {'q': 'civicrm/ajax/rest', 'txgroup_id': group_id},
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

