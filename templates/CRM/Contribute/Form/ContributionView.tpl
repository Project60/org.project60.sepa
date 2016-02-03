{*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*}
<div class="crm-block crm-content-block crm-contribution-view-form-block">
<h3>{ts domain="org.project60.sepa"}View Contribution{/ts}</h3>
<div class="action-link">
    <div class="crm-submit-buttons">
    {if call_user_func(array('CRM_Core_Permission','check'), 'edit contributions')}
       {assign var='urlParams' value="reset=1&id=$id&cid=$contact_id&action=update&context=$context"}
       {if ( $context eq 'fulltext' || $context eq 'search' ) && $searchKey}
       {assign var='urlParams' value="reset=1&id=$id&cid=$contact_id&action=update&context=$context&key=$searchKey"}
       {/if}
       <a class="button" href="{crmURL p='civicrm/contact/view/contribution' q=$urlParams}" accesskey="e"><span><div class="icon edit-icon ui-icon-pencil"></div>{ts domain="org.project60.sepa"}Edit{/ts}</span></a>
    {/if}
    {if call_user_func(array('CRM_Core_Permission','check'), 'delete in CiviContribute')}
       {assign var='urlParams' value="reset=1&id=$id&cid=$contact_id&action=delete&context=$context"}
       {if ( $context eq 'fulltext' || $context eq 'search' ) && $searchKey}
       {assign var='urlParams' value="reset=1&id=$id&cid=$contact_id&action=delete&context=$context&key=$searchKey"}
       {/if}
       <a class="button" href="{crmURL p='civicrm/contact/view/contribution' q=$urlParams}"><span><div class="icon delete-icon ui-icon-trash"></div>{ts domain="org.project60.sepa"}Delete{/ts}</span></a>
    {/if}
    {include file="CRM/common/formButtons.tpl" location="top"}
    </div>
