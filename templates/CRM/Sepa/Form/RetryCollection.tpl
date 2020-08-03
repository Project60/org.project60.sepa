{*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 SYSTOPIA                       |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+-------------------------------------------------------*}


<h3>{ts}Select the transaction groups you want to re-collect.{/ts}</h3>

<div class="crm-section">
  <div class="label">{$form.date_range.label}</div>
  <div class="content">{$form.date_range.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.creditor_list.label}</div>
  <div class="content">{$form.creditor_list.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.txgroup_list.label}</div>
  <div class="content">{$form.txgroup_list.html}</div>
  <div class="clear"></div>
</div>

<h3>{ts}Add some filters{/ts}</h3>

<div class="crm-section">
  <div class="label">{$form.cancel_reason_list.label}</div>
  <div class="content">{$form.cancel_reason_list.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.contribution_status_list.label}</div>
  <div class="content">{$form.contribution_status_list.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.frequencies.label}</div>
  <div class="content">{$form.frequencies.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.amount_min.label}</div>
  <div class="content">{$form.amount_min.html}&nbsp;-&nbsp;{$form.amount_max.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.mandate_status.label}</div>
  <div class="content">{$form.mandate_status.html}</div>
  <div class="clear"></div>
</div>


<h3>{ts}Collection Options{/ts}</h3>

<div class="crm-section">
  <div class="label">{$form.collection_date.label}</div>
  <div class="content">{include file="CRM/common/jcalendar.tpl" elementName=collection_date}</div>
  <div class="clear"></div>
</div>

<h3>{ts}Preview{/ts}</h3>

<p>
<div id="separetry-text" style="text-align: center; font-size: 1.6em;"></div>
<div id="separetry-busy" align="center" style="display: none;"><img src="{$config->resourceBase}i/loading-overlay.gif" width="32"/></div>
</p>

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
