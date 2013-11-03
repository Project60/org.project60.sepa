<fieldset>
  <legend style="margin-left: 20px;" >Housekeeping tools</legend>
  <button onclick="document.location='{crmURL p="civicrm/sepa/test" q="action=dummyCreditor"}';">Create the dummy creditor</button>
  <button onclick="document.location='{crmURL p="civicrm/sepa/test" q="action=zap"}';">Zap the operational data</button>
</fieldset>

<form action="{crmURL p="civicrm/sepa/test"}" method="get">
  <input type="hidden" name="action" value="newMandate">
  <fieldset>
    <legend style="margin-left: 20px;" >Create a mandate</legend>
    Mandate status : 
    <select name="status">
      <option value="INIT">INIT</option>
      <option value="FRST">FRST</option>
      <option value="RCUR">RCUR</option>
      <option value="OOFF">OOFF</option>
      <option value="ONHOLD">ONHOLD</option>
      <option value="INVALID">INVALIE</option>
      <option value="CANCELLED">CANCELLED</option>
      <option value="COMPLETE">COMPLETE</option>
    </select>
    <br/>
    Create contract object : 
    <br/>
    <input type="radio" name="contract" value="none"> None
    <br/>
    <input type="radio" name="contract" value="rc0"> Recurring contribution, no initial contribution
    <br/>
    <input type="radio" name="contract" value="rc1"> Recurring contribution, with initial contribution
    <br/>
    <input type="radio" name="contract" value="running"> Recurring contribution, with next contribution (forces RCUR status on mandate)
  </fieldset>
  <button>Create a mandate</button>
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
  