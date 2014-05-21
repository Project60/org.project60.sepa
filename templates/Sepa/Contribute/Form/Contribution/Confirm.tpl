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