</div>
<table class="crm-info-panel">
    <tr>
        <td class="label">{ts domain="org.project60.sepa"}From{/ts}</td>
        <td class="bold">{$displayName}</td>
    </tr>
    <tr>
        <td class="label">{ts domain="org.project60.sepa"}Financial Type{/ts}</td>
    	<td>{$financial_type}{if $is_test} {ts domain="org.project60.sepa"}(test){/ts} {/if}</td>
    </tr>
    {if $lineItem}
    <tr>
        <td class="label">{ts domain="org.project60.sepa"}Contribution Amount{/ts}</td>
        <td>{include file="CRM/Price/Page/LineItem.tpl" context="Contribution"}
      {if $contribution_recur_id}
              <strong><a href="{crmURL p="civicrm/contact/view/contributionrecur" q="reset=1&id=$contribution_recur_id&cid=$contactId&context=contribution"}">{ts domain="org.project60.sepa"}Recurring Contribution{/ts}</a></strong> <br/>
              {ts domain="org.project60.sepa"}Installments{/ts}: {if $recur_installments}{$recur_installments}{else}{ts domain="org.project60.sepa"}(ongoing){/ts}{/if}, {ts domain="org.project60.sepa"}Interval{/ts}: {$recur_frequency_interval} {$recur_frequency_unit}(s)
            {/if}</td>
        </tr>
    {else}
    <tr>
        <td class="label">{ts domain="org.project60.sepa"}Total Amount{/ts}</td>
        <td><strong>{$total_amount|crmMoney:$currency}</strong>&nbsp;
            {if $contribution_recur_id}
              <strong><a href="{crmURL p="civicrm/contact/view/contributionrecur" q="reset=1&id=$contribution_recur_id&cid=$contactId&context=contribution"}">{ts domain="org.project60.sepa"}Recurring Contribution{/ts}</a></strong> <br/>
              {ts domain="org.project60.sepa"}Installments{/ts}: {if $recur_installments}{$recur_installments}{else}{ts domain="org.project60.sepa"}(ongoing){/ts}{/if}, {ts domain="org.project60.sepa"}Interval{/ts}: {$recur_frequency_interval} {$recur_frequency_unit}(s)
            {/if}
        </td>
    </tr>
    {/if}
    {if $non_deductible_amount}
        <tr>
          <td class="label">{ts domain="org.project60.sepa"}Non-deductible Amount{/ts}</td>
          <td>{$non_deductible_amount|crmMoney:$currency}</td>
      </tr>
  {/if}
  {if $fee_amount}
      <tr>
          <td class="label">{ts domain="org.project60.sepa"}Fee Amount{/ts}</td>
          <td>{$fee_amount|crmMoney:$currency}</td>
      </tr>
  {/if}
  {if $net_amount}
      <tr>
          <td class="label">{ts domain="org.project60.sepa"}Net Amount{/ts}</td>
          <td>{$net_amount|crmMoney:$currency}</td>
      </tr>
  {/if}

  <tr>
      <td class="label">{ts domain="org.project60.sepa"}Received{/ts}</td>
      <td>{if $receive_date}{$receive_date|crmDate}{else}({ts domain="org.project60.sepa"}not available{/ts}){/if}</td>
  </tr>
	{if $to_financial_account }
  <tr>
	    <td class="label">{ts domain="org.project60.sepa"}Received Into{/ts}</td>
    	<td>{$to_financial_account}</td>
	</tr>
	{/if}
	<tr>
      <td class="label">{ts domain="org.project60.sepa"}Contribution Status{/ts}</td>
      <td {if $contribution_status_id eq 3} class="font-red bold"{/if}>{$contribution_status}
      {if $contribution_status_id eq 2} {if $is_pay_later}: {ts domain="org.project60.sepa"}Pay Later{/ts} {else} : {ts domain="org.project60.sepa"}Incomplete Transaction{/ts} {/if}{/if}</td>
  </tr>

  {if $cancel_date}
        <tr>
          <td class="label">{ts domain="org.project60.sepa"}Cancelled / Refunded Date{/ts}</td>
          <td>{$cancel_date|crmDate}</td>
        </tr>
      {if $cancel_reason}
          <tr>
              <td class="label">{ts domain="org.project60.sepa"}Cancellation / Refund Reason{/ts}</td>
              <td>{$cancel_reason}</td>
          </tr>
      {/if}
  {/if}
  <tr>
      <td class="label">{ts domain="org.project60.sepa"}Paid By{/ts}</td>
      <td>{$payment_instrument}</td>
  </tr>

  {if $payment_instrument eq 'Check'|ts}
        <tr>
            <td class="label">{ts domain="org.project60.sepa"}Check Number{/ts}</td>
            <td>{$check_number}</td>
        </tr>
  {/if}
  <tr>
      <td class="label">{ts domain="org.project60.sepa"}Source{/ts}</td>
      <td>{$source}</td>
  </tr>

  {if $campaign}
  <tr>
      <td class="label">{ts domain="org.project60.sepa"}Campaign{/ts}</td>
          <td>{$campaign}</td>
  </tr>
  {/if}

  {if $contribution_page_title}
        <tr>
            <td class="label">{ts domain="org.project60.sepa"}Online Contribution Page{/ts}</td>
          <td>{$contribution_page_title}</td>
        </tr>
    {/if}
  {if $receipt_date}
      <tr>
          <td class="label">{ts domain="org.project60.sepa"}Receipt Sent{/ts}</td>
          <td>{$receipt_date|crmDate}</td>
      </tr>
  {/if}
  {foreach from=$note item="rec"}
    {if $rec }
        <tr>
            <td class="label">{ts domain="org.project60.sepa"}Note{/ts}</td><td>{$rec}</td>
        </tr>
    {/if}
  {/foreach}

  {if $trxn_id}
        <tr>
          <td class="label">{ts domain="org.project60.sepa"}Transaction ID{/ts}</td>
          <td>{$trxn_id}</td>
      </tr>
  {/if}

  {if $invoice_id}
      <tr>
          <td class="label">{ts domain="org.project60.sepa"}Invoice ID{/ts}</td>
          <td>{$invoice_id}&nbsp;</td>
      </tr>
  {/if}

  {if $honor_display}
      <tr>
          <td class="label">{$honor_type}</td>
          <td>{$honor_display}&nbsp;</td>
      </tr>
  {/if}

  {if $thankyou_date}
      <tr>
          <td class="label">{ts domain="org.project60.sepa"}Thank-you Sent{/ts}</td>
          <td>{$thankyou_date|crmDate}</td>
      </tr>
  {/if}

  {if $softCreditToName and !$pcp_id} {* We show soft credit name with PCP section if contribution is linked to a PCP. *}
    <tr>
      <td class="label">{ts domain="org.project60.sepa"}Soft Credit To{/ts}</td>
        <td><a href="{crmURL p="civicrm/contact/view" q="reset=1&cid=`$soft_credit_to`"}" id="view_contact" title="{ts domain="org.project60.sepa"}View contact record{/ts}">{$softCreditToName}</a></td>
    </tr>
    {/if}
