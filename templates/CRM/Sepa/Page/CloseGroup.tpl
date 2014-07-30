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
			<font size="+1">
			{capture assign=group_text}{ts}Group '%s' <b>is now closed</b>. It cannot be changed any more.{/ts}{/capture}
			<p>{$group_text|sprintf:$txgroup.reference}</p>
			</font>
		{elseif $status eq "invalid"}
			<font size="+1">
			<p>
			{ts}You can try this if the file was not accepted{/ts}:
			<ol>
				<li>#todo</li>
				<li>#todo</li>
				<li>#todo</li>
			</ol>
			</p>
			</font>
		{else}	
		<font size="+0.5">
		<p>{ts}Download Link:{/ts}&nbsp;<a href="{$file_link}" download="{$file_name}">{$file_name}</a></p>

		<p id="closed_group_instruction_text">
			{ts}<b>It is vital that you <i>immediately</i> do the following:</b>
			<ol>
				<li>Download the file via the above link</li>
				<li>Submit that file to your bank</li>
				<li>Come back here and select one of the options below depending on the result</li>
			</ol>{/ts}
		</p>
		</font>
		{/if}
	</div>
	<p>
	{if $status eq "closed"}
		<a href="{crmURL p="civicrm/sepa/dashboard" q="status=closed"}" class="button button_export">{ts}Return to dashboard{/ts}</a>
	{elseif $status eq "invalid"}
		<a href="{crmURL p="civicrm/sepa/dashboard"}" class="button button_export">{ts}Return to dashboard{/ts}</a>
	{else}
		<a href="{crmURL p="civicrm/sepa/closegroup" q="group_id=$txgid&status=closed"}" class="button button_export">{ts}I have successfully submitted it{/ts}</a><a href="{crmURL p="civicrm/sepa/closegroup" q="group_id=$txgid&status=invalid"}" class="button button_export">{ts}The file was not accepted{/ts}</a><a href="{crmURL p="civicrm/sepa/dashboard"}" class="button button_export">{ts}Return to dashboard{/ts}</a>
	{/if}
	</p>
</div>
