<PMRQ:PmtInf xmlns:PMRQ="urn:CBI:xsd:CBISDDReqLogMsg.00.01.00">
    <PMRQ:PmtInfId>{$group.reference}</PMRQ:PmtInfId>
    <PMRQ:PmtMtd>DD</PMRQ:PmtMtd>
    <PMRQ:PmtTpInf>
        <PMRQ:SvcLvl>
            <PMRQ:Cd>SEPA</PMRQ:Cd>
        </PMRQ:SvcLvl>
        <PMRQ:LclInstrm>
            <PMRQ:Cd>CORE</PMRQ:Cd>
        </PMRQ:LclInstrm>
        <PMRQ:SeqTp>{$group.type}</PMRQ:SeqTp>
        <PMRQ:CtgyPurp>
            <PMRQ:Cd>OTHR</PMRQ:Cd>
        </PMRQ:CtgyPurp>
    </PMRQ:PmtTpInf>
    <PMRQ:ReqdColltnDt>{$group.collection_date|crmDate:"%Y-%m-%d"}</PMRQ:ReqdColltnDt>
    <PMRQ:Cdtr>
        <PMRQ:Nm>{$creditor.name}</PMRQ:Nm>
    </PMRQ:Cdtr>
    <PMRQ:CdtrAcct>
        <PMRQ:Id>
            <PMRQ:IBAN>{$creditor.iban}</PMRQ:IBAN>
        </PMRQ:Id>
    </PMRQ:CdtrAcct>
    <PMRQ:CdtrAgt>
        <PMRQ:FinInstnId>
            <PMRQ:ClrSysMmbId>
                <PMRQ:MmbId>{$creditor.iban|regex_replace:'/[A-Z][A-Z][0-9][0-9][A-Z]/':""|truncate:5:""}</PMRQ:MmbId>
            </PMRQ:ClrSysMmbId>
        </PMRQ:FinInstnId>
    </PMRQ:CdtrAgt>
    <PMRQ:CdtrSchmeId>
        <PMRQ:Nm>{$creditor.name}</PMRQ:Nm>
        <PMRQ:Id>
            <PMRQ:PrvtId>
                <PMRQ:Othr>
                    <PMRQ:Id>{$creditor.identifier}</PMRQ:Id>
                </PMRQ:Othr>
            </PMRQ:PrvtId>
        </PMRQ:Id>
    </PMRQ:CdtrSchmeId>

{foreach from=$contributions item="contribution"}
    <PMRQ:DrctDbtTxInf xmlns:PMRQ="urn:CBI:xsd:CBISDDReqLogMsg.00.01.00">
        <PMRQ:PmtId>
            <PMRQ:InstrId>3</PMRQ:InstrId>
            <PMRQ:EndToEndId>{$contribution.end2endID}</PMRQ:EndToEndId>
        </PMRQ:PmtId>
        <PMRQ:InstdAmt Ccy="EUR">45.00</PMRQ:InstdAmt>
        <PMRQ:DrctDbtTx>
            <PMRQ:MndtRltdInf>
                <PMRQ:MndtId>BSSRGR42H03H501E</PMRQ:MndtId>
                <PMRQ:DtOfSgntr>2022-03-02</PMRQ:DtOfSgntr>
            </PMRQ:MndtRltdInf>
        </PMRQ:DrctDbtTx>
        <PMRQ:Dbtr>
            <PMRQ:Nm>Bises    Ruggero</PMRQ:Nm>
        </PMRQ:Dbtr>
        <PMRQ:DbtrAcct>
            <PMRQ:Id>
                <PMRQ:IBAN>IT45A0200801005000005310398</PMRQ:IBAN>
            </PMRQ:Id>
        </PMRQ:DbtrAcct>
        <PMRQ:RmtInf>
            <PMRQ:Ustrd>ordinario</PMRQ:Ustrd>
        </PMRQ:RmtInf>

        <PmtId>
          <EndToEndId>{$contribution.end2endID}</EndToEndId>
        </PmtId>
        <InstdAmt Ccy="{$contribution.currency}">{$contribution.total_amount}</InstdAmt>
        <DrctDbtTx>
          <MndtRltdInf>
            <MndtId>{$contribution.reference}</MndtId>
            <DtOfSgntr>{$contribution.date|crmDate:"%Y-%m-%d"}</DtOfSgntr>
          </MndtRltdInf>
        </DrctDbtTx>
        <DbtrAgt>
          <FinInstnId>
            <BIC>{$contribution.bic}</BIC>
          </FinInstnId>
        </DbtrAgt>
        <Dbtr>
          <Nm>{$contribution.display_name}</Nm>
        </Dbtr>
        <DbtrAcct>
          <Id>
            <IBAN>{$contribution.iban}</IBAN>
          </Id>
        </DbtrAcct>
        <RmtInf>
          <Ustrd>{$contribution.message}</Ustrd>
        </RmtInf>
    </PMRQ:DrctDbtTxInf>
{/foreach}

</PMRQ:PmtInf>
