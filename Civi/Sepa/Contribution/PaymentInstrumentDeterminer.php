<?php
/*
 * Copyright (C) 2026 SYSTOPIA GmbH
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

declare(strict_types = 1);

namespace Civi\Sepa\Contribution;

final class PaymentInstrumentDeterminer {

  /**
   * Determines the payment instrument ID for the initial contribution when
   * creating a new mandate.
   *
   * @throws \CRM_Core_Exception
   */
  public function determineInitialPaymentInstrument(
    int $creditorId,
    string $mandateType,
    ?int $givenPaymentInstrumentId
  ): int {
    $eligiblePaymentInstruments = \CRM_Sepa_Logic_PaymentInstruments::getPaymentInstrumentsForCreditor(
      $creditorId,
      $mandateType
    );

    if ([] === $eligiblePaymentInstruments) {
      // no payment instrument -> disabled
      throw new \CRM_Core_Exception(
        "$mandateType mandate for creditor ID [$creditorId] disabled, i.e. no valid payment instrument set."
      );
    }

    if (NULL === $givenPaymentInstrumentId) {
      // no payment instrument given, see if there is a unique one set
      if (count($eligiblePaymentInstruments) === 1) {
        return key($eligiblePaymentInstruments);
      }

      // unclear which one to take
      throw new \CRM_Core_Exception(
        // phpcs:ignore Generic.Files.LineLength.TooLong
        "You have to define the payment_instrument_id for $mandateType mandates for creditor ID [$creditorId], there are multiple options."
      );
    }

    // a payment instrument is set, verify that it's allowed
    if (isset($eligiblePaymentInstruments[$givenPaymentInstrumentId])) {
      return $givenPaymentInstrumentId;
    }

    throw new \CRM_Core_Exception(
      // phpcs:ignore Generic.Files.LineLength.TooLong
      "Payment instrument [$givenPaymentInstrumentId] invalid for $mandateType mandates with creditor ID [$creditorId]."
    );
  }

  public function determineCollectReceivablePaymentInstrument(int $creditorId, int $rcurPaymentInstrumentId): int {
    $eligiblePaymentInstruments = \CRM_Sepa_Logic_PaymentInstruments::getPaymentInstrumentsForCreditor(
      $creditorId,
      'OOFF'
    );

    if ([] === $eligiblePaymentInstruments) {
      // no payment instrument -> disabled
      throw new \CRM_Core_Exception(
        "OOFF mandate for creditor ID [$creditorId] disabled, i.e. no valid payment instrument set."
      );
    }

    if (isset($eligiblePaymentInstruments[$rcurPaymentInstrumentId])) {
      // Use the same payment instrument ID that was used for the RCUR mandate, if possible.
      return $rcurPaymentInstrumentId;
    }

    // Use the first configured FRST payment instrument.
    return key($eligiblePaymentInstruments);
  }

}
