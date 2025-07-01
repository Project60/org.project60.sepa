# Usage

This section covers the day-to-day operations of CiviSEPA. Whether you're new to SEPA direct debits or an experienced user, this guide will walk you through everything from creating your first mandate to processing payments and handling the occasional hiccup.

## Dashboard Overview

Think of the CiviSEPA Dashboard as your mission control center. This is where you'll spend most of your time monitoring payments, checking status, and making sure everything runs smoothly.

### Getting to Your Dashboard

You'll find your dashboard under **Contributions → CiviSEPA Dashboard**. Once you're there, you'll see a clean overview of all your payment activities organized into groups based on when they're due for collection.

### Understanding What You See

The dashboard shows you payment groups, which are essentially batches of payments that will be collected on the same date. You'll notice that CiviSEPA automatically organizes these for you - no need to manually sort hundreds of individual payments.

Each group displays some key information at a glance. The group name might look cryptic at first, but it actually tells you everything you need to know: the collection date, whether it's a one-off payment or recurring, and sometimes even which creditor it belongs to if you're managing multiple bank accounts.

The color coding makes it easy to prioritize your work. Green groups are humming along nicely with no immediate action needed. Yellow groups are approaching their deadlines - you'll want to review these soon. Red groups need your attention right now, either because they're overdue or there's an issue that needs resolving. Gray groups are finished business - payments that have already been collected or processed.

### Taking Action from the Dashboard

The dashboard isn't just for looking - it's your primary tool for managing the payment flow. You'll see several action buttons that let you generate new payment batches, finalize groups for bank submission, and confirm when payments have been successfully collected.

When you click "Update One-Off" or "Update Recurring," CiviSEPA looks ahead based on your configuration settings and creates new payment groups for any mandates that are due for collection. This is typically something you'll do weekly or monthly, depending on your organization's rhythm.

The "Close Group" action is your way of saying "this batch is ready to go to the bank." Once closed, the system generates the XML file your bank needs and locks the group to prevent accidental changes.

## Creating and Managing SEPA Mandates

A SEPA mandate is essentially a permission slip from your supporter, allowing you to collect payments directly from their bank account. Think of it as a more sophisticated version of a standing order, but with better legal protections for both parties.

### Creating Your First Mandate

In newer versions (1.2.5+), you'll find a dedicated "SEPA Mandates" tab on contact records. In older versions, use the "Record SEPA Contribution" button under the Contributions tab.

The process is straightforward: select the contact, choose the financial type (Donation, Membership Fee, etc.), enter the payment amount and frequency, then add the banking details.

### Required Information

You'll need the supporter's IBAN and BIC (automatically looked up if you have the Little BIC Extension installed), along with basic payment details like amount and financial type. CiviSEPA generates unique mandate references automatically, though you can customize these if needed.

### Understanding Mandate Types

SEPA offers two main types of payment arrangements, and choosing the right one depends on what you're trying to accomplish.

One-off mandates are perfect for single payments: emergency appeals, event registrations, or one-time donations. Once the payment is collected, the mandate expires automatically. This gives supporters confidence that you can't accidentally charge them again.

Recurring mandates are ideal for memberships, regular donations, or any ongoing financial relationship. The supporter authorizes you to collect payments at agreed intervals - monthly, quarterly, annually, or whatever schedule works for both of you.

### The Mandate Lifecycle

Understanding how mandates progress through their lifecycle helps you manage them effectively and troubleshoot issues when they arise.

When you first create a mandate, it starts in "Created" status. At this point, it's ready for collection but hasn't been used yet. Once you include it in a payment batch and submit to your bank, it becomes "Active." This is where most mandates spend their working life.

For one-off mandates, "Completed" status means the single payment has been successfully collected and the mandate has served its purpose. For recurring mandates, completion usually means you've received all the payments you expected, or the supporter has cancelled their authorization.

Sometimes mandates end up "Cancelled" - this might happen if a supporter withdraws consent, if there are persistent payment failures, or if circumstances change. Cancelled mandates can't be used for future collections, but they preserve the historical record of what happened.

### Managing Your Mandate Portfolio

As your organization grows, you'll accumulate many mandates, and keeping track of them becomes important for both operational and compliance reasons.

The mandate overview gives you a bird's-eye view of all active payment arrangements. You can filter and search to find specific mandates, check payment histories, and spot patterns that might need attention.

Regular review of your mandates helps catch issues early. Look for mandates that are consistently failing - these might need updated banking information or a conversation with the supporter. Watch for mandates approaching their end dates, especially if you want to renew the relationship.

