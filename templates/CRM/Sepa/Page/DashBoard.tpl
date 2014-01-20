<div class="crm-actions-ribbon">
  <ul id="actions">
    <li>
      {if $status eq 'closed'}
      <a title="{ts}show active groups{/ts}" class="search button" href="{$show_open_url}">
        <span>
          <div class="icon search-icon"></div>
          {ts}show active groups{/ts}
        </span>
      </a>
      {else}
      <a title="{ts}show closed groups{/ts}" class="search button" href="{$show_closed_url}">
        <span>
          <div class="icon search-icon"></div>
          {ts}show closed groups{/ts}
        </span>
      </a>
      {/if}
    </li>
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
  </ul>
  <div class="clear"></div>
</div>

<table>
  <tr>
    <th>{ts}Reference{/ts}</th>
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
    <td>{$group.status}</td>
    <td>{$group.type}</td>
    <td>{$group.latest_submission_date}</td>
    <td>{$group.collection_date}</td>
    <td class="nb_contrib" title="list all the contributions">{$group.nb_contrib}</td>
    <td>{$group.total} &euro;</td>
    <td>
      {if $group.status_id == '2'}
        <a href="{crmURL p="civicrm/sepa/closegroup" q="group_id=$group_id"}" class="button button_close">{ts}Close and Submit{/ts}</a>
      {else}
        <a href="{crmURL p="civicrm/sepa/xml" q="id=$file_id"}" download="{$group.file}.xml" class="button button_export">{ts}Download Again{/ts}</a>
      {/if}
    </td>
  </tr>
  {/foreach}
</table>

{literal}
<script>
cj(function($){
  $(".button_close").click(function() {
    var $tr=$(this).closest("tr");
    CRM.api("SepaAlternativeBatching","close",{"txgroup_id":$tr.data("id")},{"success":function(data) {
      location.reload();
    }});
  });
});
</script>

<style>
  tr.submit_urgently {background-color:#FA583F;}
  tr.submit_soon {background-color:#FAB83F;}
  tr.submit_closed {background-color:#f0f8ff;}
</style>
{/literal}

