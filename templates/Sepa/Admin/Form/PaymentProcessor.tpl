{$form.creditor_id.html}
<div class="crm-accordion-wrapper crm-accordion_title-accordion crm-accordion-processed" id="sepa">
      <div class="crm-accordion-header">
        {ts}Sepa Creditor{/ts}
      </div>
      <div class="crm-accordion-body">
        <table class="form-layout-compressed" >
<tr id="crmf-creditor_id">
  <td class="label">{$form.creditor_contact_id.label}</td>
  <td>{$form.creditor_contact_id.html}</td>
</tr>
<tr id="crmf-iban">
  <td class="label">{$form.creditor_iban.label}</td>
  <td>{$form.creditor_iban.html}</td>
</tr>
<tr id="crmf-bic">
  <td class="label">{$form.creditor_bic.label}</td>
  <td>{$form.creditor_bic.html}</td>
</tr>
<tr id="crmf-is_active">
  <td class="label">{$form.creditor_name.label}</td>
  <td>{$form.creditor_name.html}</td>
</tr>
<tr id="crmf-address">
  <td class="label">{$form.creditor_address.label}</td>
  <td>{$form.creditor_address.html}</td>
</tr>
<tr id="crmf-mandate_active">
  <td class="label">{$form.mandate_active.label}</td>
  <td>{$form.mandate_active.html}</td>
</tr>
<tr id="crmf-mandate_prefix">
  <td class="label">{$form.creditor_prefix.label}</td>
  <td>{$form.creditor_prefix.html}</td>
</tr>
<tr id="crmf-sepa_file_format_id">
  <td class="label">{$form.sepa_file_format_id.label}</td>
  <td>{$form.sepa_file_format_id.html}</td>
</tr>
<tr id="crmf-extra_advance_days">
  <td class="label">{$form.extra_advance_days.label}</td>
  <td>{$form.extra_advance_days.html} {help id='extra_advance_days' file='Sepa/Admin/Form/PaymentProcessor.hlp'}</td>
</tr>
<tr id="crmf-maximum_advance_days">
  <td class="label">{$form.maximum_advance_days.label}</td>
  <td>{$form.maximum_advance_days.html} {help id='maximum_advance_days' file='Sepa/Admin/Form/PaymentProcessor.hlp'}</td>
</tr>
<tr id="crmf-use_cor1">
  <td class="label">{$form.use_cor1.label}</td>
  <td>{$form.use_cor1.html} {help id='use_cor1' file='Sepa/Admin/Form/PaymentProcessor.hlp'}</td>
</tr>
<tr id="crmf-group_batching_mode">
  <td class="label">{$form.group_batching_mode.label}</td>
  <td>{$form.group_batching_mode.html} {help id='group_batching_mode' file='Sepa/Admin/Form/PaymentProcessor.hlp'}</td>
</tr>
<tr id="crmf-month_wrap_policy">
  <td class="label">{$form.month_wrap_policy.label}</td>
  <td>{$form.month_wrap_policy.html} {help id='month_wrap_policy' file='Sepa/Admin/Form/PaymentProcessor.hlp'}</td>
</tr>
<tr id="crmf-remittance_info">
  <td class="label">{$form.remittance_info.label}</td>
  <td>{$form.remittance_info.html} {help id='remittance_info' file='Sepa/Admin/Form/PaymentProcessor.hlp'}</td>
</tr>
          
</table>
</div>
</div>
{literal}
<script>
cj(function($) {
  var dom=["crm-paymentProcessor-form-block-test_url_site"
      ,"crm-paymentProcessor-form-block-url_site",
      "crm-paymentProcessor-form-block-url_recur",
      "crm-paymentProcessor-form-block-test_url_recur"];
  $.each(dom,function(i){$("."+dom[i]).hide();});
  $('#sepa').insertBefore('.crm-submit-buttons:last');
});
</script>
{/literal}
