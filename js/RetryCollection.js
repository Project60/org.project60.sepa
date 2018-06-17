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

    function separetry_updateForm() {
      // call the API for some stats
      CRM.api3('SepaLogic', 'get_retry_stats', {
        "date_from":     separetry_getDate('from'),
        "date_to":       separetry_getDate('to'),
        "creditor_list": cj("#creditor_list").val(),
        "txgroup_list":  cj("#txgroup_list").val(),
      }).done(function(result) {
          // UPDATE FORM
          // update the text
          if (result['contribution_count'] > 0) {
              // TODO: multi-currency
              cj("#separetry-text").html(ts("Will attempt to re-collect %1 failed debit contributions from %2 different contacts.<br/>The total amount is %3.", {
                  domain: 'org.project60.sepa',
                  1: result['contribution_count'],
                  2: result['contact_count'],
                  3: CRM.formatMoney(result['total_amount'])
              }));
          } else {
              cj("#separetry-text").text(ts("No SEPA collections match your criteria.", {domain: 'org.project60.sepa'}));
          }

          // update creditors

          console.log("YAY!");
          console.log(result);

      });
    }

    // add change handler to all items
    cj("[name=date_range], #creditor_list, #txgroup_list").change(separetry_updateForm);
    separetry_updateForm();
});
