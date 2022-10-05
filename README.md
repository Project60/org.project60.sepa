If you are in Europe and use CiviCRM to manage recurring contributions, you need this extension.

# SEPA Direct Debit Module

This branch is currently maintained by Björn Endres (SYSTOPIA, endres@systopia.de).

Find more documentation [here](https://docs.civicrm.org/civisepa).

**Important:** Please download a [official release](https://github.com/Project60/org.project60.sepa/releases/latest)!

# What it can do

* OOFF and RCUR payments
* SEPA dashboard gives you great status overview
* Payment processer for online donations and event registrations with the [SEPA PP Extension](https://github.com/Project60/org.project60.sepapp)
* UI to manipulate mandates
* Automatic BIC lookup if [Little BIC Extension](https://github.com/Project60/org.project60.bic) in installed
* Full SEPA group life cycle: 'open'-'closed/sent'->'received'
* Record SEPA payment action and form for contacts
* Manual batching with parameters for notice period and horizon
* Automatic adjustment of late OOFF and RCUR transactions
* Integration with [FormProcessor Actions](https://civicrm.org/extensions/form-processor)
* Integration with [CiviBanking](https://github.com/Project60/CiviBanking)
* Membership payments (with the [Project60 Membership Extension](https://github.com/Project60/org.project60.membership))


# What it can not (yet) do
* automatic submission to the banks

# Automated Testing

[![CircleCI](https://circleci.com/gh/Project60/org.project60.sepa.svg?style=svg)](https://circleci.com/gh/Project60/org.project60.sepa)


# Customisation

If you need customised mandate references, exclude certain collection dates, or add a custom transaction message to the collection, you want to create a sepa customization extension implementing the following hooks:
* `civicrm_create_mandate` - to generate custom mandate reference numbers
* `civicrm_defer_collection_date` - to avoid days when your bank won't accept collections. (Version 1.2+ can skip weekends w/o this hook)
* `civicrm_modify_txmessage` - to customize the transaction message (Version 1.2+ can set a generic message w/o this hook)
* `civicrm_alter_next_collection_date` - alter the next collection date for a mandate.

We added an example implementation for your convenience: [org.project60.sepacustom](https://github.com/Project60/sepa_dd/tree/master/org.project60.sepacustom)

