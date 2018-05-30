
## (Civi-)Sepa Terminology
## SEPA Payment-types
You can initiate two types of SEPA Direct Debits:
    -one-off (OOFF).
    -recurring (RCUR).

One-off: is used when the debtor, via the mandate, has only allowed you to perform one single direct debit. After the first direct debit the mandate has expired and you cannot use it anymore. Recurring is used when the debtor mandate allows for regular collections. This type of collection can be split into three sub-types:
Recurring (first): Must be used when you initiate the first direct debit under a new mandate.
Recurring (Recurring): Must be used when you initiate the subsequent direct debits under the mandate.
There is also a third subtype (Recurring final) which can be used when initiating the last DD under a mandate. The third subtype is not implemented in CiviSEPA

## Mandates
For each DD you need a mandate. It specifies all relevant data such as information on the debtor (your contact), the amount of the payment, etc. Each mandate must have a mandate reference which must be unique for each mandate in combination with your SEPA Direct Debit creditor identifier.

Officially, your debtor has to sign a SEPA Direct Debit paper mandate for a particular contract held with you before you may start sending collections to the debtor. This means that you would need to print out the mandate, send it out to the debtor (e.g. your donor) who then has to sign it and send it back to you. Since that is rather impractical and may not be actually required, you may need to find out local regulations with authorities and/or your bank.

## Pre-Notification
SEPA requires you to inform your debtor(s) upfront about when and why you will collect money. THat is called a pre-notification.
However, there is an ongoing discussion on how to interpret this rule and you will need to find out, how your country's and/or bank's approach is toward the pre-notification. You will also need to define your own policy on this matter.

## Collection date
The day the bank collects/subscribes payments from people's accounts

## Horizon
The period (from today) in days that you want to _look ahead_ and check and list contributions that can be collected on a collection date. 

## Notice day
The number of days a bank needs to carry out the direct debit activities

## Bank file
The file you export from CiviCRM and send to the bank to collect the money.

## Grace
The number of days after you created a bank file during which the bank file is kept. After the grace period, the file is deleted from CiviCRM. 

## Transaction message



## Country-, Bank- or Software-specific rules/exceptions
Each country has it's own DD-History. Many exceptions exist from the rules have emerged and will continue to do so. Also each bank may interpret the SEPA-”standards” slightly differently in ways that suit them best. Finally, if you use banking software that interprets your payment information before submitting it to the bank, be prepared to do some analysing in case of problems.
