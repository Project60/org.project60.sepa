    <PmtInf>
      <PmtInfId>{$group.reference}</PmtInfId>
      <PmtMtd>DD</PmtMtd>
      <BtchBookg>false</BtchBookg>
      <NbOfTxs>{$nbtransactions}</NbOfTxs>
      <CtrlSum>{$total}</CtrlSum>
      <PmtTpInf>
        <SvcLvl>
          <Cd>SEPA</Cd>
        </SvcLvl>
        <LclInstrm>
          <Cd>CORE</Cd>
        </LclInstrm>
        <SeqTp>{$group.type}</SeqTp>
      </PmtTpInf>
      <ReqdColltnDt>{$group.collection_date|crmDate:"%Y-%m-%d"}</ReqdColltnDt>
      <Cdtr>
        <Nm>{$creditor.name}</Nm>
        <PstlAdr>
          <Ctry>DE</Ctry>
          <AdrLine>{if $contribution.street_address}{$contribution.street_address}{else}n/a{/if}</AdrLine>
          <AdrLine>{if $contribution.postal_code}{$contribution.postal_code}{else}n/a{/if} {if $contribution.city}{$contribution.city}{/if}</AdrLine>
        </PstlAdr>
      </Cdtr>
      <CdtrAcct>
        <Id>
          <IBAN>{$creditor.iban}</IBAN>
        </Id>
      </CdtrAcct>
      <CdtrAgt>
        <FinInstnId>
          <BIC>{$creditor.bic}</BIC>
        </FinInstnId>
      </CdtrAgt>
      <ChrgBr>SLEV</ChrgBr>
{foreach from=$contributions item="contribution"}
      <DrctDbtTxInf>
        <PmtId>
          <EndToEndId>{$contribution.end2endID}</EndToEndId>
        </PmtId>
        <InstdAmt Ccy="{$contribution.currency}">{$contribution.total_amount}</InstdAmt>
        <DrctDbtTx>
          <MndtRltdInf>
            <MndtId>{$contribution.reference}</MndtId>
            <DtOfSgntr>{$contribution.date|crmDate:"%Y-%m-%d"}</DtOfSgntr>
          </MndtRltdInf>
          <CdtrSchmeId>
            <Id>
              <PrvtId>
                <Othr>
                  <Id>{$creditor.identifier}</Id>
                  <SchmeNm>
                    <Prtry>SEPA</Prtry>
                  </SchmeNm>
                </Othr>
              </PrvtId>
            </Id>
          </CdtrSchmeId>
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
      </DrctDbtTxInf>
{/foreach}
    </PmtInf>
