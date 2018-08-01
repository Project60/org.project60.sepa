{*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 SYSTOPIA                       |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+-------------------------------------------------------*}

<div id="sdd-create-mandate">

  <div class="crm-section">
    <div class="label">{$form.creditor_id.label}</div>
    <div class="content">{$form.creditor_id.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.financial_type_id.label}</div>
    <div class="content">{$form.financial_type_id.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.campaign_id.label}</div>
    <div class="content">{$form.campaign_id.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.reference.label}</div>
    <div class="content">{$form.reference.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.source.label}</div>
    <div class="content">{$form.source.html}</div>
    <div class="clear"></div>
  </div>

  <hr/>

  <div class="crm-section">
    <div class="label">{$form.bank_account_preset.label}</div>
    <div class="content">{$form.bank_account_preset.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.iban.label}</div>
    <div class="content">{$form.iban.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.bic.label}</div>
    <div class="content">{$form.bic.html}</div>
    <div class="clear"></div>
  </div>

  <hr/>

  <div class="crm-section">
    <div class="label">{$form.amount.label}</div>
    <div class="content">{$form.amount.html}&nbsp;{$form.currency.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.type.label}</div>
    <div class="content">{$form.type.html}</div>
    <div class="clear"></div>
  </div>


  <div id="sdd-ooff-data">
    <div class="crm-section">
      <div class="label">{$form.ooff_date.label}</div>
      <div class="content">{$form.ooff_date.html} {include file="CRM/common/jcalendar.tpl" elementName='ooff_date'}</div>
      <div class="clear"></div>
    </div>
  </div>


  <div id="sdd-rcur-data">
    <div class="crm-section">
      <div class="label">{$form.rcur_start_date.label}</div>
      <div class="content">{$form.rcur_start_date.html} {include file="CRM/common/jcalendar.tpl" elementName='rcur_start_date'}</div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.cycle_day.label}</div>
      <div class="content">{$form.cycle_day.html}</div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.interval.label}</div>
      <div class="content">{$form.interval.html}</div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.rcur_end_date.label}</div>
      <div class="content">{$form.rcur_end_date.html} {include file="CRM/common/jcalendar.tpl" elementName='rcur_end_date'}</div>
      <div class="clear"></div>
    </div>

  </div>
</div>

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

{literal}
<script type="application/javascript">

  // logic to hide OOFF/RCUR fields
  function sdd_change_type() {
      var type = cj("#sdd-create-mandate").find("[name=type]").val();
      if (type == 'RCUR') {
          cj("#sdd-create-mandate div#sdd-ooff-data").hide(100);
          cj("#sdd-create-mandate div#sdd-rcur-data").show(100);
      } else {
          cj("#sdd-create-mandate div#sdd-ooff-data").show(100);
          cj("#sdd-create-mandate div#sdd-rcur-data").hide(100);
      }
  }
  cj("#sdd-create-mandate").find("[name=type]").change(sdd_change_type);
  sdd_change_type();


  // logic to set bank accounts
  cj("#sdd-create-mandate").find("[name=bank_account_preset]").change(function() {
    var new_account = cj("#sdd-create-mandate").find("[name=bank_account_preset]").val();
    if (new_account.length > 0) {
        var bits = new_account.split("/");
        cj("#sdd-create-mandate").find("[name=iban]").val(bits[0]);
        cj("#sdd-create-mandate").find("[name=bic]").val(bits[1]);
    } else {
        cj("#sdd-create-mandate").find("[name=iban]").val('');
        cj("#sdd-create-mandate").find("[name=bic]").val('');
    }
  });


</script>
{/literal}