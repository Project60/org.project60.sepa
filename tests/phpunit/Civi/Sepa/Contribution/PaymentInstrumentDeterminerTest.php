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

use Civi\Sepa\AbstractSepaHeadlessTestCase;
use Systopia\TestFixtures\Fixtures\Builders\SepaCreditorBuilder;

/**
 * @covers \Civi\Sepa\Contribution\PaymentInstrumentDeterminer
 *
 * @group headless
 */
final class PaymentInstrumentDeterminerTest extends AbstractSepaHeadlessTestCase {

  protected function setUp(): void {
    parent::setUp();
  }

  public function testDetermineCollectReceivablePaymentInstrument(): void {
    $paymentInstrumentDeterminer = new PaymentInstrumentDeterminer();

    $creditorId = SepaCreditorBuilder::createDefault(['pi_ooff' => '2,3']);

    // Given RCUR payment instrument ID is also a valid OOFF payment instrument ID.
    static::assertSame(3, $paymentInstrumentDeterminer->determineCollectReceivablePaymentInstrument($creditorId, 3));

    // Given RCUR payment instrument ID is not a valid OOFF payment instrument ID.
    // First OOFF payment instrument ID is returned.
    static::assertSame(2, $paymentInstrumentDeterminer->determineCollectReceivablePaymentInstrument($creditorId, 4));

    $creditorId = SepaCreditorBuilder::createDefault(['pi_ooff' => '']);
    $this->expectException(\CRM_Core_Exception::class);
    $this->expectExceptionMessage(
      "OOFF mandate for creditor ID [$creditorId] disabled, i.e. no valid payment instrument set."
    );
    $paymentInstrumentDeterminer->determineCollectReceivablePaymentInstrument($creditorId, 3);
  }

}
