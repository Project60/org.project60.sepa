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

{crmScope extensionKey='org.project60.sepa'}
  <div class="crm-actions-ribbon">
    <ul id="actions">
    {if $status eq 'closed'}
      <li>
        <a title="{ts escape='htmlattribute'}show active groups{/ts}" class="search button" href="{$show_open_url}">
          <span>
            <div class="icon inform-icon"></div>
            {ts}show active groups{/ts}
          </span>
        </a>
      </li>
    {else}
      <li>
        <a title="{ts escape='htmlattribute'}show closed groups{/ts}" class="search button" href="{$show_closed_url}">
          <span>
            <div class="icon inform-icon"></div>
            {ts}show closed groups{/ts}
          </span>
        </a>
      <li>
      {if $can_batch}
      <li>
        <a title="{ts escape='htmlattribute'}update one-off{/ts}" class="refresh button" href="{$batch_ooff}">
          <span>
            <div class="icon refresh-icon ui-icon-refresh"></div>
            {ts}update one-off{/ts}
          </span>
        </a>
      </li>
      <li>
        <a title="{ts escape='htmlattribute'}update recurring{/ts}" class="refresh button" href="{$batch_recur}">
          <span>
            <div class="icon refresh-icon ui-icon-refresh"></div>
            {ts}update recurring{/ts}
          </span>
        </a>
      </li>
        <li>
          <a title="{ts escape='htmlattribute'}retry collection{/ts}" class="refresh button" href="{$batch_retry}">
          <span>
            <div class="icon refresh-icon  ui-icon-circle-plus"></div>
            {ts}retry collection{/ts}
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

  <table class="sepa_dashboard" id="options">
    <thead>
      <tr role="row">
        <th class="sorting" aria-controls="sepa-option">{ts}Group Name{/ts}</th>
        <th class="sorting" aria-controls="sepa-option">{ts}Status{/ts}</th>
        <th class="sorting" aria-controls="sepa-option">{ts}Type{/ts}</th>
        <th class="sorting" aria-controls="sepa-option">{ts}Submission{/ts}</th>
        <th class="sorting" aria-controls="sepa-option">{ts}Collection{/ts}</th>
        <th class="sorting" aria-controls="sepa-option">{ts}Transactions{/ts}</th>
        <th class="sorting" aria-controls="sepa-option">{ts}Total{/ts}</th>
        <th></th>
      </tr>
    </thead>
    {foreach from=$groups item=group}
    {assign var='file_id' value=$group.file_id}
    {assign var='group_id' value=$group.id}
    <tr bgcolor="#FF0000" class="status_{$group.status_id} submit_{$group.submit}" data-id="{$group.id}" data-type="{$group.type}">
      <td title="id {$group.id}" class="nb_contrib">
        {$group.reference}
        {if $group.transaction_message}<span class="crm-i fa-envelope-o" title="{ts escape='htmlattribute'}Custom Transaction Message:{/ts} {$group.transaction_message}"></span>{/if}
        {if $group.transaction_note}<span class="crm-i fa-sticky-note" title="{ts escape='htmlattribute'}Note:{/ts} {$group.transaction_note}"></span>{/if}
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
      <td class="sepa_dashboard__button_group">
        {crmButton p="civicrm/sepa/listgroup" q="group_id=$group_id" class="button_view" title="{ts escape='htmlattribute'}Contributions{/ts}" icon="fa-info"}{ts}Contributions{/ts}{/crmButton}
        {if $group.status == 'open'}
          {if $can_batch}
            {if $group.submit == 'missed'}
              {crmButton p="civicrm/sepa/closegroup" q="group_id=$group_id&status=missed" class="button_close" title="{ts escape='htmlattribute'}Close and Submit{/ts}" icon="fa-paper-plane"}{ts}Close and Submit{/ts}{/crmButton}
            {else}
              {crmButton p="civicrm/sepa/closegroup" q="group_id=$group_id" class="button_close" title="{ts escape='htmlattribute'}Close and Submit{/ts}" icon="fa-paper-plane"}{ts}Close and Submit{/ts}{/crmButton}
            {/if}
          {/if}
        {else}
          {crmButton p="civicrm/sepa/xml" q="id=$file_id" class="button_export" title="{ts escape='htmlattribute'}Download Again{/ts}" icon=""}{ts}Download Again{/ts}{/crmButton}
          {if $closed_status_id eq $group.status_id}
            {if not $group.collection_date_in_future}
              {crmButton p="civicrm/sepa/mark_received" q="group_id=$group_id" class="button_received" title="{ts escape='htmlattribute'}Mark Received{/ts}" icon=""}{ts}Mark Received{/ts}{/crmButton}
            {/if}
          {/if}
        {/if}
        {if $can_delete}
          {crmButton p="civicrm/sepa/deletegroup" q="group_id=$group_id" class="button_view" title="{ts escape='htmlattribute'}Delete{/ts}" icon="fa-trash-can"}{ts}Delete{/ts}{/crmButton}
        {/if}
      </td>
    </tr>
    {/foreach}
  </table>

  {* legend by @scardinius *}
  <br/>
  <table class="sepa_dashboard">
    <caption>{ts}Legend{/ts}</caption>
    <tr>
      <th>{ts}Status{/ts}</th>
      <th>{ts}Description{/ts}</th>
    </tr>
    <tr class="submit_missed">
      <td>{ts}Missed{/ts}</td>
      <td>{ts}Submission date has passed!{/ts}</td>
    </tr>
    <tr class="submit_urgently">
      <td>{ts}Urgent{/ts}</td>
      <td>{ts}Submission date is immanent, you have to close group and upload file to creditor today!{/ts}</td>
    </tr>
    <tr class="submit_soon">
      <td>{ts}Soon{/ts}</td>
      <td>{ts}Submission within 6 days or OOFF (submission not enforced){/ts}</td>
    </tr>
    <tr class="submit_later">
      <td>{ts}Upcoming{/ts}</td>
      <td>{ts}Submission date more than 6 days from now{/ts}</td>
    </tr>
    <tr class="submit_closed">
      <td>{ts}Closed{/ts}</td>
      <td>{ts}The group is closed and uploaded to creditor, submission date is in the past.{/ts}</td>
    </tr>
  </table>

  <script type="text/javascript">
  let received_confirmation_message = `{ts}Do you really want to mark this groups as 'payment received'?{/ts}`;

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
{/crmScope}
