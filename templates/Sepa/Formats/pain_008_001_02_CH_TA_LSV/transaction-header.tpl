<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="http://www.six-interbank-clearing.com/de/pain.008.001.02.ch.03.xsd" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.six-interbank-clearing.com/de/pain.008.001.02.ch.03.xsd pain.008.001.02.ch.03.xsd">
  <CstmrDrctDbtInitn>
    <GrpHdr>
      <MsgId>{$file.reference}</MsgId>
{* for some reason, the seconds (%s) in the following line will not be converted correctly, hence 42 *}
      <CreDtTm>{$file.created_date|crmDate:"%Y-%m-%dT%H:%i:42"}</CreDtTm>
      <NbOfTxs>{$nbtransactions}</NbOfTxs>
      <CtrlSum>{$total}</CtrlSum>
      <InitgPty>
        <Nm>{$creditor.name}</Nm>
        <Id>
          <OrgId>
            <Othr>
              <Id>{$creditor.lsv_id}</Id>
            </Othr>
          </OrgId>
        </Id>
      </InitgPty>
    </GrpHdr>
