{if $messages}
  {foreach from=$messages item=message}
    <p>{$message}</p>
  {/foreach}
{else}
  <p>{ts}Database appears to be up to date already.{/ts}</p>
{/if}
