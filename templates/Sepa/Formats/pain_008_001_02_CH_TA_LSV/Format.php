<?php
/*
 * Copyright (C) 2025 SYSTOPIA GmbH
 *
 * This program is free software: you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Implementation of pain.008.001.02 CH-TA LSV+.
 *
 * @see https://www.six-group.com/dam/download/banking-services/standardization/sps/ig-swiss-direct-debits-sps2018-de.pdf
 */
class CRM_Sepa_Logic_Format_pain_008_001_02_CH_TA_LSV extends CRM_Sepa_Logic_Format {

  private static function calcEsrCheckNumber(string $referenceNumber): int
  {
    static $checkList = [0, 9, 4, 6, 8, 2, 7, 1, 3, 5];
    $transferNumber = 0;
    foreach(str_split($referenceNumber) as $number) {
      $transferNumber = $checkList[($transferNumber + (int) $number) % 10];
    }

    return (10 - $transferNumber) % 10;
  }

  /**
   * Extracts the IID ("Instituts-Identifikation") from the IBAN.
   *
   * @see https://www.six-group.com/de/products-services/banking-services/payment-standardization/standards/iban.html#scrollTo=format
   */
  private static function extractIidFromIban(string $iban): string {
    $iban = str_replace([' ', '-'], '', $iban);
    $iid = substr($iban, 4, 5);
    if (strlen($iid) !== 5) {
      throw new \InvalidArgumentException(sprintf('Could not extract IID from IBAN "%s"', $iban));
    }

    return $iid;
  }

  private static function getIid(string $iban, ?string $bic): string {
    if (str_starts_with($bic ?? '', 'RAIFCH')) {
      // The IBANs of Raiffeisen Bank doesn't always contain a valid IDD. For
      // Raiffeisen the IID of the headquarters has to be used instead.
      return '80000';
    }

    return self::extractIidFromIban($iban);
  }

  public function assignExtraVariables($template) {
    $creditor = $template->getTemplateVars('creditor');
    if (!isset($creditor['iban'])) {
      throw new \InvalidArgumentException('Creditor IBAN is missing');
    }

    $creditor['iid'] = self::getIid($creditor['iban'], $creditor['bic']);
    $template->assign('creditor', $creditor);
  }

  public function extendTransaction(&$txn, $creditor_id): void {
    if (!isset($txn['iban'])) {
      throw new \InvalidArgumentException('Transaction IBAN is missing');
    }

    $txn['iid'] = self::getIid($txn['iban'], $txn['bic']);
    $txn['esr'] = $this->generateEsr($txn);
  }

  /**
   * @return string
   *   A string of length 27 containing:
   *   <Contact ID><filling zeros><Invoice ID or Contribution ID><check digit>
   */
  private function generateEsr(array $txn): string {
    $contactIdStr = (string) $txn['contact_id'];
    $contactIdLen = strlen($contactIdStr);
    $maxInvoiceIdLen = 26 - $contactIdLen;
    if (preg_match("/^[0-9]{1,$maxInvoiceIdLen}\$/", $txn['invoice_id'] ?? '') === 1) {
      $invoiceId = $txn['invoice_id'];
    }
    else {
      $invoiceId = (string) $txn['contribution_id'];
    }

    $zeroCount = 26 - $contactIdLen - strlen($invoiceId);
    $referenceNumber = $contactIdStr . str_repeat('0', $zeroCount) . $invoiceId;

    return $referenceNumber . self::calcEsrCheckNumber($referenceNumber);
  }

}
