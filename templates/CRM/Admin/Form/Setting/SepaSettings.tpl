{*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 SYSTOPIA                       |
| Author: N. Bochan (bochan -at- systopia.de)            |
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

{crmScope extensionKey='org.project60.sepa'}
{literal}
<style>
div.sdd-settings {
  padding: 10px;
}
div.sdd-add-creditor {
  display:none;
  background-color: #e6e6bb;
  border: 2px dotted #f00;
  margin: 5px;
  padding: 10px;
}
</style>
{/literal}

<div class="crm-block crm-form-block crm-alternative_batching-form-block">
  <div class="sdd-settings">
      <h2>{ts}Creditors{/ts}</h2>
      {if $creditors}
      <table class="form-layout">
          <tr class="crm-creditor-block">
            <th>{ts}ID{/ts}</th>
            <th>{ts}Label{/ts}</th>
            <th>{ts}IBAN{/ts}</th>
            <th>{ts}BIC{/ts}</th>
            <th>{ts}Actions{/ts}</th>
          </tr>
        {foreach item=creditor from=$creditors}
          <tr class="crm-creditor-block">
            <td>[{$creditor.id}]</td>
            <td>{$creditor.label}</td>
            <td>{$creditor.iban}</td>
            <td>{$creditor.bic}</td>
            <td>
              <a class="add button" title="Copy" onclick="fetchCreditor({$creditor.id}, true);">
                <span><div class="icon add-icon ui-icon-circle-plus"></div>{ts}Copy{/ts}</span>
              </a>
              <a class="edit button" title="Edit" onclick="fetchCreditor({$creditor.id}, false); cj('a.add').hide();">
                <span><div class="icon edit-icon ui-icon-pencil"></div>{ts}Edit{/ts}</span>
              </a>
              <a class="delete button" title="Delete" onclick="deletecreditor({$creditor.id});">
                <i class="crm-i fa-trash" aria-hidden="true"></i> {ts}Delete{/ts}
              </a>
            </td>
          </tr>
        {/foreach}
      </table>
      {else}
        <p style="text-align: center;">{ts}No creditors found{/ts}</p>
      {/if}
      <a class="add button" title="Add" onclick="cj('#addcreditor').toggle(500); cj(this).hide(); resetValues();">
        <span><div class="icon add-icon ui-icon-circle-plus"></div>{ts}Add{/ts}</span>
      </a><br/>
      <div id="addcreditor" class="sdd-add-creditor" >
     <h2>{ts}Add/Edit Creditor{/ts}</h2>
     <h3>{ts}Creditor Information{/ts}</h3>
     <table id="creditorinfo" class="form-layout">
        <tr>
         <td class="label">{$form.addcreditor_label.label} <a onclick='CRM.help("{ts}Creditor Label{/ts}", {literal}{"id":"id-label","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
         <td>
           {$form.addcreditor_label.html}
         </td>
        </tr>
        <tr>
         <td class="label">{$form.addcreditor_name.label} <a onclick='CRM.help("{ts}Creditor Name{/ts}", {literal}{"id":"id-name","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
         <td>
           {$form.addcreditor_name.html}
         </td>
        </tr>
        <tr>
          <td class="label">{$form.is_test_creditor.label} <a onclick='CRM.help("{ts}Test Creditor{/ts}", {literal}{"id":"id-test-creditor","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
          <td>
            {$form.is_test_creditor.html}
          </td>
        </tr>
        <tr>
          <td class="label">{$form.addcreditor_creditor_id.label} <a onclick='CRM.help("{ts}Creditor Contact{/ts}", {literal}{"id":"id-contact","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
          <td>
            {$form.addcreditor_creditor_id.html}
          </td>
        </tr>
        <tr>
          <td class="label">{$form.addcreditor_address.label} <a onclick='CRM.help("{ts}Creditor Address{/ts}", {literal}{"id":"id-address","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
          <td>
            {$form.addcreditor_address.html}
          </td>
        </tr>
        <tr>
          <td class="label">{$form.addcreditor_country_id.label} <a onclick='CRM.help("{ts}Creditor Country{/ts}", {literal}{"id":"id-country","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
          <td>
            {$form.addcreditor_country_id.html}
          </td>
        </tr>
        <tr>
          <td class="label">{$form.addcreditor_currency.label}</td>
          <td>
            {$form.addcreditor_currency.html}
          </td>
        </tr>
        <tr>
          <td class="label">{$form.addcreditor_id.label} <a onclick='CRM.help("{ts}Creditor Identifier{/ts}", {literal}{"id":"id-id","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
          <td>
            {$form.addcreditor_id.html}
          </td>
        </tr>
        <tr>
          <td class="label">{$form.addcreditor_iban.label} <a onclick='CRM.help("{ts}IBAN{/ts}", {literal}{"id":"id-iban","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
          <td>
            {$form.addcreditor_iban.html}
          </td>
        </tr>
        <tr>
          <td class="label">{$form.addcreditor_bic.label} <a onclick='CRM.help("{ts}BIC{/ts}", {literal}{"id":"id-bic","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
          <td>
            {$form.addcreditor_bic.html}
          </td>
        </tr>
        <tr>
          <td class="label">{$form.addcreditor_cuc.label} <a onclick='CRM.help("{ts}CUC{/ts}", {literal}{"id":"id-cuc","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
          <td>
            {$form.addcreditor_cuc.html}
          </td>
        </tr>
        <tr>
          <td class="label">{$form.addcreditor_pain_version.label} <a onclick='CRM.help("{ts}PAIN Version{/ts}", {literal}{"id":"id-pain","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
          <td>
            {$form.addcreditor_pain_version.html}
          </td>
        </tr>
        <tr>
          <td class="label">{$form.addcreditor_type.label} <a onclick='CRM.help("{ts}Creditor Type{/ts}", {literal}{"id":"id-creditor-type","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
          <td>
            {$form.addcreditor_type.html}
          </td>
        </tr>
         <tr>
             <td class="label">{$form.addcreditor_pi_ooff.label} <a onclick='CRM.help("{ts}One-Off Payment Instruments{/ts}", {literal}{"id":"id-payment-instruments-ooff","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
             <td>
                 {$form.addcreditor_pi_ooff.html}
             </td>
         </tr>
         <tr>
             <td class="label">{$form.addcreditor_pi_rcur.label} <a onclick='CRM.help("{ts}Recurring Payment Instruments{/ts}", {literal}{"id":"id-payment-instruments-rcur","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
             <td>
                 {$form.addcreditor_pi_rcur.html}
             </td>
         </tr>
         <tr>
             <td class="label">{$form.addcreditor_uses_bic.label} <a onclick='CRM.help("{ts}Uses BICs{/ts}", {literal}{"id":"id-uses-bic","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
             <td>
                 {$form.addcreditor_uses_bic.html}
             </td>
         </tr>
        <tr>
          <td class="label">{$form.custom_txmsg.label} <a onclick='CRM.help("{ts}Transaction Message{/ts}", {literal}{"id":"id-txmsg","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
          <td>
            {$form.custom_txmsg.html}
          </td>
        </tr>
       </table>
       {$form.add_creditor_id.html}
       {$form.edit_creditor_id.html}
     <h3>{ts}Custom Batching Settings (for this creditor){/ts}</h3>
     <table id="custombatching" class="form-layout">
            <tr class="crm-custom-form-block-cycle-days">
              <td class="label">{$form.custom_cycledays.label} <a onclick='CRM.help("{ts}Cycle Day(s){/ts}", {literal}{"id":"id-cycle-days","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.custom_cycledays.html}
              </td>
            </tr>
            <tr class="crm-custom-form-block-ooff-horizon-days">
              <td class="label">{$form.custom_OOFF_horizon.label} <a onclick='CRM.help("{ts}Batching Horizon{/ts}", {literal}{"id":"id-ooff-horizon","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.custom_OOFF_horizon.html}
              </td>
            </tr>
            <tr class="crm-custom-form-block-ooff-notice-days">
              <td class="label">{$form.custom_OOFF_notice.label} <a onclick='CRM.help("{ts}Batching Notice Days{/ts}", {literal}{"id":"id-notice","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.custom_OOFF_notice.html}
              </td>
            </tr>
            <tr class="crm-custom-form-block-rcur-horizon-days">
              <td class="label">{$form.custom_RCUR_horizon.label} <a onclick='CRM.help("{ts}Batching Horizon{/ts}", {literal}{"id":"id-rcur-horizon","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.custom_RCUR_horizon.html}
              </td>
            </tr>
            <tr class="crm-custom-form-block-rcur-grace-days">
              <td class="label">{$form.custom_RCUR_grace.label} <a onclick='CRM.help("{ts}Grace Period{/ts}", {literal}{"id":"id-rcur-grace","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.custom_RCUR_grace.html}
              </td>
            </tr>
            <tr class="crm-custom-form-block-rcur-notice-days">
              <td class="label">{$form.custom_RCUR_notice.label} <a onclick='CRM.help("{ts}Batching Notice Days{/ts}", {literal}{"id":"id-notice","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.custom_RCUR_notice.html}
              </td>
            </tr>
            <tr class="crm-custom-form-block-frst-notice-days">
              <td class="label">{$form.custom_FRST_notice.label} <a onclick='CRM.help("{ts}Batching Notice Days{/ts}", {literal}{"id":"id-notice","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.custom_FRST_notice.html}
              </td>
            </tr>
       </table>
       <br/>
       <div>
          <a class="save button" title="Save" onclick="updateCreditor();">
            <span>{ts}Save{/ts}</span>
          </a>
          <a class="cancel button" title="Cancel" onclick="resetValues(); cj('#addcreditor').hide(500); cj('a.add').show(); return;">
            <span>{ts}Cancel{/ts}</span>
          </a><br/>
       </div>
       <br/>
   </div>
   </div>
  <br/><br/>
  <div class="sdd-settings">
    <fieldset>
        <h2>{ts}Default Batching Settings{/ts}</h2>
        <table class="form-layout">
            <tr class="crm-alternative_batching-form-block-cycle-days">
              <td class="label">{$form.cycledays.label} <a onclick='CRM.help("{ts}Cycle Day(s){/ts}", {literal}{"id":"id-cycle-days","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.cycledays.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-ooff-horizon-days">
              <td class="label">{$form.batching_OOFF_horizon.label} <a onclick='CRM.help("{ts}Batching Horizon{/ts}", {literal}{"id":"id-ooff-horizon","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.batching_OOFF_horizon.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-ooff-notice-days">
              <td class="label">{$form.batching_OOFF_notice.label} <a onclick='CRM.help("{ts}Batching Notice Days{/ts}", {literal}{"id":"id-notice","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.batching_OOFF_notice.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-rcur-horizon-days">
              <td class="label">{$form.batching_RCUR_horizon.label} <a onclick='CRM.help("{ts}Batching Horizon{/ts}", {literal}{"id":"id-rcur-horizon","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.batching_RCUR_horizon.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-rcur-grace-days">
              <td class="label">{$form.batching_RCUR_grace.label} <a onclick='CRM.help("{ts}Grace Period{/ts}", {literal}{"id":"id-rcur-grace","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.batching_RCUR_grace.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-rcur-notice-days">
              <td class="label">{$form.batching_RCUR_notice.label} <a onclick='CRM.help("{ts}Batching Notice Days{/ts}", {literal}{"id":"id-notice","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.batching_RCUR_notice.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-frst-notice-days">
              <td class="label">{$form.batching_FRST_notice.label} <a onclick='CRM.help("{ts}Batching Notice Days{/ts}", {literal}{"id":"id-notice","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.batching_FRST_notice.html}
              </td>
            </tr>
       </table>
       <br/>
       <h2>{ts}System Settings{/ts}</h2>
        <table class="form-layout">
            <tr class="crm-alternative_batching-form-block-batching_default_creditor">
              <td class="label">{$form.batching_default_creditor.label} <a onclick='CRM.help("{ts}Default Creditor{/ts}", {literal}{"id":"id-defaultcreditor","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.batching_default_creditor.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-buffer-days">
                <td class="label">{$form.pp_buffer_days.label} <a onclick='CRM.help("{ts}Recurring Buffer Days{/ts}", {literal}{"id":"id-buffer-days","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
                <td>
                    {$form.pp_buffer_days.html}
                </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-allow_mandate_modification">
              <td class="label">{$form.allow_mandate_modification.label} <a onclick='CRM.help("{ts}Mandate Modifications{/ts}", {literal}{"id":"id-mandatemodifications","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.allow_mandate_modification.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-custom-txmsg">
              <td class="label">{$form.custom_txmsg.label} <a onclick='CRM.help("{ts}Transaction Message{/ts}", {literal}{"id":"id-txmsg","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.custom_txmsg.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-update-lock_timeout">
              <td class="label">{$form.batching_UPDATE_lock_timeout.label} <a onclick='CRM.help("{ts}Update lock timeout{/ts}", {literal}{"id":"id-lock","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.batching_UPDATE_lock_timeout.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-exclude-weekends">
              <td class="label">{$form.exclude_weekends.label} <a onclick='CRM.help("{ts}Exclude Weekends{/ts}", {literal}{"id":"id-exclude-weekends","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.exclude_weekends.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-skip-closed">
              <td class="label">{$form.sdd_skip_closed.label} <a onclick='CRM.help("{ts}Only Completed Contributions{/ts}", {literal}{"id":"id-skip-closed","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.sdd_skip_closed.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-no-draft-xml">
              <td class="label">{$form.sdd_no_draft_xml.label} <a onclick='CRM.help("{ts}No XML Draft Files{/ts}", {literal}{"id":"id-no-draft-xml","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.sdd_no_draft_xml.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-async-batching">
              <td class="label">{$form.sdd_async_batching.label} <a onclick='CRM.help("{ts}Support Large Groups{/ts}", {literal}{"id":"id-async-batching","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.sdd_async_batching.html}
              </td>
            </tr>
       </table>
       <br/>
      <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
  </div>
  </fieldset>
</div>

{/crmScope}
