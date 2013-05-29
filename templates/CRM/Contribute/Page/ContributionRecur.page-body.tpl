<h3>{ts}Sepa Mandate{/ts}</h3>
        <div class="crm-block crm-content-block crm-sdd-mandate">
          <table class="crm-info-panel">
            <tr><td class="label">{ts}Reference{/ts}</td><td>{$sepa.reference}</td></tr>
            <tr><td class="label">{ts}IBAN{/ts}</td><td>{$sepa.iban}</td></tr>
            <tr><td class="label">{ts}BIC{/ts}</td><td>{$sepa.bic}</td></tr>
            <tr><td class="label">{ts}Enabled{/ts}</td><td>{$sepa.is_enabled}</td></tr>
            <tr><td class="label">{ts}Creation date{/ts}</td><td>{$sepa.creation_date}</td></tr>
            <tr><td class="label">{ts}Signature date{/ts}</td><td>{$sepa.date}</td></tr>
            <tr><td class="label">{ts}Validation date{/ts}</td><td>{$sepa.validation_date}</td></tr>
</table></div>
<div class="crm-submit-buttons">
<div class="crm-button"><div class="icon"></div>{ts}Done{/ts}</div>
<div class="crm-button"><div class="icon"></div>{ts}Edit{/ts}</div>
<div class="crm-button"><div class="icon ui-icon-print"></div>{ts}Print{/ts}</div>
<div class="crm-button"><div class="icon ui-icon-mail-closed"></div>{ts}Mail{/ts}</div>
</div>

{literal}
<style>
.crm-recurcontrib-view-block .crm-submit-buttons {display:none;}
</style>
{/literal}

