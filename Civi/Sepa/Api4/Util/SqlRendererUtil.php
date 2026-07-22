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

namespace Civi\Sepa\Api4\Util;

use Civi\Api4\Query\Api4SelectQuery;

final class SqlRendererUtil {

  /**
   * @phpstan-param array<string, mixed> $field
   *
   * @throws \CRM_Core_Exception
   */
  public static function getFieldSqlName(array $field, Api4SelectQuery $query, string $fieldName): string {
    if (isset($field['explicit_join'])) {
      // @phpstan-ignore binaryOp.invalid
      $resultField = $query->getField($field['explicit_join'] . '.' . $fieldName, TRUE);
    }
    elseif (isset($field['implicit_join'])) {
      // @phpstan-ignore binaryOp.invalid
      $resultField = $query->getField($field['implicit_join'] . '.' . $fieldName, TRUE);
    }
    else {
      $resultField = $query->getField($fieldName, TRUE);
    }

    /** @phpstan-var array{sql_name: string} $resultField */
    return $resultField['sql_name'];
  }

}
