<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.008.001.02" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="urn:iso:std:iso:20022:tech:xsd:pain.008.001.02 pain.008.001.02.xsd">
  <CstmrDrctDbtInitn>
    <GrpHdr>
      <MsgId>{$file.reference}</MsgId>
{* for some reason, the seconds (%s) in the following line will not be converted correctly, hence 00 *}
      <CreDtTm>{$file.created_date|crmDate:"%Y-%m-%dT%H:%i:00"}</CreDtTm>
      <NbOfTxs>{$nbtransactions}</NbOfTxs>
      <CtrlSum>{$total}</CtrlSum>
      <InitgPty>
        <Nm>{$creditor.name}</Nm>
      </InitgPty>
    </GrpHdr>
