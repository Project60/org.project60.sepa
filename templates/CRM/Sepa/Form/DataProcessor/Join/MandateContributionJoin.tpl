{crmScope extensionKey='org.project60.sepa'}
  <p class="help">
      {ts}Select the ID of the contribution. This could be either on the contribution source with the field id. Or the any other data source which holds a contribution ID field.{/ts}
  </p>
  <div class="crm-section">
    <div class="label">{ts}Required join{/ts} <span class="marker">*</span></div>
    <div class="content">{$form.mandate_join_type.html}
      <p class="description">{ts}Required means that both Sepa Mandate Entity ID field and the Contribution ID need to be set. {/ts}</p>
    </div>
  </div>
  <div class="crm-section">
    <div class="label">{ts}Join on Contribution ID field{/ts} <span class="marker">*</span></div>
    <div class="content">
        {$form.left_field.html}
      =
        {$form.right_field.html}
    </div>
  </div>
{/crmScope}
