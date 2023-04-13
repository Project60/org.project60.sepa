<?xml version="1.0" standalone="yes"?>
<BODY:CBIBdySDDReq xmlns:PMRQ="urn:CBI:xsd:CBISDDReqLogMsg.00.01.00" xmlns:BODY="urn:CBI:xsd:CBIBdySDDReq.00.01.00" xmlns:SGNT="urn:CBI:xsd:CBISgnInf.001.04">
    <BODY:PhyMsgInf>
        <BODY:PhyMsgTpCd>INC-SDDC-01</BODY:PhyMsgTpCd>
        <BODY:NbOfLogMsg>1</BODY:NbOfLogMsg>
    </BODY:PhyMsgInf>
    <BODY:CBIEnvelSDDReqLogMsg>
        <BODY:CBISDDReqLogMsg xmlns:BODY="urn:CBI:xsd:CBIBdySDDReq.00.01.00">
            <PMRQ:GrpHdr xmlns:PMRQ="urn:CBI:xsd:CBISDDReqLogMsg.00.01.00">
                <PMRQ:MsgId>{$file.reference}</PMRQ:MsgId>
                {* for some reason, the seconds (%s) in the following line will not be converted correctly, hence 42 *}
                <PMRQ:CreDtTm>{$file.created_date|crmDate:"%Y-%m-%dT%H:%i:42"}</PMRQ:CreDtTm>
                <PMRQ:NbOfTxs>{$nbtransactions}</PMRQ:NbOfTxs>
                <PMRQ:CtrlSum>{$total}</PMRQ:CtrlSum>
                <PMRQ:InitgPty>
                    <PMRQ:Nm>{$creditor.name}</PMRQ:Nm>
                    <PMRQ:Id>
                        <PMRQ:OrgId>
                            <PMRQ:Othr>
                                <PMRQ:Id>{$creditor.cuc}</PMRQ:Id>
                                <PMRQ:Issr>CBI</PMRQ:Issr>
                            </PMRQ:Othr>
                        </PMRQ:OrgId>
                    </PMRQ:Id>
                </PMRQ:InitgPty>
            </PMRQ:GrpHdr>