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


