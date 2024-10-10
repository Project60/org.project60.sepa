<?php
namespace Civi\Api4;

use Civi\Sepa\Api4\Action\SepaTransactionGroup\GetAction;

/**
 * SepaTransactionGroup entity.
 *
 * Provided by the CiviSEPA extension.
 *
 * @package Civi\Api4
 */
class SepaTransactionGroup extends Generic\DAOEntity {

  public static function get($checkPermissions = TRUE) {
    return (new GetAction(static::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

}
