Installation instructions for the SEPA DD Extension for CiviCRM
===============================================================

This guide will explain how to install the sepa_dd extension. It assumes you have a up and running civicrm installation and know how to install third-party extensions on civi.

Installation
------------

1. Install the SEPA DD extension 

2. Create a creditor in the database

<table><tr>
<th>Field</th><th>What to fill in</th><th>Example</th>
</tr><tr>
<td>ID</td><td>Levae blank</td><td>1</td></tr>
<tr><td>Creditor_id</td><td>Contact ID of the organisation (probably contact 1)</td><td>1</td></tr>
<tr><td>identifier</td><td>Creditor Identifier (calculation differs per country)</td><td>EU51ZZZ12345</td></tr>
<tr><td>name</td><td>Name of the creditor</td><td>Example</td></tr>
<tr><td>address</td><td>Address of the creditor</td><td>10 downing street</td></tr>
<tr><td>Country_id</td><td>ID of the country</td><td>1152 for the Netherland</td></tr>
<tr><td>iban</td><td>Iban number of the creditor</td><td>GR12930482038349</td></tr>
<tr><td>bic</td><td>Bic of the creditor bank</td><td>YOURBIC</td></tr>
<tr><td>Mandate_prefix</td><td>Prefix for the mandate numbers</td><td>SEPA</td></tr>
<tr><td>Payment processor id</td><td>ID of the payment processor</td><td>3</td></tr>
<tr><td>Category</td><td>Unknown</td><td></td></tr>
</table>

The creditor identifier is provided by your bank. Fair warning, it takes forever to get it. 

3. Create a contribution page, choose the payment processor sepa

4. After a user has filled in this contribution form, the mandate is generated.

5. When the mandate got returned (signed) you go to the recurring contribution and set the mandate as active

6. Go to civicrm/sepa, you have a dashboard with all your groups of transaction (if you click on the name, you get the detail of the transaction in that group)

