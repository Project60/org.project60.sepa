If you are in Europe and use CiviCRM to manage recurring contributions, you need this extension.

# SEPA Direct Debit Module

This branch if currently maintained by Xavier Dutoit (TTTP, xavier@tttp.eu) and  Björn Endres (SYSTOPIA, endres@systopia.de).

Find more documentation on http://wiki.civicrm.org/confluence/display/CRM/CiviSEPA


# What it can do

* OOFF and RCUR payments
* SEPA dashboard gives you great status overview
* payment processer e.g. for online donations
* UI to manipulate mandates
* full SEPA group life cycle: 'open'-'closed/sent'->'received'
* record SEPA payment action and form for contacts
* manual batching with parameters for notice period and horizon
* automatic adjustment of late OOFF and RCUR transactions
* integration with CiviBanking


# What it can not (yet) do
* permission management


# Customisation

If you need customised mandate references, exclude certain collection dates, or add a custom transaction message to the collection, you want to create a sepa customization extension implementing the following hooks:
* `civicrm_create_mandate` - to generate custom mandate reference numbers
* `civicrm_defer_collection_date` - to avoid days when your bank won't accept collections
* `civicrm_modify_txmessage` - to customize the transaction message

Example:
```
function sepademo_civicrm_create_mandate(&$mandate_parameters) {

  if (isset($mandate_parameters['reference']) && !empty($mandate_parameters['reference']))
    return;   // user defined mandate

  // load contribution
  if ($mandate_parameters['entity_table']=='civicrm_contribution') {
    $contribution = civicrm_api('Contribution', 'getsingle', array('version' => 3, 'id' => $mandate_parameters['entity_id']));
    $interval = '00';   // one-time
  } else if ($mandate_parameters['entity_table']=='civicrm_contribution_recur') {
    $contribution = civicrm_api('ContributionRecur', 'getsingle', array('version' => 3, 'id' => $mandate_parameters['entity_id']));
    if ($contribution['frequency_unit']=='month') {
      $interval = sprintf('%02d', 12/$contribution['frequency_interval']);
    } else if ($contribution['frequency_unit']=='year') {
      $interval = '01';
    } else {
      // error:
      $interval = '99';
    }
  } else {
    die("unsupported mandate");
  }

  $reference  = 'SYSTOPIA';
  $reference .= $interval;
  $reference .= sprintf('C%08d', $contribution['contact_id']);
  $reference .= 'D';          // separator
  $reference .= date('Ymd');
  $reference .= 'N';          // separator
  $reference .= '%d';         // for numbers

  // try to find one that's not used yet...
  for ($n=0; $n < 10; $n++) {
    $reference_candidate = sprintf($reference, $n);
    // check if it exists
    $mandate = civicrm_api('SepaMandate', 'getsingle', array('version' => 3, 'reference' => $reference_candidate));
    if (isset($mandate['is_error']) && $mandate['is_error']) {
      // does not exist! take it!
      $mandate_parameters['reference'] = $reference_candidate;
      return;
    }
  }

  // if we get here, there are no more IDs
  die('No mandates IDs left for this id/date/type.');
}

function sepademo_civicrm_defer_collection_date(&$collection_date, $creditor_id) {
  // Don't collect on the week end
  $day_of_week = date('N', strtotime($collection_date));
  if ($day_of_week > 5) {
    // this is a weekend -> skip to Monday
    $defer_days = 8 - $day_of_week;
    $collection_date = date('Y-m-d', strtotime("+$defer_days day", strtotime($collection_date)));
  }
}

// generate transaction message
function sepademo_civicrm_modify_txmessage(&$txmessage, $info, $creditor) {
	$txmessage = "greetings from SYSTOPIA";
}
```

If you need help on how to create an extension, check out Tim Otten's great work on ```civix```: https://github.com/totten/civix/
