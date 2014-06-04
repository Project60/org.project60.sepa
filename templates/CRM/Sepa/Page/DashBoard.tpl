<div class="action-link">
  <div class="crm-submit-buttons">
    <a class="button" href="{crmURL p='civicrm/sepa/createnext'}"><span>{ts}Generate Recurring Payments{/ts}</span></a>
  </div>
</div>

{foreach from=$groups key=creditor_id item=creditor}
<div class='crm-accordion-wrapper'>
  <div class='crm-accordion-header'>{ts}Creditor{/ts} {$creditor_id}</div>
  <div class="crm-accordion-body">

    <div class="action-link">
      <div class="crm-submit-buttons">
        <a class="button" href="{crmURL p='civicrm/sepa/batchingaction' q="_action=batch_for_submit&creditor_id=$creditor_id"}"><span>{ts}Prepare Submit{/ts}</span></a>
      </div>
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
{foreach from=$creditor item=group}
<tr class="status_{$group.status}" data-id="{$group.id}" data-type="{$group.type}">
<td title="id {$group.id}" class="nb_contrib">{$group.reference}</td>
<td>{$group.status_label}</td>
<td>{$group.type}</td>
<td>{$group.created_date}</td>
<td>{$group.collection_date}</td>
{assign var='file_id' value=$group.file_id}
<td class="file_{$group.file_id}">{$group.file_href}</td>
<td class="nb_contrib" title="list all the contributions">{$group.nb_contrib}</td>
<td>{$group.total} &euro;</td>
<td>
{if $group.status == 'Batched'}
  <a class="button" href="{crmURL p='civicrm/sepa/batchingaction' q="_action=cancel_submit_file&file_id=$file_id"}">{ts}Cancel File{/ts}</a>
  <a class="button" href="{crmURL p='civicrm/sepa/batchingaction' q="_action=confirm_submit_file&file_id=$file_id"}">{ts}File Submitted{/ts}</a>
{elseif $group.status == 'In Progress'}
  {assign var='group_id' value=$group.id}
  <a class="button" href="{crmURL p='civicrm/sepa/batchingaction' q="_action=abort_group&txgroup_id=$group_id"}">{ts}Group Aborted{/ts}</a>
  <a class="button" href="{crmURL p='civicrm/sepa/batchingaction' q="_action=complete_group&txgroup_id=$group_id"}">{ts}Group Completed{/ts}</a>
  <a class="button" href="{crmURL p='civicrm/sepa/batchingaction' q="_action=abort_file&file_id=$file_id"}">{ts}File Aborted{/ts}</a>
  <a class="button" href="{crmURL p='civicrm/sepa/batchingaction' q="_action=complete_file&file_id=$file_id"}">{ts}File Completed{/ts}</a>
{/if}
</td>
</tr>
{/foreach}
</table>

  </div> <!-- crm-accordion-body -->
</div> <!--crm-accordion-wrapper -->
{/foreach}

<script type="text/javascript">
  {literal}
    cj(function() {
      cj().crmAccordions();
    });
  {/literal}
</script>

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
</table>
</td>
</tr>
</script>
<script>
cj(function($){
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

