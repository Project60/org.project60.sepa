<?php
/*
 * Copyright (C) 2024 SYSTOPIA GmbH
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation in version 3.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types = 1);

namespace Civi\Sepa\Util;

use Civi\Api4\FinancialType;

class ContributionUtil {

  /**
   * Retrieves the list of (CiviSEPA) payment instruments.
   *
   * @return array<int, string>
   *   An array of payment instrument labels, keyed by their ID.
   */
  public static function getPaymentInstrumentList(): array {
    $list = [];
    $payment_instruments = \CRM_Sepa_Logic_PaymentInstruments::getAllSddPaymentInstruments();
    foreach ($payment_instruments as $payment_instrument) {
      $list[$payment_instrument['id']] = $payment_instrument['label'];
    }

    return $list;
  }

  /**
   * Retrieves the list of (active) financial types.
   *
   * @return array<int, string>
   *   An array of financial type names, keyed by their ID.
   */
  public static function getFinancialTypeList(): array {
  // Check permissions for financial types for evaluating Financial ACLs.
  return FinancialType::get()
    ->addSelect('id', 'name')
    ->addWhere('is_active', '=', TRUE)
    ->execute()
    ->indexBy('id')
    ->column('name');
}

}
