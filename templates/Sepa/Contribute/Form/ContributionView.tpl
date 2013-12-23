<!-- Mandate -->
<div class="crm-accordion-wrapper ">
  <div class="crm-accordion-header">
    {ts}Sepa Mandate{/ts} {$sepa.id}
  </div>
  <div class="crm-accordion-body">
    <table class="crm-info-panel">
      <tr><td class="label">{ts}Reference{/ts}</td><td>{$sepa.reference}</td></tr>
      <tr><td class="label">{ts}IBAN{/ts}</td><td>{$sepa.iban}</td></tr>
      <tr><td class="label">{ts}BIC{/ts}</td><td>{$sepa.bic}</td></tr>
      <tr><td class="label">{ts}Enabled{/ts}</td><td>{$sepa.is_enabled}</td></tr>
      <tr><td class="label">{ts}Creation date{/ts}</td><td>{$sepa.creation_date}</td></tr>
      <tr><td class="label">{ts}Signature date{/ts}</td><td>{$sepa.date}</td></tr>
      <tr><td class="label">{ts}Validation date{/ts}</td><td>{$sepa.validation_date}</td></tr>
    </table>
  </div>
</div>
<!-- /Mandate -->
