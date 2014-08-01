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
			{ts}<font size="+1">Group '%s' <b>is now closed</b> and cannot be changed any more.</font>{/ts}
			{/capture}
			<p>{$group_text|sprintf:$txgroup.reference}</p>
			<p>{ts}The money should be on it's way{/ts}</p>
		{elseif $status eq "invalid"}
			{capture assign=settings_url}{crmURL p="civicrm/admin/setting/sepa"}{/capture}
			<p>{ts}Sorry to hear that the bank has rejected your payment XML file.{/ts}</p>
			<p>{ts}You might now want to try the following:{/ts}</p>
			<ul>
				<li>{ts}Make sure your bank does support your XML PAIN format. If it doesn't, change the generated format on the <a href="{$settings_url}">settings page</a>. Then dowload the file again.{/ts}</li>
				<li>{ts}Find out why exactly it was rejected. Oftentimes there are BICs and IBANs that are formally correct, but the accounts do not exist. In this case, banks usually would let you know which data exactly they reject. Fix this then in your mandates, update the groups, and try again.{/ts}</li>
				<li>{ts}In the unlikely event that the file is formally wrong, try a SEPA validation tool on the internet to check the generated XML file. Contact us, if the system really generates incorrect XML files.{/ts}</li>
			</ul>
		{else}	
			<p>{ts}<font size="+0.5">Download Link:{/ts}&nbsp;<a href="{$file_link}" download="{$file_name}">{$file_name}</a></font></p>
			

			<p id="closed_group_instruction_text">
				{ts}<b>In order to collect these payments, you have to do the following:</b>{/ts}
				<ol>
					<li>{ts}Download the file via the above link{/ts}</li>
					<li>{ts}Submit that file to your bank{/ts}</li>
					<li>{ts}Select one of the options below{/ts}</li>
				</ol>
				{ts}<b>Do it now! No money will be transferred until the file has been submitted successfully.</b>{/ts}
			</p>
			</p>
		{/if}
	</div>
	<p>
	{if $status eq "closed"}
		<a href="{crmURL p="civicrm/sepa/dashboard" q="status=closed"}" class="button button_export">{ts}Return to dashboard{/ts}</a>
	{elseif $status eq "invalid"}
		<a href="{crmURL p="civicrm/sepa/dashboard"}" class="button button_export">{ts}Return to dashboard{/ts}</a>
	{else}
		<a href="{crmURL p="civicrm/sepa/closegroup" q="group_id=$txgid&status=closed"}" class="button button_export">{ts}The file was submitted successfully{/ts}</a><a href="{crmURL p="civicrm/sepa/closegroup" q="group_id=$txgid&status=invalid"}" class="button button_export">{ts}The file was rejected{/ts}</a><a href="{crmURL p="civicrm/sepa/dashboard"}" class="button button_export">{ts}I changed my mind{/ts}</a>
	{/if}
	</p>
</div>
