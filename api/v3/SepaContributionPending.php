<?php

/**
 * Get unbatched SEPA Contributions from pseudo-group(s) defined by a set of parameters.
 *
 * This call is an amalgamation of Contribution and Mandate .get calls,
 * passing some of the parameters to each, and joining the results.
 * Filtering is applied both by Contribution and by Mandate fields.
 *
 * The Contribution is the main entity, getting the parameters array directly.
 * Parameters for the Mandate need to be passed in a 'mandate' sub-array.
 *
 * The filter parameters would typically include `receive_date` and `payment_instrument_id`,
 * as well as `creditor_id` for the Mandate --
 * though sticking to this set is not an actual requirement of this API.
 *
 * Default filters by Contribution status, Mandate status, and Contact `is_deleted` status
 * are always applied, to ensure that indeed only pending active contributions are considered.
 *
 * For the benefit of the AJAX-based group detail listing on the Dashboard,
 * this call can optionally also fetch the corresponding `contribution_recur` record,
 * if a 'recur' parameter sub-array is supplied.
 * The result will be joined into the main result as well.
 *
 * (As we need to fetch the Contact objects for checking the `is_deleted` status,
 * it's also possible to pass explicit Contact parameters and request return values
 * in a 'contact' sub-array, just as for Mandates and 'recur' records --
 * though this is not presently needed for any of the existing use cases...)
 *
 * Beware of chaining further API calls onto this one: the results might be unexpected,
 * because filtering by related Objects fields is applied only after the main API invocation.
 *
 * Implementation note: for performance reasons, this function is not using a large nested API call,
 * as this would invoke the sub-calls separately for each single Contribution record,
 * which involves quite substantial overhead.
 * Rather, this function first fetches only the main entity (Contribution) records,
 * and collects all the IDs of the related objects from the result,
 * so it can then fetch all the objects of each type in just one API call.
 */
