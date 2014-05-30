<div class="crm-block crm-form-block crm-alternative_batching-form-block">
  <div>
      <h2>Creditors</h2>
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
              <a class="edit button" title="Edit" onclick="fetchCreditor({$creditor.id});">
                <span><div class="icon edit-icon"></div>{ts}Edit{/ts}</span>
              </a>
              <a class="delete button" title="Delete" onclick="deletecreditor({$creditor.id});">
                <span><div class="icon delete-icon"></div>{ts}Delete{/ts}</span>
              </a>
            </td>
          </tr>
        {/foreach}
      </table>
      {else}
        <p style="text-align: center;">{ts}No creditors found{/ts}</p>
      {/if}
      <a class="add button" title="Add" onclick="cj('#addcreditor').toggle(500);">
        <span><div class="icon add-icon"></div>{ts}Add{/ts}</span>
      </a><br/>
      <div id="addcreditor" style="display:none;">
     <h2>Add/Edit Creditor</h2>
     <h3>Creditor Information</h3>
     <table id="creditorinfo" class="form-layout">
            <tr>
              <td class="label">{$form.addcreditor_creditor_id.label}</td>
              <td>
                {$form.addcreditor_creditor_id.html}
              </td>
            </tr>
            <tr>
              <td class="label">{$form.addcreditor_name.label}</td>
              <td>
                {$form.addcreditor_name.html}
              </td>
            </tr>
            <tr>
              <td class="label">{$form.addcreditor_address.label}</td>
              <td>
                {$form.addcreditor_address.html}
              </td>
            </tr>
            <tr>
              <td class="label">{$form.addcreditor_country_id.label}</td>
              <td>
                {$form.addcreditor_country_id.html}
              </td>
            </tr>
            <tr>
              <td class="label">{$form.addcreditor_id.label}</td>
              <td>
                {$form.addcreditor_id.html}
              </td>
            </tr>
            <tr>
              <td class="label">{$form.addcreditor_iban.label}</td>
              <td>
                {$form.addcreditor_iban.html}
              </td>
            </tr>
            <tr>
              <td class="label">{$form.addcreditor_bic.label}</td>
              <td>
                {$form.addcreditor_bic.html}
              </td>
            </tr>
            <tr>
              <td class="label">{$form.addcreditor_pain_version.label}</td>
              <td>
                {$form.addcreditor_pain_version.html}
              </td>
            </tr>
       </table>
     <h3>Custom Batching Settings</h3>
     <table id="custombatching" class="form-layout">
            <tr class="crm-custom-form-block-ooff-horizon-days">
              <td class="label">{$form.custom_OOFF_horizon.label}</td>
              <td>
                {$form.custom_OOFF_horizon.html}
              </td>
            </tr>
            <tr class="crm-custom-form-block-ooff-notice-days">
              <td class="label">{$form.custom_OOFF_notice.label}</td>
              <td>
                {$form.custom_OOFF_notice.html}
              </td>
            </tr>
            <tr class="crm-custom-form-block-rcur-horizon-days">
              <td class="label">{$form.custom_RCUR_horizon.label}</td>
              <td>
                {$form.custom_RCUR_horizon.html}
              </td>
            </tr>
            <tr class="crm-custom-form-block-rcur-notice-days">
              <td class="label">{$form.custom_RCUR_notice.label}</td>
              <td>
                {$form.custom_RCUR_notice.html}
              </td>
            </tr>
            <tr class="crm-custom-form-block-frst-horizon-days">
              <td class="label">{$form.custom_FRST_horizon.label}</td>
              <td>
                {$form.custom_FRST_horizon.html}
              </td>
            </tr>
            <tr class="crm-custom-form-block-frst-notice-days">
              <td class="label">{$form.custom_FRST_notice.label}</td>
              <td>
                {$form.custom_FRST_notice.html}
              </td>
            </tr>
            <tr class="crm-custom-form-block-edit-creditor-id">
              <td>
                {$form.edit_creditor_id.html}
              </td>
            </tr>
       </table>
       <div>
          <a class="save button" title="Save" onclick="">
            <span>{ts}Save{/ts}</span>
          </a>
          <a class="cancel button" title="Cancel" onclick="resetValues()">
            <span>{ts}Cancel{/ts}</span>
          </a><br/>
       </div>
   </div>
   </div>
  <div>
    <fieldset>
        <h2>Default Batching Settings</h2>
        <table class="form-layout">
            <tr class="crm-alternative_batching-form-block-ooff-horizon-days">
              <td class="label">{$form.batching_alt_OOFF_horizon.label}</td>
              <td>
                {$form.batching_alt_OOFF_horizon.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-ooff-notice-days">
              <td class="label">{$form.batching_alt_OOFF_notice.label}</td>
              <td>
                {$form.batching_alt_OOFF_notice.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-rcur-horizon-days">
              <td class="label">{$form.batching_alt_RCUR_horizon.label}</td>
              <td>
                {$form.batching_alt_RCUR_horizon.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-rcur-notice-days">
              <td class="label">{$form.batching_alt_RCUR_notice.label}</td>
              <td>
                {$form.batching_alt_RCUR_notice.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-frst-horizon-days">
              <td class="label">{$form.batching_alt_FRST_horizon.label}</td>
              <td>
                {$form.batching_alt_FRST_horizon.html}
              </td>
            </tr>
            <tr class="crm-alternative_batching-form-block-frst-notice-days">
              <td class="label">{$form.batching_alt_FRST_notice.label}</td>
              <td>
                {$form.batching_alt_FRST_notice.html}
              </td>
            </tr>
       </table>
       <h2>System Settings</h2>
        <table class="form-layout">
            <tr class="crm-alternative_batching-form-block-update-lock_timeout">
              <td class="label">{$form.batching_alt_update_lock_timeout.label}</td>
              <td>
                {$form.batching_alt_update_lock_timeout.html}
              </td>
            </tr>
       </table>
      <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div> 
  </div>
  </fieldset>
</div>

{literal}
<script type="text/javascript">
  cj('#edit_creditor_id').val("none");

  function deletecreditor(id) {

    CRM.confirm(function() {
              CRM.api('SepaCreditor', 'delete', {'q': 'civicrm/ajax/rest', 'sequential': 1, 'id': id},
                {success: function(data) {
                    CRM.alert("{/literal}{ts}Creditor deleted{/ts}", "{ts}Success{/ts}{literal}", "success");
                    location.reload();
                }
              }
            );
        },
        {
          message: {/literal}"{ts}Are you sure you want to delete this creditor?{/ts}"{literal}
        }
    );
  }


  /*
    This function is needed due to the asynchronous call of success() in CRM.api().
  */
  function createCallback(data, map, i) {
    return function (data) {
      if (data['is_error'] == 0) {
        cj("#"+map[i][1]).val(data['result']);   
      }
    }
  }

  function fetchCreditor(id) {
    CRM.api('SepaCreditor', 'getsingle', {'q': 'civicrm/ajax/rest', 'sequential': 1, 'id': id},
    {success: function(data) {
        if (data['is_error'] == 0) {
          cj('#edit_creditor_id').val(data['id']);
          cj('#addcreditor_creditor_id').val(data['creditor_id']);
          cj('#addcreditor_name').val(data['name']);
          cj('#addcreditor_address').val(data['address']);
          cj('#addcreditor_country_id').val(data['country_id']);
          cj('#addcreditor_id').val(data['identifier']);
          cj('#addcreditor_iban').val(data['iban']);
          cj('#addcreditor_bic').val(data['bic']);
          cj("#addcreditor_pain_version").val(data['sepa_file_format_id']);
          cj('#addcreditor').show(500);

          var cbat = [
                      ["batching.alt." + data['id'] + ".OOFF.horizon", "custom_OOFF_horizon"],
                      ["batching.alt." + data['id'] + ".OOFF.notice", "custom_OOFF_notice"],
                      ["batching.alt." + data['id'] + ".RCUR.horizon", "custom_RCUR_horizon"],
                      ["batching.alt." + data['id'] + ".RCUR.notice", "custom_RCUR_notice"],
                      ["batching.alt." + data['id'] + ".FRST.horizon", "custom_FRST_horizon"],
                      ["batching.alt." + data['id'] + ".FRST.notice", "custom_FRST_notice"]
                      //["batching.alt." + data['id'] + ".update.lock.timeout", "custom_lock_timeout"]
                    ];
          for (var i = 0; i < cbat.length; i++) {
            var test = cbat[i][0];
            CRM.api('Setting', 'getvalue', {'q': 'civicrm/ajax/rest', 'sequential': 1, 'group': 'org.project60', 'name': cbat[i][0]}, {success: createCallback(data, cbat, i)});
          };
        };
      }
    }
    );
  }

  function updateCreditor(values) {
    var inputCreditorInfo   = cj("#addcreditor #creditorinfo :input").serializeArray();
    var inputCustomBatching = cj("#addcreditor #custombatching :input").serializeArray();
    var updateFields = inputCreditorInfo.concat(inputCustomBatching);

    var creditorId = cj('#edit_creditor_id').val();
    console.log(creditorId);
    var map = [
                "edit_creditor_id"        => "id",
                "addcreditor_creditor_id" => "creditor_id",
                "addcreditor_name"        => "name",
                "addcreditor_address"     => "address",
                "addcreditor_country_id"  => "country_id",
                "addcreditor_id"          => "identifier",
                "addcreditor_iban"        => "iban",
                "addcreditor_bic"         => "bic",
                "addcreditor_pain_version"=> "sepa_file_format_id"
              ];

    var cbat = [
                      ["batching.alt." + data['id'] + ".OOFF.horizon", "custom_OOFF_horizon"],
                      ["batching.alt." + data['id'] + ".OOFF.notice", "custom_OOFF_notice"],
                      ["batching.alt." + data['id'] + ".RCUR.horizon", "custom_RCUR_horizon"],
                      ["batching.alt." + data['id'] + ".RCUR.notice", "custom_RCUR_notice"],
                      ["batching.alt." + data['id'] + ".FRST.horizon", "custom_FRST_horizon"],
                      ["batching.alt." + data['id'] + ".FRST.notice", "custom_FRST_notice"]
                      //["batching.alt." + data['id'] + ".update.lock.timeout", "custom_lock_timeout"]
                    ];

    for (var i = 0; i < updateFields.length; i++) {
      var name = map[(updateFields[i]["name"])];
      var value = updateFields[i]["value"];
      if (value != "") {
        // TODO
      };
    };
  }

  function resetValues() {
    cj('#custombatching :input').val("");
    cj('#creditorinfo :input').val("");
    cj('#edit_creditor_id').val("none");
  }
</script>
{/literal}