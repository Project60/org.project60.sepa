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
<th>Reference</th>
<th>status</th>
<th>type</th>
<th>created</th>
<th>collection</th>
<th>file</th>
<th>transactions</th>
<th>total</th>
<th></th>
</tr>
{foreach from=$groups item=group}
<tr class="status_{$result.status_id}" data-id="{$group.id}" data-type="{$group.type}">
<td title="id {$group.id}" class="nb_contrib">{$group.reference}</td>
<td>{$group.status_id}</td>
<td>{$group.type}</td>
<td>{$group.created_date}</td>
<td>{$group.collection_date}</td>
{assign var='file_id' value=$group.file_id}
<td class="file_{$group.file_id}"><a href='{crmURL p="civicrm/sepa/xml" q="id=$file_id"}'>{$group.file}</a></td>
<td class="nb_contrib" title="list all the contributions">{$group.nb_contrib}</td>
<td>{$group.total} &euro;</td>
<td>
<a href="#" class="button button_close">Close</a>
{if $group.type != 'OOFF'}
<a href="#" class="button button_generate">Generate next batch</a>
{/if}
</td>
</tr>
{/foreach}
{literal}
<script type="text/template" id="detail">
<tr class="detail">
<td></td>
<td colspan="7">
<table>
  <tr>
    <th>mandate</th>
    <th>amount</th>
    <th>contact</th>
    <th>contrib</th>
    <th>receive</th>
<% if(type != 'OOFF') { %>
    <th>recur</th>
    <th>next</th>
<% } %>
    <th>instrument</th>
  </tr>
<% _.each(values,function(item){ %>
  <tr>
    <td><a href="<%= CRM.url("civicrm/sepa/pdf",{"ref":item.reference}) %>"><%= item.reference %></a></td>
    <td><%= item.total_amount %></td>
    <td><a href="<%= CRM.url("civicrm/contact/view",{"cid":item.contact_id}) %>"><%= item.contact_id %></a></td>
    <td><a href="<%= CRM.url("civicrm/contact/view/contribution",{"id":item.contribution_id,"cid":item.contact_id,"action":"view"}) %>"><%= item.contribution_id %></a></td>
    <td><%= item.receive_date.substring(0,10) %></td>
<% if(type != 'OOFF') { %>
    <td><a href="<%= CRM.url("civicrm/contact/view/contributionrecur",{"id":item.recur_id,"cid":item.contact_id,"a  ction":"view"}) %>"><%= item.recur_id %></a></td>
    <td><%= item.next_sched_contribution_date.substring(0,10) %></td>
<% } %>
    <td><%= item.payment_instrument_id %></td>
  </tr>
<%  }); %>
</script>
</table>
</td>
</tr>
</table>

<script>
cj(function($){
  $(".button_close").click(function() {
    var $tr=$(this).closest("tr");
    CRM.api("SepaTransactionGroup","close",{"id":$tr.data("id")},{"success":function(data) {
      console.log(data);
    }});
  });
  $(".button_generate").click(function() {
console.log ("click");
    var $tr=$(this).closest("tr");
    CRM.api("SepaTransactionGroup","createnext",{"id":$tr.data("id")},{"success":function(data) {
      CRM.alert(ts('reload to see it'), 'New batch '+ data.values[0].reference, 'success');
      console.log(data);
    }});
    return false;
  });
  $(".nb_contrib").click(function(){
    var $tr=$(this).closest("tr");
    if ($tr.next().hasClass("detail")) {
     $tr.next().remove();
     return;
    }
    CRM.api("SepaContributionGroup","getdetail",{"id":$tr.data("id")},{"success":function(data) {
      _.extend(data,$tr.data());
      $tr.after(_.template($("#detail").html(),data));
      console.log(data);
    }});
  });
});
</script>
<style>
  .nb_contrib {cursor:pointer}
  .nb_contrib:hover {text-decoration:underline;}
</style>
{/literal}

