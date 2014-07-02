<?php

/*
 * Get Contributions, using generic BAO-based approach.
 *
 * This basically provides the same functionality as the official api.Contribution.get --
 * but the different implementation results in quite a number of small differences.
 *
 * While it is not fully compatible with the standard 'Contribution' API,
 * this implementation is more efficient; has less anomalies compared to other APIs;
 * and has more complete handling of standard input parameters.
 * (Notably including handling of filter operands such as 'IN',
 * and the standard syntax for providing the 'limit' parameter.)
 *
 * One downside is somewhat strange handling of the `payment_instrument_id` field:
 * as the schema provides a `contribution_payment_instrument_id` unique name for this field,
 * it has to be passed by this name in input parameters (filters);
 * but it will still appear as just `payment_instrument_id` in the output.
 * (Which also causes a 'missing value' warning,
 * because the auto-requested `contribution_payment_instrument_id` doesn't exist in the result...)
 * This probably should be considered a bug in the generic BAO-based API code.
 */
function civicrm_api3_sepa_contribution_get($params) {
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO('civicrm_api3_contribution_get'), $params);
}
