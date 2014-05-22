<div class="crm-block crm-form-block crm-sepasettings-form-block">
  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="top"}</div>
<fieldset>
    <h3>Default Settings</h3>
    <table class="form-layout">
        <tr class="crm-sepasetting-form-block-ooff-horizon-days">
          <td class="label">{$form.sepasettings_ooff_horizon_days.label}</td>
          <td>
            {$form.sepasettings_ooff_horizon_days.html}
          </td>
        </tr>
        <tr class="crm-sepasettings-form-block-ooff-notice-days">
          <td class="label">{$form.sepasettings_ooff_notice_days.label}</td>
          <td>
            {$form.sepasettings_ooff_notice_days.html}
          </td>
        </tr>
        <tr class="crm-sepasetting-form-block-rcur-horizon-days">
          <td class="label">{$form.sepasettings_rcur_horizon_days.label}</td>
          <td>
            {$form.sepasettings_rcur_horizon_days.html}
          </td>
        </tr>
        <tr class="crm-sepasettings-form-block-rcur-notice-days">
          <td class="label">{$form.sepasettings_rcur_notice_days.label}</td>
          <td>
            {$form.sepasettings_rcur_notice_days.html}
          </td>
        </tr>
        <tr class="crm-sepasetting-form-block-frst-horizon-days">
          <td class="label">{$form.sepasettings_frst_horizon_days.label}</td>
          <td>
            {$form.sepasettings_frst_horizon_days.html}
          </td>
        </tr>
        <tr class="crm-sepasettings-form-block-frst-notice-days">
          <td class="label">{$form.sepasettings_frst_notice_days.label}</td>
          <td>
            {$form.sepasettings_frst_notice_days.html}
          </td>
        </tr>
   </table>
   <h3>System Settings</h3>
    <table class="form-layout">
        <tr class="crm-sepasetting-form-block-update-lock_timeout">
          <td class="label">{$form.sepasettings_update_lock_timeout.label}</td>
          <td>
            {$form.sepasettings_update_lock_timeout.html}
          </td>
        </tr>
   </table>
    <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
</fieldset>
 
</div>