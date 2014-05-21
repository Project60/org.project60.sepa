<div class="crm-block crm-form-block crm-sepasettings-form-block">
  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="top"}</div>
<h3>Default Settings</h3>
<fieldset>
    <table class="form-layout">
        <tr class="crm-sepasetting-form-block-ooff-horizon-days">
          <td class="label">{$form.sepasettings_ooff_horizon_days.label}</td>
          <td>
            {$form.sepasettings_ooff_horizon_days.html}
          </td>
        </tr>
         <tr class="crm-sepasettings-form-block-recipient">
          <td class="label">{$form.sepasettings_recipient.label}</td>
          <td>
            {$form.sepasettings_recipient.html}
          </td>
        </tr>
   </table>
 
    <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
</fieldset>
 
</div>