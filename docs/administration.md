# Administration

This section covers the installation, configuration, and administration of CiviSEPA.

## Installation

### Installing from Extension Directory

CiviSEPA is available in the official CiviCRM Extension Directory and can be installed directly from your CiviCRM administration interface.

**Installation Steps:**
1. Navigate to **Administer → System Settings → Extensions**
2. Click on **"Add New"** tab
3. Search for **"SEPA Direct Debit"**
4. Click **"Download"** next to the CiviSEPA extension
5. Click **"Install"** to activate the extension
6. **Update database schema** when prompted (CiviCRM will show a notification)

**Post-Installation:**
- Verify installation in **Administer → System Settings → Extensions** (should show as "Installed")
- Check for any database update prompts in **Administer → Administration Console → System Status**
- A new menu item **"CiviSEPA Dashboard"** will appear under **Contributions**

### Manual Installation (Development/Testing)

For development or testing purposes, you can install CiviSEPA manually:

```bash
# Download from GitHub releases
wget https://github.com/Project60/org.project60.sepa/releases/download/1.12.1/org.project60.sepa-1.12.1.zip

# Extract to extensions directory
unzip org.project60.sepa-1.12.1.zip -d /path/to/civicrm/ext/

# Enable in CiviCRM
cv ext:enable org.project60.sepa
```

