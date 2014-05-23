<div class="crm-block crm-form-block crm-alternative_batching-form-block">
  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="top"}</div>
<fieldset>
    <h3>Default Settings</h3>
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
   <h3>System Settings</h3>
    <table class="form-layout">
        <tr class="crm-alternative_batching-form-block-update-lock_timeout">
          <td class="label">{$form.batching_alt_update_lock_timeout.label}</td>
          <td>
            {$form.batching_alt_update_lock_timeout.html}
          </td>
        </tr>
   </table>
    <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
</fieldset>
 
</div>