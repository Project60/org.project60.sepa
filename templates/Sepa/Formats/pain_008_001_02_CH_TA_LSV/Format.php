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

use Civi\Api4\SepaCreditor;

/**
 * Implementation of pain.008.001.02 CH-TA LSV+.
 *
 * Note: The creditor identifier has to be in this form (i.e. the three values
 * separated by "/"):
 * LSV+-Identifikation/ESR-Teilnehmernummer/ESR-Referenznummernpräfix
 *
 * @see https://www.six-group.com/dam/download/banking-services/standardization/sps/ig-swiss-direct-debits-sps2018-de.pdf
 */
class CRM_Sepa_Logic_Format_pain_008_001_02_CH_TA_LSV extends CRM_Sepa_Logic_Format {

  /**
   * @var array<int, string>
   */
  private array $creditorIdentifiers = [];

  private static function calcEsrCheckDigit(string $referenceNumber): int
  {
    static $checkList = [0, 9, 4, 6, 8, 2, 7, 1, 3, 5];
    $transferNumber = 0;
    foreach(str_split($referenceNumber) as $number) {
      $transferNumber = $checkList[($transferNumber + (int) $number) % 10];
    }

    return (10 - $transferNumber) % 10;
  }

  /**
   * @return array{string, string, string}
   *   LSV+-Identifikation, ESR-Teilnehmernummer, ESR-Referenznummerpräfix
   */
  private static function explodeCreditorIdentifier(string $identifier): array {
    [$lsvId, $esrTeilnehmernummer, $esrPrefix] = explode('/', $identifier) + [NULL, NULL, NULL];
    if (NULL === $esrPrefix) {
      throw new \InvalidArgumentException(
        'Creditor identifier has to be in this form: LSV+-Identifikation/ESR-Teilnehmernummer/ESR-Referenznummernpräfix'
      );
    }

    return [$lsvId, $esrTeilnehmernummer, $esrPrefix];
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

    return ltrim($iid, '0');
  }

  private static function getIid(string $iban, ?string $bic): string {
    if (str_starts_with($bic ?? '', 'RAIFCH')) {
      // The IBANs of Raiffeisen Bank doesn't always contain a valid IID. For
      // Raiffeisen the IID 80800 has to be used instead.
      return '80800';
    }

    return self::extractIidFromIban($iban);
  }

  public function assignExtraVariables($template): void {
    $creditor = $template->getTemplateVars('creditor');
    if (!isset($creditor['iban'])) {
      throw new \InvalidArgumentException('Creditor IBAN is missing');
    }

    $creditor['iid'] = self::getIid($creditor['iban'], $creditor['bic']);

    [$lsvId, $esrTeilnehmernummer, $esrPrefix] = self::explodeCreditorIdentifier($creditor['identifier']);
    $creditor['lsv_id'] = $lsvId;
    $creditor['esr_teilnehmernummer'] = $esrTeilnehmernummer;

    $template->assign('creditor', $creditor);
  }

  public function extendTransaction(&$txn, $creditorId): void {
    if (!isset($txn['iban'])) {
      throw new \InvalidArgumentException('Transaction IBAN is missing');
    }

    $txn['iid'] = self::getIid($txn['iban'], $txn['bic']);
    $txn['esr'] = $this->generateEsr($txn, $creditorId);
  }

  private function getCreditorIdentifier(int $creditorId): string {
    return $this->creditorIdentifiers[$creditorId] ??= SepaCreditor::get(FALSE)
      ->addSelect('identifier')
      ->addWhere('id', '=', $creditorId)
      ->execute()
      ->single()['identifier'];
  }

  /**
   * @return string
   *   A string of length 27 containing:
   *   <ESR prefix><filling zeros><Contact ID>00<Invoice ID or Contribution ID><check digit>
   */
  private function generateEsr(array $txn, int $creditorId): string {
    $creditorIdentifier = $this->getCreditorIdentifier($creditorId);
    [$lsvId, $esrTeilnehmernummer, $esrPrefix] = self::explodeCreditorIdentifier($creditorIdentifier);

    $prefixLen = strlen($esrPrefix);
    $contactIdStr = (string) $txn['contact_id'];
    // Separate contact ID and invoice ID/contribution ID with "00".
    $contactIdStr .= '00';
    $contactIdLen = strlen($contactIdStr);
    $maxInvoiceIdLen = 26 - $prefixLen - $contactIdLen;
    if (preg_match("/^[0-9]{1,$maxInvoiceIdLen}\$/", $txn['invoice_id'] ?? '') === 1) {
      $invoiceId = $txn['invoice_id'];
    }
    else {
      $invoiceId = (string) $txn['contribution_id'];
    }

    $zeroCount = 26 - $prefixLen - $contactIdLen - strlen($invoiceId);
    $referenceNumber = $esrPrefix . str_repeat('0', $zeroCount) . $contactIdStr . $invoiceId;

    return $referenceNumber . self::calcEsrCheckDigit($referenceNumber);
  }

}
