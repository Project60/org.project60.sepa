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

<fieldset class="label-left crm-sdd">

<div class="header-dark">{ts}Direct Debit Payment{/ts}</div>

<div class="crm-section iban-section no-label">
  <div class="label"><label>{ts}Mandate Reference{/ts}</label></div>
  <div class="content">{$mandate_reference}<input type="hidden" name="mandate_reference" value="{$mandate_reference}" id="mandate_reference"></div>
  <div class="clear"></div>
</div>

<div class="crm-section iban-section no-label">
  <div class="label"><label>{ts}IBAN{/ts}</label></div>
  <div class="content">{$bank_iban}<input type="hidden" name="bank_iban" value="{$bank_iban}" id="bank_iban"></div>
  <div class="clear"></div>
</div>

<div class="crm-section iban-section no-label">
  <div class="label"><label>{ts}BIC{/ts}</label></div>
  <div class="content">{$bank_bic}<input type="hidden" name="bank_bic" value="{$bank_bic}" id="bank_bic"></div>
  <div class="clear"></div>
</div>

</fieldset>


<script type="text/javascript">
cj('.credit_card-group').html("");
</script>

