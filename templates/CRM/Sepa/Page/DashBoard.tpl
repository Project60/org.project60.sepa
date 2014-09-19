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
          <div class="icon search-icon"></div>
          {ts}show active groups{/ts}
        </span>
      </a>
    </li>
    {else}
    <li>
      <a title="{ts}show closed groups{/ts}" class="search button" href="{$show_closed_url}">
        <span>
          <div class="icon search-icon"></div>
          {ts}show closed groups{/ts}
        </span>
      </a>
    <li>
    <li>
      <a title="{ts}update one-off{/ts}" class="refresh button" href="{$batch_ooff}">
        <span>
          <div class="icon refresh-icon"></div>
          {ts}update one-off{/ts}
        </span>
      </a>
    </li>
    <li>
      <a title="{ts}update recurring{/ts}" class="refresh button" href="{$batch_recur}">
        <span>
          <div class="icon refresh-icon"></div>
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
    <td>{$group.status_label}</td>
    <td>{$group.type}</td>
  {if $status eq 'closed'}
    <td>{$group.file_created_date}</td>
  {else}
    <td>{$group.latest_submission_date}</td>
  {/if}
    <td>{$group.collection_date}</td>
    <td class="nb_contrib" title="list all the contributions">{$group.nb_contrib}</td>
    <td>{$group.total} &euro;</td>
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
        <a href="{crmURL p="civicrm/sepa/xml" q="id=$file_id"}" download="{$group.file}.xml" class="button button_export">{ts}Download Again{/ts}</a>
        {if $closed_status_id eq $group.status_id}
          {if $group.collection_date|strtotime lt $smarty.now}
            <a onClick="mark_received({$group_id});" class="button button_export">{ts}Mark Received{/ts}</a>
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

{literal}
<style>
  tr.submit_missed {background-color:#000000; color:#FA583F;}
  tr.submit_urgently {background-color:#FA583F;}
  tr.submit_soon {background-color:#FAB83F;}
  tr.submit_closed {background-color:#f0f8ff;}
</style>
{/literal}

<script type="text/javascript">
var received_confirmation_message = "{ts}Do you really want to mark this groups as 'payment received'?{/ts}";

{literal}
function mark_received(group_id) {
  if (confirm(received_confirmation_message)) {
    CRM.api('SepaAlternativeBatching', 'received', {'q': 'civicrm/ajax/rest', 'txgroup_id': group_id},
      {success: function(data) {
        // reload page
        location.reload();     
      },
       error: function(data) {
        // show error message
        alert(data.error_message.error_message);
      }}
    );    
  }
}
</script>
{/literal}

