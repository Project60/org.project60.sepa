<div class="form-item">
    <div class="crm-block crm-form-block crm-admin-options-form-block">
        {if $countNotPackaged gt 0}
            <div id="help">
                {ts}There are <strong>{$countNotPackaged}</strong> mandates not packaged. Confirm creating new package.{/ts}
            </div>
            <table  class="form-layout-compressed">
                <tr>
                    <td class="label ">{$form.confirm.label}</td>
                    <td>{$form.confirm.html}<br><span class="description">{ts}Confirm creating new package{/ts}</span></td>
                </tr>
            </table>
        {else}
            <div id="help">
                {ts}There are all mandates packaged. Open list packages or mandates.{/ts}
            </div>
        {/if}
        <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
    </div>
</div>
