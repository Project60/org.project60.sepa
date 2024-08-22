<?php
namespace Civi\Api4;

use Civi\Sepa\Api4\Action\SepaMandate\GetAction;

/**
 * SepaMandate entity.
 *
 * Provided by the CiviSEPA extension.
 *
 * @package Civi\Api4
 */
class SepaMandate extends Generic\DAOEntity {

  public static function get($checkPermissions = TRUE) {
    return (new GetAction(static::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

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
