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

namespace Civi\Sepa\SpecProvider;

use Civi\Api4\Service\Spec\FieldSpec;
use Civi\Api4\Service\Spec\Provider\Generic\SpecProviderInterface;
use Civi\Api4\Service\Spec\RequestSpec;
use Civi\Core\Service\AutoService;

/**
 * @service
 * @internal
 */
final class SepaMandateSpecProvider extends AutoService implements SpecProviderInterface {

  /**
   * @param \Civi\Api4\Service\Spec\RequestSpec $spec
   *
   * @see \CRM_Sepa_BAO_SEPAMandate::self_hook_civicrm_pre()
   */
  public function modifySpec(RequestSpec $spec): void {
    $spec->getFieldByName('creditor_id')?->setRequired(FALSE);
    $spec->getFieldByName('date')?->setRequired(FALSE);
    $spec->getFieldByName('reference')?->setRequired(FALSE);

    if ('createFull' === $spec->getAction()) {
      $spec->addFieldSpec(new FieldSpec('payment_instrument_id', 'SepaMandate', 'Integer'));

      $spec->addFieldSpec(
        (new FieldSpec('financial_type_id', 'SepaMandate', 'Integer'))
        ->setRequired(TRUE)
        ->setFkEntity('FinancialType')
      );

      $spec->addFieldSpec(
        (new FieldSpec('contribution_contact_id', 'SepaMandate', 'Integer'))
        ->setFkEntity('Contact')
      );

      $spec->addFieldSpec(new FieldSpec('currency', 'SepaMandate', 'String'));

      $spec->addFieldSpec(new FieldSpec('contribution_status_id', 'SepaMandate', 'Integer'));

      $spec->addFieldSpec(new FieldSpec('is_pay_later', 'SepaMandate', 'Boolean'));

      $spec->addFieldSpec(
        (new FieldSpec('amount', 'SepaMandate', 'Money'))
        ->setRequired(TRUE)
      );
    }
  }

  public function applies(string $entity, string $action): bool {
    return 'SepaMandate' === $entity;
  }

}
