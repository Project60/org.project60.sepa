<?php

declare(strict_types = 1);

namespace Civi\Api4;

use Civi\Sepa\Api4\Action\SepaMandate\GetAction;
use Civi\Sepa\Api4\Action\SepaMandate\SuspendAction;
use Civi\Sepa\Api4\Action\SepaMandate\ReinstateAction;

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

  public static function suspend(bool $checkPermissions = TRUE): SuspendAction {
    return (new SuspendAction(static::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function resume(bool $checkPermissions = TRUE): ReinstateAction {
    return (new ReinstateAction(static::getEntityName(), __FUNCTION__))
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
      'update' => ['edit sepa mandates'],
    ];
  }

}
