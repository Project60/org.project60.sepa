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

  <div style="text-align: center; font-size: large;">
    <span id="sdd_summary_text"></span>
  </div>

  <hr/>

  <div class="crm-section">
    <div class="label">{$form.amount.label}</div>
    <div class="content">{$form.amount.html}&nbsp;{$form.currency.html}</div>
    <div class="clear"></div>
  </div>

  {*<div class="crm-section">*}
    {*<div class="label">{$form.type.label}</div>*}
    {*<div class="content">{$form.type.html}</div>*}
    {*<div class="clear"></div>*}
  {*</div>*}
    <div class="crm-section">
        <div class="label">{$form.interval.label}</div>
        <div class="content">{$form.interval.html}</div>
        <div class="clear"></div>
    </div>


  <div id="sdd-ooff-data">
    <div class="crm-section">
      <div class="label">{$form.ooff_date.label}</div>
      <div class="content">{include file="CRM/common/jcalendar.tpl" elementName='ooff_date'}</div>
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
      <div class="label">{$form.rcur_end_date.label}</div>
      <div class="content">{$form.rcur_end_date.html} {include file="CRM/common/jcalendar.tpl" elementName='rcur_end_date'}</div>
      <div class="clear"></div>
    </div>
  </div>

  <hr/>

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

</div>

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

<script type="application/javascript">
  var creditor_data = {$sdd_creditors};

  {literal}

  // logic to calculate text
  function sdd_calculate_collection_description() {
      // TODO
  }

  /**
   * Identify and get the jQuery field for the given value
   */
  function sdd_getF(fieldname) {
    return cj("#sdd-create-mandate").find("[name=" + fieldname + "]");
  }

  /**
   * Utility function to set the date on the %^$W#$&$&% datepicker elements
   * PRs welcome :)
   **/
  function sdd_setDate(fieldname, date) {
      var dp_element = cj("#sdd-create-mandate").find("[name^=" + fieldname + "].hasDatepicker");
      dp_element.datepicker('setDate', date);

      // flash the field a little bit to indicate change
      sdd_getF(fieldname).parent().fadeOut(50).fadeIn(50);
  }


  // logic to hide OOFF/RCUR fields
  function sdd_change_type() {
      var interval = sdd_getF('interval').val();
      if (parseInt(interval) > 0) {
          cj("#sdd-create-mandate div#sdd-ooff-data").hide(100);
          cj("#sdd-create-mandate div#sdd-rcur-data").show(100);
      } else {
          cj("#sdd-create-mandate div#sdd-ooff-data").show(100);
          cj("#sdd-create-mandate div#sdd-rcur-data").hide(100);
      }
  }
  sdd_getF('interval').change(sdd_change_type);
  sdd_change_type();


  // logic to set bank accounts
  sdd_getF('bank_account_preset').change(function() {
    var new_account = sdd_getF('bank_account_preset').val();
    if (new_account.length > 0) {
        var bits = new_account.split("/");
        sdd_getF('iban').val(bits[0]);
        sdd_getF('bic').val(bits[1]);
    } else {
        sdd_getF('iban').val('');
        sdd_getF('bic').val('');
    }
  })


  /**
   * Update the form's start dates and descriptive texts
   */
  function sdd_recalculate_fields() {
      var today = new Date();
      var creditor_id = sdd_getF('creditor_id').val();
      var creditor = creditor_data[creditor_id];
      console.log(creditor);

      // ADJUST OOFF START DATE
      var ooff_earliest = new Date(today.getFullYear(), today.getMonth(), today.getDate() + creditor['buffer_days'] + creditor['ooff_notice']);
      var ooff_current_value = sdd_getF('ooff_date').val();
      var ooff_new = null;
      if (ooff_current_value.length > 0) {
          // parse date and overwrite only if too early
          var ooff_current = Date.parse(ooff_current_value);
          if (ooff_earliest > ooff_current) {
              sdd_setDate('ooff_date', ooff_earliest);
          }
      } else {
          // no date set yet?
          sdd_setDate('ooff_date', ooff_earliest);
      }

      // ADJUST RCUR START DATE
      var rcur_earliest = new Date(today.getFullYear(), today.getMonth(), today.getDate() + creditor['buffer_days'] + creditor['frst_notice']);

      // TODO: move to next cycle day

      var rcur_current_value = sdd_getF('rcur_start_date').val();
      var rcur_new = null;
      if (rcur_current_value.length > 0) {
          // parse date and overwrite only if too early
          var rcur_current = Date.parse(rcur_current_value);
          if (rcur_earliest > rcur_current) {
              sdd_setDate('rcur_start_date', rcur_earliest);
          }
      } else {
          // no date set yet?
          sdd_setDate('rcur_start_date', rcur_earliest);
      }


      // CALCULATE SUMMARY TEXT
      var text = ts("<i>Missing Information</i>", {'domain':'org.project60.sepa'});
      var amount = parseFloat(sdd_getF('amount').val()); // TODO:
      var money_display = CRM.formatMoney(amount);
      if (amount) {
          if (type == 'OOFF') {
              var text = ts("Will collect %1 on %2", {
                  1: money_display,
                  2: sdd_getF('ooff_date').val(),
                  'domain':'org.project60.sepa'});
          }
      }
      // update text
      cj("#sdd_summary_text").html(text);
  }

  // logic to update creditor-based stuff
  function sdd_creditor_changed() {
     var creditor_id = cj("#sdd-create-mandate").find("[name=creditor_id]").val();

     // reset cycle days
     cj("#sdd-create-mandate").find("[name=cycle_day] option").remove();
     var cycle_days = creditor_data[creditor_id]['cycle_days'];
     for (var day in cycle_days) {
         cj("#sdd-create-mandate").find("[name=cycle_day]").append('<option val="' + day + '">' + day + '</option>');
     }

     // trigger update of calculations
      sdd_recalculate_fields();
  }

  // attach the update methods to the various change events
  cj("#sdd-create-mandate").find("[name=type],[name=amount],[name=cycle_day]").change(sdd_recalculate_fields);
  cj("#sdd-create-mandate").find("[name=creditor_id]").change(sdd_creditor_changed);

  // trigger the whole thing once
  sdd_creditor_changed();

</script>
{/literal}