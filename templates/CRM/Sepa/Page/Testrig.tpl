<fieldset>
  <legend style="margin-left: 20px;" >Housekeeping tools</legend>
  <button onclick="document.location='{crmURL p="civicrm/sepa/test" q="action=dummyCreditor"}';">Create the dummy creditor</button>
  <button onclick="document.location='{crmURL p="civicrm/sepa/test" q="action=zap"}';">Zap the operational data</button>
</fieldset>

<form action="{crmURL p="civicrm/sepa/test"}" method="get">
  <input type="hidden" name="action" value="newMandate">
  <fieldset>
    <legend style="margin-left: 20px;" >Create a mandate</legend>
    <p>
      Type
      <select name="type">
        <option value="RCUR">RCUR - for recurring payments</option>
        <option value="OOFF">OOFF - for one-off payments</option>
      </select>
      with status
      <select name="status">
        <option value="INIT">INIT - any mandate which has not yet been activated</option>
        <option value="FRST">FRST - RCUR mandate while its FRST is absent or pending</option>
        <option value="RCUR">RCUR - RCUR mandate after the FRST is completed</option>
        <option value="OOFF">OOFF - OOFF mandate</option>
        <option value="ONHOLD">ONHOLD - mandate which is temporarily disabled</option>
        <option value="INVALID">INVALID - mandate which could never be consumed</option>
        <option value="CANCELLED">CANCELLED - mandate cancelled by debtor or creditor</option>
        <option value="COMPLETE">COMPLETE - mandate completely consumed (temporary or OOFF)</option>
      </select>
    </p>
    <p>
      Use as contract object: 
      <br/>
      <input type="radio" name="contract" value="single"> single contribution
      <br/>
      <input type="radio" name="contract" value="rc0"> recurring contribution, no initial contribution created
      <br/>
      <input type="radio" name="contract" value="rc1"> recurring contribution, with initial contribution created
      <br/>
      <input type="radio" name="contract" value="running"> recurring contribution, with next contribution created
    </p>
    <p>
      for 
      <input type="text" name="amount" size="8" value="{$amount}">
      EUR
      <select name="freq">
        <option value="month">every month</option>
        <option value="quarter">every quarter</option>
        <option value="year">every year</option>
      </select>
      on the 
      <input type="text" name="pivot" size="3" value="8">th
      starting on
      <input type="text" name="startdate" size="10" value="{$startdate}">

    </p>
    <p>
      <input type="checkbox" name="member" disabled> Create a membership for the contract object
    </p>
  </fieldset>
  <button>Create</button>
</form>


{literal}
<script>
  cj(function() {
    cj("ul#sdd li ul").click( function() { cj(this).children("li").toggle(); return false; });
  });
</script>
<style>
ul#sdd {
  margin-left: 0px;
  padding: 0px;
  color: #999;
}
ul#sdd li {
  list-style: none;
  background-color: #eeffee;
  padding: 4px;
  border: 1px solid #ddd;
  border-width: 1px 1px 1px 4px;
  border-radius: 6px;
  margin: 10px 10px 10px 20px;
}
ul#sdd li ul {
  list-style: none;
  margin: 0px;
  padding: 0px;
}
ul#sdd li span {
  color: #333;
  font-weight: bold;
  margin-left: 10px;
}
ul#sdd li ul li {
  background-color: #ccffcc;
}
ul#sdd li ul li ul li {
  background-color: #fff;
  display: none;
}
ul#sdd li.sddfileopen { border-color: #009900;background-color: #eeffee;}
ul#sdd li.sddfileclosed { border-color: #990000; background-color: #ffeeee;}
ul#sdd li.txgopen { border-color: #009900; background-color: #ccffcc;}
ul#sdd li.txgclosed { border-color: #990000; background-color: #ffcccc;}
</style>
{/literal}
  