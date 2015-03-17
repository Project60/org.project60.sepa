<div class="crm-accordion-wrapper crm-accordion_title-accordion crm-accordion-processed" id="sepa">
      <div class="crm-accordion-header">
        {ts}Sepa Mandate{/ts}
      </div>
      <div class="crm-accordion-body">
        {include file='CRM/Sepa/Form/SepaMandate-common.tpl'}
</div>
</div>

{literal}
<script>
cj(function($) {
  if ($('#paymentDetails_Information').length >0) { 
    $('#sepa').insertAfter('#paymentDetails_Information');
  } else {
    $('#sepa').insertAfter('.form-layout');
  } 
});
</script>
{/literal}


