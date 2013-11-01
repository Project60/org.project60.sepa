# Sepa DD Data model intricacies

Adding stuff into the Civi data model can be tricky, especially if you're touching Contributions. Here's how things stand for now :

The sequence in which a contribution is created involves

1. a payment processor of type **SDD Payment Processor**
2. a contribution page using that processor
3. a contribution is created by this contribution page.

As a result, the contribution uses a value set for payment_instrument_id. 

> We will create a PP for each **sdd_creditor** . 

Diagram :

	+--------------+
	| SDD_CREDITOR |
	+--------------+
	    |                              +-------------------+     +--------------------+
	    +--- payment_processor_id -->--| PAYMENT_PROCESSOR |--<--| CONTRIBUTION_PAGES |
	                                   +-------------------+     +--------------------+
	                                                                       |
	                                                                       ^
	                                                                       |
	                                                                +--------------+
	                                                                | CONTRIBUTION |
	                                                                +--------------+

When an sdd_creditor is created, it would need to generate the PP.

# Who does what

The different classes are 

* Creditor : represents an SDD Creditor
* Mandate 
* ContributionGroup : links a contribution to the group it is batched in
* TransactionGroup : a group of transactions to be collected together
* File : a set of tx groups to be sent to the bank together in an XML format

# Manual setup

* create an organization ORG to act as an SDD creditor
* create an sdd_creditor using this ORG's id as a creditor_id
* create a payment processor couple (live and test) of type SEPADD, and decide to ignore the test one because it would never work
* set ORG.payment_processor_id to the id of the live one
