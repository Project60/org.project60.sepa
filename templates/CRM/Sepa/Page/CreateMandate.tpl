{if $submit_url}
<form id='new_sepa_mandate' action="{$submit_url}" method="post">
	<input type="hidden" name="contact_id" value="{$contact_id}" />
	<table>
		<tr>	<!-- CONTACT -->
			<td>{ts}contact{/ts}:</td>
			<td><input disabled name="contact" type="text" size="40" value="{$display_name}"/></td>
		</tr>
		<tr>	<!-- AMOUNT -->
			<td>{ts}amount{/ts}:</td>
			<td><input name="total_amount" type="number" size="6"/>&nbsp;EUR</td>
		</tr>
		<tr>	<!-- FINANCIAL TYPE -->
			<td>{ts}financial type{/ts}:</td>
			<td>
				<select name="financial_type_id">
					{foreach from=$financial_types item=name key=id}
					<option value="{$id}">{$name}</option>
					{/foreach}
				</select>
			</td>
		</tr>
		<tr>	<!-- CAMPAIGN -->
			<td>{ts}campaign{/ts}:</td>
			<td>
				<select name="campaign_id">
					{foreach from=$campaigns item=name key=id}
					<option value="{$id}">{$name}</option>
					{/foreach}
				</select>
			</td>
		</tr>
		<tr>	<!-- SOURCE -->
			<td>{ts}source{/ts}:</td>
			<td><input name="source" type="text" value="Telefon"/></td>
		</tr>
		<tr>	<!-- IBAN -->
			<td>IBAN:</td>
			<td><input name="iban" type="text" size="26" value="{$iban}"/></td>
		</tr>
		<tr>	<!-- BIC -->
			<td>BIC:</td>
			<td><input name="bic" type="text" size="13" value="{$bic}"/></td>
		</tr>
	</table>

	<h3>{ts}mandate type{/ts}</h3>
	<table>
		<tr>	<!-- ONE OFF -->
			<td>
				<input name="mandate_type" type='radio' value="OOFF" checked>{ts}one time{/ts}</input>
			</td>
			<td>
				{ts}earliest execution date{/ts}:
				<input name="date" type="date" value="{$today}" size="10" />
			</td>
		<tr>
		<tr>	<!-- RECURRING -->
			<td>
				<input disabled name="mandate_type" type='radio' value="RCUR">{ts}recurring{/ts}</input>
			</td>
		<tr>
	</table>

	<input type="submit" value="{ts}create{/ts}" />
</form>

{else}
	{if $error_message}
		<h2>{ts}Error!{/ts} {$error_title}</h2>
		<p>{$error_message}</p>
	{else}
		<h2>{ts}Mandate successfully created.{/ts} {ts}Reference is{/ts}: {$reference}</h2>
	{/if}
{/if}
