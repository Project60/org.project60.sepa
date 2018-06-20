/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2018                                     |
| Author: B. Endres (endres@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

cj(document).ready(function() {
  // TODO
    console.log("Yo");

    /**
     * Extract the current date from the form
     * @param from_or_to should be 'from' or 'to'
     * @returns date string
     */
    function separetry_getDate(from_or_to) {
        var selected = cj("[name=date_range]").val();
        if (selected == 'custom') {
            return cj('[name=date_' + from_or_to + ']').val();
        } else {
            var from_to_date = selected.split('-');
            if (from_or_to == 'from') {
                return from_to_date[0];
            } else {
                return from_to_date[1];
            }
        }
    }

    /**
     * Updates a (multiselect) select2 field
     *
     * @param field_name  the field's name
     * @param value       the value(s) to set
     * @param list        the list of labels for the items
     */
    function separetry_updateSelect(field_name, value, list) {
        // remove old options
        cj("#" + field_name + " option").remove();

        // see if that's it already
        if (!value) {
            cj("#" + field_name).select2("val", []);
            return;
        }

        // add new values
        var values = value.split(',');
        for (v in values) {
            var val = values[v];
            var label = list[parseInt(val)];
            cj("#" + field_name).append(cj('<option>', {value: val, text: label}));
        }

        // and set
        cj("#" + field_name).select2("val", values);
    }

    /**
     * Trigger a form update based on the configurations set
     */
    function separetry_updateForm() {
      // call the API for some stats
      CRM.api3('SepaLogic', 'get_retry_stats', {
        "date_from":          separetry_getDate('from'),
        "date_to":            separetry_getDate('to'),
        "creditor_list":      cj("#creditor_list").val(),
        "txgroup_list":       cj("#txgroup_list").val(),
        "amount_min":         cj("#amount_min").val(),
        "amount_max":         cj("#amount_max").val(),
        "cancel_reason_list": cj("#cancel_reason_list").val(),
      }).done(function(result) {
          // UPDATE FORM
          console.log(result);

          // update the text
          if (result['contribution_count'] > 0) {
              // TODO: multi-currency
              cj("#separetry-text").html(ts("Will attempt to re-collect <strong>%1</strong> failed debit contributions from %2 different contacts. The total amount is <strong>%3</strong>.", {
                  domain: 'org.project60.sepa',
                  1: result['contribution_count'],
                  2: result['contact_count'],
                  3: CRM.formatMoney(result['total_amount'])
              }));
          } else {
              cj("#separetry-text").text(ts("No SEPA collections match your criteria.", {domain: 'org.project60.sepa'}));
          }

          // update creditors
          separetry_updateSelect("creditor_list", result['creditor_list'], CRM.vars.p60sdd.creditor_list);
          separetry_updateSelect("txgroup_list", result['txgroup_list'], CRM.vars.p60sdd.txgroup_list);
      });
    }

    // add change handler to all items
    cj("[name=date_range], #creditor_list, #txgroup_list, [name=cancel_reason_list], [name=frequency_list], #amount_min, #amount_max").change(separetry_updateForm);
    separetry_updateForm();
});
