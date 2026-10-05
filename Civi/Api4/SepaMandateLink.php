<?php

declare(strict_types = 1);

namespace Civi\Api4;

/**
 * SepaMandateLink entity.
 *
 * Provided by the CiviSEPA extension.
 *
 * @package Civi\Api4
 */
final class SepaMandateLink extends Generic\DAOEntity {

  use Generic\Traits\EntityBridge;

  /**
   * @return array<string, list<string|list<string>>>
   */
  public static function permissions(): array {
    return [
      'get' => ['view sepa mandates'],
      'default' => ['edit sepa mandates'],
    ];
  }

}
