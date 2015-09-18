<div class="form-item">
    <div class="crm-block crm-form-block crm-admin-options-form-block">
        {if $processState eq 'pre'}
            {if $countNotPackaged gt 0}
                <div id="help">
                    {ts}There are <strong>{$countNotPackaged}</strong> mandates not packaged. Confirm creating new package.{/ts}
                </div>
                <table  class="form-layout-compressed">
                    <tr>
                        <td class="label">{$form.creditor_id.label}</td>
                        <td>{$form.creditor_id.html}</td>
                    </tr>
                    <tr>
                        <td class="label">{$form.confirm.label}</td>
                        <td>{$form.confirm.html}<br><span class="description">{ts}Confirm creating new package{/ts}</span></td>
                    </tr>
                </table>
            {else}
                <div id="help">
                    {ts}There are all mandates packaged. Open list packages or mandates.{/ts}
                </div>
            {/if}
            <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
        {elseif $processState eq 'post'}
            {if $create_package eq true}
                {if $result.is_error eq 0}
                    <p>
                        {ts}New package was created with name <strong>{$filename}</strong>.{/ts}<br>
                        <a href="{$filelink}" download="{$filename}" class="button">Download {$filename}</a>
                    </p>
                {else}
                    <p>{ts}Error occured! New package did not created.{/ts}</p>
                {/if}
            {else}
                <p>{ts}Package did not create! The format does not provide creating packages of mandates.{/ts}</p>
            {/if}
        {/if}
    </div>
</div>
