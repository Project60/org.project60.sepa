# Configuration (docs merge)

# Step 0: Prepare before implementing CiviSEPA
There is some general advice to consider when implementing the CiviSEPA extension for your organization // TODO: Link to?
Also notice the Pros and Limitations in the current state //TODO link to?

# Step 1: Download & Install the extension
The CiviSEPA extension is not published in the public extension directory of CiviCRM yet. However, a stable version has been released and can be downloaded/cloned from the GitHub repository: https://github.com/Project60/org.project60.sepa/releases

Put the extension into the extensions folder of your CiviCRM site (as configured under "Administer | System Settings | Directories") and install it like any extension through "Administer | Customize Data and Screens | Manage Extensions".

# Step 2: Create a creditor
A creditor represents information about the bank account that will receive the SEPA drafts. You can configure these in the "CiviSEPA Settings": `.../civicrm/admin/setting/sepa`

# Step 3: Understand the SEPA workflow with CiviSEPA
The intended workflow with the CiviSEPA extension is described here: Integration of SEPA DD in CiviCRM //TODO Link

# Step 4: Adapt features with your own custom extension
If you need customised mandate references, exclude certain collection dates, or add a custom transaction message to the collection, you want to create a sepa customization extension implementing the following hooks:
* `civicrm_create_mandate` - to generate custom mandate reference numbers
* `civicrm_defer_collection_date` - to avoid days when your bank won't accept collections. (Version 1.2+ can skip weekends w/o this hook)
* `civicrm_modify_txmessage` - to customize the transaction message (Version 1.2+ can set a generic message w/o this hook)

An example implementation is available on GitHub for your convenience: [org.project60.sepacustom](https://github.com/Project60/org.project60.sepa/tree/master/org.project60.sepacustom)
# Step 5: Create Contribution Pages for SEPA
CiviSEPA provides a payment processor that can be used in contribution pages.

First, add a new CiviSEPA payment processor in the Payment Processors administration section ("Administer | System Settings | Payment Processors"). (The "Name" you set here is also used in public forms, so use something natural and generic.)

You can then select this payment processor like any other payment processor for your contribution pages.

# Step 6: Import existing SEPA mandates
Is it possible to import SEPA mandates, accounts etc. that have been processed outside CiviCRM previously and should now be managed using CiviSEPA? //TODO Fabian?