id{$recur.processor_id}
{$aaa}


    {if $recur}
        <h3>{ts}Sepa Mandate{/ts}</h3>
        <div class="crm-block crm-content-block crm-sdd-mandate">
          <table class="crm-info-panel">
            <tr><td class="label">{ts}Reference{/ts}</td><td>{$sepa.reference}</td></tr>
            <tr><td class="label">{ts}IBAN{/ts}</td><td>every {$sepa.iban}</td></tr>
            <tr><td class="label">{ts}BIC{/ts}</td><td>every {$sepa.bic}</td></tr>

</table></div>
 {/if}
