<div class="crm-block crm-form-block crm-alternative_batching-form-block">
  <div>
    <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="top"}</div>
    <fieldset>
        <h2>Default Batching Settings</h2>
        <table class="form-layout">
            <tr class="crm-alternative_batching-form-block-ooff-horizon-days">
              <td class="label">{$form.batching_alt_OOFF_horizon.label}</td>
              <td>
                {$form.batching_alt_OOFF_horizon.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-ooff-notice-days">
              <td class="label">{$form.batching_alt_OOFF_notice.label}</td>
              <td>
                {$form.batching_alt_OOFF_notice.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-rcur-horizon-days">
              <td class="label">{$form.batching_alt_RCUR_horizon.label}</td>
              <td>
                {$form.batching_alt_RCUR_horizon.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-rcur-notice-days">
              <td class="label">{$form.batching_alt_RCUR_notice.label}</td>
              <td>
                {$form.batching_alt_RCUR_notice.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-frst-horizon-days">
              <td class="label">{$form.batching_alt_FRST_horizon.label}</td>
              <td>
                {$form.batching_alt_FRST_horizon.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-frst-notice-days">
              <td class="label">{$form.batching_alt_FRST_notice.label}</td>
              <td>
                {$form.batching_alt_FRST_notice.html}
              </td>
            </tr>
       </table>
       <h2>System Settings</h2>
        <table class="form-layout">
            <tr class="crm-alternative_batching-form-block-update-lock_timeout">
              <td class="label">{$form.batching_alt_update_lock_timeout.label}</td>
              <td>
                {$form.batching_alt_update_lock_timeout.html}
              </td>
            </tr>
       </table> 
  </div>
  <div>
      <h2>Creditors</h2>
      {if $creditors}
      <table class="form-layout">
          <tr class="crm-creditor-block">
            <th>{ts}Name{/ts}</th>
            <th>{ts}IBAN{/ts}</th>
            <th>{ts}BIC{/ts}</th>
            <th>{ts}Actions{/ts}</th>
          </tr>
        {foreach item=creditor from=$creditors}
          <tr class="crm-creditor-block">
            <td>{$creditor.name}</td>
            <td>{$creditor.iban}</td>
            <td>{$creditor.bic}</td>
            <td>
              <a class="edit button" title="Edit" onclick="CRM.alert('This function is not yet implemented');">
                <span><div class="icon edit-icon"></div>{ts}Edit{/ts}</span>
              </a>
              <a class="delete button" title="Delete" onclick="deletecreditor({$creditor.id});">
                <span><div class="icon delete-icon"></div>{ts}Delete{/ts}</span>
              </a>
            </td>
          </tr>
        {/foreach}
      </table>
      {else}
        <p style="text-align: center;">{ts}No creditors found{/ts}</p>
      {/if}
   </div>
   <div>
     <h2>Add/Edit Creditor</h2>
     <h3>Creditor Information</h3>
     <table class="form-layout">
            <tr>
              <td class="label">{$form.addcreditor_name.label}</td>
              <td>
                {$form.addcreditor_name.html}
              </td>
            </tr>
            <tr>
              <td class="label">{$form.addcreditor_address.label}</td>
              <td>
                {$form.addcreditor_address.html}
              </td>
            </tr>
            <tr>
              <td class="label">{$form.addcreditor_id.label}</td>
              <td>
                {$form.addcreditor_id.html}
              </td>
            </tr>
            <tr>
              <td class="label">{$form.addcreditor_iban.label}</td>
              <td>
                {$form.addcreditor_iban.html}
              </td>
            </tr>
            <tr>
              <td class="label">{$form.addcreditor_bic.label}</td>
              <td>
                {$form.addcreditor_bic.html}
              </td>
            </tr>
       </table>
     <h3>Custom Batching Settings</h3>
     <table class="form-layout">
            <tr class="crm-alternative_batching-form-block-ooff-horizon-days">
              <td class="label">{$form.batching_alt_OOFF_horizon.label}</td>
              <td>
                {$form.batching_alt_OOFF_horizon.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-ooff-notice-days">
              <td class="label">{$form.batching_alt_OOFF_notice.label}</td>
              <td>
                {$form.batching_alt_OOFF_notice.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-rcur-horizon-days">
              <td class="label">{$form.batching_alt_RCUR_horizon.label}</td>
              <td>
                {$form.batching_alt_RCUR_horizon.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-rcur-notice-days">
              <td class="label">{$form.batching_alt_RCUR_notice.label}</td>
              <td>
                {$form.batching_alt_RCUR_notice.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-frst-horizon-days">
              <td class="label">{$form.batching_alt_FRST_horizon.label}</td>
              <td>
                {$form.batching_alt_FRST_horizon.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-frst-notice-days">
              <td class="label">{$form.batching_alt_FRST_notice.label}</td>
              <td>
                {$form.batching_alt_FRST_notice.html}
              </td>
            </tr>
       </table>
   </div>
  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
    </fieldset>
</div>

{literal}
<script type="text/javascript">
  function deletecreditor(id) {

    CRM.confirm(function() {
              CRM.api('SepaCreditor', 'delete', {'q': 'civicrm/ajax/rest', 'sequential': 1, 'id': id},
                {success: function(data) {
                    CRM.alert("{/literal}{ts}Creditor deleted{/ts}", "{ts}Success{/ts}{literal}", "success");
                    location.reload();
                }
              }
            );
        },
        {
          message: {/literal}"{ts}Are you sure you want to delete this creditor?{/ts}"{literal}
        }
    );
    
  }
</script>
{/literal}