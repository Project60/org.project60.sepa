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

namespace Civi\Sepa\Util;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Civi\Sepa\Util\CurrencyUtil
 */
final class CurrencyUtilTest extends TestCase {

  public function testGetCurrencyPrecision(): void {
    static::assertSame(2, CurrencyUtil::getPrecision('EUR'));
    static::assertSame(3, CurrencyUtil::getPrecision('IQD'));
    static::assertSame(2, CurrencyUtil::getPrecision('USD'));
    // Should be 2 for unknown currencies.
    static::assertSame(2, CurrencyUtil::getPrecision('XYZ'));
  }

}
