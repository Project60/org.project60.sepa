<?php
namespace Civi\Api4;

/**
 * Resource entity.
 *
 * Provided by the CiviCRM Resource Management extension.
 *
 * @package Civi\Api4
 */
class SepaMandate extends Generic\DAOEntity {
  
  /**
   * @see \Civi\Api4\Generic\AbstractEntity::permissions()
   * @return array[]
   */
  public static function permissions(): array {
    return [
      'get' => ['view sepa mandates'],
      'update' => ['edit sepa mandates'],
    ];
  }
  
}
