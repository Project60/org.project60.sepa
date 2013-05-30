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
<a class="button" href="{crmURL p='civicrm/contact/view' q='action=browse&selectedChild=contribute'}"><span><div class="icon ui-icon-close"></div>{ts}Done{/ts}</span></a>

{assign var="crid" value=$recur.id}
<a class="button" href="{crmURL p='civicrm/contribute/updaterecur' q="reset=1&crid=$crid&cid=$contactId&context=contribution"}"><span><div class="icon edit-icon"></div>{ts}Edit{/ts}</span></a>

<a class="button" href="#"><span><div class="icon print-icon"></div>{ts}Print{/ts}</span></a>
<a class="button" href="#"><span><div class="icon email-icon"></div>{ts}Email{/ts}</span></a>
</div>

{literal}
<style>
.crm-recurcontrib-view-block .crm-submit-buttons {display:none;}
</style>
{/literal}

