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
	<p><a href="{crmURL p="civicrm/sepa"}" class="button button_export">{ts}I swear I have sucessfully submitted it{/ts}</a></p>
</div>
