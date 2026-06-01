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

{assign var="cid" value=$recur.contact_id}
{assign var="fcid" value=$sepa.first_contribution_id}
{assign var="mid" value=$sepa.id}

<h3>{ts domain="org.project60.sepa" 1=$sepa.id}Sepa Mandate [%1]{/ts}</h3>
<div class="crm-container">
    <div class="crm-block crm-content-block crm-sdd-mandate">
        <table class="crm-info-panel">
            <tr><td class="label">{ts domain="org.project60.sepa"}Reference{/ts}</td><td>{$sepa.reference}</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}Account Holder{/ts}</td><td>{$sepa.account_holder}</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}IBAN{/ts}</td><td>{$sepa.iban}</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}BIC{/ts}</td><td>{$sepa.bic}</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}Status{/ts}</td><td>{$sepa.status}</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}Source{/ts}</td><td>{$sepa.source}</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}Creation date{/ts}</td><td>{$sepa.creation_date}</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}Signature date{/ts}</td><td>{$sepa.date}</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}Validation date{/ts}</td><td>{$sepa.validation_date}</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}1st contribution{/ts}</td><td><a href="{crmURL p='civicrm/contact/view/contribution' q="reset=1&action=view&id=$fcid&cid=$cid"}">{$sepa.first_contribution_id}</a></td></tr>
        </table>
    </div>
    {include file="CRM/common/customDataBlock.tpl" groupID='' customDataType='SepaMandate' cid=false entityID=$sepa.id}
</div>

{if $can_edit_mandate}
<div class="crm-submit-buttons">
    <a href="{crmURL p='civicrm/sepa/xmandate' q="mid=$mid"}" class="button"><span><div class="icon edit-icon ui-icon-pencil"></div>{ts domain="org.project60.sepa"}Mandate Options{/ts}</span></a>
</div>
{/if}

{* add note field *}
<table hidden="1">
{foreach from=$sepa.notes item=note}
<tr name="note_added"><td class="label">{ts domain="org.project60.sepa"}Notes{/ts}</td><td>{$note}</td></tr>
{/foreach}
</table>

<script type="text/javascript">
cj("div.crm-recurcontrib-view-block > table > tbody").append(cj("[name='note_added']"));
// remove
</script>


{literal}
<style>
.crm-recurcontrib-view-block .crm-submit-buttons {display:none;}
</style>
{/literal}
