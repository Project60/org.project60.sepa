{literal}
  <script type="text/javascript">
    function loadPaneSwitchPP( id , paymentProcessorId ) {
      var url = "{/literal}{crmURL p='civicrm/contact/view/contribution' q='snippet=4&formType=' h=0}{literal}" + id;
      url = url + "&payment_processor_id=" + paymentProcessorId;
      {/literal}
      {if $contributionMode}
        url = url + "&mode={$contributionMode}";
      {/if}
      {if $qfKey}
        url = url + "&qfKey={$qfKey}";
      {/if}
      {literal}
      if (! cj('div.'+id).html()) {
        var loading = '<img src="{/literal}{$config->resourceBase}i/loading.gif{literal}" alt="{/literal}{ts escape='js'}loading{/ts}{literal}" />&nbsp;{/literal}{ts escape='js'}Loading{/ts}{literal}...';
        cj('div.'+id).html(loading);
        cj.ajax({
          url    : url,
          success: function(data) { cj('div.'+id).html(data).trigger('crmLoad'); }
        });
      }
    }
  </script>
{/literal}
