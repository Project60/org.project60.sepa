<?php

declare(strict_types = 1);

namespace Civi\Api4;

use Civi\Sepa\Api4\Action\SepaMandate\CreateFullAction;
use Civi\Sepa\Api4\Action\SepaMandate\GetAction;
use Civi\Sepa\Api4\Action\SepaMandate\GetFieldsAction;

/**
 * SepaMandate entity.
 *
 * Provided by the CiviSEPA extension.
 *
 * @package Civi\Api4
 */
class SepaMandate extends Generic\DAOEntity {

  public static function createFull(bool $checkPermissions = TRUE): CreateFullAction {
    return (new CreateFullAction(static::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function get($checkPermissions = TRUE) {
    return (new GetAction(static::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function getFields($checkPermissions = TRUE) {
    return (new GetFieldsAction(static::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  /**
   * @return array<string, list<string|list<string>>>
   *
   * @see \Civi\Api4\Generic\AbstractEntity::permissions()
   */
  public static function permissions(): array {
    return [
      'get' => ['view sepa mandates'],
      'default' => ['edit sepa mandates'],
    ];
  }

}
