If you are in Europe and use CiviCRM to manage recurring contributions, you need this extension.
# Installation
You need to install this extension, then create a payment processor of type sepa and add it to a contribution page your donors can use to register their monthly donations.

It sends them an email with a pdf mandate to sign and return to you (you might want to adjust the template of this file and the text of the email).

Once you receive back this mandate signed, you can edit the recurring contribution and validate the mandate. This will automatically set the date of the first contribution to be debited to the next available slot (cf under for the detail of the batching logic).

See more detailed installation [instruction here](Install.md)

# Batching Logic

## General principles

Batching will occur in a waterfall model, ie. every SDD contribution created should be batched immediately, and the subsequent levels of batching would be triggered automatically. (Post) hooks would be used to trigger that.

## First level of batching

The first level of batching is called a **SEPA transaction group** (TXG).
TXG unites contributions which share
 
* a same type (FRST, RCUR or OOFF)
* a same `sdd_creditor` (identified by a common payment_instrument_id)
* a same collection date (`receive_date` is used for that)

Conditions (a) and (b) are 'hard' conditions, but (c) gives us a bit of leeway. Imagine the following calendar (# indicates a bank work day, *blank* a non-working day) :
   
&nbsp; |  #  |  #  |  #  |  &nbsp;  |  &nbsp;  |  #  |  #  |  #  |  #  |  #  |  &nbsp;  
:----- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: 
TXG collection date | | | | | | * |  | | | | 
SepaTx #1 already in the TXG | | | | | | * | | | | | 
Earlier SepaTx  | | | *| | | | | | | | 
Later SepaTx  | | | | | | | | | * | | 

As a rule, we will assume that

1. we will never speed up collection of a TX (by placing it in a batch with an earlier collection date) by **more than MAXPULL days**
2. we will never delay collection of a TX (by placing it in a batch with a later collection date) by **more than MAXPUSH days**

> The MAXPULL, MAXPUSH parameters will have to be set in a general configuration, most likely per creditor (can be added to its config blob).

### Creating a TXG

The TXG collection date will decide on the actual collection. Typically, the TXG **last possible submission date** is `collection date - delay` (5 for FRST or OOFF, 2 for RCUR) - 1 working day. Hence, the logic for calculating the collection date when creating a TXG is

	submission_date = latest( today, tx.collection_date - delay - 1 )
	effective_collection_date = submission_date + delay + 1

This puts a limit on the earliest tx that we can put in the batch. A tx with an **earlier** intended collection date will be collected later. If we add  a tx with a **later** intended collection date, it will simply be collected earlier. 

Finding an appropriate TXG for a contribution hence means finding a TXG with same type and creditor, and with an effective collection date within [MAXPULL, MAXPUSH] of the contribution's intended collection date.

## Second level of batching

The second and final of batching is called an **SDD file** most commonly referred to as the **XML file**. In reality, every SDD file will correspond to an XML format of it. 

## Closing a TXG

As indicated before, a TXG has a 'send before' date defined by the `earliest collection date - delay - 1`. At that time, the TXG needs to be closed and no more contributions can be added. The SDD file it belongs to will also be closed and marked for sending. 

Any other contribution with a collection date compatible with this TXG will cause another TXG to be created, but this one will have the earliest collection date indicated earlier.
# Sepa DD Data model intricacies

Adding stuff into the Civi data model can be tricky, especially if you're touching Contributions. Here's how things stand for now :

The sequence in which a contribution is created involves

1. a payment processor of type **SDD Payment Processor**
2. a contribution page using that processor
3. a contribution is created by this contribution page.

As a result, the contribution uses a value set for payment_instrument_id. 

> We will create a PP for each **sdd_creditor** and create a payment_instrument for it as well. 

Diagram :

	+--------------+
	| SDD_CREDITOR |
	+--------------+
	    |                              +-------------------+     +-------------------+
	    +--- payment_processor_id -->--| PAYMENT_PROCESSOR |--<--| CONTRIBUTION_PAGE |
	    |                              +-------------------+     +-------------------+
	    |                                                                  |
	    |                                                                  ^
	    |                                                                  |
	    |                                                           +--------------+
	    |                                                           | CONTRIBUTION |
	    |                                                           +--------------+
	    |                                                                  |
	    |                                                                  v
	    |                                                                  |
	    +--- payment_instrument_id (option value) -------------------------+

When an sdd_creditor is created, it would need to generate the PP and the PI.


# Manual setup

* create an organization ORG to act as an SDD creditor
* create an sdd_creditor using this ORG's id as a creditor_id
* create a payment processor couple (live and test) of type SEPADD, and decide to ignore the test one because it would never work
* set ORG.payment_processor_id to the id of the live one
* define a payment_instrument_id for this PP (entry in the option_group 'payment_instrument')
* set it in the PP.payment_type
