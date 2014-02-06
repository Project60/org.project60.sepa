<fieldset class="label-left crm-iban">

<div class="header-dark">{ts}Direct debit{/ts}</div>
<div class="crm-section iban-section no-label">
  <div class="label"><label>IBAN</label></div>
  <div class="content">{$iban}<input type="hidden" name="bank_iban" value="{$iban}" id="iban"></div>
  <div class="clear"></div>
</div>
<div class="crm-section iban-section no-label">
  <div class="label"><label>BIC</label></div>
  <div class="content">{$bic}<input type="hidden" name="bank_bic" value="{$bic}" id="iban"></div>
  <div class="clear"></div>
</div>

</fieldset>
