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

<!-- Mandate -->
{assign var="mid" value=$sepa.id}

<h3>{ts domain="org.project60.sepa"}Sepa Mandate{/ts} {$sepa.id}</h3>
<div class="crm-container">
    <table class="crm-info-panel">
      <tr><td class="label">{ts domain="org.project60.sepa"}Reference{/ts}</td><td>{$sepa.reference}</td></tr>
      <tr><td class="label">{ts domain="org.project60.sepa"}IBAN{/ts}</td><td>{$sepa.iban}</td></tr>
      <tr><td class="label">{ts domain="org.project60.sepa"}BIC{/ts}</td><td>{$sepa.bic}</td></tr>
      <tr><td class="label">{ts domain="org.project60.sepa"}Status{/ts}</td><td>{$sepa.status}</td></tr>
      <tr><td class="label">{ts domain="org.project60.sepa"}Source{/ts}</td><td>{$sepa.source}</td></tr>
      <tr><td class="label">{ts domain="org.project60.sepa"}Creation date{/ts}</td><td>{$sepa.creation_date}</td></tr>
      <tr><td class="label">{ts domain="org.project60.sepa"}Signature date{/ts}</td><td>{$sepa.date}</td></tr>
      <tr><td class="label">{ts domain="org.project60.sepa"}Validation date{/ts}</td><td>{$sepa.validation_date}</td></tr>
    </table>
</div>

<div class="crm-submit-buttons" id="new_submit_buttons">
    <a href="{crmURL p='civicrm/sepa/xmandate' q="mid=$mid"}" class="button"><span><div class="icon edit-icon ui-icon-pencil"></div>{ts domain="org.project60.sepa"}Mandate Options{/ts}</span></a>
</div>

{literal}
<script type="text/javascript">
cj(document).ready(function() {
  cj("div.crm-submit-buttons div:not(#new_submit_buttons)").last().hide();
});
</script>
{/literal}
<!-- /Mandate -->
