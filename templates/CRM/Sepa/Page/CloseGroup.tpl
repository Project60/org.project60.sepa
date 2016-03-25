{*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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

<div class="crm-container" lang="de" xml:lang="de" >
	<div class="crm-block crm-form-block crm-import-datasource-form-block">
		{if $status eq "closed"}
			{capture assign=group_text}
			<font size="+1">{ts domain="org.project60.sepa"}Group '%s' <b>is now closed</b> and cannot be changed any more.{/ts}</font>
			{/capture}
			<p>{$group_text|sprintf:$txgroup.reference}</p>
			<p>{ts domain="org.project60.sepa"}The money should be on its way{/ts}</p>
		{elseif $status eq "invalid"}
			{capture assign=settings_url}{crmURL p="civicrm/admin/setting/sepa"}{/capture}
			<p>{ts domain="org.project60.sepa"}Sorry to hear that the bank has rejected your payment XML file.{/ts}</p>
			<p>{ts domain="org.project60.sepa"}You might now want to try the following:{/ts}</p>
			<ul>
				<li>{ts domain="org.project60.sepa" 1=$settings_url}Make sure your bank does support your XML PAIN format. If it doesn't, change the generated format on the <a href="%1">settings page</a>. Then dowload the file again.{/ts}</li>
				<li>{ts domain="org.project60.sepa"}Find out why exactly it was rejected. Oftentimes there are BICs and IBANs that are formally correct, but the accounts do not exist. In this case, banks usually would let you know which data exactly they reject. Fix this then in your mandates, update the groups, and try again.{/ts}</li>
				<li>{ts domain="org.project60.sepa"}In the unlikely event that the file is formally wrong, try a SEPA validation tool on the internet to check the generated XML file. Contact us, if the system really generates incorrect XML files.{/ts}</li>
			</ul>
		{elseif $status eq "missed"}
			<p><span class="icon red-icon alert-icon"> </span>
			{ts domain="org.project60.sepa"}<strong>You did not submit this SEPA group in time! It is possible that the bank will reject the payment requests.</strong>{/ts}
			<span class="icon red-icon alert-icon"> </span></p>
			<p>{ts domain="org.project60.sepa"}As a workaround, we can adjust the collection date, so that you can still submit the file today. <strong>Today! <font color="red">NOW!</font></strong>{/ts}</p>
			<p>{ts domain="org.project60.sepa"}However, the bank might still reject the file, since this is an illegal deviation from your announced collection date. Try to avoid this in the future!{/ts}</p>
		{else}
			<p><font size="+0.5">{ts domain="org.project60.sepa"}Download Link:{/ts}&nbsp;<a href="{$file_link}" download="{$file_name}">{$file_name}</a></font></p>

			<p id="closed_group_instruction_text">
				{ts domain="org.project60.sepa"}<b>In order to collect these payments, you have to do the following:</b>{/ts}
				<ol>
					<li>{ts domain="org.project60.sepa"}Download the file via the above link{/ts}</li>
					<li>{ts domain="org.project60.sepa"}Submit that file to your bank{/ts}</li>
					<li>{ts domain="org.project60.sepa"}Select one of the options below{/ts}</li>
				</ol>
				{ts domain="org.project60.sepa"}<b>Do it now! No money will be transferred until the file has been submitted successfully.</b>{/ts}
			</p>
			{if $is_test_group}
			<span style="color:#ff0000;font-size:150%;">{ts domain="org.project60.sepa"}This is a test group. You can not close it.{/ts}</span>
			{/if}
			</p>
		{/if}
	</div>
	<p>
	{if $status eq "closed"}
		<a href="{crmURL p="civicrm/sepa/dashboard" q="status=closed"}" class="button button_export">{ts domain="org.project60.sepa"}Return to dashboard{/ts}</a>
	{elseif $status eq "invalid"}
		<a href="{crmURL p="civicrm/sepa/dashboard"}" class="button button_export">{ts domain="org.project60.sepa"}Return to dashboard{/ts}</a>
	{elseif $status eq "missed"}
		<a href="{crmURL p="civicrm/sepa/closegroup" q="group_id=$txgid&adjust=today"}" class="button button_export">{ts domain="org.project60.sepa"}Do it! Now!{/ts}</a>
		<a href="{crmURL p="civicrm/sepa/dashboard"}" class="button button_export">{ts domain="org.project60.sepa"}I can't submit it right now{/ts}</a>
	{else}
		{if not $is_test_group}
		<a href="{crmURL p="civicrm/sepa/closegroup" q="group_id=$txgid&status=closed"}" class="button button_export">{ts domain="org.project60.sepa"}The file was submitted successfully{/ts}</a>
		<a href="{crmURL p="civicrm/sepa/closegroup" q="group_id=$txgid&status=invalid"}" class="button button_export">{ts domain="org.project60.sepa"}The file was rejected{/ts}</a>
		{/if}
		{if not $smarty.request.adjust}
		<a href="{crmURL p="civicrm/sepa/dashboard"}" class="button button_export">{ts domain="org.project60.sepa"}I changed my mind{/ts}</a>
		{/if}
	{/if}
	</p>
</div>
