
If your organisation is in Europe and uses CiviCRM to manage recurring contributions, you will need this extension.

## Introduction to SEPA
SEPA is EU regulation. It describes under what conditions organisations in the European Union can collect money from their contacts bank accounts through direct debit and on the basis of a mutual agreement (called a "mandate") between organisation and contact. 

## CiviSEPA
CiviSEPA is a CiviCRM extension that enables SEPA compliant direct debit actions with your constituents.

This branch is currently maintained by Xavier Dutoit (TTTP, xavier@tttp.eu) and Björn Endres (SYSTOPIA, endres@systopia.de).

View this extension in the [Extension Directory](https://civicrm.org/extensions/civisepa-sepa-direct-debit-extension).

Find more documentation on http://wiki.civicrm.org/confluence/display/CRM/CiviSEPA

**Important:** Please download a [official release](https://github.com/Project60/org.project60.sepa/releases)! Don't use the ``master`` branch unless you want bleeding edge and you know what you're doing.

## What it can do
* OOFF and RCUR payments
* SEPA dashboard gives you great status overview
* payment processer for online donations and event registrations[*](https://github.com/Project60/org.project60.sepa/issues?utf8=%E2%9C%93&q=is%3Aissue+is%3Aopen+event+registration+)
* UI to manipulate mandates
* automatic BIC lookup if [Little BIC Extension](https://github.com/Project60/org.project60.bic) in installed
* full SEPA group life cycle: 'open'-'closed/sent'->'received'
* record SEPA payment action and form for contacts
* manual batching with parameters for notice period and horizon
* automatic adjustment of late OOFF and RCUR transactions
* integration with [CiviBanking](https://docs.civicrm.org/banking/en/latest)

## What it can not (yet) do
* permission management
* membership payments
* automatic submission to the banks


## Installation

This extension needs to be installed manually into CiviCRM. It is not (yet) available from the built-in extensions catalog.

First, download an official release archive from the [release page](https://github.com/Project60/org.project60.sepa/releases). Unpack the archive and move the directory `org.project60.sepa` into your extensions directory (e.g., `.../civicrm/ext/`; you can find the exact location in your CiviCRM settings (Administer/System Settings/Directories)).

Next, open the extensions page in the CiviCRM settings (Administer/System Settings/Extensions). Find the extension `SEPA Direct Debit` in the "Extensions" tab and click on "Install". The extension will be set up.

Finally, you will have to update your database scheme. CiviCRM will prompt you to do so in a pop-up. Alternatively, you will find a prompt in the "System Status" in the admin console. Once you updated your database, the extension will be ready for use.


## Customisation
If you need customised mandate references, exclude certain collection dates, or add a custom transaction message to the collection, you want to create a sepa customization extension implementing the following hooks:

* `civicrm_create_mandate` - to generate custom mandate reference numbers
* `civicrm_defer_collection_date` - to avoid days when your bank won't accept collections. (Version 1.2+ can skip weekends w/o this hook)
* `civicrm_modify_txmessage` - to customize the transaction message (Version 1.2+ can set a generic message w/o this hook)

We added an example implementation for your convenience: [org.project60.sepacustom](https://github.com/Project60/sepa_dd/tree/master/org.project60.sepacustom)

If you want to customize the transaction message without creating an extension you can use tokens if you install [nl.hollandopensource.sepatxmessagetokens](https://github.com/HollandOpenSource/nl.hollandopensource.sepatxmessagetokens/#nlhollandopensourcesepatxmessagetokens).
