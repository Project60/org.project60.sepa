<div class="crm-block crm-form-block crm-alternative_batching-form-block">
  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="top"}</div>
<fieldset>
    <h3>Default Settings</h3>
    <table class="form-layout">
        <tr class="crm-alternative_batching-form-block-ooff-horizon-days">
          <td class="label">{$form.alternative_batching_ooff_horizon_days.label}</td>
          <td>
            {$form.alternative_batching_ooff_horizon_days.html}
          </td>
        </tr>
        <tr class="crm-alternative_batching-form-block-ooff-notice-days">
          <td class="label">{$form.alternative_batching_ooff_notice_days.label}</td>
          <td>
            {$form.alternative_batching_ooff_notice_days.html}
          </td>
        </tr>
        <tr class="crm-alternative_batching-form-block-rcur-horizon-days">
          <td class="label">{$form.alternative_batching_rcur_horizon_days.label}</td>
          <td>
            {$form.alternative_batching_rcur_horizon_days.html}
          </td>
        </tr>
        <tr class="crm-alternative_batching-form-block-rcur-notice-days">
          <td class="label">{$form.alternative_batching_rcur_notice_days.label}</td>
          <td>
            {$form.alternative_batching_rcur_notice_days.html}
          </td>
        </tr>
        <tr class="crm-alternative_batching-form-block-frst-horizon-days">
          <td class="label">{$form.alternative_batching_frst_horizon_days.label}</td>
          <td>
            {$form.alternative_batching_frst_horizon_days.html}
          </td>
        </tr>
        <tr class="crm-alternative_batching-form-block-frst-notice-days">
          <td class="label">{$form.alternative_batching_frst_notice_days.label}</td>
          <td>
            {$form.alternative_batching_frst_notice_days.html}
          </td>
        </tr>
   </table>
   <h3>System Settings</h3>
    <table class="form-layout">
        <tr class="crm-alternative_batching-form-block-update-lock_timeout">
          <td class="label">{$form.alternative_batching_update_lock_timeout.label}</td>
          <td>
            {$form.alternative_batching_update_lock_timeout.html}
          </td>
        </tr>
   </table>
    <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
</fieldset>
 
</div>