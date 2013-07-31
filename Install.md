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
<tr><td>identifier</td><td>Creditor Identifier (calculation differs per country)</td><td>NL51ZZZ405365330000</td></tr>
<tr><td>name</td><td>Name of the creditor</td><td>CiviCoop</td></tr>
<tr><td>address</td><td>Address of the creditor</td><td>Valkseweg 92a Barneveld</td></tr>
<tr><td>Country_id</td><td>ID of the country</td><td>1152 for the Netherland</td></tr>
<tr><td>iban</td><td>Iban number of the creditor</td><td>NL05RABO0181892106</td></tr>
<tr><td>bic</td><td>Bic of the creditor bank</td><td>RABONL2U</td></tr>
<tr><td>Mandate_prefix</td><td>Prefix for the mandate numbers</td><td>SEPA</td></tr>
<tr><td>Payment_instrument_id</td><td>The ID of the payment instrument (9000 for SEPA DD)</td><td>9000</td></tr>
<tr><td>Payment processor id</td><td>ID of the payment processor</td><td>3</td></tr>
<tr><td>Category</td><td>Unknown</td><td></td></tr>
</table>

In the Netherlands the creditor identifier is calculated based on the chamber of commerce number. 

3. Create a recurring contribution form

4. After a user has filled in this contribution form, the mandate is generated (but at the moment no creditor ID is set for this mandate). You have to do this manually go to the database and in the table civicrm_sdd_mandate and fill in the creditor_id

5. When the mandate got returned (signed) you go to the contribution and set the first contribution to active

6. When you point your browser to http://<yourhost>/civicrm/sepa/xml?id=1 you will get the pain.008 file

