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

{if isset($recur)}
<h3>{$contact.display_name} for {$recur.amount} {$recur.currency}/{$recur.frequency_unit}</h3>
{else}
<h3>{$contact.display_name} for {$contribution.total_amount} {$contribution.currency}</h3>
{/if}
{include file="Sepa/Contribute/Page/ContributionRecur.tpl"}
<h3>Pdf content</h3>
{$html}
