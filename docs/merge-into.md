** (docs merge) This extension integrates SEPA (Single Euro Payments Area) into CiviCRM. It's the perfect way to 
manage direct debit payments in the whole EURO zone without having to resort to an expensive bureau.**


## Status
In the course of the development the project unfortunately split into three branches, mostly due to some very tight deadlines with different requirements. Since early July 2014 there is a joint version, merging the branches of Xavier Dutoit (tttp) and Björn Endres (SYSTOPIA). Both versions have, individually, been used in production environments since the beginning of 2014.

Maintainer: Björn Endres (endres (at) systopia.de) and Xavier Dutoit (xavier (at) tttp.eu)
- Repository: https://github.com/Project60/org.project60.sepa
- Releases: https://github.com/Project60/sepa_dd/releases
- Planned releases: https://github.com/Project60/org.project60.sepa/milestones
- Bugs and issues: https://github.com/Project60/org.project60.sepa/issues


## usecases and further infos
- **Nicely integrated with [CiviBanking](https://docs.civicrm.org/banking/en/latest/)**
  CiviSEPA is nicely integrated with the extension [CiviBanking](https://docs.civicrm.org/banking/en/latest/) which offers a very sophisticated framework for importing bank account statements and matching the enclosed payments with contributions and contacts in CiviCRM.

### Limitations

- **Manual Submission of XML-Files**
  So far no automatic transmission to a bank has been implemented. If you only do DD collections a couple of times per month, this should not be too much of a problem. However, if you want to do DD it more often and/or flexible, manual transmission may be time-consuming and some work would need to be put into automatisation.

- **Pre-Notification**
  The function for pre-notification is only designed to create a PDF-file of mandate that you can print out and send to your constituents to let them sign it. There are no further features such as sending it out via E-Mail. Also, if you use pre-notification you will have to adapt your workflow and some settings to ensure that mandates are inactive as long as you did not receive the signed version of the mandate from your constituent.
  So far, all our users don't do formal pre-notification at all or are fine with the current basic functionality. If you have a workflow that is not covered by the current implementation, just talk to us.


## More Information
- CiviSEPA Setup Instructions: https://wiki.civicrm.org/confluence/display/CRM/CiviSEPA+Setup+Instructions
- Introduction to SEPA in general: SEPA-Basics https://wiki.civicrm.org/confluence/display/CRM/SEPA-Basics
- Integration of SEPA DD in CiviCRM https://wiki.civicrm.org/confluence/display/CRM/Integration+of+SEPA+DD+in+CiviCRM
- Things to consider when implementing CiviSEPA https://wiki.civicrm.org/confluence/display/CRM/Things+to+consider+when+implementing+CiviSEPA
- Technical information Page https://wiki.civicrm.org/confluence/display/CRM/CiviSEPA+Technical+Specifications
- Use [CiviBanking](https://docs.civicrm.org/banking/en/latest/) to "close the loop"