**Important**: Always use official releases from the [GitHub releases page](https://github.com/Project60/org.project60.sepa/releases). Avoid using the master branch in production environments.

## Initial Configuration

### Creditor Setup

Before processing any SEPA payments, you must configure at least one creditor with your organization's banking information.

**Access Configuration:**
Navigate to **Administer → CiviContribute → CiviSEPA Settings**

### Creating Your First Creditor

**Required Information:**
- **Creditor Name**: Your organization's legal name as registered with the bank
- **Creditor ID**: Your SEPA Creditor Identifier (obtained from your bank or national authority)
- **IBAN**: Your organization's bank account number in IBAN format
- **BIC**: Bank Identifier Code (automatically looked up if Little BIC Extension is installed)
- **Currency**: EUR (SEPA is Euro-only)

**Banking Parameters:**
- **PAIN Format**: XML format required by your bank (ask your bank for the correct version)
  - Common formats: pain.008.001.02, pain.008.001.04, pain.008.001.08
- **Cycle Day(s)**: Day(s) of the month when payments are collected (e.g., 15, 28)
- **Notice Days**: Lead time your bank requires for processing (typically 5-14 days)

**Example Configuration:**
```
Creditor Name: "Meine Organisation e.V."
Creditor ID: DE98ZZZ09999999999
IBAN: DE89370400440532013000
BIC: COBADEFFXXX
PAIN Format: pain.008.001.02
Cycle Days: 15
Notice Days (OOFF): 5
Notice Days (RCUR First): 5
Notice Days (RCUR Follow-up): 2
```

#### Format Specific Notes

##### pain.008.001.02 CH-TA LSV+

The creditor ID for this format has to be entered in this form (i.e. the three
values separated by `/`):

```
LSV+-Identifikation/ESR-Teilnehmernummer/ESR-Referenznummernpräfix
```

Please note the [decommissioning](https://www.six-group.com/de/products-services/banking-services/billing-and-payments/direct-debits/lsv-decommissioning.html)
of the corresponding direct debit procedure.

### Batching Settings

Configure how CiviSEPA groups payments for bank submission:

**Horizons** (Look-ahead periods):
- **One-off Horizon**: Days ahead to create one-time payment batches (typically 30-60 days)
- **Recurring Horizon**: Days ahead to create recurring payment batches (typically 30-60 days)

**Grace Periods**:
- **One-off Grace**: Days to keep batch files after due date (typically 3-7 days)
- **Recurring Grace**: Days to keep batch files after due date (typically 3-7 days)

**Collection Schedule**:
- **Cycle Days**: Specific days of the month for collections (e.g., 15th, 30th)
- **Buffer Days**: Additional days before collection for processing
- **Weekend Handling**: Automatic adjustment for bank holidays and weekends

### Multiple Creditors

Organizations with multiple legal entities or bank accounts can configure multiple creditors:

**Adding Additional Creditors:**
1. In CiviSEPA Settings, click **"Add"** next to existing creditors
2. Choose **"Create New"** or **"Copy from Existing"**
3. Configure banking details and batching parameters
4. Assign specific contribution types or campaigns to different creditors

**Use Cases:**
- Multiple bank accounts for different purposes
- Separate legal entities within one CiviCRM installation
- Different collection schedules for different payment types

## Advanced Configuration

### Payment Types and Financial Types

Configure how SEPA payments integrate with CiviCRM's financial structure:

**Financial Types:**
- Map SEPA contributions to appropriate financial types (Donation, Membership Fee, etc.)
- Ensure proper accounting and reporting categorization
- Configure tax deductibility settings per financial type

**Contribution Sources:**
- Set default sources for SEPA mandates (Online, Direct Mail, etc.)
- Track mandate origins for reporting and analysis
- Configure campaign-specific mandate creation

### Integration Setup

#### CiviBanking Integration

For automated payment reconciliation:

1. **Install CiviBanking Extension**
2. **Configure Bank Account**: Match with your SEPA creditor account
3. **Import Rules**: Set up automatic matching of SEPA collections
4. **Statement Processing**: Regular import of bank statements

Benefits:
- Automatic matching of collected payments with CiviCRM contributions
- Exception handling for failed or returned payments
- Comprehensive payment reconciliation workflows

#### Little BIC Extension

For automatic bank code lookup:

1. **Install Extension**: Available in Extension Directory
2. **Automatic BIC Lookup**: Reduces data entry errors when creating mandates
3. **IBAN Validation**: Additional validation of account numbers

#### Form Processor Integration

For external form integration:

**Available Actions:**
- **Find Mandate**: Locate existing mandates by reference or contact
- **Create Mandate**: Create new mandates from external forms
- **Update Mandate**: Modify existing mandate parameters

**Use Cases:**
- Self-service portals for supporters
- Integration with external donation forms
- Membership management interfaces

### Permissions and Security

#### Financial ACLs

CiviSEPA supports CiviCRM's Financial ACL system:

**Configurable Permissions:**
- **View SEPA Mandates**: Control who can see mandate information
- **Create/Edit Mandates**: Restrict mandate creation to authorized users
- **Process Batches**: Control batch processing permissions
- **Download Bank Files**: Restrict access to sensitive banking data

**Setup:**
1. Navigate to **Administer → Users and Permissions → ACLs**
2. Create Financial ACL roles for SEPA operations
3. Assign appropriate users to SEPA roles
4. Test permissions with different user accounts

#### Data Protection

**GDPR Compliance:**
- Mandate data contains sensitive financial information
- Implement appropriate data retention policies
- Ensure secure handling of bank account information
- Configure audit trails for mandate changes

**Security Best Practices:**
- Regular backups of mandate and payment data
- Secure transmission of bank files
- Access logging for sensitive operations
- Regular security updates of CiviCRM and extensions

## Troubleshooting Installation

### Common Issues

**Extension Not Installing:**
- Check CiviCRM version compatibility (5.75+ required)
- Verify sufficient disk space in extensions directory
- Check file permissions on extensions directory

**Database Update Failures:**
- Ensure database user has CREATE/ALTER permissions
- Check for conflicting extensions
- Review CiviCRM error logs for specific messages

**Permission Errors:**
- Verify web server can write to extensions directory
- Check CiviCRM file permissions
- Ensure temp directory is writable

### Getting Help

**Before Contacting Support:**
1. Check CiviCRM System Status for errors
2. Review CiviCRM error logs
3. Test with minimal configuration
4. Document exact error messages

**Support Resources:**
- [GitHub Issues](https://github.com/Project60/org.project60.sepa/issues): Bug reports and feature requests
- **Professional Support**: Contact SYSTOPIA at info@systopia.de

---

*Next: Learn about daily operations in the [Usage](usage.md) section.*
