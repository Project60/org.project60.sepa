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


<div class="crm-section">
  <div class="label">{$form.creditor_id.label}</div>
  <div class="content">{$form.creditor_id.html}</div>
  <div class="clear"></div>
</div>


<div class="crm-section">
  <div class="label">{$form.type.label}</div>
  <div class="content">{$form.type.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.financial_type_id.label}</div>
  <div class="content">{$form.financial_type_id.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.campaign_id.label}</div>
  <div class="content">{$form.campaign_id.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.reference.label}</div>
  <div class="content">{$form.reference.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.source.label}</div>
  <div class="content">{$form.source.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
