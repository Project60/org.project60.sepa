# Introduction

CiviSEPA is a comprehensive CiviCRM extension that enables SEPA-compliant direct debit processing for European organizations. Whether you're managing membership fees, processing donations, or handling recurring contributions, CiviSEPA provides the tools you need for efficient, automated payment collection.

## What is SEPA?

SEPA (Single Euro Payments Area) is a European Union regulation that standardizes direct debit payments across EU member states. It allows organizations to collect payments directly from supporters' bank accounts through a mutual agreement called a "mandate."

**Key SEPA concepts:**
- **Mandate**: Written authorization from the debtor allowing you to collect payments
- **Direct Debit**: Automatic collection of funds from a bank account
- **SEPA compliance**: Adherence to EU regulations for payment processing

## What CiviSEPA Does

CiviSEPA transforms CiviCRM into a powerful SEPA direct debit management system, handling the complete payment lifecycle from mandate creation to bank reconciliation.

### Core Functionality

**Mandate Management**
- Create and manage SEPA mandates for contacts
- Support for both one-time (OOFF) and recurring (RCUR) payments
- Automatic mandate reference generation with customization options
- Full mandate lifecycle tracking from active to completed status

**Payment Processing**
- Automated payment batching based on collection dates
- Smart grouping by payment type (first, recurring, one-off)
- Bank-ready XML file generation in multiple PAIN formats
- Comprehensive payment status tracking and reconciliation

**Dashboard & Monitoring**
- Real-time overview of all payment groups and their status
- Visual indicators for urgent actions and deadlines
- Detailed reporting on mandates, collections, and failures
- Batch management with manual and automated processing options

### Key Benefits

**For Organizations**
- **Reduced administrative overhead**: Automated payment processing eliminates manual collection tasks
- **Improved cash flow**: Predictable, recurring revenue through automated collections
- **SEPA compliance**: Built-in compliance with EU direct debit regulations
- **Comprehensive reporting**: Detailed insights into payment patterns and failures

**For Supporters**
- **Convenient giving**: Set-and-forget recurring donations or membership payments
- **Flexible payment options**: Support for various frequencies and amounts
- **Transparent process**: Clear mandate terms and payment notifications

## Use Cases

### Membership Organizations
- **Recurring membership fees**: Automated annual or monthly membership renewals
- **Tiered memberships**: Different payment amounts based on membership levels
- **Grace period handling**: Automatic management of failed payments and member status

### Fundraising
- **Recurring donations**: Monthly or quarterly donor programs
- **Campaign-specific giving**: Dedicated mandates for specific fundraising campaigns
- **Major gift processing**: Secure handling of large one-time donations

## Integration Ecosystem

CiviSEPA works seamlessly with the broader CiviCRM ecosystem:

**Core CiviCRM Integration**
- Full integration with Contributions, Memberships, and Events
- Support for CiviCRM's financial types and accounting features
- Compatible with CiviCRM's reporting and dashboard systems

**Essential Extensions**
- **CiviBanking**: Automated bank statement import and payment matching
- **Little BIC Extension**: Automatic bank code lookup for mandate creation
- **Form Processor**: External form integration for mandate updates

**Specialized Integrations**
- **Twingle API**: Seamless integration with Twingle donation forms
- **sepacustom**: Framework for organization-specific customizations

**Payment Processing**
- **SEPA Payment Processor** (separate extension): Online payment processing for events and contributions
- **PSP-SEPA**: Integration with external payment service providers

## Requirements

### Technical Requirements
- **CiviCRM**: Version 5.75 or higher
- **PHP**: Compatible with CiviCRM's PHP requirements
- **Database**: MySQL/MariaDB with automatic schema updates
- **Server**: Standard CiviCRM hosting requirements

### SEPA Requirements
- **Legal entity**: Must be a legitimate organization authorized to collect payments
- **Bank account**: European bank account capable of SEPA direct debit processing
- **Creditor identifier**: SEPA creditor ID from your bank or national authority

## Getting Started

The typical CiviSEPA implementation follows these steps:

1. **Installation**: Install CiviSEPA from the CiviCRM Extension Directory
2. **Configuration**: Set up creditor information and banking parameters
3. **Testing**: Create test mandates and validate the complete workflow
4. **Integration**: Connect with banking systems and complementary extensions
5. **Training**: Educate staff on daily operations and troubleshooting
6. **Go-live**: Begin processing real payments with proper monitoring

## Support and Development

**Maintenance**: CiviSEPA is actively maintained by SYSTOPIA with contributions from the broader CiviCRM community.

**Professional Support**: For implementation assistance, customization, or enterprise support, contact SYSTOPIA at info@systopia.de.

**Community Resources**:
- [GitHub Repository](https://github.com/Project60/org.project60.sepa): Source code and issue tracking
- [Extension Directory](https://civicrm.org/extensions/civisepa-sepa-direct-debit-extension): Official extension listing

**Contributing**: CiviSEPA welcomes community contributions through GitHub pull requests, documentation improvements, and testing feedback.

---

*Ready to get started? Continue to the [Administration](administration.md) section for installation and configuration instructions.*