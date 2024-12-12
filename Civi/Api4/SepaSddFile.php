<?php
namespace Civi\Api4;

/**
 * SepaSddFile entity.
 *
 * Provided by the CiviSEPA extension.
 *
 * @package Civi\Api4
 */
class SepaSddFile extends Generic\DAOEntity {

  public static function permissions(): array {
    return [
      'get' => ['view sepa groups'],
      'create' => ['batch sepa groups'],
      'update' => ['batch sepa groups'],
      'delete' => [
        ['batch sepa groups', 'delete sepa groups'],
      ],
    ];
  }

}
