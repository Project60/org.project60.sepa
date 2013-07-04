<PmtInf>
<PmtInfId>NGO-FRST-7553</PmtInfId>
<PmtMtd>DD</PmtMtd>
<BtchBookg>false</BtchBookg>
<NbOfTxs>1</NbOfTxs>
<CtrlSum>10.1</CtrlSum>
<PmtTpInf>
<SvcLvl>
<Cd>SEPA</Cd>
</SvcLvl>
<LclInstrm>
<Cd>CORE</Cd>
</LclInstrm>
<SeqTp>FRST</SeqTp>
</PmtTpInf>
<ReqdColltnDt>2010-09-12</ReqdColltnDt>
<Cdtr>
<Nm>NGO International</Nm>
</Cdtr>
<CdtrAcct>
<Id>
<IBAN>NL90ABNA0111111111</IBAN>
</Id>
</CdtrAcct>
<CdtrAgt>
<FinInstnId>
<BIC>ABNANL2A</BIC>
</FinInstnId>
</CdtrAgt>
<ChrgBr>SLEV</ChrgBr>
<CdtrSchmeId>
<Nm>NGO International</Nm>
<Id>
<PrvtId>
<Othr>
<Id>NL64ZZZ321096320000</Id>
<SchmeNm>
<Prtry>SEPA</Prtry>
</SchmeNm>
</Othr>
</PrvtId>
</Id>
</CdtrSchmeId>
<DrctDbtTxInf>
<PmtId>
<EndToEndId>NGO-FRST-7553-A01</EndToEndId>
</PmtId>
<InstdAmt Ccy="EUR">10.1</InstdAmt>
<DrctDbtTx>
<MndtRltdInf>
<MndtId>NGO-5673824</MndtId>
<DtOfSgntr>2008-07-13</DtOfSgntr>
</MndtRltdInf>
</DrctDbtTx>
© Project 60 2013 – P60 SDD XML 100.odt
6/8P60-SDD-XMLL
<DbtrAgt>
<FinInstnId>
<BIC>RABONL2U</BIC>
</FinInstnId>
</DbtrAgt>
<Dbtr>
<Nm>Joe Donor</Nm>
</Dbtr>
<DbtrAcct>
<Id>
<IBAN>NL44RABO0123456789</IBAN>
</Id>
</DbtrAcct>
<RmtInf>
<Ustrd>Your mandate NGO-5673824, payment for Sep 2012</Ustrd>
</RmtInf>
</DrctDbtTxInf>
</PmtInf>
{foreach from=$contributions item=c}
id={$c.id}
{/foreach}
