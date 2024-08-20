<?php
namespace Civi\Api4;

use Civi\Sepa\Api4\Action\SepaMandate\CreatefullAction;

/**
 * Resource entity.
 *
 * Provided by the CiviCRM Resource Management extension.
 *
 * @package Civi\Api4
 */
class SepaMandate extends Generic\DAOEntity {

  public static function createfull(bool $checkPermissions = TRUE): CreatefullAction {
    return (new CreatefullAction())->setCheckPermissions($checkPermissions);
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
