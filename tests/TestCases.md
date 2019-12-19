# Test Cases

## Mandates

| Case_ID | Status | Configuration | Description   | Process                                |
| ------- |:------:|:-------------:|:-------------:| :------------------------------------- |
| M01     |  PASS  | default       | Simple OOF Mandate  | Create OOFF mandate, batch, check status, close group, check status |
| M02     |  PASS  | default       | Simple RCUR Mandate | Create RCUR mandate, batch, check status, close group, check status |
| M03     |  TODO  | ooff_horizon=31 | OOFF Annual | Create ``n`` OOFF mandates with collection dates spread of the next year, batch & close, verify dates, ``timetravel:+1month``, and repeat. |
| M04     |  TODO  | rcur_horizon=31 | RCUR Annual | Create ``n`` RCUR mandates (different start dates / monthly,quarterly,semi-annually/annually)  with collection dates spread of the next year, batch & close, verify dates and collection rhythm, ``timetravel:+1month``, and repeat. |


## Batching

TODO: more complex batching scenarios

## Terminate Mandates

| Case_ID | Status | Configuration | Description   | Process                                |
| ------- |:------:|:-------------:|:-------------:| :------------------------------------- |
| T01     |  PASS  | ooff_horizon=31  | Terminate OOF Mandate   | Create OOFF mandate with collection date now, batch, assert contribution in group, terminate now, assert mandate terminated, assert contribution *not* in group, batch, assert mandate not being grouped  again |
| T02     |  ERROR  | ooff_horizon=31  | Terminate OOF Mandate   | Create OOFF mandate with collection date now, batch, assert contribution in group, *close group*, terminate now, assert result is error |
| T03     |  TODO  | rcur_horizon=31  | Terminate RCUR Mandate  | Create monthly RCUR mandate with start date now, batch, assert contribution in group, terminate now, assert mandate terminated, assert contribution *not* in group, batch, assert mandate not being grouped again |
| T04     |  TODO  | rcur_horizon=31  | Terminate RCUR Mandate  | Create monthly RCUR mandate with start date now, batch, assert contribution in group, terminate after collection date, assert mandate *not* terminated, but has end date, assert contribution still in group, batch, ``timetravel:+1month``, group, assert mandate not being grouped again |


## Deferred Collection

| Case_ID | Status | Configuration | Description   | Process                                |
| ------- |:------:|:-------------:|:-------------:| :------------------------------------- |
| D01     |  TODO  | exclude_weekends=0 | Defer OOFF   | Create OOFF mandate with collection date on a weekend, batch, assert contribution collected on the weekend |
| D02     |  TODO  | exclude_weekends=1 | Defer OOFF   | Create OOFF mandate with collection date on a weekend, batch, assert contribution *not* collected on the weekend |
| D03     |  TODO  | exclude_weekends=0 | Defer RCUR   | Create RCUR mandate with collection date on a weekend, batch, assert contribution collected on the weekend |
| D04     |  TODO  | exclude_weekends=1 | Defer RCUR   | Create RCUR mandate with collection date on a weekend, batch, assert contribution *not* collected on the weekend |

## Verification

| Case_ID | Status | Configuration | Description   | Process                                |
| ------- |:------:|:-------------:|:-------------:| :------------------------------------- |
| V01     |  TODO  | default       | Validate BICs | Creating mandates with various valid/invalid BICs, assert that with invalid ones the creation causes an error |
| V02     |  TODO  | creditor_type=PSP | Validate BICs | Creating mandates with various valid/invalid BICs, assert that all creations pass |
| V03     |  TODO  | default       | Validate IBANs | Creating mandates with various valid/invalid IBANs, assert that with invalid ones the creation causes an error |
| V04     |  TODO  | creditor_type=PSP | Validate IBANs | Creating mandates with various valid/invalid IBANs, assert that all creations pass |
| V05     |  TODO  | default | Validate Blacklist | Add some example IBANs to the black list, assert that creation with those IBANs fail, while other (valid) IBANs pass |


## Generated Files

