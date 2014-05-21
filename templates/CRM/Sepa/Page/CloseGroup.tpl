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
		<font size="+1">
		{capture assign=group_text}{ts}Group '%s' <b>is now closed</b>. It cannot be changed any more.{/ts}{/capture}
		<p>{$group_text|sprintf:$txgroup.reference}</p>
		</font>
		<font size="+0.5">
		<p>{ts}Download Link:{/ts}&nbsp;<a href="{$file_link}" download="{$file_name}">{$file_name}</a></p>

		<p id="closed_group_instruction_text">
			{ts}<b>It is vital that you <i>immediately</i> do the following:</b>
			<ol>
				<li>Download the file via the above link</li>
				<li>Submit that file to your bank</li>
			</ol>{/ts}
		</p>
		</font>
	</div>
	<p><a href="{crmURL p="civicrm/sepa/dashboard" q="status=closed"}" class="button button_export">{ts}I swear I have sucessfully submitted it{/ts}</a></p>
</div>
