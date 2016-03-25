{*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 TTTP                           |
| Author: X+                                             |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+-------------------------------------------------------*}

{$form.creditor_id.html}
<div class="crm-accordion-wrapper crm-accordion_title-accordion crm-accordion-processed" id="sepa">
      <div class="crm-accordion-header">
        {ts domain="org.project60.sepa"}Sepa Creditor{/ts}
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
