This page will explain the CiviSEPA status model.

# Single (One-Off) Collection (``OOFF``)

A one-off mandate consists of two entities:
1. The CiviSEPA mandate (``civicrm_sdd_mandate``)
2. A CiviCRM contribution (``civicrm_contribution``)

## Status 1: new
* Mandate: ``status=OOFF``
* Contribution: ``contribution_status_id=Pending``

## Status 2: submitted to bank
* Mandate: ``status=SENT``
* Contribution: ``contribution_status_id=In Progress``

## Status 3: money received
* Mandate: ``status=SENT``
* Contribution: ``contribution_status_id=Completed`` 
* *remark*: from here on, other statuses can also be used, e.g. ``Cancelled``


# Recurring Collection (``RCUR``)

A recurring mandate consists of three entities:
1. The CiviSEPA mandate (``civicrm_sdd_mandate``)
2. A CiviCRM recurring contribution (``civicrm_contribution_recur``)
3. A number of CiviCRM contributions (``civicrm_contribution``)

## Status 1: new
* Mandate: ``status=FRST``
* RecurringContribution: ``contribution_status_id=Pending``
* Contribution: ``contribution_status_id=Pending``, ``payment_instrument_id=FRST`` (or custom PIs)
* Remark: once the first contribution is submitted, it will be stored in the ``first_contribution_id`` field of the mandate.

## Status 2: running
* Mandate: ``status=RCUR``
* RecurringContribution: ``contribution_status_id=In Progress``
* Contributions: ``contribution_status_id`` one of ``Pending``/``In Progress``/``Completed``/``Cancelled``, ``payment_instrument_id=RCUR`` (or custom PIs)

## Status 3: completed/ended
* Mandate: ``status=COMPLETE``
* RecurringContribution: ``contribution_status_id=Completed``
* Contributions: ``contribution_status_id`` one of  ``Pending``/``In Progress``/``Completed``/``Cancelled``

# Additional statuses

The mandate status ``INVALID`` can be used to track invalid mandates.

The mandate status ``INIT`` can be used for mandates, that need to be activated first, e.g. via a validation by the bank.
