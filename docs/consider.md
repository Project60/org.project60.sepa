# Things to consider

We'd like to share our experiences with implementing CiviSEPA for various customers. So, here's our advice:

## Define your Direct Debit Workflow

Make sure that you understood your organisation's workflow and that it fits the features of CiviSEPA. It is particularly important how often per month you would like to collect money and if you want to do one-off and/or recurring contributions. Complexity/work load will grow according to the number of collection days per month. This is particularly due to the target-day-approach and the fact that you have to submit the xml-files manually and on time.

In case you use any banking or accounting software, make sure, it can be integrated in the new process (e.g. that the banking software can interpret SEPA-files).

## Ensure timely submission

As described above, the fixed due dates imply that you might loose money if you do not submit files on time. Make sure that files can always be submitted timely. That implies, submitting them not on the last possible day, in case there are any problems. Also make sure that more than one person is familiar with CiviSEPA and has the necessary permissions to create and submit files (in case the responsible person is absent).

## Talk to your bank

When planning your workflow, also make sure to talk to your bank and ask them about technical details (accepted formats), existing interfaces to upload your data and ANY exceptions or deviations from the standards that they may have.

Also ask them, how received payments will show up on your bank statements (e.g. single payments or bulk) and make sure, that suits your needs.

## Run a full test-cycle

Before going live, run a full test cycle and do a couple of real transactions in different formats (One-Off, Recurring First and Recurring recurring). After receiving them, mark the groups as received and so on. Be particularly keen if you use any additional software to upload the xml-files as they may change information such as the due date.

