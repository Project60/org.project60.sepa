# SEPA-Basics

We will only cover some selected and relevant aspects of SEPA as the whole matter is very complex and many much more comprehensive compendia have been written about it already. For some countries that have been using Direct Debit (DD) Payments regularly before (such as Germany), SEPA made things worse as it is more complicated than the old systems – both in regard to technical issues and the workflow of handling the payments. On top of that, is spite of a year-long process of developing a common SEPA-standard, many countries and sometimes even banks have their own exceptions or special rules.

Thus CiviSEPA was build in a way to be as adaptable as possible in order to deal with all those requirements that will most likely differ from organisation to organisation.

## Involved Parties

The following illustration depicts the general workflow of a SEPA-DD:

<a href='../img/description-civi-sepa.png'><img alt='Civi Sapa general workflow' src='../img/description-civi-sepa.png'/></a>

## SEPA Payment-types

You can initiate two types of SEPA Direct Debits:
    -one-off (OOFF).
    -recurring (RCUR).

One-off: is used when the debtor, via the mandate, has only allowed you to perform one single direct debit. After the first direct debit the mandate has expired and you cannot use it anymore. Recurring is used when the debtor mandate allows for regular collections. This type of collection can be split into three sub-types:
Recurring (first): Must be used when you initiate the first direct debit under a new mandate.
Recurring (Recurring): Must be used when you initiate the subsequent direct debits under the mandate.
There is also a third subtype (Recurring final) which can be used when initiating the last DD under a mandate but this is not implemented in CiviSEPA

## Mandates
For each DD you need a mandate. It specifies all relevant such as information on the debtor, the amount of the payment. etc. Each mandate must have a mandate reference which must be unique for each mandate in combination with your SEPA Direct Debit creditor identifier.

## Pre-Notification
Officially, your debtor has to sign a SEPA Direct Debit paper mandate for a particular contract held with you before you may start sending collections to the debtor. This means that you would need to print out the mandate, send it out to the debtor (e.g. your donor) who then has to sign it and send it back to you.
However, there is an ongoing discussion on how to interpret this rule and you will need to find out, how your country's and/or bank's approach is toward the pre-notification (see page Integration of SEPA DD in CiviCRM).

## Due Date & Target Days
One major aspect (and headache) of SEPA is, that you have to specify a due date on which you have to collect the money. Officially, you have to inform the debtor of that due-date and collect the money on that day – not earlier and not later. This means, that if you fail to do so, you are officially not allowed to collect the money anymore.
Not complicated enough? The due date must also be a “Target Day”. Target Days are those days that banks actually do DD. Saturdays and Sundays are excluded, as well as certain holidays. So if your due-date is for example a Saturday (e.g. August 16, 2014), you would have to set it to the following Monday (August 19, 2014).
There are different approaches on how this is handled. In our experience after submitting the SEPA-XML-files to your bank, they will correct the Due Date automatically to the next possible target day if the due date is not a target day. Again, you will have to check with your bank on how they will handle that. Also, if you use a banking software, to upload the xml-file this software may alter the date accordingly...

## Submission Deadlines
Depending on the type of payment, there are certain deadlines for submitting the information to your bank. For the CORE-scheme (which is the standard for DD payments that are not b2b) you need to to submit the information (that is the XML-file) as follows:
    -One-Off & Recurring (First): Due Day – 5 Target Days – 1 Calendar Day (at 22:00 CET/CEST)
    -Recurring (Recurring): Due Day – 2 Target Days – 1 Calendar Day (at 22:00 CET/CEST)
In a nutshell that means that for each collection the number of days you have to submit your data in advance may differ each time depending on weekends, holidays etc. For One-Off & Recurring (First) you will need to send it at least 6 days before but usually more. On an easter-weekend with 4 consecutive non-target-days it will be much more.
Given the fact that for example in Germany it usually only took 1-2 days to initiate and complete a DD payment, you can imagine how happy everyone is about those deadlines. No surprise, another SEPA-scheme emerged called COR1 (shorter cut-off times) which is however not supported by all countries/banks...

## Challenges
The descriptions above only reflect some rough basics and are not comprehensive at all. If you really want to go into the details, google it and prepare for a trip down the rabbit hole. Here we would like to describe in what ways the SEPA-standards continue to pose challenges to implement DD in CiviSEPA.

## Different Ways of submitting SEPA-Files
Each bank has its own ways of how files can/have to be submitted to them. In addition, if you use a banking software this may also change on how the information is transmitted as the software often interprets and the payment information contained in a SEPA-File before sending it to the bank.
Hence, any automatic transmission of SEPA-Files from CiviCRM to a bank must be implemented individually for each customer – or do it manually as described on the page Integration of SEPA DD in CiviCRM.

## Fixed Due Date
As collection needs to take place on a fixed due date and you have deadlines for submitting the xml-files to the bank, there is always a risk of messing up. If you forget to submit the data or something goes wrong, there is no proper way of collecting the money at a later point in time. There is -of course- workarounds for this case, but they are bending the rules. In addition, the complicated target-day-system makes it hard to plan, when a file has to be submitted.
Especially if you submit your information to the bank manually, you need to plan your workflow and responsibilities very well or run the risk to loose payments...

## Country-, Bank- or Software-specific rules/exceptions
As SEPA itself is not complicated enough and each country has it's own DD-History many exceptions from the rules have emerged and will continue to do so. Also each bank may interpret the SEPA-”standards” slightly differently in ways that suit them best. Finally, if you use a banking software that interprets your payment information before submitting it to the bank, be ready to to do a lot of analysing in case of problems (needless to say, that your bank and banking-software-company will blame each other or you for any mistakes/problems...)