| Case_ID | Status | Configuration | Description   | Process                                |
| ------- |:------:|:-------------:|:-------------:| :------------------------------------- |
| F01     |  TODO  | adjust creditor file format  | Verify ``pain.008.001.02`` | Create a couple of mandates, batch, export file, verify valid XML, verify namespace, verify that all references/end2end ids/amounts are in there |
| F02     |  TODO  | adjust creditor file format  | Verify ``pain.008.003.02`` | Create a couple of mandates, batch, export file, verify valid XML, verify namespace, verify that all references/end2end ids/amounts are in there |
| F03     |  TODO  | adjust creditor file format  | Verify ``pain.008.003.02 COR1`` | Create a couple of mandates, batch, export file, verify valid XML, verify namespace, verify that all references/end2end ids/amounts are in there |
| F04     |  TODO  | adjust creditor file format  | Verify ``pain.008.003.02`` | Create a couple of mandates, batch, export file, verify valid XML, verify namespace, verify that all references/end2end ids/amounts are in there |
| F05     |  TODO  | adjust creditor file format  | Verify ``pain.008.003.02 EBICS3`` | Create a couple of mandates, batch, export file, verify valid XML, verify namespace, verify that all references/end2end ids/amounts are in there |
| F06     |  TODO  | adjust creditor file format  | Verify ``pain_008_003_02_rcur_ch`` | Create a couple of mandates, batch, export file, verify valid XML, verify namespace, verify that all references/end2end ids/amounts are in there |
| F07     |  TODO  | adjust creditor file format  | Verify ``pain_008_001_02_CH_03`` | Create a couple of mandates, batch, export file, verify valid XML, verify namespace, verify that all references/end2end ids/amounts are in there |
| F08     |  TODO  | adjust creditor file format  | Verify ``pain_008_003_02_address`` | Create a couple of mandates, batch, export file, verify valid XML, verify namespace, verify that all references/end2end ids/amounts are in there |
| F09     |  TODO  | adjust creditor file format  | Verify ``pain_008_001_02_OTHERID`` | Create a couple of mandates, batch, export file, verify valid XML, verify namespace, verify that all references/end2end ids/amounts are in there |
| F10     |  WAIT  | adjust creditor file format  | Verify ``citibank-pl`` | Create a couple of mandates, batch, export file, verify ??? are in there |


## Reference Generation

| Case_ID | Status | Configuration | Description   | Process                                |
| ------- |:------:|:-------------:|:-------------:| :------------------------------------- |
| R01     |  TODO  | default       | Default OOFF Reference | Create a OOFF mandate, verify reference integrity (SEPA reference allowed charecters) |
| R02     |  TODO  | default       | Default RCUR Reference | Create a RCUR mandate, verify reference integrity (SEPA reference allowed charecters) |
| R03     |  TODO  | default       | Multiple OOFF Reference | Create 101 OOFF mandates with varying financial types and campaigns, verify reference integrity (SEPA reference allowed charecters), detect collisions |
| R04     |  TODO  | default       | Multiple RCUR Reference | Create 101 RCUR mandates with varying financial types and campaigns, verify reference integrity (SEPA reference allowed charecters), detect collisions |
| R05     |  TODO  | change creditor prefix | Default OOFF Reference | Create a OOFF mandate, verify reference integrity (SEPA reference allowed charecters) |
| R06     |  TODO  | change creditor prefix | Default RCUR Reference | Create a RCUR mandate, verify reference integrity (SEPA reference allowed charecters) |

## Retry Collection

LATER

## Next Collection Date

| Case_ID | Status | Configuration | Description   | Process                                |
| ------- |:------:|:-------------:|:-------------:| :------------------------------------- |
| X01     |  TODO  | rcur_horizon=31 | RCUR Annual | Like M04: Create ``n`` RCUR mandates (different start dates / monthly,quarterly,semi-annually/annually)  with collection dates spread of the next year, batch & close, verify dates and collection rhythm, ``timetravel:+1month``, and repeat. Each time check, if next execution date is correct |

## Hooks

These would have to be implemented in a separate extension shipped with the tests. There might be a point in having the extension always installed, and add enable/disable flags in the respective hook implementations. This should perform a lot better than enabling the extension over and over.

| Case_ID | Status | Configuration | Description   | Process                                |
| ------- |:------:|:-------------:|:-------------:| :------------------------------------- |
| H01     |  TODO  | default  | Custom OOFF reference | Like R03 but implement ``create_mandate`` hook for a custom reference. See bundled example module. Verify result |
| H02     |  TODO  | default  | Custom RCUR reference | Like R04 but implement ``create_mandate`` hook for a custom reference. See bundled example module. Verify result |
| H03     |  TODO  | default  | Custom OOFF group reference | Like M01 but implement ``modify_txgroup_reference`` hook for a custom reference. See bundled example module. Verify result |
| H04     |  TODO  | default  | Custom RCUR group reference | Like M02 but implement ``modify_txgroup_reference`` hook for a custom reference. See bundled example module. Verify result |
| H05     |  TODO  | default  | Custom OOFF txmessage | Like F01 but implement ``modify_txmessage`` hook for a custom txmessage. See bundled example module. Verify result in XML |
| H06     |  TODO  | default  | Custom RCUR txmessage | Like F01 but implement ``modify_txmessage`` hook for a custom txmessage. See bundled example module. Verify result in XML |
| H07     |  TODO  | default  | Custom end2end | Like F01 but implement ``modify_endtoendid`` hook for a custom end2end id. See bundled example module. Verify result in XML |
| H08     |  TODO  | default  | Customize installment | Create RCUR mandate, implement ``installment_created`` hook to change something in the contribution, batch, verify that the changes have been applied to the newly created contribution |
| H09     |  TODO  | default  | Customize recurring contribution | Implement ``mend_rcontrib`` hook to change something in the recurring contribution, create RCUR mandate, verify that the changes have been applied to the newly created recurring contribution |
| H10     |  TODO  | default  | Customize collection date | Like M04, but implement ``defer_collection_date`` hook to offset the collection date, batch and verify that the date has been applied |


## Bugs

TODO: bug tests as they're coming in
