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
    /**
     * Extract the current date from the form
     * @param from_or_to should be 'from' or 'to'
     * @returns date string
     */
    function separetry_getDate(from_or_to) {
        let selected = cj("[name=date_range]").val();
        if (selected == 'custom') {
            return cj('[name=date_' + from_or_to + ']').val();
        } else {
            let from_to_date = selected.split('-');
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
            value = '';
        }

        // save old values
        let values = value.split(',');
        let old_values = cj("#" + field_name).select2("val");

        if (mode == 'fill') {  // leave selection as it is, but add options

            // first: remove all options currently not selected
            cj("#" + field_name + " option")
                .filter(function(index) { return !(cj(this).attr('value') in old_values);})
                .remove();

            // second: add all new options that aren't in there yet
            // add new values
            for (v in values) {
                let val = values[v];
                if (val.length == 0) continue;
                let existing_option = cj("#" + field_name + " option[value=" + val + "]");
                if (existing_option.length) {
                    continue;
                }
                // add all
                let label = 'Label Error';
                if (val in list) {
                    label = list[val];
                } else {
                    label = list[parseInt(val)];
                }
                cj("#" + field_name).append(cj('<option>', {value: val, text: label}));
            }

            cj("#" + field_name).select2("val", old_values);
        }

        if (mode == 'replace') {// replace field content completely
            // remove old options
            cj("#" + field_name + " option").remove();

            // add all values
            for (val in list) {
                cj("#" + field_name).append(cj('<option>', {value: val, text: list[val]}));
            }
        }

        // calculate the intersection
        if (mode == 'select' || mode == 'replace') {
            let intersection = values.filter(value => -1 !== old_values.indexOf(value));
            cj("#" + field_name).select2("val", intersection);
        } else if (mode == 'fill') {
            // do nothing
        } else {
            cj("#" + field_name).select2("val", values);
        }
    }

    /**
     * Trigger a form update based on the configurations set
     */
    function separetry_updateForm(event) {
        // find out what triggered it:
        let filter_triggered = false;
        let change_source = '';
        if (event) {
            change_source = event.currentTarget.id;
        } else {
            change_source = 'date_range';
        }

        // these should always be there
        let query = {};
        query['date_from']          = separetry_getDate('from');
        query['date_to']            = separetry_getDate('to');
        query['creditor_list']      = cj("#creditor_list").val();
        query['txgroup_list']       = cj("#txgroup_list").val();

        if (cj.inArray(change_source, ['cancel_reason_list', 'frequencies', 'amount_max', 'amount_min']) >= 0) {
            // this is a filter query
            query['cancel_reason_list'] = cj("#cancel_reason_list").val();
            query['frequencies']        = cj("#frequencies").val();
            query['amount_max']         = cj("#amount_max").val();
            query['amount_min']         = cj("#amount_min").val();
        }

        // call the API for some stats
        // console.log(query);
        CRM.api3('SepaLogic', 'get_retry_stats', query).done(function(result) {
            // UPDATE FORM
            // console.log(result);

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

            // rebuild cancel reason list
            let cancel_reason_list = {};
            if (result['cancel_reason_list']) {
                let cancel_reason_values = result['cancel_reason_list'].split(',');
                for (idx in cancel_reason_values) {
                    cancel_reason_list[cancel_reason_values[idx]] = cancel_reason_values[idx];
                }
            }


            // update fields
            switch (change_source) {
                case 'date_range':
                    // this is a 'search' -> update lists
                    separetry_updateSelect("creditor_list", '', CRM.vars.p60sdd.creditor_list,  'replace');
                case 'creditor_list':
                    separetry_updateSelect("txgroup_list",  '', CRM.vars.p60sdd.txgroup_list,   'replace');
                case 'txgroup_list':
                    // reset all filters
                    separetry_updateSelect("cancel_reason_list", '', cancel_reason_list,             'replace');
                    separetry_updateSelect("frequencies",        '', CRM.vars.p60sdd.frequencies,    'replace');
                    cj("#amount_max").val('');
                    cj("#amount_max").val('');
                    break;
                default:
                case 'amount_max':
                case 'amount_min':
                case 'frequencies':
                case 'cancel_reason_list':
                    // these are filters -> don't change
                    break;
            }
      });
    }

    // add change handler to all items
    cj("[name=date_range], #creditor_list, #txgroup_list, #frequencies, #cancel_reason_list, #amount_min, #amount_max").change(separetry_updateForm);
    separetry_updateForm(null);
});
