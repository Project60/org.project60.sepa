{*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 TTTP                           |
| Author: X+                                             |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+-------------------------------------------------------*}

<table>
<thead><tr>
<th>recurring</th>
<th>contact</th>
<th>start</th>
<th>create</th>
<th>amount</th>
<th>#contributions</th>
<th>total</th>
</tr></thead>
<tbody>
{foreach from=$contributions.values item="contribution"}
<tr>
<td>{$contribution.id}</td>
<td>{$contribution.contact_id}</td>
<td>{$contribution.start_date}</td>
<td>{$contribution.create_date}</td>
<td>{$contribution.amount}</td>
<td>{$contribution.nb_contribution}</td>
<td>{$contribution.total}</td>
</tr>
{/foreach}
</tbody>
</table>
{*
*}
