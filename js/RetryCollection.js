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

    function updateForm() {
      console.log("change!");
      // call the API for some stats
      CRM.api3('SepaLogic', 'get_retry_stats', {
        "date_range":       cj("[name=date_range]").val(),
        "date_custom_from": cj("[name=date_from]").val(),
        "date_custom_to":   cj("[name=date_to]").val(),
        "creditor_list":    cj("#creditor_list").val(),
        "txgroup_list":     cj("#txgroup_list").val(),
      }).done(function(result) {
          // do something
          console.log("YAY!");
          console.log(result);
      });
    }

    // add change handler to all items
    cj("[name=date_range], #creditor_list, #txgroup_list").change(updateForm);
});