function civicrm_api3_sepa_contribution_pending_get($params) {
  $mandateParams = CRM_Utils_Array::value('mandate', $params, array());
  $contactParams = CRM_Utils_Array::value('contact', $params, array());
  $recurParams = CRM_Utils_Array::value('recur', $params); /* No default here, as this one is optional, and will be fetched only if the 'recur' sub-array is supplied. */

  $transaction = new CRM_Core_Transaction(); /* We only do read operations here -- however, we better make sure the data doesn't change beneath our feet while fetching the related objects. */

  #$instruments = array();
  #foreach (array('FRST', 'RCUR', 'OOFF') as $type) {
  #  $instruments[] = CRM_Core_OptionGroup::getValue('payment_instrument', $type, 'name');
  #}

  $instruments = array_map(
    function ($type) { return CRM_Core_OptionGroup::getValue('payment_instrument', $type, 'name'); },
    array('FRST', 'RCUR', 'OOFF')
  );

  #$result = civicrm_api3('Contribution', 'get', array_merge_recursive(array(
  #  'option.limit' => 1234567890,
  #  'contribution_status_id' => CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name'),
  #  #'payment_instrument_id' => array('IN' => $instruments), # 'IN' doesn't work with Contribution API...
  #  'return' => array('contribution_recur_id', 'contact_id'),
  #  'api.SepaContributionMandate.getsingle' => array_merge_recursive(array(
  #    'contribution_id' => '$value.id',
  #    'contribution_recur_id' => '$value.contribution_recur_id',
  #    'status' => array('IN' => array('FRST', 'RCUR', 'OOFF')),
  #  ), $mandateParams),
  #  'api.Contact.getsingle' => array(
  #    'id' => '$value.contact_id',
  #    'is_deleted' => 0,
  #    'format.only_id' => 1,
  #  ),
  #), $params));

  #$apiParams = (array_replace_recursive(array(
  #  'options' => array('limit' => 1234567890),
  #  'contribution_status_id' => CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name'),
  #  'contribution_payment_instrument_id' => array('IN' => $instruments),
  #  'return' => array('_recur' => 'contribution_recur_id', '_contact' => 'contact_id'),
  #  'api.SepaContributionMandate.getsingle' => array_merge_recursive(array(
  #    'contribution_id' => '$value.id',
  #    'contribution_recur_id' => '$value.contribution_recur_id',
  #    'status' => array('IN' => array('FRST', 'RCUR', 'OOFF')),
  #  ), $mandateParams),
  #  'api.Contact.getsingle' => array(
  #    'id' => '$value.contact_id',
  #    'is_deleted' => 0,
  #    'format.only_id' => 1,
  #  ),
  #), $params));
  #$mainResult = civicrm_api3('SepaContribution', 'get', $apiParams);

  /*
   * Using our custom 'SepaContribution' API instead of standard 'Contribution' one,
   * because it's faster, and has more consistent and complete parameter handling.
   *
   * (Specifically, supporting the standard form for the 'limit' parameter;
   * and more importantly, supporting the crucial 'IN' operator for 'contribution_payment_instrument_id'.)
   */
  $mainResult = civicrm_api3('SepaContribution', 'get', array_replace_recursive(array(
    'options' => array('limit' => 1234567890),
    'contribution_status_id' => CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name'),
    'contribution_payment_instrument_id' => array('IN' => $instruments),
    'return' => array('_recur' => 'contribution_recur_id', '_contact' => 'contact_id'), /* Using explicit keys to make them distinct from any passed from the caller, so array_replace() concatenates the values rather than overwriting. */
  ), $params));
  $contributions = &$mainResult['values'];

  /* Collect IDs of all related objects we need to fetch.
   *
   * Note: there will be some duplicates in there --
   * but these do not affect the result;
   * and the number should normally be so low
   * that it's probably not helpful to filter them for performance. */
  $contactIds = $rcurIds = $ooffIds = array(0); /* '0' is a dummy ID that never matches anything, but prevents errors when the list is empty otherwise. */
  foreach ($contributions as $id => $contribution) {
    $contactIds[] = $contribution['contact_id'];
    if (isset($contribution['contribution_recur_id'])) {
      $rcurIds[] = $contribution['contribution_recur_id'];
    } else {
      $ooffIds[] = $id;
    }
  }

  /* Get all Mandates from $ooffIds and $rcurIds into $ooffMandes and $rcurMandates. */
  foreach (array('ooff' => 'civicrm_contribution', 'rcur' => 'civicrm_contribution_recur') as $type => $entityTable) {
    $result = civicrm_api3('SepaMandate', 'get', array_replace_recursive(array(
      'options' => array('limit' => 1234567890),
      'entity_table' => $entityTable,
      'entity_id' => array('IN' => ${$type.'Ids'}),
      'status' => array('IN' => array('FRST', 'RCUR', 'OOFF')),
      'return' => array('_entity' => 'entity_id'), /* For some reason we have to request it explicitly in this case, although it's among the parameters?... */
    ), $mandateParams));

    /* We need the Mandates array indexed by `entity_id`, not `id`. */
    ${$type.'Mandates'} = array_reduce(
      $result['values'],
      function ($carry, $item) { return $carry + array($item['entity_id'] => $item); },
      array()
    );
  }

  $result = civicrm_api3('Contact', 'get', array_replace_recursive(array(
    'options' => array('limit' => 1234567890),
    'id' => array('IN' => $contactIds),
    'is_deleted' => 0,
    'return' => array(''), /* Don't really need anything... Just the ID that is passed as the key anyways. */
  ), $contactParams));
  $contacts = $result['values'];

  if (isset($recurParams)) {
    $result = civicrm_api3('ContributionRecur', 'get', array_replace_recursive(array(
      'options' => array('limit' => 1234567890),
      'id' => array('IN' => $rcurIds),
    ), $recurParams));
    $recurs = $result['values'];
  }

  $transaction->commit(); /* We are done with the DB queries here. */

  /* Apply the filters and merge the data from the related objects. */
  foreach ($contributions as $contributionId => &$contribution) {
    $contribution['contribution_id'] = $contribution['id'];

    #if (civicrm_error($contribution['api.Contact.getsingle'])) {
    #  unset($result['values'][$id]);
    #  continue;
    #}

    #$mandate = $contribution['api.SepaContributionMandate.getsingle'];
    #if (isset($mandate['id'])) {
    #  $contribution = array_merge($mandate, $contribution, array('mandate_id' => $mandate['id']));
    #} else {
    #  unset($result['values'][$id]);
    #  continue;
    #}

    /* If we have a matching Contact, merge its data; otherwise, drop the Contribution. */
    $contact = CRM_Utils_Array::value($contribution['contact_id'], $contacts);
    if ($contact) {
      $contribution = array_merge($contact, $contribution);
    } else {
      unset($contributions[$contributionId]);
      continue;
    }

    /* If we have a matching Mandate, merge its data; otherwise, drop the Contribution. */
    $recurId = CRM_Utils_Array::value('contribution_recur_id', $contribution);
    if ($recurId) {
      $mandate = CRM_Utils_Array::value($recurId, $rcurMandates);
    } else {
      $mandate = CRM_Utils_Array::value($contributionId, $ooffMandates);
    }
    if ($mandate) {
      $contribution = array_merge($mandate, $contribution, array('mandate_id' => $mandate['id']));
    } else {
      unset($contributions[$contributionId]);
      continue;
    }

    if (isset($recurParams)) {
      if ($recurId) { /* Only handle Recur if this is actually a recurring contribution... */
        /* If we have a matching Recur object, merge its data; otherwise, drop the Contribution. */
        $recur = CRM_Utils_Array::value($recurId, $recurs);
        if ($recur) {
          $contribution = array_merge($recur, $contribution, array('recur_id' => $recurId)); /* Explicitly setting 'recur_id' (redundant with 'contribution_recur_id' from Contribution record) for compatibility with api.SepaContributionGroup.getdetail. */
        } else {
          unset($contributions[$contributionId]);
          continue;
        }
      }
    }
  } /* foreach ($contribution) */

  $mainResult['count'] = count($contributions); /* Need to recount, as we dropped some of the Contribution result records when filtering be the other entities. */

  return $mainResult;
} /* civicrm_api3_sepa_contribution_pending_get() */
