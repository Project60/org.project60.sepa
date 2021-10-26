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
  // make sure we only keep the ones we want
  if (typeof CRM.vars.sdd.pis_keep !== 'undefined' && CRM.vars.sdd.pis_keep !== null) {
    cj("select[name=payment_instrument_id] option").each(function() {
      let pi = parseInt(cj(this).attr('value'));
      if (cj.inArray(pi, CRM.vars.sdd.pis_keep) == -1) {
        cj(this).remove();
      }
    });
  }

  // make sure drop the ones we don't want
  if (typeof CRM.vars.sdd.pis_remove !== 'undefined' && CRM.vars.sdd.pis_remove !== null) {
    for (let i = 0; i < CRM.vars.sdd.pis_remove.length; i++) {
      cj("select[name=payment_instrument_id] option[value=" + CRM.vars.sdd.pis_remove[i] + "]").remove();
    }
  }
});