## Managing Membership Fee Collections

One of CiviSEPA's most powerful features is its integration with CiviCRM's membership system. This allows you to seamlessly connect membership records with payment arrangements, creating a smooth experience for both your members and your administrative team.

### Setting Up Membership Payments

When you create a membership with SEPA payment, you're connecting the membership record with a payment arrangement. CiviSEPA automatically synchronizes membership status with payment status - successful collections maintain active memberships, while failed payments can trigger grace periods or status changes.

The process starts with creating the membership as usual, then choosing SEPA Direct Debit as the payment method. You can link to an existing mandate or create a new one specifically for this membership.

**Important Configuration Notes:**
- **CiviCRM Scheduled Jobs**: The membership processing relies on CiviCRM's scheduled jobs running correctly. If memberships aren't updating properly, check that your cron jobs are configured and running.
- **Mandate Termination**: You can configure whether mandates should automatically terminate when memberships end. This setting is found in the CiviSEPA configuration and affects how cancelled or expired memberships are handled.

### Handling Different Membership Scenarios

Real-world membership organizations have complex needs, and CiviSEPA is designed to handle various scenarios you'll encounter.

Annual memberships are straightforward: one payment per year, usually around renewal time. You set up a recurring mandate with annual frequency, and the system takes care of the rest. Members get the convenience of automatic renewal, and you get predictable cash flow.

Monthly memberships require a bit more attention to cash flow management, but they can be great for member retention and budgeting. The key is setting up clear communication about when payments will be collected and what happens if a payment fails.

Family memberships often involve multiple contacts but a single payment arrangement. You can handle this by creating the mandate under the primary contact and linking the family membership to that payment record.

Corporate or organizational memberships might have higher amounts and different billing cycles. These often work well with quarterly payments, balancing cash flow management with administrative efficiency.

### Dealing with Membership Changes

Life happens, and your members' circumstances change. CiviSEPA provides flexibility to handle upgrades, downgrades, and other modifications without starting from scratch.

When a member wants to upgrade their membership level, you can modify the existing mandate to reflect the new payment amount. The system handles the transition smoothly, and you maintain the payment history and relationship continuity.

Downgrades work similarly, though you might want to confirm the change with the member before reducing their payment amount. Some organizations handle this by creating a new mandate and properly closing the old one, which provides a clear audit trail.

Temporary membership suspensions can be handled by pausing the mandate without cancelling it entirely. This is useful for members going through temporary financial difficulties or taking extended travel breaks.

## Processing Payments

Payment processing follows a logical flow: CiviSEPA groups individual payments into batches based on collection dates, you review and approve these batches, then generate bank-ready XML files for submission.

### How Batching Works

CiviSEPA groups individual payments into batches based on collection date and payment type. This isn't just for convenience - banks prefer to receive well-organized files rather than random individual payments, and the batching process includes important validation steps.

The system looks ahead based on your horizon settings (configured in the administration section) and identifies mandates that are due for collection. It then groups these by collection date and creates separate batches for different payment types: one-off payments, first-time recurring payments, and subsequent recurring payments.

This automatic grouping saves you tremendous time and reduces errors. Instead of manually organizing hundreds of individual payments, you work with a manageable number of batches that are ready for processing.

### Understanding Payment Types

SEPA distinguishes between different types of payments, and each has specific requirements and processing rules.

One-off payments (OOFF) are the simplest: single payments with no future collections expected. These might be donations, event fees, or one-time purchases. Because there's no ongoing relationship, the regulatory requirements are somewhat relaxed.

First-time recurring payments (FRST) mark the beginning of an ongoing payment relationship. These have special significance because they establish the mandate for future collections. Banks often apply additional scrutiny to FRST payments, and supporters need to be properly notified about what to expect.

Subsequent recurring payments (RCUR) are the bread and butter of ongoing relationships. These typically process more smoothly because the payment relationship is established and both the bank and supporter know what to expect.

### Generating and Managing Bank Files

Once you've reviewed and approved a payment batch, the next step is creating the XML file that your bank needs to process the payments. This file contains all the payment instructions formatted according to SEPA standards.

CiviSEPA generates files in the PAIN format (Payment Initiation). The specific version depends on your bank's requirements - common formats include PAIN.008.001.02, PAIN.008.001.04, and PAIN.008.001.08. Your bank will tell you which format they need during your initial SEPA setup.

