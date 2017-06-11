
If you are in Europe and use CiviCRM to manage recurring contributions, you need this extension.

# SEPA Direct Debit Module

This branch is currently maintained by Xavier Dutoit (TTTP, xavier@tttp.eu) and Björn Endres (SYSTOPIA, endres@systopia.de).

Find more documentation on http://wiki.civicrm.org/confluence/display/CRM/CiviSEPA

**Important:** Please download a [official release](https://github.com/Project60/org.project60.sepa/releases)! Don't use the ``master`` branch unless you want bleeding edge and you know what you're doing.

# What it can do

* OOFF and RCUR payments
* SEPA dashboard gives you great status overview
* payment processer for online donations and event registrations[*](https://github.com/Project60/org.project60.sepa/issues?utf8=%E2%9C%93&q=is%3Aissue+is%3Aopen+event+registration+)
* UI to manipulate mandates
* automatic BIC lookup if [Little BIC Extension](https://github.com/Project60/org.project60.bic) in installed
* full SEPA group life cycle: 'open'-'closed/sent'->'received'
* record SEPA payment action and form for contacts
* manual batching with parameters for notice period and horizon
* automatic adjustment of late OOFF and RCUR transactions
* integration with [CiviBanking](https://github.com/Project60/CiviBanking)


# What it can not (yet) do

* permission management
* membership payments
* automatic submission to the banks


# Customisation

If you need customised mandate references, exclude certain collection dates, or add a custom transaction message to the collection, you want to create a sepa customization extension implementing the following hooks:

* `civicrm_create_mandate` - to generate custom mandate reference numbers
* `civicrm_defer_collection_date` - to avoid days when your bank won't accept collections. (Version 1.2+ can skip weekends w/o this hook)
* `civicrm_modify_txmessage` - to customize the transaction message (Version 1.2+ can set a generic message w/o this hook)

We added an example implementation for your convenience: [org.project60.sepacustom](https://github.com/Project60/sepa_dd/tree/master/org.project60.sepacustom)

