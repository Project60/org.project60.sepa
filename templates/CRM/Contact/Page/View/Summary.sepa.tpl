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

<script type="text/javascript">
var contribution_snippet_changed  = false;
var sepa_edit_mandate_html        = "{ts domain="org.project60.sepa"}SEPA Mandate{/ts}";
var contribution_tab_selector_44x = "#{ts domain="org.project60.sepa"}Contributions{/ts} > div.crm-container-snippet";
var can_create_mandate            = {$can_create_mandate};
var can_edit_mandate              = {$can_edit_mandate};

// listen to DOM changes
cj("#mainTabContainer").bind("DOMSubtreeModified", sepa_modify_summary_tab_contribution);

{literal}
function sepa_modify_summary_tab_contribution() {
  if (contribution_snippet_changed) return;

  // check if the tab is fully loaded
  // these selectors differ from 4.4.x to 4.5.x
  var contribution_tab_id_45x       = cj("#mainTabContainer").find(".crm-contact-tabs-list #tab_contribute").attr("aria-controls");
  if (contribution_tab_id_45x) {
    var contribution_tab_selector_45x = cj("#" + contribution_tab_id_45x + " form[id='Search']");
  } else {
    var contribution_tab_selector_45x = cj([]);
  }
  var contribution_tab = cj("#mainTabContainer").find(contribution_tab_selector_45x);
  if (!contribution_tab.length) {
    // fallback for CiviCRM 4.4.x:
    var contribution_tab = cj("#mainTabContainer").find(contribution_tab_selector_44x);
  }

  if (contribution_tab.length > 0) {
    contribution_snippet_changed = true; // important to do this BEFORE changing the model

    // modify the edit links for recurring contributons, if they are mandates
    var recurring_contribution_table_rows = contribution_tab.find("table.selector:last() > tbody > tr[id]");
    for (var i=0; i<recurring_contribution_table_rows.length; i++) {
      var recurring_contribution_table_row = cj(recurring_contribution_table_rows[i]);
      var rcur_id_components = recurring_contribution_table_row.attr('id').split(/[_\-]+/);
      if (rcur_id_components.length==0) continue;
      var rcur_id = rcur_id_components[rcur_id_components.length-1];
      if (!rcur_id.match(/^[0-9]+$/)) continue;   // only digits, we're looking for an ID

      CRM.api3('SepaMandate', 'get', {'q': 'civicrm/ajax/rest',
                                     'entity_id': rcur_id,
                                     'entity_table': 'civicrm_contribution_recur',
                                     'return': 'entity_id,id'},
      {success: function(data) {
          if (data['is_error']==0 && data['count']==1) {
              for (var mandate_id in data['values']) {
                  var rcur_id = data['values'][mandate_id]['entity_id'];
                  cj("#row_" + rcur_id + ", #contribution_recur-" + rcur_id).find("a.action-item").parent().html("[" + sepa_edit_mandate_html + "]");
              }
          }
        }
      });
    }
  }
}
{/literal}
</script>
