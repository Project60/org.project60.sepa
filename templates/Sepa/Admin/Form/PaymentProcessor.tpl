<div class="crm-accordion-wrapper crm-accordion_title-accordion crm-accordion-processed" id="sepa">
      <div class="crm-accordion-header">
        {ts}Sepa Creditor{/ts}
      </div>
      <div class="crm-accordion-body">
        <table class="form-layout-compressed" >
<tr id="crmf-is_active">
  <td class="label">{$form.creditor_name.label}</td>
  <td>{$form.creditor_name.html}</td>
</tr>
<tr id="crmf-address">
  <td class="label">{$form.creditor_address.label}</td>
  <td>{$form.creditor_address.html}</td>
</tr>
<tr id="crmf-country-idd">
  <td class="label">{$form.mandate_active.label}</td>
  <td>{$form.mandate_active.html}</td>
</tr>
<tr id="crmf-mandate_prefix">
  <td class="label">{$form.creditor_prefix.label}</td>
  <td>{$form.creditor_prefix.html}</td>
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