</table>

{if $premium}
    <div class="crm-accordion-wrapper ">
        <div class="crm-accordion-header">
            {ts domain="org.project60.sepa"}Premium Information{/ts}
        </div>
        <div class="crm-accordion-body">
        <table class="crm-info-panel">
          <td class="label">{ts domain="org.project60.sepa"}Premium{/ts}</td><td>{$premium}</td>
          <td class="label">{ts domain="org.project60.sepa"}Option{/ts}</td><td>{$option}</td>
          <td class="label">{ts domain="org.project60.sepa"}Fulfilled{/ts}</td><td>{$fulfilled|truncate:10:''|crmDate}</td>
        </table>
        </div>
    </div>
{/if}

{if $pcp_id}
    <div id='PCPView' class="crm-accordion-wrapper ">
         <div class="crm-accordion-header">
                {ts domain="org.project60.sepa"}Personal Campaign Page Contribution Information{/ts}
         </div>
         <div class="crm-accordion-body">
            <table class="crm-info-panel">
                <tr>
                  <td class="label">{ts domain="org.project60.sepa"}Personal Campaign Page{/ts}</td>
                    <td><a href="{crmURL p="civicrm/pcp/info" q="reset=1&id=`$pcp_id`"}">{$pcp}</a><br />
                        <span class="description">{ts domain="org.project60.sepa"}Contribution was made through this personal campaign page.{/ts}</span>
                    </td>
                </tr>
                <tr>
                  <td class="label">{ts domain="org.project60.sepa"}Soft Credit To{/ts}</td>
                    <td><a href="{crmURL p="civicrm/contact/view" q="reset=1&cid=`$soft_credit_to`"}" id="view_contact" title="{ts domain="org.project60.sepa"}View contact record{/ts}">{$softCreditToName}</a></td>
                </tr>
                <tr><td class="label">{ts domain="org.project60.sepa"}In Public Honor Roll?{/ts}</td><td>{if $pcp_display_in_roll}{ts domain="org.project60.sepa"}Yes{/ts}{else}{ts domain="org.project60.sepa"}No{/ts}{/if}</td></tr>
                {if $pcp_roll_nickname}
                    <tr><td class="label">{ts domain="org.project60.sepa"}Honor Roll Name{/ts}</td><td>{$pcp_roll_nickname}</td></tr>
                {/if}
                {if $pcp_personal_note}
                    <tr><td class="label">{ts domain="org.project60.sepa"}Personal Note{/ts}</td><td>{$pcp_personal_note}</td></tr>
                {/if}
            </table>
         </div>
    </div>
{/if}

{include file="CRM/Custom/Page/CustomDataView.tpl"}

{if $billing_address}
<fieldset><legend>{ts domain="org.project60.sepa"}Billing Address{/ts}</legend>
  <div class="form-item">
    {$billing_address|nl2br}
  </div>
</fieldset>
{/if}

<div class="crm-submit-buttons">
    {if call_user_func(array('CRM_Core_Permission','check'), 'edit contributions')}
       {assign var='urlParams' value="reset=1&id=$id&cid=$contact_id&action=update&context=$context"}
       {if ( $context eq 'fulltext' || $context eq 'search' ) && $searchKey}
       {assign var='urlParams' value="reset=1&id=$id&cid=$contact_id&action=update&context=$context&key=$searchKey"}
       {/if}
       <a class="button" href="{crmURL p='civicrm/contact/view/contribution' q=$urlParams}" accesskey="e"><span><div class="icon edit-icon ui-icon-pencil"></div>{ts domain="org.project60.sepa"}Edit{/ts}</span></a>
    {/if}
    {if call_user_func(array('CRM_Core_Permission','check'), 'delete in CiviContribute')}
       {assign var='urlParams' value="reset=1&id=$id&cid=$contact_id&action=delete&context=$context"}
       {if ( $context eq 'fulltext' || $context eq 'search' ) && $searchKey}
       {assign var='urlParams' value="reset=1&id=$id&cid=$contact_id&action=delete&context=$context&key=$searchKey"}
       {/if}
       <a class="button" href="{crmURL p='civicrm/contact/view/contribution' q=$urlParams}"><span><div class="icon delete-icon ui-icon-trash"></div>{ts domain="org.project60.sepa"}Delete{/ts}</span></a>
    {/if}
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
</div>
