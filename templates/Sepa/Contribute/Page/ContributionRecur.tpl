<h3>Sepa Mandate {$sepa.id}</h3>
<div class="crm-container">
        <div class="crm-block crm-content-block crm-sdd-mandate">
          <table class="crm-info-panel">
            <tr><td class="label">{ts}Reference{/ts}</td><td>{$sepa.reference}</td></tr>
            <tr><td class="label">{ts}IBAN{/ts}</td><td>{$sepa.iban}</td></tr>
            <tr><td class="label">{ts}BIC{/ts}</td><td>{$sepa.bic}</td></tr>
            <tr><td class="label">{ts}Status{/ts}</td><td>{$sepa.status}</td></tr>
            <tr><td class="label">{ts}Creation date{/ts}</td><td>{$sepa.creation_date}</td></tr>
            <tr><td class="label">{ts}Signature date{/ts}</td><td>{$sepa.date}</td></tr>
            <tr><td class="label">{ts}Validation date{/ts}</td><td>{$sepa.validation_date}</td></tr>
{assign var="cid" value=$recur.contact_id}
{assign var="fcid" value=$sepa.first_contribution_id}
          <tr><td class="label">{ts}1st contribution{/ts}</td><td><a href="{crmURL p='civicrm/contact/view/contribution' q="reset=1&action=view&id=$fcid&cid=$cid"}">{$sepa.first_contribution_id}</a></td></tr>
</table></div>
 
{assign var="mid" value=$sepa.id}
<div class="crm-submit-buttons">
<form action="{crmURL p='civicrm/sepa/pdf' q="reset=1&id=$mid"}" amethod="post">
<input type="hidden" name="id" value="{$sepa.id}"/>
<input type="hidden" name="reset" value="1"/>
<a class="button" href="{crmURL p='civicrm/contact/view' q="action=browse&selectedChild=contribute&cid=$contactId"}"><span><div class="icon ui-icon-close"></div>{ts}Done{/ts}</span></a>

{assign var="crid" value=$recur.id}
<a class="button" href="{crmURL p='civicrm/contribute/updaterecur' q="reset=1&crid=$crid&cid=$contactId&context=contribution"}"><span><div class="icon edit-icon"></div>{ts}Edit{/ts}</span></a>
<button name="pdfaction" value="print" class="ui-button ui-button-text-icon-primary">
<span class="ui-button-icon-primary ui-icon ui-icon-print"></span>
<span class="ui-button-text">Print</span>
</button>
<button name="pdfaction" value="email" class="ui-button ui-button-text-icon-primary">
<span class="ui-button-icon-primary ui-icon ui-icon-mail-closed"></span>
<span class="ui-button-text">Email</span>
</button>

</form>
</div>
</div>
{literal}
<style>
.crm-recurcontrib-view-block .crm-submit-buttons {display:none;}
</style>
{/literal}

