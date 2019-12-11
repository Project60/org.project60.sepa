# Test Cases

## Mandates

| Case_ID | Status | Configuration | Description   | Process                                |
| ------- |:------:|:-------------:|:-------------:| :------------------------------------- |
| M01     |  PASS? | default       | Simple OOF Mandate  | Create OOFF mandate, batch, check status, close group, check status |
| M02     |  PASS? | default       | Simple RCUR Mandate | Create RCUR mandate, batch, check status, close group, check status |
| M03     |  TODO  | ooff_horizon=31 | OOFF Annual | Create ``n`` OOFF mandates with collection dates spread of the next year, batch & close, verify dates, ``timetravel:+1month``, and repeat. |
| M04     |  TODO  | rcur_horizon=31 | RCUR Annual | Create ``n`` RCUR mandates (different start dates / monthly,quarterly,semi-annually/annually)  with collection dates spread of the next year, batch & close, verify dates and collection rhythm, ``timetravel:+1month``, and repeat. |
 
 
## Batching

TODO

## Terminate Mandates

| Case_ID | Status | Configuration | Description   | Process                                |
| ------- |:------:|:-------------:|:-------------:| :------------------------------------- |
| T01     |  TODO  | ooff_horizon=31  | Terminate OOF Mandate   | Create OOFF mandate with collection date now, batch, assert contribution in group, terminate now, assert mandate terminated, assert contribution *not* in group, batch, assert mandate not being grouped  again |
| T02     |  TODO  | ooff_horizon=31  | Terminate OOF Mandate   | Create OOFF mandate with collection date now, batch, assert contribution in group, *close group*, terminate now, assert result is error |
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

TODO

## Reference Generation

TODO

## Retry Collection

TODO

## Next Collection Date

TODO

## Hooks

TODO

## Bugs

TODO