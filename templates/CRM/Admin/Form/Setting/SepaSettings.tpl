{*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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
            <th>{ts}Name{/ts}</th>
            <th>{ts}IBAN{/ts}</th>
            <th>{ts}BIC{/ts}</th>
            <th>{ts}Actions{/ts}</th>
          </tr>
        {foreach item=creditor from=$creditors}
          <tr class="crm-creditor-block">
            <td>{$creditor.name}</td>
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
                <span><div class="icon delete-icon ui-icon-trash"></div>{ts}Delete{/ts}</span>
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
           <td class="label">{$form.addcreditor_name.label} <a onclick='CRM.help("{ts}Creditor Name{/ts}", {literal}{"id":"id-name","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a>
     </div></td>
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
              <td class="label">{$form.addcreditor_creditor_id.label} <a onclick='CRM.help("{ts}Creditor Contact{/ts}", {literal}{"id":"id-contact","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a>
        </div></td>
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
            {if $multi_currency}
              <tr>
                <td class="label">{$form.addcreditor_currency.label} <a onclick='CRM.help("{ts}Currency{/ts}", {literal}{"id":"id-currency","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
                <td>
                  {$form.addcreditor_currency.html}
                </td>
              </tr>
            {/if}
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
              <td class="label">{$form.addcreditor_pain_version.label} <a onclick='CRM.help("{ts}PAIN Version{/ts}", {literal}{"id":"id-pain","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.addcreditor_pain_version.html}
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
            <tr class="crm-alternative_batching-form-block-default_mandate_type">
              <td class="label">{$form.default_mandate_type.label} <a onclick='CRM.help("{ts}Default Mandate Type{/ts}", {literal}{"id":"id-defaultmandatetype","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.default_mandate_type.html}
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
            <tr class="crm-alternative_batching-form-block-multi-currency">
              <td class="label">{$form.multi_currency_field.label} <a onclick='CRM.help("{ts}Exclude Weekends{/ts}", {literal}{"id":"id-multi-currency","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
              <td>
                {$form.multi_currency_field.html}
              </td>
            </tr>
       </table>
       <br/>
       <h2>{ts}Payment Processor Settings{/ts}</h2>
        <table class="form-layout">
          <tr class="crm-pp-form-block-hide-bic">
            <td class="label">{$form.pp_hide_bic.label} <a onclick='CRM.help("{ts}Hide BIC{/ts}", {literal}{"id":"id-hide-bic","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
            <td>
              {$form.pp_hide_bic.html}
            </td>
          </tr>
          <tr class="crm-pp-form-block-hide-bic">
            <td class="label">{$form.pp_improve_frequency.label} <a onclick='CRM.help("{ts}Improve frequency dropdown{/ts}", {literal}{"id":"id-improve-frequency","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></td>
            <td>
              {$form.pp_improve_frequency.html}
            </td>
          </tr>
       </table>
       <br/>
      <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div> 
  </div>
  </fieldset>
</div>

<script type="text/javascript">
  var multi_currency = {$multi_currency};
</script>
{literal}
<script type="text/javascript">
  cj('#edit_creditor_id').val("none");

  cj(function() {

    CRM.api3('Domain', 'getsingle', {
      'sequential': 1,
      'return': 'version'
    }).done(function(result) {
      if(result['is_error'] === 0) {
        var raw_version = result['version'].split('.', 3);
        var version = [];

        cj.each(raw_version, function(k,v) {
          version[k] = parseInt(v, 10);
        });

        // <= 4.4.x
        if(version[0] <= 4 && version[1] <= 4) {

        var contactUrl = {/literal}"{crmURL p='civicrm/ajax/rest' q='className=CRM_Contact_Page_AJAX&fnName=getContactList&json=1&context=contact' h=0}"{literal};

          cj('#addcreditor_creditor_id').autocomplete(contactUrl, {
                width: 200,
                selectFirst: false,
                minChars: 1,
                matchContains: true,
                delay: 400,
                max: 20
          }).result(function(event, data, formatted) {
             cj('#addcreditor_creditor_id').val(data[0]);
             cj('#add_creditor_id').val(data[1]);
             return false;
          });

        }else{
          // > 4.4.x (4.5.x+)

    cj('#addcreditor_creditor_id').autocomplete({
      source: function(request, response) {
        var
          option = cj('#addcreditor_creditor_id'),
          params = {
                  'sequential': 1,
                  'sort_name': option.val()
                };
        CRM.api3('Contact', 'get', params).done(function(result) {
          var ret = [];
          if (result.values) {
            cj.each(result.values, function(k, v) {
              ret.push({value: v.contact_id, label: "[" + v.contact_id + "] " + v.display_name});
            })
          }
          response(ret);
        })
      },
      focus: function (event, ui) {
        return false;
      },
      select: function (event, ui) {
        cj('#addcreditor_creditor_id').val(ui.item.label);
        cj('#add_creditor_id').val(ui.item.value);
        return false;
      }
    });

        }
      }
    });

  });

  var customBatchingParams = [
              ["cycledays_override",      "custom_cycledays",    null],
              ["batching_OOFF_horizon_override", "custom_OOFF_horizon", null],
              ["batching_OOFF_notice_override",  "custom_OOFF_notice", null],
              ["batching_RCUR_horizon_override", "custom_RCUR_horizon", null],
              ["batching_RCUR_grace_override", "custom_RCUR_grace", null],
              ["batching_RCUR_notice_override", "custom_RCUR_notice", null],
              ["batching_FRST_notice_override", "custom_FRST_notice", null],
              ["custom_txmsg_override", "custom_txmsg", null]
             ];

  function deletecreditor(id) {

    CRM.confirm(function() {
              CRM.api('SepaCreditor', 'delete', {'q': 'civicrm/ajax/rest', 'sequential': 1, 'id': id},
                {success: function(data) {
                    CRM.api('Setting', 'get', {'q': 'civicrm/ajax/rest', 'sequential': 1},
                    {success: function(data) {
                        if (data['is_error'] == 0) {
                          cj.each(data["values"], function(key, value) {
                              if (value.batching_default_creditor == id) {
                                CRM.api('Setting', 'create', {'batching_default_creditor': '0'}, {success: function(data) {}});
                              }
                          });

                          CRM.alert("{/literal}{ts}Creditor deleted{/ts}", "{ts}Success{/ts}{literal}", "success");
                          location.reload();
                        }
                      }
                    }
                  );
                }
              }
            );
            resetValues();
        },
        {
          message: {/literal}"{ts}Are you sure you want to delete this creditor?{/ts}"{literal}
        }
    );
  }

  
  // This function is needed due to the asynchronous call of success() in CRM.api().
  function createCallback(data, map, i, creditorId) {
    return function (data) {
      if (data['is_error'] == 0) {
        var result = "";
        
        if (data['result'] != "undefined") {
          result = cj.parseJSON(data['result']);
          customBatchingParams[i][2] = result;  
        }

        if (result[creditorId] != undefined) {
          cj("#"+map[i][1]).val(result[creditorId]); 
        }else{
          cj("#"+map[i][1]).val("");
        }
      }
    }
  }

  function fetchCreditor(id, isCopy) {
    CRM.api('SepaCreditor', 'getsingle', {'q': 'civicrm/ajax/rest', 'sequential': 1, 'id': id},
    {success: function(data) {
        if (data['is_error'] == 0) {
          if (!isCopy) {
            cj('#edit_creditor_id').val(data['id']);
          }else{
            cj('#edit_creditor_id').val("none");
          }
          cj('#add_creditor_id').val(data['creditor_id']);
          cj('#addcreditor_name').val(data['name']);
          cj('#addcreditor_address').val(data['address']);
          cj('#addcreditor_country_id').val(data['country_id']);
          if (multi_currency) {
            cj('#addcreditor_currency').val(data['currency']);
          }
          cj('#addcreditor_id').val(data['identifier']);
          cj('#addcreditor_iban').val(data['iban']);
          cj('#addcreditor_bic').val(data['bic']);
          cj("#addcreditor_pain_version").val(data['sepa_file_format_id']);
          cj("#is_test_creditor").prop("checked", (data['category'] == "TEST"));
          cj('#addcreditor').show(500);

          CRM.api('Contact', 'getsingle', {'q': 'civicrm/ajax/rest', 'sequential': 1, 'id': data['creditor_id']}, 
            {success: function(data2) {
                if (data2['is_error'] == 0) {
                  cj('#addcreditor_creditor_id').val("[" + data2['id'] + "] " + data2['display_name']);
                }
            }
          });

          for (var i = 0; i < customBatchingParams.length; i++) {
            CRM.api('Setting', 'getvalue', {'q': 'civicrm/ajax/rest', 'sequential': 1, 'group': 'SEPA Direct Debit Preferences', 'name': customBatchingParams[i][0]}, {success: createCallback(data, customBatchingParams, i, id)});
          }
        }
      }
    }
    );
  }

  function updateCreditor() {
    var inputCreditorInfo   = cj("#addcreditor #creditorinfo :input").serializeArray();
    var inputCustomBatching = cj("#addcreditor #custombatching :input").serializeArray();
    inputCustomBatching.push({'name': "custom_txmsg", 'value': cj('#custom_txmsg').val()});
    var creditorId = cj('#edit_creditor_id').val();

    var map = new Array();
    map["edit_creditor_id"]         = "id";
    map["addcreditor_name"]         = "name";
    map["addcreditor_address"]      = "address";
    map["addcreditor_country_id"]   = "country_id";
    if (multi_currency) {
      map["addcreditor_currency"]     = "currency";
    }
    map["addcreditor_id"]           = "identifier";
    map["addcreditor_iban"]         = "iban";
    map["addcreditor_bic"]          = "bic";
    map["addcreditor_pain_version"] = "sepa_file_format_id";
    map["addcreditor_creditor_id"]  = "creditor_id";
    map["custom_txmsg"]             = "custom_txmsg";

    // update creditor information
    var updatedCreditorInfo = new Array();
    for (var i = 0; i < inputCreditorInfo.length; i++) {
      var name = map[(inputCreditorInfo[i]["name"])] || inputCreditorInfo[i]["name"];
      var value = inputCreditorInfo[i]["value"];
      if (name == "creditor_id") {
        value = cj('#add_creditor_id').val();
      }
      if (value != "") {
        updatedCreditorInfo[name] = value;
      }
    }

    var isTestCreditor = cj('#is_test_creditor').is(':checked');
    if (isTestCreditor) {
      updatedCreditorInfo['category'] = "TEST";
    }else{
      updatedCreditorInfo['category'] = "";
    }

    var stdObj = {'q': 'civicrm/ajax/rest', 'sequential': 1, 'mandate_active': 1};
    if (creditorId != "none") {
      stdObj.id = creditorId;
    }
    
    if(updatedCreditorInfo['creditor_id'] === undefined) {
      CRM.alert("{/literal}{ts}You must provide a valid contact to save this creditor{/ts}", "{ts}Error{/ts}{literal}", "error");
      return;
    }

    var reIBAN = /^[A-Z0-9]+$/;
    if(!reIBAN.test(updatedCreditorInfo['iban'])) {
      CRM.alert("{/literal}{ts}IBAN is not correct{/ts}", "{ts}Error{/ts}{literal}", "error");
      return;
    }

    cj(".save").addClass("disabled");
    cj(".save").attr('onclick','').unbind('click');

    var updObj = cj.extend(stdObj, updatedCreditorInfo);

    CRM.api('SepaCreditor', 'create', updObj,
            {success: function(data) {
                if (data['is_error'] == 0) {
                  // check whether we updated an existing creditor 
                  // or created a new one
                  var creditorId = cj('#edit_creditor_id').val();
                  if (creditorId == "none") {
                    creditorId = data['values'][0]['id'];
                  }

                  // update creditor batching settings
                  for (var i = 0; i < customBatchingParams.length; i++) {
                    var name = customBatchingParams[i][0];
                    var value = inputCustomBatching[i].value;
                    var param = {};

                    // modify the object from the database if it exists
                    if (customBatchingParams[i][2] !== null) {
                      param[name] = customBatchingParams[i][2];
                    }else{
                      param[name] = {};
                    }

                    if (value != "") {
                      param[name][creditorId] = value; 
                    }else{
                      delete param[name][creditorId];
                    }

                    param[name] = JSON.stringify(param[name]);
		    var once = true;
                    CRM.api('Setting', 'create', param, {success: function(data) {
			 if(once) {
                           once = !once;
			   CRM.alert("{/literal}{ts}Creditor updated{/ts}", "{ts}Success{/ts}{literal}", "success");
                           resetValues();
                           location.reload();
			 }
                      }});
                  }
                }
               }
            }
    );
  }

  function resetValues() {
    cj('#custombatching :input').val("");
    cj('#creditorinfo :input').val("");
    cj('#edit_creditor_id').val("none");
    cj('#add_creditor_id').val("");
  }
</script>
{/literal}
