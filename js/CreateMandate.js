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

(function($, _, ts) {
  $(document).ready(function($) {
    let calculationInProgress = false;

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
        let dp_element = cj("#sdd-create-mandate").find("[name^=" + fieldname + "].hasDatepicker");
        dp_element.datepicker('setDate', date);

        // flash the field a little bit to indicate change
        sdd_getF(fieldname).parent().fadeOut(50).fadeIn(50);
        sdd_recalculate_fields();
    }

    /**
     * convert a Date object into a formatted string
     *  using the sdd_converter element
     */
    function sdd_formatDate(date) {
        return CRM.utils.formatDate(date);
    }

    // logic to hide OOFF/RCUR fields
    function sdd_change_type() {
        let interval = sdd_getF('interval').val();
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
        let new_account = sdd_getF('bank_account_preset').val();
        if (new_account.length > 0) {
            let bits = new_account.split("/");
            sdd_getF('iban').val(bits[0]);
            sdd_getF('bic').val(bits[1]);
        } else {
            sdd_getF('iban').val('');
            sdd_getF('bic').val('');
        }
    })

    /**
     * Get the amount as given in the amount field
     * This is tricky, because it should be able to deal with different separators
     */
    function sdd_getAmount() {
        let raw_value = sdd_getF('amount').val();
        if (raw_value.length > 0) {
            // TODO: deal with currencies that have different separators, or more than two decimals
            let cleaned_value = raw_value.replace(/[^0-9]/g, '.');
            let value = parseFloat(cleaned_value);

            // now write it back to the field
            // (unfortunately, CRM.formatMoney adds thousands-separators)
            sdd_getF('amount').val(value.toFixed(2));

            return value;

        } else {
            sdd_getF('amount').val('');
            return 0;
        }
    }
    sdd_getF('amount').change(sdd_getAmount);

    /**
     * Update the form's start dates and descriptive texts
     */
    function sdd_recalculate_fields() {
        if (calculationInProgress) {
            return;
        }
        calculationInProgress = true;

        try {
            let today = new Date();
            let creditor_id = sdd_getF('creditor_id').val();
            let creditor = CRM.vars.p60sdd.creditor_data[creditor_id];
            let frequency = parseInt(sdd_getF('interval').val());

            // UPDATE AVAILABLE PAYMENT instruments
            let available_pis = frequency ? creditor['pi_rcur_options'] : creditor['pi_ooff_options'];
            let payment_instrument_field = sdd_getF('payment_instrument_id');
            payment_instrument_field.find('option').remove();
            for (let available_pi_id in available_pis) {
              let pi_option = new Option(available_pis[available_pi_id], available_pi_id, false, true);
              payment_instrument_field.append(pi_option);
            }
            payment_instrument_field.change();

            // hide field if only one option available
            if (Object.keys(available_pis).length > 1) {
              payment_instrument_field.parent().parent().show();
            }
            else {
              payment_instrument_field.parent().parent().hide();
            }

            // ADJUST OOFF START DATE
            let ooff_earliest = new Date(today.getFullYear(), today.getMonth(), today.getDate() + creditor['buffer_days'] + creditor['ooff_notice']);
            let ooff_current_value = sdd_getF('ooff_date').val();
            let ooff_new = null;
            if (ooff_current_value.length > 0) {
              // parse date and overwrite only if too early
              let ooff_current = Date.parse(ooff_current_value);
              if (ooff_earliest > ooff_current) {
                sdd_setDate('ooff_date', ooff_earliest);
              }
            }
            else {
              // no date set yet?
              sdd_setDate('ooff_date', ooff_earliest);
            }
            cj("#sdd_ooff_earliest")
              .attr('date', ooff_earliest)
              .text(ts("earliest: %1", { 1: sdd_formatDate(ooff_earliest), domain: 'org.project60.sepa' }));

            // ADJUST RCUR START DATE
            let rcur_earliest = new Date(today.getFullYear(), today.getMonth(), today.getDate() + creditor['buffer_days'] + creditor['frst_notice']);
            let cycle_day = parseInt(sdd_getF('cycle_day').val());
            while (rcur_earliest.getDate() != cycle_day) { // move to next cycle day
              rcur_earliest = new Date(rcur_earliest.getFullYear(), rcur_earliest.getMonth(), rcur_earliest.getDate() + 1);
            }
            let rcur_current_value = sdd_getF('rcur_start_date').val();
            let rcur_new = null;
            if (rcur_current_value.length > 0) {
              // parse date and overwrite only if too early
              let rcur_current = new Date(rcur_current_value);
              if (rcur_earliest > rcur_current) {
                sdd_setDate('rcur_start_date', rcur_earliest);
              }
            }
            else {
              // no date set yet?
              sdd_setDate('rcur_start_date', rcur_earliest);
            }

            cj("#sdd_rcur_earliest")
              .data('date', rcur_earliest)
              .text(ts("earliest: %1", { 1: CRM.utils.formatDate(rcur_earliest) }));

            // CALCULATE SUMMARY TEXT
            let text = ts("<i>Not enough information</i>", { 'domain': 'org.project60.sepa' });
            let amount = sdd_getAmount();
            let money_display = CRM.formatMoney(amount);
            if (amount) {
              if (frequency == 0) {
                let my_start_date = new Date(sdd_getF('ooff_date').val());
                text = ts("Collects %1 on %2", {
                  1: money_display,
                  2: sdd_formatDate(my_start_date),
                  'domain': 'org.project60.sepa'
                });
              }
              else {
                let annual_display = CRM.formatMoney(amount * frequency);
                let my_start_date = new Date(sdd_getF('rcur_start_date').val());
                text = ts("Collects %1 %2 on the %3., beginning %4.<br/>Annual amount is %5.", {
                  1: money_display,
                  2: sdd_getF('interval').find('option[value=' + frequency + ']').text(),
                  3: sdd_getF('cycle_day').val(),
                  4: sdd_formatDate(my_start_date),
                  5: annual_display,
                  'domain': 'org.project60.sepa'
                });
              }
            }
            // update text
            cj("#sdd_summary_text").html(text);
        } finally {
            calculationInProgress = false;
        }
    }

    // logic to update creditor-based stuff
    function sdd_creditor_changed() {
        let creditor_id = sdd_getF('creditor_id').val();
        let creditor = CRM.vars.p60sdd.creditor_data[creditor_id];

        // show/hide BIC field
        if (parseInt(creditor['uses_bic'])) {
            sdd_getF('bic')
                .parent()
                .parent()
                .show();
        } else {
            sdd_getF('bic')
                .val('')
                .parent()
                .parent()
                .hide();
        }

        // reset cycle days
        sdd_getF('cycle_day').find('option').remove();
        let cycle_days = creditor['cycle_days'];
        for (var day in cycle_days) {
            sdd_getF('cycle_day').append('<option val="' + day + '">' + day + '</option>');
        }

        // calculate best (next possible) cycle day
        let today = new Date();
        let best_cycle_date = new Date(today.getFullYear(), today.getMonth(), today.getDate() + creditor['buffer_days'] + creditor['frst_notice']);
        while (!(best_cycle_date.getDate() in cycle_days)) {
            best_cycle_date = new Date(best_cycle_date.getFullYear(), best_cycle_date.getMonth(), best_cycle_date.getDate() + 1);
        }
        sdd_getF('cycle_day').val(best_cycle_date.getDate());

        // set currency
        sdd_getF('currency')
            .val(CRM.vars.p60sdd.creditor_data[creditor_id]['currency'])
            .fadeOut(50).fadeIn(50);

        // if creditor type is not SEPA (or empty): rename bic/iban
        if (!('creditor_type' in creditor) || creditor['creditor_type'] == 'SEPA') {
            // this is a SEPA creditor
            sdd_getF('bic').attr('placeholder', 'required');
            cj("#sdd-create-mandate").find("label[for=bic]").contents().first()[0].textContent = ts("BIC", {'domain': 'org.project60.sepa'});
            cj("#sdd-create-mandate").find("label[for=iban]").contents().first()[0].textContent = ts("IBAN", {'domain': 'org.project60.sepa'});

        } else {
            // this is NOT a SEPA creditor
            sdd_getF('bic').attr('placeholder', '');
            cj("#sdd-create-mandate").find("label[for=bic]").contents().first()[0].textContent = ts("Account Name", {'domain': 'org.project60.sepa'});
            cj("#sdd-create-mandate").find("label[for=iban]").contents().first()[0].textContent = ts("Account Reference", {'domain': 'org.project60.sepa'});
        }

        // update recurring/ooff intervals
        let interval_field = sdd_getF('interval');
        if (Object.keys(creditor['pi_ooff_options']).length > 0) {
            interval_field.find('option[value=0]').removeAttr('disabled');
        } else {
            interval_field.find('option[value=0]').attr('disabled', 'disabled');
        }
        if (Object.keys(creditor['pi_rcur_options']).length > 0) {
            interval_field.find('option[value!=0]').removeAttr('disabled');
        } else {
            interval_field.find('option[value!=0]').attr('disabled', 'disabled');
        }
        // set frequency to the first enabled option
        if (!interval_field.val()) {
          interval_field.find('option:not([disabled])').first().attr('selected', true).change();
        }

        // trigger update of calculations
        sdd_recalculate_fields();
    }

    /**
     * Set the amount picker back to "new account",
     *  e.g. after manually editing iban or bic
     */
    function sdd_iban_bic_changed() {
        // remove whitespaces and other stuff from IBAN and BIC
        let current_iban = sdd_getF('iban').val().replace(/\s+/g, '');
        sdd_getF('iban').val(current_iban);

        let current_bic = sdd_getF('bic').val().replace(/\s+/g, '');
        sdd_getF('bic').val(current_bic);

        // reset the picker without triggering change event
        sdd_getF('bank_account_preset').select2('val', '');
    }

    // attach earliest link handlers
    cj("#sdd-create-mandate").find("a.sdd-earliest").click(function() {
        if (cj(this).attr('id') == 'sdd_rcur_earliest') {
            sdd_setDate('rcur_start_date', $(this).data('date'));
        } else {
            sdd_setDate('ooff_date', $(this).data('date'));
        }
    });

    // attach the update methods to the various change events
    cj("#sdd-create-mandate").find("[name=interval],[name=amount],[name=cycle_day],[name^=ooff_date],[name^=rcur_start_date]").change(sdd_recalculate_fields);
    cj("#sdd-create-mandate").find("[name=creditor_id]").change(sdd_creditor_changed);
    cj("#sdd-create-mandate").find("[name=iban],[name=bic]").change(sdd_iban_bic_changed);

    // trigger the whole thing once
    sdd_creditor_changed();
  });
})(CRM.$, CRM._, CRM.ts('org.project60.sepa'));