The file generation process includes extensive validation to catch errors before they reach the bank. The system checks IBAN formats, validates payment amounts, ensures mandate references are correct, and verifies that all required information is present.

Once generated, these files contain sensitive financial information and should be handled securely. Download them directly to a secure location, submit them through your bank's secure portal, and follow your organization's data protection procedures.

### Reconciliation: Confirming Successful Collections

After submitting payment files to your bank, you need to confirm when payments have been successfully collected. This reconciliation process updates your CiviCRM records and completes the payment cycle.

The manual approach involves checking your bank statements or online banking portal, then returning to the CiviSEPA dashboard to mark batches as "received." This works well for smaller organizations or when you're starting with SEPA.

For organizations processing many payments, the CiviBanking extension can automate much of this reconciliation. It imports bank statements, matches payments to your CiviCRM records, and updates statuses automatically. This saves significant time and reduces the chance of overlooking failed payments.

### When Things Go Wrong: Handling Payment Failures

Not every payment attempt succeeds, and CiviSEPA provides tools to handle various failure scenarios gracefully.

Common reasons for payment failures include insufficient funds in the supporter's account, incorrect banking information, expired mandates, or technical issues at the bank level. Each type of failure might require a different response.

For temporary issues like insufficient funds, you might want to retry the payment after a few days or weeks. CiviSEPA can help manage this retry process, tracking how many attempts have been made and spacing them appropriately.

Permanent issues like invalid account details require direct contact with the supporter to update their information. The system can help you identify these patterns and prioritize your follow-up efforts.

Some organizations set up automated retry schedules, while others prefer to review each failure manually. The right approach depends on your organization's size, resources, and relationship with your supporters.

## Specialized Scenarios and Advanced Features

### Working with Multiple Creditors

Larger organizations sometimes need to manage multiple SEPA creditor arrangements. This might be because you have multiple legal entities, separate bank accounts for different purposes, or international operations across different countries.

CiviSEPA handles multi-creditor scenarios elegantly. You can configure different creditors with their own banking details, batch processing schedules, and even different PAIN formats if your banks have varying requirements.

When creating mandates, you choose which creditor the payment should be associated with. This affects which batch the payment joins and which bank file it appears in. The system keeps everything organized so you never accidentally mix payments between different banking relationships.

### Campaign-Specific Payment Processing

Sometimes you want to track payments related to specific campaigns or appeals separately from your regular operations. CiviSEPA integrates with CiviCRM's campaign functionality to support this need.

You can create mandates that are linked to specific campaigns, making it easy to track financial performance and ensure that restricted donations are properly accounted for. The reporting tools can then show you exactly how much you've collected for each campaign and when those payments were received.

Campaign-specific processing also allows for customized transaction messages, so supporters see relevant information on their bank statements that connects their payment to the specific cause they're supporting.

### Integration with Event Registration

For event payments, install the separate **SEPA Payment Processor** extension (org.project60.sepapp) which handles online SEPA payments during event registration. See that extension's documentation for setup details.

## Troubleshooting and Problem Resolution

Even with the most careful setup, you'll occasionally encounter issues that need attention. The key to effective troubleshooting is understanding what to look for and knowing where to find the information you need.

### Diagnosing Batch Processing Issues

When payments don't appear in batches as expected, start by checking the mandate status and collection dates. Mandates must be in "Active" status to be included in batches, and the collection date must fall within your configured horizon period.

If mandates seem correct but batches aren't generating, review your creditor configuration settings. The horizon and notice period settings control how far ahead the system looks for due payments and how much lead time your bank requires.

Sometimes timing issues occur when you're working across month boundaries or during holiday periods. The system respects banking holidays and weekend rules, which might push collection dates later than you expect.

### Resolving Payment Failures

When individual payments fail, the first step is understanding why. Bank return codes provide specific information about failure reasons, though they're often expressed in technical language that requires interpretation.

Common failure codes include "Account does not exist," "Insufficient funds," "Mandate cancelled by debtor," and "Technical error." Each suggests a different course of action, from updating account information to contacting the supporter about their payment preferences.

Document any patterns you notice in payment failures. If specific banks frequently reject payments, or if failures cluster around certain dates, this information can help you refine your processing schedule or identify systemic issues.

### Getting Help

For technical issues beyond basic troubleshooting, check the [GitHub Issues](https://github.com/Project60/org.project60.sepa/issues) for known problems and solutions. For professional support with complex setups or urgent issues, contact SYSTOPIA at info@systopia.de.