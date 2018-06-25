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
    function separetry_updateSelect(field_name, value, list, mode) {
        if (!value) {
            return;
        }

        // save old values
        var values = value.split(',');
        var old_values = cj("#" + field_name).select2("val");

        if (mode == 'replace') {
            // remove old options
            cj("#" + field_name + " option").remove();

            // see if that's it already
            if (!value) {
                cj("#" + field_name).select2("val", []);
                return;
            }

            // add new values
            for (v in values) {
                var val = values[v];
                var label = list[parseInt(val)];
                cj("#" + field_name).append(cj('<option>', {value: val, text: label}));
            }
        }


        // calculate the intersection
        if (mode == 'select') {
            var intersection = values.filter(value => -1 !== old_values.indexOf(value));
            cj("#" + field_name).select2("val", intersection);
        } else {
            cj("#" + field_name).select2("val", values);
        }
    }

    /**
     * Trigger a form update based on the configurations set
     */
    function separetry_updateForm(event) {
        // find out what triggered it:
        var change_source = 'date_range';
        if (event) {
            change_source = event.currentTarget.id;
        }

        // compile the request
        var query = {}
        switch (change_source) {
            case 'amount_max':
                query['amount_max'] = cj("#amount_max").val();
            case 'amount_min':
                query['amount_min'] = cj("#amount_min").val();
            case 'frequencies':
                query['frequencies'] = cj("#frequencies").val();
            case 'cancel_reason_list':
                query['cancel_reason_list'] = cj("#cancel_reason_list").val();
            case 'txgroup_list':
                query['txgroup_list'] = cj("#txgroup_list").val();
            case 'creditor_list':
                query['creditor_list'] = cj("#creditor_list").val();
            default:
                query['date_from'] = separetry_getDate('from');
                query['date_to'] = separetry_getDate('to');
        }
        // call the API for some stats
        CRM.api3('SepaLogic', 'get_retry_stats', query).done(function(result) {
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

            separetry_updateSelect("creditor_list",      result['creditor_list'],      CRM.vars.p60sdd.creditor_list,  'select');
            separetry_updateSelect("txgroup_list",       result['txgroup_list'],       CRM.vars.p60sdd.txgroup_list,   'replace');
            separetry_updateSelect("cancel_reason_list", result['cancel_reason_list'], result['cancel_reason_list'],   'replace');
            // separetry_updateSelect("frequencies",     result['frequencies'],        CRM.vars.p60sdd.frequencies, 'select');
            // separetry_updateSelect("txgroup_list", result['txgroup_list'], CRM.vars.p60sdd.txgroup_list, 'options_only');
      });
    }

    // add change handler to all items
    cj("[name=date_range], #creditor_list, #txgroup_list, #frequencies, #cancel_reason_list, #amount_min, #amount_max").change(separetry_updateForm);
    separetry_updateForm(null);
});
